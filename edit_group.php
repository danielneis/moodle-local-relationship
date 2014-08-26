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
 * Edit Relationship's Groups page
 *
 * @package local_relationship
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../config.php');
require_once($CFG->dirroot.'/course/lib.php');
require($CFG->dirroot.'/local/relationship/lib.php');
require_once($CFG->dirroot.'/local/relationship/locallib.php');

require_login();

$category = null;
if ($relationshipgroupid = optional_param('relationshipgroupid', 0, PARAM_INT)) {
    $relationshipgroup = $DB->get_record('relationship_groups', array('id' => $relationshipgroupid), '*', MUST_EXIST);
    $relationship = $DB->get_record('relationship', array('id' => $relationshipgroup->relationshipid), '*', MUST_EXIST);
} else {
    $relationshipid = required_param('relationshipid', PARAM_INT);
    $relationship = $DB->get_record('relationship', array('id' => $relationshipid), '*', MUST_EXIST);
    $relationshipgroup = new stdClass();
    $relationshipgroup->id = 0;
    $relationshipgroup->relationshipid = $relationshipid;
    $relationshipgroup->name = '';
    $relationshipgroup->uniformdistribution = 0;
}
$context = context::instance_by_id($relationship->contextid, MUST_EXIST);

require_capability('local/relationship:manage', $context);
if (!empty($relationship->component)) {
    print_error('cantedit', 'local_relationship');
}

$baseurl = new moodle_url('/local/relationship/edit_group.php', array('relationshipgroupid' => $relationshipgroupid));
$returnurl = new moodle_url('/local/relationship/groups.php', array('relationshipid' => $relationship->id));

if (optional_param('confirmdelete', 0, PARAM_BOOL) && confirm_sesskey() && $relationshipgroup->id) {
    relationship_delete_group($relationshipgroup);
    redirect($returnurl);
}

$uniformdistribution = optional_param('uniformdistribution', -1, PARAM_BOOL);
if (($uniformdistribution == 0 || $uniformdistribution == 1) && $relationshipgroup->id) {
    $DB->set_field('relationship_groups', 'uniformdistribution', $uniformdistribution, array('id' => $relationshipgroup->id));
    redirect($returnurl);
}

if (optional_param('distributeremaining', 0, PARAM_BOOL) && $relationship->id) {
    relationship_uniformly_distribute_members($relationship->id);
    redirect($returnurl);
}

relationship_set_header($context, $baseurl, $relationship, 'groups');

if (optional_param('delete', 0, PARAM_BOOL) && $relationshipgroup->id) {
    $desc = format_string($relationshipgroup->name);
    relationship_set_title($relationship, 'deletegroup', $desc);
    echo $OUTPUT->notification(get_string('removegroupwarning', 'local_relationship'));
    $yesurl = new moodle_url('/local/relationship/edit_group.php', array('relationshipgroupid' => $relationshipgroup->id, 'relationshipid' => $relationship->id, 'delete' => 1, 'confirmdelete' => 1, 'sesskey' => sesskey()));
    $message = get_string('confirmdeletegroup', 'local_relationship', $desc);
    echo $OUTPUT->confirm($message, $yesurl, $returnurl);
    echo $OUTPUT->footer();
    die;
}

$editform = new \local_relationship\form\edit_group(null, array('data' => $relationshipgroup));

if ($editform->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $editform->get_data()) {
    if ($data->id) {
        relationship_update_group($data);
    } else {
        relationship_add_group($data);
    }

    // Use new context id, it could have been changed.
    redirect(new moodle_url('/local/relationship/groups.php', array('relationshipid' => $relationship->id)));
}

if ($relationshipgroup->id) {
    $desc = format_string($relationshipgroup->name);
    relationship_set_title($relationship, 'editgroup', $desc);
} else {
    relationship_set_title($relationship, 'addgroup');
}
echo $editform->display();
echo $OUTPUT->footer();

