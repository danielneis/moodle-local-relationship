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
 * relationshipgroup related management functions, this file needs to be included manually.
 *
 * @package    local_relationship
 * @copyright  2010 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

class relationshiptag_edit_form extends moodleform {

    /**
     * Define the relationshiptag edit form
     */
    public function definition() {

        $mform = $this->_form;
        $editoroptions = $this->_customdata['editoroptions'];
        $relationshiptag = $this->_customdata['data'];
        
        $mform->addElement('text', 'name', get_string('tagname', 'local_relationship'), 'maxlength="254" size="50"');
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->setType('name', PARAM_TAG);
        
        $mform->addElement('hidden', 'id', $relationshiptag->id);
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'relationshipid', $relationshiptag->relationshipid);
        $mform->setType('relationshipid', PARAM_INT);
        
        $this->add_action_buttons();

        $this->set_data($relationshiptag);
    }

    public function validation($data, $files) {
        global $DB;

        $errors = parent::validation($data, $files);
        if($DB->record_exists('relationship_tags', array('relationshipid'=>$data['relationshipid'], 'name'=>$data['name']))) {
            $errors['name'] = get_string('tag_already_exists', 'local_relationship');
        }
        return $errors;
    }

}

