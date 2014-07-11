<?php

require_once(__DIR__.'/../../config.php');
require_once($CFG->dirroot.'/local/relationship/locallib.php');
require_once($CFG->dirroot.'/local/relationship/edit_form.php');

require_login();

$relationshipid = optional_param('relationshipid', 0, PARAM_INT);
if ($relationshipid) {
    $relationship = relationship_get_relationship($relationshipid);
    $context = context::instance_by_id($relationship->contextid, MUST_EXIST);
} else {
    $contextid = optional_param('contextid', 0, PARAM_INT);
    $context = context::instance_by_id($contextid, MUST_EXIST);
    $relationship = new stdClass();
    $relationship->id = 0;
    $relationship->contextid = $context->id;
    $relationship->name = '';
    $relationship->description = '';
    $relationship->tags = array();
}

require_capability('local/relationship:manage', $context);
if (!empty($relationship->component)) {
    print_error('cantedit', 'local_relationship');
}

$baseurl = new moodle_url('/local/relationship/edit.php', array('relationshipid' => $relationship->id, 'contextid' => $context->id));
$returnurl = new moodle_url('/local/relationship/index.php', array('contextid' => $context->id));

if (optional_param('confirmdelete', 0, PARAM_BOOL) && confirm_sesskey() && $relationship->id) {
    relationship_delete_relationship($relationship);
    redirect($returnurl);
}

if (optional_param('delete', 0, PARAM_BOOL) && $relationship->id) {
    relationship_set_header($context, $baseurl, $relationship);
    relationship_set_title($relationship, 'deleterelationship');
    $yesurl = new moodle_url('/local/relationship/edit.php', array('relationshipid' => $relationship->id, 'confirmdelete' => 1, 'sesskey' => sesskey()));
    $message = get_string('confirmdelete', 'local_relationship', format_string($relationship->name));
    echo $OUTPUT->confirm($message, $yesurl, $returnurl);
    echo $OUTPUT->footer();
    die;
}

relationship_set_header($context, $baseurl, $relationship);

$editoroptions = array('maxfiles' => 0, 'context' => $context);

$relationship = file_prepare_standard_editor($relationship, 'description', $editoroptions, $context);
$editform = new relationship_edit_form(null, array('editoroptions' => $editoroptions, 'data' => $relationship));

if ($editform->is_cancelled()) {
    redirect($returnurl);
} else if ($data = $editform->get_data()) {
    $data = file_postupdate_standard_editor($data, 'description', $editoroptions, $context);
    if ($data->id) {
        relationship_update_relationship($data);
    } else {
        relationship_add_relationship($data);
    }
    redirect($returnurl);
}

$action = $relationship->id ? 'editrelationship' : 'addrelationship';
relationship_set_title($relationship, $action);
echo $editform->display();
echo $OUTPUT->footer();
