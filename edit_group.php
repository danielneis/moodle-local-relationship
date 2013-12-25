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
 * @package    core_relationship
 * @copyright  2010 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require($CFG->dirroot.'/course/lib.php');
require($CFG->dirroot.'/local/relationship/locallib.php');
require($CFG->dirroot.'/local/relationship/edit_group_form.php');

$groupid   = optional_param('groupid', 0, PARAM_INT);
$relationshipid = optional_param('relationshipid', 0, PARAM_INT);
$delete    = optional_param('delete', 0, PARAM_BOOL);
$confirm   = optional_param('confirm', 0, PARAM_BOOL);

require_login();

$category = null;
if ($groupid) {
    $group = $DB->get_record('relationship_groups', array('id'=>$groupid), '*', MUST_EXIST);
    $relationship = $DB->get_record('relationship', array('id'=>$group->relationshipid), '*', MUST_EXIST);
} else {
    $relationship = $DB->get_record('relationship', array('id'=>$relationshipid), '*', MUST_EXIST);
    $group = new stdClass();
    $group->id             = 0;
    $group->relationshipid = $relationshipid;
    $group->name           = '';
}

$context = context::instance_by_id($relationship->contextid, MUST_EXIST);
if ($context->contextlevel != CONTEXT_COURSECAT and $context->contextlevel != CONTEXT_SYSTEM) {
    print_error('invalidcontext');
}

require_capability('local/relationship:manage', $context);

$returnurl = new moodle_url('/local/relationship/groups.php', array('groupid'=>$group->id, 'relationshipid'=>$relationship->id));

if (!empty($relationship->component)) {
    // We can not manually edit relationships that were created by external systems, sorry.
    redirect($returnurl);
}

$PAGE->set_context($context);
$PAGE->set_url('/local/relationship/edit_group.php', array('groupid'=>$group->id, 'relationshipid'=>$relationship->id));
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');

if ($context->contextlevel == CONTEXT_COURSECAT) {
    $category = $DB->get_record('course_categories', array('id'=>$context->instanceid), '*', MUST_EXIST);
    navigation_node::override_active_url(new moodle_url('/local/relationship/index.php', array('contextid'=>$context->id)));

} else {
    navigation_node::override_active_url(new moodle_url('/local/relationship/index.php', array()));
}

if ($delete and $group->id) {
    $PAGE->url->param('delete', 1);
    if ($confirm and confirm_sesskey()) {
        relationship_delete_group($group);
        redirect($returnurl);
    }
    $strheading = get_string('delgroup', 'local_relationship');
    $PAGE->navbar->add($strheading);
    $PAGE->set_title($strheading);
    $PAGE->set_heading($COURSE->fullname);
    echo $OUTPUT->header();
    echo $OUTPUT->heading($strheading);
    $yesurl = new moodle_url('/local/relationship/edit_group.php', array('groupid'=>$group->id, 'relationshipid'=>$relationship->id, 'delete'=>1, 'confirm'=>1,'sesskey'=>sesskey()));
    $message = get_string('delconfirmgroup', 'local_relationship', format_string($group->name));
    echo $OUTPUT->confirm($message, $yesurl, $returnurl);
    echo $OUTPUT->footer();
    die;
}

$editoroptions = array('maxfiles'=>0, 'context'=>$context);
$strheading = get_string('editgroup', 'local_relationship');

$PAGE->set_title($strheading);
$PAGE->set_heading($COURSE->fullname);
$PAGE->navbar->add($strheading);

$editform = new group_edit_form(null, array('editoroptions'=>$editoroptions, 'data'=>$group));

if ($editform->is_cancelled()) {
    redirect($returnurl);

} else if ($data = $editform->get_data()) {
    // $data = file_postupdate_standard_editor($data, 'description', $editoroptions, $context);
    if ($data->id) {
        relationship_update_group($data);
    } else {
        relationship_add_group($data);
    }

    // Use new context id, it could have been changed.
    redirect(new moodle_url('/local/relationship/groups.php', array('relationshipid'=>$relationship->id)));
}

echo $OUTPUT->header();
echo $OUTPUT->heading($strheading);
echo $editform->display();
echo $OUTPUT->footer();

