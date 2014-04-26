<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

class relationshipgroup_edit_form extends moodleform {

    /**
     * Define the relationshipgroup edit form
     */
    public function definition() {

        $mform = $this->_form;
        $relationshipgroup = $this->_customdata['data'];

        $mform->addElement('text', 'name', get_string('groupname', 'local_relationship'), 'maxlength="254" size="50"');
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->setType('name', PARAM_NOTAGS);

//        $mform->addElement('selectyesno', 'uniformdistribution', get_string('uniformdistribute', 'local_relationship'));
//        $mform->addHelpButton('uniformdistribution', 'uniformdistribute', 'local_relationship');

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'relationshipid');
        $mform->setType('relationshipid', PARAM_INT);

        $this->add_action_buttons();

        $this->set_data($relationshipgroup);
    }

    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);
        if($DB->record_exists_select('relationship_groups',
                                     "relationshipid = :relationshipid AND name = :name AND id != :id",
                                     array('relationshipid'=>$data['relationshipid'], 'name'=>$data['name'], 'id'=>$data['id']))){
               $errors['name'] = get_string('group_already_exists', 'local_relationship');
        }
        return $errors;
    }

}

