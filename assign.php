<?php

require_once(__DIR__.'/../../config.php');
require_once($CFG->dirroot.'/local/relationship/lib.php');
require_once($CFG->dirroot.'/local/relationship/locallib.php');

require_login();

$relationshipgroupid = required_param('relationshipgroupid', PARAM_INT);
$relationshipgroup = $DB->get_record('relationship_groups', array('id' => $relationshipgroupid), '*', MUST_EXIST);
$relationship = $DB->get_record('relationship', array('id' => $relationshipgroup->relationshipid), '*', MUST_EXIST);
$context = context::instance_by_id($relationship->contextid, MUST_EXIST);

require_capability('local/relationship:view', $context);

$canassign = has_capability('local/relationship:assign', $context);
$editable = $canassign && empty($relationship->component);

$baseurl = new moodle_url('/local/relationship/assign.php', array('relationshipgroupid' => $relationshipgroupid));
$returnurl = new moodle_url('/local/relationship/groups.php', array('relationshipid' => $relationship->id));

if (optional_param('cancel', false, PARAM_BOOL)) {
    redirect($returnurl);
}

relationship_set_header($context, $baseurl, $relationship, 'groups');
relationship_set_title($relationship, 'assignto', format_string($relationshipgroup->name));

echo $OUTPUT->notification(get_string('removeuserwarning', 'local_relationship'));

// Get the user_selector we will need.
if ($editable) {
    $potentialuserselector = new relationship_candidate_selector('addselect', array('relationshipgroup' => $relationshipgroup));
}
$existinguserselector = new relationship_existing_selector('removeselect', array('relationshipgroup' => $relationshipgroup));

// Process incoming user assignments to the relationship

if ($canassign && optional_param('add', false, PARAM_BOOL) && confirm_sesskey()) {
    $userstoassign = $potentialuserselector->get_selected_users();
    if (!empty($userstoassign)) {
        foreach ($userstoassign as $adduser) {
            relationship_add_member($relationshipgroup->id, $adduser->relationshipcohortid, $adduser->id);
        }
        $potentialuserselector->invalidate_selected_users();
        $existinguserselector->invalidate_selected_users();
    }
}

// Process removing user assignments to the relationship
if ($canassign && optional_param('remove', false, PARAM_BOOL) && confirm_sesskey()) {
    $userstoremove = $existinguserselector->get_selected_users();
    if (!empty($userstoremove)) {
        foreach ($userstoremove as $removeuser) {
            relationship_remove_member($relationshipgroup->id, $removeuser->relationshipcohortid, $removeuser->id);
        }
        $potentialuserselector->invalidate_selected_users();
        $existinguserselector->invalidate_selected_users();
    }
}

// Print the form.
if ($editable) {
    ?>
    <form id="assignform" method="post" action="<?php echo $PAGE->url ?>">
        <div>
            <input type="hidden" name="sesskey" value="<?php echo sesskey() ?>"/>

            <table summary="" class="generaltable generalbox boxaligncenter" cellspacing="0">
                <tr>
                    <td id="existingcell">
                        <p>
                            <label for="removeselect"><?php print_string('currentusers', 'local_relationship'); ?></label>
                        </p>
                        <?php $existinguserselector->display() ?>
                    </td>
                    <td id="buttonscell">
                        <div id="addcontrols">
                            <input name="add" id="add" type="submit"
                                   value="<?php echo $OUTPUT->larrow().'&nbsp;'.s(get_string('add')); ?>"
                                   title="<?php p(get_string('add')); ?>"/><br/>
                        </div>

                        <div id="removecontrols">
                            <input name="remove" id="remove" type="submit"
                                   value="<?php echo s(get_string('remove')).'&nbsp;'.$OUTPUT->rarrow(); ?>"
                                   title="<?php p(get_string('remove')); ?>"/>
                        </div>
                    </td>
                    <td id="potentialcell">
                        <p><label for="addselect"><?php print_string('potusers', 'local_relationship'); ?></label></p>
                        <?php $potentialuserselector->display() ?>
                    </td>
                </tr>
            </table>
        </div>
    </form>
<?php
} else {
    ?>
    <div>
        <table summary="" class="generaltable generalbox boxaligncenter" cellspacing="0">
            <tr>
                <td id="existingcell">
                    <p><label for="removeselect"><?php print_string('currentusers', 'local_relationship'); ?></label>
                    </p>
                    <?php $existinguserselector->display() ?>
                </td>
            </tr>
        </table>
    </div>
<?php

}

echo $OUTPUT->footer();
