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

if ($ADMIN->fulltree) {
    //--- settings ------------------------------------------------------------------------------------------
    $settings->add(new admin_setting_heading('enrol_authorize_settings', '', get_string('pluginname_desc', 'enrol_authorize')));

    $settings->add(new admin_setting_configtext('enrol_authorize/apilogin', get_string('apilogin', 'enrol_authorize'), get_string('apilogin_desc', 'enrol_authorize'), '', PARAM_TEXT));

    $settings->add(new admin_setting_configtext('enrol_authorize/transactionkey', get_string('transactionkey', 'enrol_authorize'), get_string('transactionkey_desc', 'enrol_authorize'), '', PARAM_TEXT));

    $settings->add(new admin_setting_configmulticheckbox('enrol_authorize/acceptcc', get_string('adminacceptccs', 'enrol_authorize'), '', '', get_list_of_creditcards(true)));

    $settings->add(new admin_setting_configcheckbox('enrol_authorize/an_avs', get_string('anavs', 'enrol_authorize'), get_string('anavsdesc', 'enrol_authorize'), '', PARAM_BOOL));

    $settings->add(new admin_setting_configtime('enrol_authorize/an_cutoff_hour', 'an_cutoff_min', get_string('cutofftime', 'enrol_authorize'), get_string('cutofftimedesc', 'enrol_authorize'), array('h' => 0, 'm' => 5)));

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

    $settings->add(new admin_setting_configtext('enrol_authorize/enrolperiod',
                    get_string('enrolperiod', 'enrol_authorize'), get_string('enrolperiod_desc', 'enrol_authorize'), 0, PARAM_INT));

    //------ email students? --------------------------------------------------------------------------------

    $settings->add(new admin_setting_configcheckbox('enrol_authorize/mailstudents',
                    get_string('mailstudents', 'enrol_authorize'),
                    get_string('mailstudents_desc', 'enrol_authorize'), 1));
}
