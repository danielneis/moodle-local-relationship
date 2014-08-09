<?php

require_once(__DIR__.'/../../config.php');
require_once($CFG->dirroot.'/local/relationship/lib.php');
require_once($CFG->dirroot.'/local/relationship/locallib.php');

require_login();

$relationshipid = required_param('relationshipid', PARAM_INT);
$relationship = $DB->get_record('relationship', array('id' => $relationshipid), '*', MUST_EXIST);
$context = context::instance_by_id($relationship->contextid, MUST_EXIST);

require_capability('local/relationship:view', $context);
$manager = has_capability('local/relationship:manage', $context);
$editable = $manager && empty($relationship->component);

$baseurl = new moodle_url('/local/relationship/cohorts.php', array('relationshipid' => $relationship->id));
$returnurl = new moodle_url('/local/relationship/index.php', array('contextid' => $context->id));

relationship_set_header($context, $baseurl, $relationship, 'cohorts');
relationship_set_title($relationship, 'cohorts');

$relationshipcohorts = relationship_get_cohorts($relationshipid);
$data = array();
foreach ($relationshipcohorts as $rch) {
    $line = array();

    $line[] = $rch->cohort ? $rch->cohort->name : '?';
    $line[] = $rch->role_name;
    $line[] = $rch->allowdupsingroups ? get_string('yes') : get_string('no');
    $line[] = $rch->uniformdistribution ? get_string('yes') : get_string('no');

    if ($editable) {
        $buttons = array();
        $buttons[] = html_writer::link(new moodle_url('/local/relationship/edit_cohort.php', array('relationshipcohortid' => $rch->id, 'delete' => 1)),
                html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('t/delete'), 'alt' => get_string('delete'), 'title' => get_string('delete'), 'class' => 'iconsmall')));
        $buttons[] = html_writer::link(new moodle_url('/local/relationship/edit_cohort.php', array('relationshipcohortid' => $rch->id)),
                html_writer::empty_tag('img', array('src' => $OUTPUT->pix_url('t/edit'), 'alt' => get_string('edit'), 'title' => get_string('edit'), 'class' => 'iconsmall')));
        $line[] = implode(' ', $buttons);
    }

    $data[] = $line;
}
$table = new html_table();
$table->head = array(
        get_string('cohort', 'cohort'),
        get_string('role'),
        get_string('allowdupsingroups', 'local_relationship').$OUTPUT->help_icon('allowdupsingroups', 'local_relationship'),
        get_string('uniformdistribute', 'local_relationship').$OUTPUT->help_icon('uniformdistribute', 'local_relationship'),
        get_string('edit')
);
$table->colclasses = array(
        'leftalign',
        'leftalign',
        'leftalign',
        'centeralign',
        'leftalign',
        'leftalign'
);

$table->id = 'relationships';
$table->attributes['class'] = 'admintable generaltable';
$table->data = $data;

echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
echo html_writer::table($table);

if ($editable) {
    $cohorts = relationship_get_cohort_options($relationshipid);
    foreach ($relationshipcohorts AS $rc) {
        unset($cohorts[$rc->cohortid]);
    }
    if (empty($cohorts)) {
        echo $OUTPUT->heading(get_string('nocohorts', 'local_relationship'), 4);
    } else {
        $add = new single_button(new moodle_url('/local/relationship/edit_cohort.php',
                array('relationshipid' => $relationshipid)), get_string('add'));
        echo $OUTPUT->render($add);
    }
} else if ($manager) {
    echo $OUTPUT->heading(get_string('noeditable', 'local_relationship', 4));
}
echo $OUTPUT->box_end();

echo $OUTPUT->footer();
