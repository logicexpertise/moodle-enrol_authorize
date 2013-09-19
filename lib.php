<?php

// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Authorize enrolment plugin.
 *
 * This plugin allows you to set up paid courses, using authorize.net.
 *
 * @package    enrol
 * @subpackage authorize
 * @author     Dan Watts - based on code by Eugene Venter
 * @author     Olumuyiwa Taiwo - enhancements
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/**
 * Authorize enrolment plugin implementation.
 */
class enrol_authorize_plugin extends enrol_plugin {
    const AN_DELIM = '|';
    const AN_ENCAP = '"';

    const AN_REASON_NOCCTYPE = 17;
    const AN_REASON_NOCCTYPE2 = 28;
    const AN_REASON_NOACH = 18;
    const AN_REASON_ACHONLY = 56;
    const AN_REASON_NOACHTYPE = 245;
    const AN_REASON_NOACHTYPE2 = 246;

    /**
     * Returns optional enrolment information icons.
     *
     * This is used in course list for quick overview of enrolment options.
     *
     * We are not using single instance parameter because sometimes
     * we might want to prevent icon repetition when multiple instances
     * of one type exist. One instance may also produce several icons.
     *
     * @param array $instances all enrol instances of this type in one course
     * @return array of pix_icon
     */
    public function get_info_icons(array $instances) {
        return array();
    }

    public function roles_protected() {
        return false;
    }

    public function allow_unenrol(stdClass $instance) {
        return true;
    }

    public function allow_manage(stdClass $instance) {
        return true;
    }

    public function show_enrolme_link(stdClass $instance) {
        return $instance->status == ENROL_INSTANCE_ENABLED;
    }

    /**
     * cron support.
     * @return void
     */
    public function cron() {
        $this->sync(null, true);
        $this->send_expiry_notifications(true);
    }

    /**
     * Sync course enrolments.
     * Currently just checks for expired enrolments.
     * based on enrol_self::sync
     *
     * @param int $courseid one course, empty mean all
     * @param bool $verbose verbose CLI output
     * @return int 0 means ok, 1 means error, 2 means plugin disabled
     */
    public function sync($courseid = null, $verbose = false) {
        global $DB;

        if (!enrol_is_enabled('authorize')) {
            return 2;
        }

        // Unfortunately this may take a long time, execution can be interrupted safely here.
        @set_time_limit(0);
        raise_memory_limit(MEMORY_HUGE);

        if ($verbose) {
            mtrace('Verifying authorize-enrolments...');
        }

        $params = array('now'=>time(), 'useractive'=>ENROL_USER_ACTIVE, 'courselevel'=>CONTEXT_COURSE);
        $coursesql = "";
        if ($courseid) {
            $coursesql = "AND e.courseid = :courseid";
            $params['courseid'] = $courseid;
        }

        // Note: the logic of authorize enrolment guarantees that user logged in at least once (=== u.lastaccess set)
        //       and that user accessed course at least once too (=== user_lastaccess record exists).

        // First deal with users that did not log in for a really long time - they do not have user_lastaccess records.
        $sql = "SELECT e.*, ue.userid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = 'authorize' AND e.customint2 > 0)
                  JOIN {user} u ON u.id = ue.userid
                 WHERE :now - u.lastaccess > e.customint2
                       $coursesql";
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $instance) {
            $userid = $instance->userid;
            unset($instance->userid);
            $this->unenrol_user($instance, $userid);
            if ($verbose) {
                $days = $instance->customint2 / 60*60*24;
                mtrace("  unenrolling user $userid from course $instance->courseid as they have did not log in for at least $days days");
            }
        }
        $rs->close();

        // Now unenrol from course user did not visit for a long time.
        $sql = "SELECT e.*, ue.userid
                  FROM {user_enrolments} ue
                  JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = 'authorize' AND e.customint2 > 0)
                  JOIN {user_lastaccess} ul ON (ul.userid = ue.userid AND ul.courseid = e.courseid)
                 WHERE :now - ul.timeaccess > e.customint2
                       $coursesql";
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $instance) {
            $userid = $instance->userid;
            unset($instance->userid);
            $this->unenrol_user($instance, $userid);
            if ($verbose) {
                $days = $instance->customint2 / 60*60*24;
                mtrace("  unenrolling user $userid from course $instance->courseid as they have did not access course for at least $days days");
            }
        }
        $rs->close();

        // Deal with expired accounts.
        $action = $this->get_config('expiredaction', ENROL_EXT_REMOVED_KEEP);

        if ($action == ENROL_EXT_REMOVED_UNENROL) {
            $instances = array();
            $sql = "SELECT ue.*, e.courseid, c.id AS contextid
                      FROM {user_enrolments} ue
                      JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = 'authorize')
                      JOIN {context} c ON (c.instanceid = e.courseid AND c.contextlevel = :courselevel)
                     WHERE ue.timeend > 0 AND ue.timeend < :now
                           $coursesql";
            $rs = $DB->get_recordset_sql($sql, $params);
            foreach ($rs as $ue) {
                if (empty($instances[$ue->enrolid])) {
                    $instances[$ue->enrolid] = $DB->get_record('enrol', array('id'=>$ue->enrolid));
                }
                $instance = $instances[$ue->enrolid];
                if ($instance->roleid) {
                    role_unassign($instance->roleid, $ue->userid, $ue->contextid, '', 0);
                }
                $this->unenrol_user($instance, $ue->userid);
                if ($verbose) {
                    mtrace("  unenrolling expired user $ue->userid from course $instance->courseid");
                }
            }
            $rs->close();
            unset($instances);

        } else if ($action == ENROL_EXT_REMOVED_SUSPENDNOROLES) {
            $instances = array();
            $sql = "SELECT ue.*, e.courseid, c.id AS contextid
                      FROM {user_enrolments} ue
                      JOIN {enrol} e ON (e.id = ue.enrolid AND e.enrol = 'authorize')
                      JOIN {context} c ON (c.instanceid = e.courseid AND c.contextlevel = :courselevel)
                     WHERE ue.timeend > 0 AND ue.timeend < :now
                           AND ue.status = :useractive
                           $coursesql";
            $rs = $DB->get_recordset_sql($sql, $params);
            foreach ($rs as $ue) {
                if (empty($instances[$ue->enrolid])) {
                    $instances[$ue->enrolid] = $DB->get_record('enrol', array('id'=>$ue->enrolid));
                }
                $instance = $instances[$ue->enrolid];
                if (1 == $DB->count_records('role_assignments', array('userid'=>$ue->userid, 'contextid'=>$ue->contextid))) {
                    role_unassign_all(array('userid'=>$ue->userid, 'contextid'=>$ue->contextid, 'component'=>'', 'itemid'=>0), true);
                } else if ($instance->roleid) {
                    role_unassign($instance->roleid, $ue->userid, $ue->contextid, '', 0);
                }
                $this->update_user_enrol($instance, $ue->userid, ENROL_USER_SUSPENDED);
                if ($verbose) {
                    mtrace("  suspending expired user $ue->userid in course $instance->courseid");
                }
            }
            $rs->close();
            unset($instances);

        } else {
            // ENROL_EXT_REMOVED_KEEP means no changes.
        }

        if ($verbose) {
            mtrace('...user authorize-enrolment updates finished.');
        }

        return 0;
    }

    /**
     * Sets up navigation entries.
     *
     * @param object $instance
     * @return void
     */
    public function add_course_navigation($instancesnode, stdClass $instance) {
        if ($instance->enrol !== 'authorize') {
            throw new coding_exception('Invalid enrol instance type!');
        }

        $context = get_context_instance(CONTEXT_COURSE, $instance->courseid);
        if (has_capability('enrol/authorize:config', $context)) {
            $managelink = new moodle_url('/enrol/authorize/edit.php', array('courseid' => $instance->courseid, 'id' => $instance->id));
            $instancesnode->add($this->get_instance_name($instance), $managelink, navigation_node::TYPE_SETTING);
        }
    }

    /**
     * Returns edit icons for the page with list of instances
     * @param stdClass $instance
     * @return array
     */
    public function get_action_icons(stdClass $instance) {
        return array();
    }

    /**
     * Returns link to page which may be used to add new instance of enrolment plugin in course.
     * @param int $courseid
     * @return moodle_url page url
     */
    public function get_newinstance_link($courseid) {
        $context = get_context_instance(CONTEXT_COURSE, $courseid, MUST_EXIST);

        if (!has_capability('moodle/course:enrolconfig', $context) or !has_capability('enrol/authorize:config', $context)) {
            return NULL;
        }

        // multiple instances supported - different cost for different roles
        return new moodle_url('/enrol/authorize/edit.php', array('courseid' => $courseid));
    }

    /**
     * Gets an array of the user enrolment actions
     *
     * @param course_enrolment_manager $manager
     * @param stdClass $ue A user enrolment object
     * @return array An array of user_enrolment_actions
     */
    public function get_user_enrolment_actions(course_enrolment_manager $manager, $ue) {
        $actions = array();
        $context = $manager->get_context();
        $instance = $ue->enrolmentinstance;
        $params = $manager->get_moodlepage()->url->params();
        $params['ue'] = $ue->id;
        if ($this->allow_unenrol_user($instance, $ue) && has_capability('enrol/authorize:unenrol', $context)) {
            $url = new moodle_url('/enrol/unenroluser.php', $params);
            $actions[] = new user_enrolment_action(new pix_icon('t/delete', ''), get_string('unenrol', 'enrol'), $url, array('class'=>'unenrollink', 'rel'=>$ue->id));
        }
        return $actions;
    }
    
    /**
     * Determine if SSL is used.
     *
     * @since 2.6.0
     * @link http://core.trac.wordpress.org/browser/tags/3.3.2/wp-includes/functions.php#L0
     *
     * @return bool True if SSL, false if not used.
     * @license GPL
     */
    public static function is_ssl() {
        if (isset($_SERVER['HTTPS'])) {
            if ('on' == strtolower($_SERVER['HTTPS']))
                return true;
            if ('1' == $_SERVER['HTTPS'])
                return true;
        } elseif (isset($_SERVER['SERVER_PORT']) && ( '443' == $_SERVER['SERVER_PORT'] )) {
            return true;
        }
        return true;
    }

    /**
     * Creates course enrol form, checks if form submitted
     * and enrols user if necessary. It can also redirect.
     *
     * @param stdClass $instance
     * @return string html text, usually a form in a text box
     */
    public function enrol_page_hook(stdClass $instance) {
        global $CFG, $SITE, $USER, $OUTPUT, $DB;

        // ensure ssl is being used
        if (!$this->is_ssl()) {
            print_error('httpsrequired', 'enrol_authorize');
        }

        ob_start();

        if ($DB->record_exists('user_enrolments', array('userid' => $USER->id, 'enrolid' => $instance->id))) {
            return ob_get_clean();
        }

        if ($instance->enrolstartdate != 0 && $instance->enrolstartdate > time()) {
            return ob_get_clean();
        }

        if ($instance->enrolenddate != 0 && $instance->enrolenddate < time()) {
            return ob_get_clean();
        }

        $course = $DB->get_record('course', array('id' => $instance->courseid));

        $strloginto = get_string('loginto', '', $course->shortname);
        $strcourses = get_string('courses');

        $context = get_context_instance(CONTEXT_COURSE, $course->id);
        // Pass $view=true to filter hidden caps if the user cannot see them
        if ($users = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC', '', '', '', '', false, true)) {
            $users = sort_by_roleassignment_authority($users, $context);
            $teacher = array_shift($users);
        } else {
            $teacher = $this->get_config('email_from');
        }

        if (!$instance->currency) {
            $instance->currency = 'USD';
        }

        if ((float) $instance->cost <= 0) {
            $cost = (float) $this->get_config('cost');
        } else {
            $cost = (float) $instance->cost;
        }

        if (abs($cost) < 0.01) { // no cost, other enrolment methods (instances) should be used
            echo '<p>' . get_string('nocost', 'enrol_authorize') . '</p>';
        } else {
            if (isguestuser()) { // force login only for guest user, not real users with guest role
                if (empty($CFG->loginhttps)) {
                    $wwwroot = $CFG->wwwroot;
                } else {
                    // This actually is not so secure ;-), 'cause we're
                    // in unencrypted connection...
                    $wwwroot = str_replace("http://", "https://", $CFG->wwwroot);
                }
                echo '<div class="mdl-align"><p>' . get_string('paymentrequired') . '</p>';
                echo '<p><b>' . get_string('cost') . ": $instance->currency $cost" . '</b></p>';
                echo '<p><a href="' . $wwwroot . '/login/">' . get_string('loginsite') . '</a></p>';
                echo '</div>';
            } else {
                //Sanitise some fields before building the Authorize.Net form
                $coursefullname = format_string($course->fullname, true, array('context' => $context));
                $courseshortname = $course->shortname;

                // TODO move to lang strings
                $description = '';
                $period = '';
                if ($instance->enrolperiod) {
                    $period .= get_string('enrolperiod', 'enrol_authorize', format_time($instance->enrolperiod));
                }
                if ($instance->enrolstartdate && $instance->enrolenddate) {
                    $description .= get_string('enrolwindow', 'enrol_authorize',
                            array('start'=>userdate($instance->enrolstartdate), 'end'=>userdate($instance->enrolenddate)));
                } else if ($instance->enrolstartdate) {
                    $description .= get_string('enrolafter', 'enrol_authorize', userdate($instance->enrolstartdate));
                } else if ($instance->enrolenddate) {
                    $description .= get_string('enrolbefore', 'enrol_authorize', userdate($instance->enrolenddate));
                } else {
                    $description .= get_string('enrolnolimit', 'enrol_authorize');
                }
                echo '<div class="mdl-align"><p>' . get_string('paymentrequired') . '</p>';
                echo '<p><b>' . get_string('enrolname', 'enrol_authorize') . '</b></p>';
                echo '<p><b>' . get_string('cost') . ": {$instance->currency} {$cost} {$period}" . '</b></p>';
                echo '<p><b>' . $description . '</b></p>';
                echo '</div>';

                require_once "$CFG->dirroot/enrol/authorize/enrol_form.php";

                $form = new enrol_authorize_form(NULL, $instance);
                $instanceid = optional_param('instanceid', 0, PARAM_INT);

                if ($instance->id == $instanceid) {
                    if ($data = $form->get_data()) {
                        require_once 'anet_php_sdk/AuthorizeNet.php';

                        $enrol = enrol_get_plugin('authorize');

                        require_once('authorizenet.class.php');
                        /// insert a record in enrol_authorize table
                        $timenow = time();
                        $order = new stdClass();
                        $order->paymentmethod = AN_METHOD_CC;
                        $order->refundinfo = substr($data->cc, -4);
                        $order->ccname = $data->firstname . " " . $data->lastname;
                        $order->courseid = $course->id;
                        $order->instanceid = $instance->id;
                        $order->userid = $USER->id;
                        $order->status = AN_STATUS_AUTHCAPTURE; // Transaction recorded as APPROVED
                        $order->settletime = AuthorizeNet::getsettletime($timenow); // CRON will change this
                        $order->timecreated = $timenow;
                        $order->transid = 0; // Transaction Id
                        $order->amount = $instance->cost;
                        $order->currency = $instance->currency;
                        $order->id = $DB->insert_record("enrol_authorize", $order);
                        if (!$order->id) {
                            message_to_admin("Error while trying to insert new data", $order);
                            return "Insert record error. Admin has been notified!";
                        }
                        $useripno = getremoteaddr();
                        $purchase = new AuthorizeNetAIM($enrol->get_config('apilogin'), $enrol->get_config('transactionkey'));
                        // CHANGE TO 'false' WHEN DEPLOYING LIVE!!! via settings screen
                        if ($enrol->get_config('sandbox', true) === '0') {
                            $purchase->setSandbox(false);
                        }
                        $purchase->first_name = $data->firstname;
                        $purchase->last_name = $data->lastname;
                        $purchase->address = $data->ccaddress;
                        $purchase->city = $data->cccity;
                        $purchase->state = $data->ccstate;
                        $purchase->country = $data->cccountry;
                        $purchase->zip = $data->cczip;
                        $purchase->email = $data->email;
                        $purchase->amount = $instance->cost;
                        $purchase->card_num = $data->cc;
                        $purchase->card_code = $data->cvv;
                        $purchase->exp_date = str_pad($data->ccexpiremm, 2, '0', STR_PAD_LEFT) . substr($data->ccexpireyyyy, 2);
                        $purchase->description = "$courseshortname: $coursefullname";
                        $purchase->cust_id = $USER->id;
                        $purchase->customer_ip = $useripno;
                        $purchase->invoice_num = $order->id;
                        $purchase->email_customer = $this->get_config('mailstudents') ? 'TRUE' : 'FALSE';

                        $response = $purchase->authorizeAndCapture();

                        if ($response->approved) {
                            // Insert CC data record in enrol_authorize_avs for use on receipt
                            if ($this->get_config('an_avs')) {
                                $cc = new stdClass();
                                $cc->orderid = $order->id;
                                $cc->ccfirstname = $data->firstname;
                                $cc->cclastname = $data->lastname;
                                $cc->ccaddress = $data->ccaddress;
                                $cc->cccity = $data->cccity;
                                $cc->ccstate = $data->ccstate;
                                $cc->cccountry = $data->cccountry;
                                $cc->id = $DB->insert_record("enrol_authorize_avs", $cc);
                                if (!$cc->id) {
                                    message_to_admin("Error while trying to insert new data in enrol_authorize_avs for order # ", $order->id);
                                }
                                // update enrol_authorize table
                                $order->transid = $response->transaction_id; // Transaction Id
                                $receiptnumber = $this->get_config('receipt_nextnumber');
                                $receiptprefix = $this->get_config('receipt_prefix');
                                $order->receipt = $receiptprefix . str_pad($receiptnumber, 4, '0', STR_PAD_LEFT);
                                if (!$DB->update_record('enrol_authorize', $order)) {
                                    message_to_admin("Error while trying to update enrol_authorize with receipt for order # ", $order->id);
                                }
                                set_config('receipt_nextnumber', $receiptnumber + 1, 'enrol_authorize');
                            }

                            /// Enrol the user
                            $timestart = time();
                            if ($instance->enrolperiod) {
                                $timeend = $timestart + $instance->enrolperiod;
                            } else {
                                $timeend = 0;
                            }
                            $this->enrol_user($instance, $USER->id, $instance->roleid, $timestart, $timeend);
                            add_to_log($instance->courseid, 'course', 'enrol', '../enrol/users.php?id=' . $instance->courseid, $instance->courseid); //there should be userid somewhere!
                            if ($this->get_config('mailstudents')) {
                                // send_welcome_messages($order->id); // times out - use email_to_user instead
                                $user = $DB->get_record('user', array('id' => $USER->id));
                                if (!empty($teacher)) {
                                    $from = $teacher;
                                } else {
                                    $from = get_admin();
                                }
                                $subject = $this->get_config('email_subject', get_string('enrolmentnew', 'enrol', $SITE->fullname));
                                $a = new stdClass;
                                $a->name = $user->firstname;
                                $a->courses = $course->fullname;
                                $a->profileurl = "$CFG->wwwroot/user/view.php?id=$USER->id";
                                $a->paymenturl = "$CFG->wwwroot/enrol/authorize/index.php?user=$USER->id";
                                $emailmessage = $this->get_config('email_body', get_string('welcometocoursesemail', 'enrol_authorize', $a));
                                $replyto = $this->get_config('email_replyto');
                                email_to_user($user, $from, $subject, $emailmessage, '', '', '', true, $replyto);
                            }
                            redirect($CFG->wwwroot . '/enrol/authorize/index.php?order=' . $order->id);
                        } else { // if ($response->error || $response->declined) {
                            echo '<div class="errorbox">';
                            echo "<p>$response->response_reason_text</p>";
                            echo '<p>' . get_string('callsupport', 'enrol_authorize') . '</p>';
                            echo '</div>';

                            $form->display();
                        }
                    } else {
                        $form->display();
                    }
                } else {
                    $form->display();
                }
            }
        }

        return $OUTPUT->box(ob_get_clean());
    }

}

