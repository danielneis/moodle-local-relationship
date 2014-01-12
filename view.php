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

$relationshipid = required_param('relationshipid', PARAM_INT);

require_login();

$relationship = $DB->get_record('relationship', array('id'=>$relationshipid), '*', MUST_EXIST);
$context = context::instance_by_id($relationship->contextid, MUST_EXIST);
if ($context->contextlevel != CONTEXT_COURSECAT and $context->contextlevel != CONTEXT_SYSTEM) {
    print_error('invalidcontext');
}

require_capability('local/relationship:view', $context);

$returnurl = new moodle_url('/local/relationship/index.php', array('contextid'=>$context->id));

$PAGE->set_context($context);
$PAGE->set_url('/local/relationship/view.php', array('id'=>$relationship->id));
$PAGE->set_pagelayout('admin');
navigation_node::override_active_url(new moodle_url('/local/relationship/index.php', array('contextid'=>$relationship->contextid)));

$strheading = get_string('relationshipname', 'local_relationship', $relationship->name);

$PAGE->set_title($strheading);
$PAGE->set_heading($COURSE->fullname);
$PAGE->navbar->add(get_string('viewreport', 'local_relationship'));

echo $OUTPUT->header();
echo $OUTPUT->heading($strheading);

// -------------------------------------------------------------------------------------

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

echo $OUTPUT->box_start('generalbox boxaligncenter boxwidthwide');
echo $OUTPUT->heading(get_string('relationshipcourses', 'local_relationship', format_string($relationship->name)));
echo html_writer::table($table);
echo $OUTPUT->box_end();

echo $OUTPUT->single_button(new moodle_url('/local/relationship/index.php', array('contextid'=>$context->id)), get_string('backtorelationships', 'local_relationship'));

echo $OUTPUT->footer();

