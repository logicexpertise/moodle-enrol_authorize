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
 * Authorize enrolments plugin settings and presets
 *
 * @package    enrol
 * @subpackage authorize
 * @author     Dan Watts - based on code by Eugene Venter
 * @author     Olumuyiwa Taiwo - enhancements
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

require_once 'localfuncs.php';
global $SITE;

if ($ADMIN->fulltree) {
    //--- settings ------------------------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_authorize_settings', '', get_string('pluginname_desc', 'enrol_authorize')));

    $settings->add(new admin_setting_configcheckbox('enrol_authorize/sandbox', get_string('sandbox', 'enrol_authorize'), get_string('sandbox_desc', 'enrol_authorize'), '1'));

    $settings->add(new admin_setting_configtext('enrol_authorize/apilogin', get_string('apilogin', 'enrol_authorize'), get_string('apilogin_desc', 'enrol_authorize'), '', PARAM_TEXT));

    $settings->add(new admin_setting_configpasswordunmask('enrol_authorize/transactionkey', get_string('transactionkey', 'enrol_authorize'), get_string('transactionkey_desc', 'enrol_authorize'), '', PARAM_TEXT));

    $settings->add(new admin_setting_configmulticheckbox('enrol_authorize/acceptcc', get_string('adminacceptccs', 'enrol_authorize'), '', '', get_list_of_creditcards(true)));

    $settings->add(new admin_setting_configcheckbox('enrol_authorize/an_avs', get_string('anavs', 'enrol_authorize'), get_string('anavsdesc', 'enrol_authorize'), '', PARAM_BOOL));

    $settings->add(new admin_setting_configtime('enrol_authorize/an_cutoff_hour', 'an_cutoff_min', get_string('cutofftime', 'enrol_authorize'), get_string('cutofftimedesc', 'enrol_authorize'), array('h' => 0, 'm' => 5)));

    $options = array(
        ENROL_EXT_REMOVED_KEEP           => get_string('extremovedkeep', 'enrol'),
        ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'),
        ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
    );
    $settings->add(new admin_setting_configselect('enrol_authorize/expiredaction', get_string('expiredaction', 'enrol_authorize'), get_string('expiredaction_help', 'enrol_authorize'), ENROL_EXT_REMOVED_KEEP, $options));

    $options = array();
    for ($i=0; $i<24; $i++) {
        $options[$i] = $i;
    }
    $settings->add(new admin_setting_configselect('enrol_self/expirynotifyhour', get_string('expirynotifyhour', 'core_enrol'), '', 6, $options));

    // ---- receipt settings --------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_authorize_receipt_settings',
                    get_string('receipt_settings', 'enrol_authorize'),
                    get_string('reecipt_settings_desc', 'enrol_authorize')));
    $settings->add(new admin_setting_configtext('enrol_authorize/receipt_prefix',
                    get_string('receipt_prefix', 'enrol_authorize'),
                    get_string('receipt_prefix_desc', 'enrol_authorize'), '', PARAM_TEXT, 5));
    $settings->add(new admin_setting_configtext('enrol_authorize/receipt_nextnumber',
                    get_string('receipt_nextnumber', 'enrol_authorize'),
                    get_string('receipt_nextnumber_desc', 'enrol_authorize'), 1, PARAM_INT));
    $settings->add(new admin_setting_configtextarea('enrol_authorize/receipt_addresshtml',
                    get_string('receipt_addresshtml', 'enrol_authorize'),
                    get_string('receipt_addresshtml_desc', 'enrol_authorize'), '', PARAM_CLEANHTML));

    $settings->add(new admin_setting_configtextarea('enrol_authorize/receipt_footerhtml',
                    get_string('receipt_footerhtml', 'enrol_authorize'),
                    get_string('receipt_footerhtml_desc', 'enrol_authorize'), '', PARAM_CLEANHTML));

    // ---- email settings ------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_authorize_email_settings',
                    get_string('email_settings', 'enrol_authorize'),
                    get_string('email_settings_desc', 'enrol_authorize')));
    $settings->add(new admin_setting_configtext('enrol_authorize/email_from',
                    get_string('email_from', 'enrol_authorize'),
                    get_string('email_from_desc', 'enrol_authorize'),$CFG->supportemail, PARAM_EMAIL));
    $settings->add(new admin_setting_configtext('enrol_authorize/email_replyto',
                    get_string('email_replyto', 'enrol_authorize'),
                    get_string('email_replyto_desc', 'enrol_authorize'),$CFG->supportemail, PARAM_EMAIL));
    $settings->add(new admin_setting_configtext('enrol_authorize/email_subject',
                    get_string('email_subject', 'enrol_authorize'),
                    get_string('email_subject_desc', 'enrol_authorize'), get_string('enrolmentnew', 'enrol', $SITE->fullname), PARAM_TEXT));
    $settings->add(new admin_setting_configtextarea('enrol_authorize/email_body',
                    get_string('email_body', 'enrol_authorize'),
                    get_string('email_body_desc', 'enrol_authorize'), get_string('welcometocoursesemail', 'enrol_authorize'), PARAM_RAW));

    //--- enrol instance defaults ----------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_authorize_defaults',
                    get_string('enrolinstancedefaults', 'admin'), get_string('enrolinstancedefaults_desc', 'admin')));

    $options = array(ENROL_INSTANCE_ENABLED => get_string('yes'),
        ENROL_INSTANCE_DISABLED => get_string('no'));
    $settings->add(new admin_setting_configselect('enrol_authorize/status',
                    get_string('status', 'enrol_authorize'), get_string('status_desc', 'enrol_authorize'), ENROL_INSTANCE_DISABLED, $options));

    $settings->add(new admin_setting_configtext('enrol_authorize/cost', get_string('cost', 'enrol_authorize'), '', 0, PARAM_FLOAT, 4));

    if (!during_initial_install()) {
        $options = get_default_enrol_roles(get_context_instance(CONTEXT_SYSTEM));
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect('enrol_authorize/roleid',
                        get_string('defaultrole', 'enrol_authorize'), get_string('defaultrole_desc', 'enrol_authorize'), $student->id, $options));
    }

    $settings->add(new admin_setting_configduration('enrol_authorize/enrolperiod',
        get_string('enrolperiod', 'enrol_authorize'), get_string('enrolperiod_desc', 'enrol_authorize'), 0));

    $options = array(0 => get_string('no'), 1 => get_string('expirynotifyenroller', 'core_enrol'), 2 => get_string('expirynotifyall', 'core_enrol'));
    $settings->add(new admin_setting_configselect('enrol_authorize/expirynotify',
        get_string('expirynotify', 'core_enrol'), get_string('expirynotify_help', 'core_enrol'), 0, $options));

    $settings->add(new admin_setting_configduration('enrol_authorize/expirythreshold',
        get_string('expirythreshold', 'core_enrol'), get_string('expirythreshold_help', 'core_enrol'), 86400, 86400));

    $options = array(0 => get_string('never'),
                     1800 * 3600 * 24 => get_string('numdays', '', 1800),
                     1000 * 3600 * 24 => get_string('numdays', '', 1000),
                     365 * 3600 * 24 => get_string('numdays', '', 365),
                     180 * 3600 * 24 => get_string('numdays', '', 180),
                     150 * 3600 * 24 => get_string('numdays', '', 150),
                     120 * 3600 * 24 => get_string('numdays', '', 120),
                     90 * 3600 * 24 => get_string('numdays', '', 90),
                     60 * 3600 * 24 => get_string('numdays', '', 60),
                     30 * 3600 * 24 => get_string('numdays', '', 30),
                     21 * 3600 * 24 => get_string('numdays', '', 21),
                     14 * 3600 * 24 => get_string('numdays', '', 14),
                     7 * 3600 * 24 => get_string('numdays', '', 7));
    $settings->add(new admin_setting_configselect('enrol_authorize/longtimenosee',
        get_string('longtimenosee', 'enrol_authorize'), get_string('longtimenosee_help', 'enrol_authorize'), 0, $options));

    $settings->add(new admin_setting_configtext('enrol_authorize/maxenrolled',
        get_string('maxenrolled', 'enrol_authorize'), get_string('maxenrolled_help', 'enrol_authorize'), 0, PARAM_INT));

    //------ email students? --------------------------------------------------------------------------------

    $settings->add(new admin_setting_configcheckbox('enrol_authorize/mailstudents',
                    get_string('mailstudents', 'enrol_authorize'),
                    get_string('mailstudents_desc', 'enrol_authorize'), 1));
}
