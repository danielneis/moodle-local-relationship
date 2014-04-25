<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

class relationship_edit_form extends moodleform {

    public function definition() {

        $mform = $this->_form;
        $editoroptions = $this->_customdata['editoroptions'];
        $relationship = $this->_customdata['data'];

        $mform->addElement('text', 'name', get_string('name', 'local_relationship'), 'maxlength="254" size="50"');
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->setType('name', PARAM_TEXT);

        $mform->addElement('editor', 'description_editor', get_string('description', 'local_relationship'), null, $editoroptions);
        $mform->setType('description_editor', PARAM_RAW);

        $mform->addElement('tags', 'tags', get_string('tags'), array('display' => 'noofficial'));
        $mform->setType('tags', PARAM_TEXT);

        $mform->addElement('selectyesno', 'enabled', get_string('enabled', 'local_relationship'));

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'contextid');
        $mform->setType('contextid', PARAM_INT);

        $this->add_action_buttons();

        $this->set_data($relationship);
    }

    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);

        $params = array('name'=>addslashes($data['name']), 'contextid'=>$data['contextid']);
        if($data['id']){
            $where = "id != :id AND";
            $params['id'] = $data['id'];
        } else {
            $where = '';
        }
        $sql = "SELECT id FROM {relationship} WHERE {$where} name = :name AND contextid = :contextid";
        if($DB->record_exists_sql($sql, $params)){
            $errors['name'] = get_string('relationship_already_exists', 'local_relationship');
        }

        return $errors;
    }

}
