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
 * Edit Relationship's Cohort page
 * @package local_relationship
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../config.php');
require($CFG->dirroot.'/local/relationship/lib.php');
require_once($CFG->dirroot.'/local/relationship/locallib.php');

require_login();

if ($relationshipcohortid = optional_param('relationshipcohortid', 0, PARAM_INT)) {
    $relationshipcohort = relationship_get_cohort($relationshipcohortid);
    $relationship = $DB->get_record('relationship', array('id' => $relationshipcohort->relationshipid), '*', MUST_EXIST);
} else {
    $relationshipid = required_param('relationshipid', PARAM_INT);
    $relationship = $DB->get_record('relationship', array('id' => $relationshipid), '*', MUST_EXIST);
    $relationshipcohort = new stdClass();
    $relationshipcohort->id = 0;
    $relationshipcohort->relationshipid = $relationshipid;
    $relationshipcohort->roleid = 0;
    $relationshipcohort->cohortid = 0;
    $relationshipcohort->allowdupsingroups = 0;
    $relationshipcohort->uniformdistribution = 0;
}

$context = context::instance_by_id($relationship->contextid, MUST_EXIST);
require_capability('local/relationship:manage', $context);
if (!empty($relationship->component)) {
    print_error('cantedit', 'local_relationship');
}

$baseurl = new moodle_url('/local/relationship/edit_cohort.php', array('relationshipid' => $relationship->id, 'relationshipcohortid' => $relationshipcohort->id));
$returnurl = new moodle_url('/local/relationship/cohorts.php', array('relationshipid' => $relationship->id));

if (optional_param('confirmedelete', 0, PARAM_BOOL) && confirm_sesskey() && $relationshipcohort->id) {
    relationship_delete_cohort($relationshipcohort);
    redirect($returnurl);
}

relationship_set_header($context, $baseurl, $relationship, 'cohorts');

if (optional_param('delete', 0, PARAM_BOOL) && $relationshipcohort->id) {
    $desc = format_string($relationshipcohort->role_name.'/'.$relationshipcohort->cohort->name);
    relationship_set_title($relationship, 'deletecohort', $desc);
    echo $OUTPUT->notification(get_string('deletecohortwarning', 'local_relationship'));
    $yesurl = new moodle_url('/local/relationship/edit_cohort.php', array('relationshipcohortid' => $relationshipcohort->id, 'relationshipid' => $relationship->id, 'delete' => 1, 'confirmedelete' => 1, 'sesskey' => sesskey()));
    $message = get_string('confirmdeleletecohort', 'local_relationship', $desc);
    echo $OUTPUT->confirm($message, $yesurl, $returnurl);
    echo $OUTPUT->footer();
    exit;
}

$editform = new \local_relationship\form\edit_cohort(null, array('data' => $relationshipcohort));

if ($editform->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $editform->get_data()) {
    if ($data->id) {
        relationship_update_cohort($data);
    } else {
        $id = relationship_add_cohort($data);
    }
    redirect($returnurl);
}

if ($relationshipcohort->id) {
    $desc = format_string($relationshipcohort->role_name.'/'.$relationshipcohort->cohort->name);
    relationship_set_title($relationship, 'editcohort', $desc);
} else {
    relationship_set_title($relationship, 'addcohort');
}
echo $editform->display();
echo $OUTPUT->footer();
