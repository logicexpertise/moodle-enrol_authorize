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
 * Adds new instance of enrol_authorize to specified course
 * or edits current instance.
 *
 * @package    enrol
 * @subpackage authorize
 * @copyright  2011 Dan Watts
 * @author     Dan Watts - based on code by Eugene Venter and others
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');

class enrol_authorize_edit_form extends moodleform
{

    function definition()
    {
        $mform = $this->_form;

        list($instance, $plugin, $context) = $this->_customdata;

        $mform->addElement('header', 'header', get_string('pluginname', 'enrol_authorize'));

        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'));

        $options = array(ENROL_INSTANCE_ENABLED  => get_string('yes'),
                         ENROL_INSTANCE_DISABLED => get_string('no'));
        $mform->addElement('select', 'status', get_string('status', 'enrol_authorize'), $options);
        $mform->setDefault('status', $plugin->get_config('status'));

        $mform->addElement('text', 'cost', get_string('cost', 'enrol_authorize'), array('size'=>4));
        $mform->setDefault('cost', $plugin->get_config('cost'));

        if ($instance->id)
        {
            $roles = get_default_enrol_roles($context, $instance->roleid);
        }
        else
        {
            $roles = get_default_enrol_roles($context, $plugin->get_config('roleid'));
        }
        $mform->addElement('select', 'roleid', get_string('assignrole', 'enrol_authorize'), $roles);
        $mform->setDefault('roleid', $plugin->get_config('roleid'));


        $mform->addElement('duration', 'enrolperiod', get_string('enrolperiod', 'enrol_authorize'), array('optional' => true, 'defaultunit' => DAYSECS));
        $mform->setDefault('enrolperiod', $plugin->get_config('enrolperiod'));

        $options = array(0 => get_string('no'), 1 => get_string('expirynotifyenroller', 'core_enrol'), 2 => get_string('expirynotifyall', 'core_enrol'));
        $mform->addElement('select', 'expirynotify', get_string('expirynotify', 'core_enrol'), $options);
        $mform->addHelpButton('expirynotify', 'expirynotify', 'core_enrol');

        $mform->addElement('duration', 'expirythreshold', get_string('expirythreshold', 'core_enrol'), array('optional' => false, 'defaultunit' => 86400));
        $mform->addHelpButton('expirythreshold', 'expirythreshold', 'core_enrol');
        $mform->disabledIf('expirythreshold', 'expirynotify', 'eq', 0);


        $mform->addElement('date_selector', 'enrolstartdate', get_string('enrolstartdate', 'enrol_authorize'), array('optional' => true));
        $mform->setDefault('enrolstartdate', 0);


        $mform->addElement('date_selector', 'enrolenddate', get_string('enrolenddate', 'enrol_authorize'), array('optional' => true));
        $mform->setDefault('enrolenddate', 0);

        $options = array(0 => get_string('never'),
                 1800 * DAYSECS => get_string('numdays', '', 1800),
                 1000 * DAYSECS => get_string('numdays', '', 1000),
                 365 * DAYSECS => get_string('numdays', '', 365),
                 180 * DAYSECS => get_string('numdays', '', 180),
                 150 * DAYSECS => get_string('numdays', '', 150),
                 120 * DAYSECS => get_string('numdays', '', 120),
                 90 * DAYSECS => get_string('numdays', '', 90),
                 60 * DAYSECS => get_string('numdays', '', 60),
                 30 * DAYSECS => get_string('numdays', '', 30),
                 21 * DAYSECS => get_string('numdays', '', 21),
                 14 * DAYSECS => get_string('numdays', '', 14),
                 7 * DAYSECS => get_string('numdays', '', 7));
        $mform->addElement('select', 'customint2', get_string('longtimenosee', 'enrol_authorize'), $options);
        $mform->addHelpButton('customint2', 'longtimenosee', 'enrol_authorize');

        $mform->addElement('text', 'customint3', get_string('maxenrolled', 'enrol_authorize'));
        $mform->addHelpButton('customint3', 'maxenrolled', 'enrol_authorize');
        $mform->setType('customint3', PARAM_INT);

        $mform->addElement('advcheckbox', 'customint4', get_string('mailstudents', 'enrol_authorize'));
        $mform->addHelpButton('customint4', 'mailstudents', 'enrol_authorize');

        $mform->addElement('hidden', 'id');
        $mform->addElement('hidden', 'courseid');

        $this->add_action_buttons(true, ($instance->id ? null : get_string('addinstance', 'enrol')));

        $this->set_data($instance);
    }

    function validation($data, $files)
    {
        global $DB, $CFG;
        $errors = parent::validation($data, $files);

        list($instance, $plugin, $context) = $this->_customdata;

        if ($data['status'] == ENROL_INSTANCE_ENABLED)
        {
            if (!empty($data['enrolenddate']) and $data['enrolenddate'] < $data['enrolstartdate'])
            {
                $errors['enrolenddate'] = get_string('enrolenddaterror', 'enrol_authorize');
            }

            if (!is_numeric($data['cost']))
            {
                $errors['cost'] = get_string('costerror', 'enrol_authorize');

            }
        }

        return $errors;
    }
}
