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
 * relationship related management functions, this file needs to be included manually.
 *
 * @package    local_relationship
 * @copyright  2010 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/lib/formslib.php');

class relationship_edit_form extends moodleform {

    /**
     * Define the relationship edit form
     */
    public function definition() {

        $mform = $this->_form;
        $editoroptions = $this->_customdata['editoroptions'];
        $relationship = $this->_customdata['data'];

        $mform->addElement('text', 'name', get_string('name', 'local_relationship'), 'maxlength="254" size="50"');
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->setType('name', PARAM_TEXT);

        $mform->addElement('editor', 'description_editor', get_string('description', 'local_relationship'), null, $editoroptions);
        $mform->setType('description_editor', PARAM_RAW);

        $cohort_options = $this->get_cohort_options($relationship->contextid);
        $role_options = $this->get_role_options();

        $mform->addElement('select', 'cohortid1', get_string('cohort1', 'local_relationship'), $cohort_options);
        $mform->addElement('select', 'roleid1', get_string('role1', 'local_relationship'), $role_options);
        $mform->addElement('select', 'cohortid2', get_string('cohort2', 'local_relationship'), $cohort_options);
        $mform->addElement('select', 'roleid2', get_string('role2', 'local_relationship'), $role_options);

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

        return $errors;
    }

    protected function get_cohort_options($contextid) {
        global $DB;

        $sql = "SELECT *
                  FROM {cohort}
                 WHERE contextid IN ($contextid, 1)
                ORDER BY name";
        $cohortsdb = $DB->get_records_sql($sql);
        $cohorts = array();
        foreach($cohortsdb AS $id=>$ch) {
            $cohorts[$id] = $ch->name;
        }
        return $cohorts;
    }

    protected function get_role_options() {
        global $DB;

        $sql = "SELECT *
                  FROM {role}
                 WHERE shortname NOT IN ('manager','coursecreator','guest','user','frontpage')
                ORDER BY shortname";
        $rolesdb = $DB->get_records_sql($sql);
        $roles = array();
        foreach($rolesdb AS $id=>$r) {
            $roles[$id] = role_get_name($r);
        }
        return $roles;
    }

    protected function get_category_options($currentcontextid) {
        global $CFG;
        require_once($CFG->libdir. '/coursecatlib.php');
        $displaylist = coursecat::make_categories_list('local/relationship:manage');
        $options = array();
        $syscontext = context_system::instance();
        if (has_capability('local/relationship:manage', $syscontext)) {
            $options[$syscontext->id] = $syscontext->get_context_name();
        }
        foreach ($displaylist as $cid=>$name) {
            $context = context_coursecat::instance($cid);
            $options[$context->id] = $name;
        }
        // Always add current - this is not likely, but if the logic gets changed it might be a problem.
        if (!isset($options[$currentcontextid])) {
            $context = context::instance_by_id($currentcontextid, MUST_EXIST);
            $options[$context->id] = $syscontext->get_context_name();
        }
        return $options;
    }
}

