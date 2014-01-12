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

require('../../config.php');
require($CFG->dirroot.'/course/lib.php');
require($CFG->dirroot.'/local/relationship/locallib.php');
require($CFG->dirroot.'/local/relationship/edit_form.php');

$relationshipid = optional_param('relationshipid', 0, PARAM_INT);
$contextid = optional_param('contextid', 0, PARAM_INT);
$delete    = optional_param('delete', 0, PARAM_BOOL);
$confirm   = optional_param('confirm', 0, PARAM_BOOL);

require_login();

$category = null;
if ($relationshipid) {
    $relationship = $DB->get_record('relationship', array('id'=>$relationshipid), '*', MUST_EXIST);
    $context = context::instance_by_id($relationship->contextid, MUST_EXIST);
} else {
    $context = context::instance_by_id($contextid, MUST_EXIST);
    if ($context->contextlevel != CONTEXT_COURSECAT and $context->contextlevel != CONTEXT_SYSTEM) {
        print_error('invalidcontext');
    }
    $relationship = new stdClass();
    $relationship->id          = 0;
    $relationship->contextid   = $context->id;
    $relationship->name        = '';
    $relationship->description = '';
}

require_capability('local/relationship:manage', $context);

$returnurl = new moodle_url('/local/relationship/index.php', array('contextid'=>$context->id));

if (!empty($relationship->component)) {
    // We can not manually edit relationships that were created by external systems, sorry.
    redirect($returnurl);
}

$PAGE->set_context($context);
$PAGE->set_url('/local/relationship/edit.php', array('contextid'=>$context->id, 'id'=>$relationship->id));
$PAGE->set_pagelayout('admin');

if ($context->contextlevel == CONTEXT_COURSECAT) {
    $category = $DB->get_record('course_categories', array('id'=>$context->instanceid), '*', MUST_EXIST);
    navigation_node::override_active_url(new moodle_url('/local/relationship/index.php', array('contextid'=>$relationship->contextid)));

} else {
    navigation_node::override_active_url(new moodle_url('/local/relationship/index.php', array()));
}

if ($delete and $relationship->id) {
    $PAGE->url->param('delete', 1);
    if ($confirm and confirm_sesskey()) {
        relationship_delete_relationship($relationship);
        redirect($returnurl);
    }
    $strheading = get_string('delrelationship', 'local_relationship');
    $PAGE->navbar->add($strheading);
    $PAGE->set_title($strheading);
    $PAGE->set_heading($COURSE->fullname);
    echo $OUTPUT->header();
    echo $OUTPUT->heading($strheading);
    $yesurl = new moodle_url('/local/relationship/edit.php', array('relationshipid'=>$relationship->id, 'delete'=>1, 'confirm'=>1,'sesskey'=>sesskey()));
    $message = get_string('delconfirm', 'local_relationship', format_string($relationship->name));
    echo $OUTPUT->confirm($message, $yesurl, $returnurl);
    echo $OUTPUT->footer();
    die;
}

$editoroptions = array('maxfiles'=>0, 'context'=>$context);
if ($relationship->id) {
    // Edit existing.
    $relationship = file_prepare_standard_editor($relationship, 'description', $editoroptions, $context);
    $strheading = get_string('editrelationship', 'local_relationship');

} else {
    // Add new.
    $relationship = file_prepare_standard_editor($relationship, 'description', $editoroptions, $context);
    $strheading = get_string('addrelationship', 'local_relationship');
}

$PAGE->set_title($strheading);
$PAGE->set_heading($COURSE->fullname);
$PAGE->navbar->add($strheading);

$editform = new relationship_edit_form(null, array('editoroptions'=>$editoroptions, 'data'=>$relationship));

if ($editform->is_cancelled()) {
    redirect($returnurl);

} else if ($data = $editform->get_data()) {
    $data = file_postupdate_standard_editor($data, 'description', $editoroptions, $context);

    if ($data->id) {
        relationship_update_relationship($data);
    } else {
        relationship_add_relationship($data);
    }

    // Use new context id, it could have been changed.
    redirect(new moodle_url('/local/relationship/index.php', array('contextid'=>$data->contextid)));
}

echo $OUTPUT->header();
echo $OUTPUT->heading($strheading);
echo $editform->display();

// ---------------------------------------------------------------------------------------------
// Show related courses
if ($relationship->id) {
    $relationshipcourses = relationship_get_courses($relationship->id);
    $data = array();
    foreach($relationshipcourses as $rc) {
        $enrol_url = new moodle_url('/enrol/instances.php', array('id'=>$rc->id));
        $enrol_link = html_writer::link($enrol_url, format_string($rc->fullname), array('target'=>'_new'));
        $data[] = array($enrol_link);
    }

    $table = new html_table();
    $table->head  = array(get_string('fullname'));
    $table->colclasses = array('leftalign name');
    $table->id = 'relationships courses';
    $table->attributes['class'] = 'admintable generaltable';
    $table->data = $data;

    echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthnarrow');
    echo $OUTPUT->heading(get_string('relationshipcourses', 'local_relationship', format_string($relationship->name)));
    echo html_writer::table($table);
    echo $OUTPUT->box_end();
}

echo $OUTPUT->footer();

