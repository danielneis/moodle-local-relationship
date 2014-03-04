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
require($CFG->dirroot.'/local/relationship/locallib.php');
require_once($CFG->libdir.'/adminlib.php');

$relationshipid = required_param('relationshipid', PARAM_INT);

require_login();

$relationship = $DB->get_record('relationship', array('id'=>$relationshipid), '*', MUST_EXIST);
$context = context::instance_by_id($relationship->contextid, MUST_EXIST);
if ($context->contextlevel != CONTEXT_COURSECAT and $context->contextlevel != CONTEXT_SYSTEM) {
    print_error('invalidcontext');
}

$category = null;
if ($context->contextlevel == CONTEXT_COURSECAT) {
    $category = $DB->get_record('course_categories', array('id'=>$context->instanceid), '*', MUST_EXIST);
}

$manager = has_capability('local/relationship:manage', $context);
$canassign = has_capability('local/relationship:assign', $context);
if (!$manager) {
    require_capability('local/relationship:view', $context);
}

if ($category) {
    $PAGE->set_pagelayout('admin');
    $PAGE->set_context($context);
    $PAGE->set_url('/local/relationship/groups.php', array('relationshipid'=>$relationshipid));
    $PAGE->set_title($relationship->name);
    $PAGE->set_heading($COURSE->fullname);
} else {
    admin_externalpage_setup('relationships', '', null, '', array('pagelayout'=>'report'));
}

if ($context->contextlevel == CONTEXT_COURSECAT) {
    $category = $DB->get_record('course_categories', array('id'=>$context->instanceid), '*', MUST_EXIST);
    navigation_node::override_active_url(new moodle_url('/local/relationship/index.php', array('contextid'=>$relationship->contextid)));

} else {
    navigation_node::override_active_url(new moodle_url('/local/relationship/index.php', array()));
}

echo $OUTPUT->header();

echo $OUTPUT->heading($context->get_context_name());

$relationshipgroups = relationship_get_groups($relationshipid);
$data = array();
foreach($relationshipgroups as $relationshipgroup) {
    $line = array();

    $line[] = format_string($relationshipgroup->name);
    $line[] = $relationshipgroup->size;
    $line[] = format_string($relationship->component);

    $buttons = array();
    if($relationship->uniformdistribution) {
        $line[] = $relationshipgroup->uniformdistribution ? get_string('yes') : get_string('no');
        $buttons[] = html_writer::link(new moodle_url('/local/relationship/see_group_members.php', array('relationshipgroupid'=>$relationshipgroup->id)),
                html_writer::empty_tag('img', array('src'=>$OUTPUT->pix_url('t/assignroles'), 'alt'=>get_string('see_group_members', 'local_relationship'), 'title'=>get_string('see_group_members', 'local_relationship'), 'class'=>'iconsmall')));
    } else {
        if($relationshipgroup->uniformdistribution) {
            $link = html_writer::link(new moodle_url('/local/relationship/edit_group.php', array('relationshipgroupid'=>$relationshipgroup->id, 'uniformdistribution'=>0)), get_string('disable'));
            $line[] = get_string('yes') . " ({$link})";
        } else {
            $link = html_writer::link(new moodle_url('/local/relationship/edit_group.php', array('relationshipgroupid'=>$relationshipgroup->id, 'uniformdistribution'=>1)), get_string('enable'));
            $line[] = get_string('no') . " ({$link})";
        }

        if (empty($relationship->component)) {
            if ($manager) {
                $buttons[] = html_writer::link(new moodle_url('/local/relationship/edit_group.php', array('relationshipgroupid'=>$relationshipgroup->id, 'delete'=>1)),
                    html_writer::empty_tag('img', array('src'=>$OUTPUT->pix_url('t/delete'), 'alt'=>get_string('delete'), 'title'=>get_string('delete'), 'class'=>'iconsmall')));
                $buttons[] = html_writer::link(new moodle_url('/local/relationship/edit_group.php', array('relationshipgroupid'=>$relationshipgroup->id)),
                    html_writer::empty_tag('img', array('src'=>$OUTPUT->pix_url('t/edit'), 'alt'=>get_string('edit'), 'title'=>get_string('edit'), 'class'=>'iconsmall')));
            }
            if ($manager or $canassign) {
                $buttons[] = html_writer::link(new moodle_url('/local/relationship/assign.php', array('relationshipgroupid'=>$relationshipgroup->id)),
                    html_writer::empty_tag('img', array('src'=>$OUTPUT->pix_url('t/assignroles'), 'alt'=>get_string('assign', 'local_relationship'), 'title'=>get_string('assign', 'local_relationship'), 'class'=>'iconsmall')));
            }
        }
    }
    $line[] = implode(' ', $buttons);

    $data[] = $line;
}
$table = new html_table();
$table->head  = array(get_string('name', 'local_relationship'),
                      get_string('memberscount', 'local_relationship'),
                      get_string('component', 'local_relationship'),
                      get_string('uniformdistribute', 'local_relationship'),
                      get_string('edit'));
$table->colclasses = array('leftalign name',
                           'leftalign size',
                           'leftalign component',
                           'centeralign uniformdistribute',
                           'leftalign name');

$table->id = 'relationships';
$table->attributes['class'] = 'admintable generaltable';
$table->data = $data;

echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
echo $OUTPUT->heading(get_string('relationshipgroups', 'local_relationship', format_string($relationship->name)));
echo html_writer::table($table);
if ($manager && empty($relationship->component) && !$relationship->uniformdistribution) {
    $add = new single_button(new moodle_url('/local/relationship/edit_group.php', array('relationshipid'=>$relationshipid)), get_string('addgroup', 'local_relationship'));
    $back = new single_button(new moodle_url('/local/relationship/index.php', array('contextid'=>$context->id)), get_string('backtorelationships', 'local_relationship'));
    echo html_writer::tag('div', $OUTPUT->render($add) . $OUTPUT->render($back), array('class' => 'buttons'));
} else {
    if($relationship->uniformdistribution) {
        echo $OUTPUT->heading(get_string('groups_unchangeable', 'local_relationship'), '3', 'error');
    }
    echo $OUTPUT->single_button(new moodle_url('/local/relationship/index.php', array('contextid'=>$context->id)), get_string('backtorelationships', 'local_relationship'));
}
echo $OUTPUT->box_end();

echo $OUTPUT->footer();
