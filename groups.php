<?php

require('../../config.php');
require($CFG->dirroot.'/local/relationship/locallib.php');
require_once($CFG->libdir.'/adminlib.php');

require_login();

$relationshipid = required_param('relationshipid', PARAM_INT);
$relationship = $DB->get_record('relationship', array('id'=>$relationshipid), '*', MUST_EXIST);
$context = context::instance_by_id($relationship->contextid, MUST_EXIST);

require_capability('local/relationship:view', $context);
$manager = has_capability('local/relationship:manage', $context);
$canassign = has_capability('local/relationship:assign', $context);

$baseurl = new moodle_url('/local/relationship/groups.php', array('relationshipid'=>$relationship->id));
$returnurl = new moodle_url('/local/relationship/index.php', array('contextid'=>$context->id));

relationship_set_header($context, $baseurl, $relationship, 'groups');
relationship_set_title($relationship, 'groups');

$relationshipgroups = relationship_get_groups($relationshipid);
$data = array();
foreach($relationshipgroups as $relationshipgroup) {
    $line = array();

    $line[] = format_string($relationshipgroup->name);
    $line[] = $relationshipgroup->size;
    $line[] = format_string($relationship->component);

    $buttons = array();
    if($relationshipgroup->uniformdistribution) {
        $line[] = get_string('yes');
    } else {
        $line[] = get_string('no');
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
if ($manager && empty($relationship->component)) {
    $add = new single_button(new moodle_url('/local/relationship/edit_group.php', array('relationshipid'=>$relationshipid)), get_string('addgroup', 'local_relationship'));
    echo html_writer::tag('div', $OUTPUT->render($add), array('class' => 'buttons'));
}
echo $OUTPUT->box_end();

echo $OUTPUT->footer();
