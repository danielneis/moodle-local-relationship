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
require_once($CFG->dirroot.'/local/relationship/locallib.php');

$relationshipgroupid = required_param('relationshipgroupid', PARAM_INT);

require_login();

$relationshipgroup = $DB->get_record('relationship_groups', array('id'=>$relationshipgroupid), '*', MUST_EXIST);
$relationship = $DB->get_record('relationship', array('id'=>$relationshipgroup->relationshipid), '*', MUST_EXIST);
$context = context::instance_by_id($relationship->contextid, MUST_EXIST);

require_capability('local/relationship:assign', $context);

$PAGE->set_context($context);
$PAGE->set_url('/local/relationship/assign.php', array('relationshipgroupid'=>$relationshipgroupid));
$PAGE->set_pagelayout('admin');

$returnurl = new moodle_url('/local/relationship/groups.php', array('relationshipid'=>$relationship->id));

if (!empty($relationship->component)) {
    // We can not manually edit relationships that were created by external systems, sorry.
    redirect($returnurl);
}

if (optional_param('cancel', false, PARAM_BOOL)) {
    redirect($returnurl);
}

if ($context->contextlevel == CONTEXT_COURSECAT) {
    $category = $DB->get_record('course_categories', array('id'=>$context->instanceid), '*', MUST_EXIST);
    navigation_node::override_active_url(new moodle_url('/local/relationship/index.php', array('contextid'=>$relationship->contextid)));
} else {
    navigation_node::override_active_url(new moodle_url('/local/relationship/index.php', array()));
}
$PAGE->navbar->add(get_string('assign', 'local_relationship'));

$PAGE->set_title(get_string('relationship:assign', 'local_relationship'));
$PAGE->set_heading($COURSE->fullname);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('relationshipname', 'local_relationship', format_string($relationship->name)));
echo $OUTPUT->heading(get_string('assignto', 'local_relationship', format_string($relationshipgroup->name)));

echo $OUTPUT->notification(get_string('removeuserwarning', 'local_relationship'));

// Get the user_selector we will need.
$potentialuserselector = new relationship_candidate_selector('addselect', array('relationshipgroup'=>$relationshipgroup));
$existinguserselector = new relationship_existing_selector('removeselect', array('relationshipgroup'=>$relationshipgroup));

// Process incoming user assignments to the relationship

if (optional_param('add', false, PARAM_BOOL) && confirm_sesskey()) {
    $userstoassign = $potentialuserselector->get_selected_users();
    if (!empty($userstoassign)) {
        foreach ($userstoassign as $adduser) {
            relationshipgroup_add_member($relationshipgroup->id, $adduser->id, $adduser->roleid);
        }
        $potentialuserselector->invalidate_selected_users();
        $existinguserselector->invalidate_selected_users();
    }
}

// Process removing user assignments to the relationship
if (optional_param('remove', false, PARAM_BOOL) && confirm_sesskey()) {
    $userstoremove = $existinguserselector->get_selected_users();
    if (!empty($userstoremove)) {
        foreach ($userstoremove as $removeuser) {
            relationshipgroup_remove_member($relationshipgroup->id, $removeuser->id, $removeuser->roleid);
        }
        $potentialuserselector->invalidate_selected_users();
        $existinguserselector->invalidate_selected_users();
    }
}

// Print the form.
?>
<form id="assignform" method="post" action="<?php echo $PAGE->url ?>"><div>
  <input type="hidden" name="sesskey" value="<?php echo sesskey() ?>" />

  <table summary="" class="generaltable generalbox boxaligncenter" cellspacing="0">
    <tr>
      <td id="existingcell">
          <p><label for="removeselect"><?php print_string('currentusers', 'local_relationship'); ?></label></p>
          <?php $existinguserselector->display() ?>
      </td>
      <td id="buttonscell">
          <div id="addcontrols">
              <input name="add" id="add" type="submit" value="<?php echo $OUTPUT->larrow().'&nbsp;'.s(get_string('add')); ?>" title="<?php p(get_string('add')); ?>" /><br />
          </div>

          <div id="removecontrols">
              <input name="remove" id="remove" type="submit" value="<?php echo s(get_string('remove')).'&nbsp;'.$OUTPUT->rarrow(); ?>" title="<?php p(get_string('remove')); ?>" />
          </div>
      </td>
      <td id="potentialcell">
          <p><label for="addselect"><?php print_string('potusers', 'local_relationship'); ?></label></p>
          <?php $potentialuserselector->display() ?>
      </td>
    </tr>
    <tr><td colspan="3" id='backcell'>
      <input type="submit" name="cancel" value="<?php p(get_string('backtogroupsrelationship', 'local_relationship')); ?>" />
    </td></tr>
  </table>
</div></form>

<?php

echo $OUTPUT->footer();
