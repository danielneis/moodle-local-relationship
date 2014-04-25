<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

class relationshipcohort_edit_form extends moodleform {

    public function definition() {

        $mform = $this->_form;
        $relationshipcohort = $this->_customdata['data'];

        $roles = relationship_get_role_options();
        $mform->addElement('select', 'roleid', get_string('role'), $roles);

        $cohorts = relationship_get_cohort_options($relationshipcohort->relationshipid);
        $mform->addElement('select', 'cohortid', get_string('cohort', 'cohort'), $cohorts);
        if($relationshipcohort->id) {
            $mform->freeze('cohortid');
        }

        $mform->addElement('selectyesno', 'allowdupsingroups', get_string('allowdupsingroups', 'local_relationship'));
        $mform->addElement('selectyesno', 'uniformdistribution', get_string('uniformdistribution', 'local_relationship'));
        $mform->addElement('selectyesno', 'enabled', get_string('enabled', 'local_relationship'));

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'relationshipid');
        $mform->setType('relationshipid', PARAM_INT);

        $this->add_action_buttons();

        $this->set_data($relationshipcohort);
    }

    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);
        return $errors;
    }

}

