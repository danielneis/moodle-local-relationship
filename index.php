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

$contextid = optional_param('contextid', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$searchquery  = optional_param('search', '', PARAM_RAW);

require_login();

if ($contextid) {
    $context = context::instance_by_id($contextid, MUST_EXIST);
} else {
    $context = context_system::instance();
}

if ($context->contextlevel != CONTEXT_COURSECAT and $context->contextlevel != CONTEXT_SYSTEM) {
    print_error('invalidcontext');
}

$category = null;
if ($context->contextlevel == CONTEXT_COURSECAT) {
    $category = $DB->get_record('course_categories', array('id'=>$context->instanceid), '*', MUST_EXIST);
}

$manager = has_capability('local/relationship:manage', $context);
if (!$manager) {
    require_capability('local/relationship:view', $context);
}

$strrelationships = get_string('relationships', 'local_relationship');

if ($category) {
    $PAGE->set_pagelayout('admin');
    $PAGE->set_context($context);
    $PAGE->set_url('/local/relationship/index.php', array('contextid'=>$context->id));
    $PAGE->set_title($strrelationships);
    $PAGE->set_heading($COURSE->fullname);
} else {
    admin_externalpage_setup('relationships', '', null, '', array('pagelayout'=>'report'));
}

echo $OUTPUT->header();

$relationships = relationship_get_relationships($context->id, $page, 25, $searchquery);

$count = '';
if ($relationships['allrelationships'] > 0) {
    if ($searchquery === '') {
        $count = ' ('.$relationships['allrelationships'].')';
    } else {
        $count = ' ('.$relationships['totalrelationships'].'/'.$relationships['allrelationships'].')';
    }
}

echo $OUTPUT->heading(get_string('relationshipsin', 'local_relationship', $context->get_context_name()).$count);

// Add search form.
$search  = html_writer::start_tag('form', array('id'=>'searchrelationshipquery', 'method'=>'get'));
$search .= html_writer::start_tag('div');
$search .= html_writer::label(get_string('searchrelationship', 'local_relationship'), 'relationship_search_q'); // No : in form labels!
$search .= html_writer::empty_tag('input', array('id'=>'relationship_search_q', 'type'=>'text', 'name'=>'search', 'value'=>$searchquery));
$search .= html_writer::empty_tag('input', array('type'=>'submit', 'value'=>get_string('search', 'local_relationship')));
$search .= html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'contextid', 'value'=>$contextid));
$search .= html_writer::end_tag('div');
$search .= html_writer::end_tag('form');
echo $search;


// Output pagination bar.
$params = array('page' => $page);
if ($contextid) {
    $params['contextid'] = $contextid;
}
if ($search) {
    $params['search'] = $searchquery;
}
$baseurl = new moodle_url('/local/relationship/index.php', $params);
echo $OUTPUT->paging_bar($relationships['totalrelationships'], $page, 25, $baseurl);

$data = array();
foreach($relationships['relationships'] as $relationship) {
    $line = array();

    $line[] = format_string($relationship->name);
    $line[] = format_text($relationship->description, $relationship->descriptionformat);

    $sql = "SELECT count(DISTINCT rm.userid)
              FROM relationship_groups rg
              JOIN relationship_members rm ON (rm.relationshipgroupid = rg.id)
             WHERE rg.relationshipid = :relationshipid";
    $line[] = $DB->count_records_sql($sql, array('relationshipid'=>$relationship->id));

    if($cohort1 = $DB->get_record('cohort', array('id'=>$relationship->cohortid1))) {
        $line[] = $cohort1->name;
    } else {
        $line[] = '?';
    }
    if($role1 = $DB->get_record('role', array('id'=>$relationship->roleid1))) {
        $line[] = role_get_name($role1);
    } else {
        $line[] = '?';
    }

    if($cohort2 = $DB->get_record('cohort', array('id'=>$relationship->cohortid2))) {
        $line[] = $cohort2->name;
    } else {
        $line[] = '?';
    }
    if($role2 = $DB->get_record('role', array('id'=>$relationship->roleid2))) {
        $line[] = role_get_name($role2);
    } else {
        $line[] = '?';
    }

    if (empty($relationship->component)) {
        $line[] = get_string('nocomponent', 'local_relationship');
    } else {
        $line[] = get_string('pluginname', $relationship->component);
    }

    $buttons = array();
    if (empty($relationship->component)) {
        if ($manager) {
            $buttons[] = html_writer::link(new moodle_url('/local/relationship/edit.php', array('id'=>$relationship->id, 'delete'=>1)),
                html_writer::empty_tag('img', array('src'=>$OUTPUT->pix_url('t/delete'), 'alt'=>get_string('delete'), 'class'=>'iconsmall')));
            $buttons[] = html_writer::link(new moodle_url('/local/relationship/edit.php', array('id'=>$relationship->id)),
                html_writer::empty_tag('img', array('src'=>$OUTPUT->pix_url('t/edit'), 'alt'=>get_string('edit'), 'class'=>'iconsmall')));
            $buttons[] = html_writer::link(new moodle_url('/local/relationship/groups.php', array('relationshipid'=>$relationship->id)),
                html_writer::empty_tag('img', array('src'=>$OUTPUT->pix_url('i/users'), 'alt'=>get_string('assign', 'local_relationship'), 'class'=>'iconsmall')));
        }
    }
    $line[] = implode(' ', $buttons);

    $data[] = $line;
}
$table = new html_table();
$table->head  = array(get_string('name', 'local_relationship'), get_string('description', 'local_relationship'),
                      get_string('memberscount', 'local_relationship'),
                      get_string('cohort1', 'local_relationship'),
                      get_string('role1', 'local_relationship'),
                      get_string('cohort2', 'local_relationship'),
                      get_string('role2', 'local_relationship'),
                      get_string('component', 'local_relationship'),
                      get_string('edit'));
$table->colclasses = array('leftalign name', 'leftalign description', 'leftalign size',
                           'leftalign cohort', 'leftalign role',
                           'leftalign cohort', 'leftalign role',
                           'centeralign source', 'centeralign action');
$table->id = 'relationships';
$table->attributes['class'] = 'admintable generaltable';
$table->data  = $data;
echo html_writer::table($table);
echo $OUTPUT->paging_bar($relationships['totalrelationships'], $page, 25, $baseurl);

if ($manager) {
    echo $OUTPUT->single_button(new moodle_url('/local/relationship/edit.php', array('contextid'=>$context->id)), get_string('add'));
}

echo $OUTPUT->footer();
