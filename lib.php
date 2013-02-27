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
     * Creates course enrol form, checks if form submitted
     * and enrols user if necessary. It can also redirect.
     *
     * @param stdClass $instance
     * @return string html text, usually a form in a text box
     */
    public function enrol_page_hook(stdClass $instance) {
        global $CFG, $SITE, $USER, $OUTPUT, $PAGE, $DB;

        // ensure ssl is being used
        if (!strpos($CFG->httpwwwroot, "https://")
                && !strpos($SITE->url, "https://")
                && !isset($_SERVER['HTTPS'])
                && $_SERVER['HTTPS'] !== "on")
        {
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
            $teacher = false;
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

                echo '<div class="mdl-align"><p>' . get_string('paymentrequired') . '</p>';
                echo '<p><b>' . get_string('enrolname', 'enrol_authorize') . '</b></p>';
                echo '<p><b>' . get_string('cost') . ": $instance->currency $cost" . '</b></p>';
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
                        // TODO: CHANGE TO 'false' WHEN DEPLOYING LIVE!!!
                        define('AUTHORIZENET_SANDBOX', true);
                        $purchase = new AuthorizeNetAIM($enrol->get_config('apilogin'), $enrol->get_config('transactionkey'));
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
                                $receiptnumber = get_config('enrol_authorize', 'receipt_nextnumber');
                                $receiptprefix = get_config('enrol_authorize', 'receipt_prefix');
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
                                if ($teacher) {
                                    $from = $teacher;
                                } else {
                                    $from = get_admin();
                                }
                                $subject = get_string('enrolmentnew', 'enrol', $SITE->fullname);
                                $a = new stdClass;
                                $a->name = $user->firstname;
                                $a->courses = $course->fullname;
                                $a->profileurl = "$CFG->wwwroot/user/view.php?id=$USER->id";
                                $a->paymenturl = "$CFG->wwwroot/enrol/authorize/index.php?user=$USER->id";
                                $emailmessage = get_string('welcometocoursesemail', 'enrol_authorize', $a);
                                email_to_user($user, $from, $subject, $emailmessage);
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

