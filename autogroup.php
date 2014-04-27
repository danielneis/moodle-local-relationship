<?php

require_once('../../config.php');
require_once('locallib.php');
require_once('autogroup_form.php');
require_once($CFG->dirroot . '/group/lib.php');

require_login();

$relationshipid = required_param('relationshipid', PARAM_INT);
$relationship = $DB->get_record('relationship', array('id'=>$relationshipid), '*', MUST_EXIST);
$context = context::instance_by_id($relationship->contextid, MUST_EXIST);

require_capability('local/relationship:manage', $context);
if (!empty($relationship->component)) {
    print_error('cantedit', 'local_relationship');
}

$baseurl = new moodle_url('/local/relationship/autogroup.php', array('relationshipid'=>$relationship->id));
$returnurl = new moodle_url('/local/relationship/groups.php', array('relationshipid'=>$relationship->id));

relationship_set_header($context, $baseurl, $relationship, 'groups');

// Print the page and form
$preview = '';
$error = '';

/// Create the form
$editform = new autogroup_form(null, array('relationshipid' => $relationshipid));
$editform->set_data(array('relationshipid' => $relationshipid));

/// Handle form submission
if ($editform->is_cancelled()) {
    redirect($returnurl);
} elseif ($data = $editform->get_data()) {
    $numgrps  = $data->number;
    $relationshipcohortid = $data->relationshipcohortid;

    $existing_groups = $DB->get_records_menu('relationship_groups', array('relationshipid'=>$relationshipid), null, 'name, id');

    // Plan the allocation
    $new_groups = array();
    if($relationshipcohortid) {
        $sql = "SELECT cm.userid, u.firstname, u.lastname
                  FROM {relationship_cohorts} rc
                  JOIN {cohort_members} cm ON (cm.cohortid = rc.cohortid)
                  JOIN {user} u ON (u.id = cm.userid)
                 WHERE rc.id = :relationshipcohortid
              ORDER BY u.firstname";
        $members = $DB->get_records_sql($sql, array('relationshipcohortid'=>$relationshipcohortid));
        $i = 0;
        foreach($members as $m) {
            $new_groups[$i]['name']   = relationship_groups_parse_name(trim($data->namingscheme), "{$m->firstname} {$m->lastname}");
            $new_groups[$i]['userid'] = $m->userid;
            $new_groups[$i]['exists'] = isset($existing_groups[$new_groups[$i]['name']]);
            $i++;
        }
    } else {
        // allocate the users - all groups equal count first
        $count = $numgrps;
        $i = 0;
        while($count > 0) {
            $new_groups[$i]['name']   = relationship_groups_parse_name(trim($data->namingscheme), $i);
            $new_groups[$i]['userid'] = 0;
            $new_groups[$i]['exists'] = isset($existing_groups[$new_groups[$i]['name']]);
            if(!$new_groups[$i]['exists']) {
                $count--;
            }
            $i++;
        }
    }

    if (isset($data->preview)) {
        $table = new html_table();
        $table->size  = array('70%');
        $table->align = array('left');
        $table->width = '40%';

        $table->data  = array();
        foreach ($new_groups as $group) {
            if($group['exists']) {
                $text = html_writer::tag('span', $group['name'], array('style'=>'color:red; font-weight: bold;')) . get_string('alreadyexists', 'local_relationship');
            } else {
                $text = $group['name'];
            }
            $table->data[] = array($text);
        }
        $preview .= html_writer::table($table);
    } else {
        foreach ($new_groups as $key=>$group) {
            if(!$group['exists']) {
                $newgroup = new stdClass();
                $newgroup->relationshipid = $relationshipid;
                $newgroup->name = $group['name'];
                $newgroup->idnumber = '';
                $newgroup->uniformdistribution = 0;
                $id = relationship_add_group($newgroup);

                if($group['userid']) {
                    relationship_add_member($id, $relationshipcohortid, $group['userid']);
                }
            }
        }

        redirect($returnurl);
    }
}

relationship_set_title($relationship, 'autogroup');

if ($error != '') {
    echo $OUTPUT->notification($error);
}

/// Display the form
$editform->display();

if($preview !== '') {
    echo $OUTPUT->heading(get_string('groupspreview', 'group'));
    echo $preview;
}

echo $OUTPUT->footer();
