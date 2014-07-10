<?php

defined('MOODLE_INTERNAL') || die();

require_once('locallib.php');

function local_relationship_extends_settings_navigation(navigation_node $navigation) {
    global $PAGE;

    if (is_a($PAGE->context, 'context_coursecat') && has_capability('local/relationship:manage' ,$PAGE->context)) {
        $category_node = $navigation->get('categorysettings');
        if ($category_node) {
            $category_node->add(get_string('pluginname', 'local_relationship'),
                                new moodle_url('/local/relationship/index.php', array('contextid' => $PAGE->context->id)),
                                navigation_node::TYPE_SETTING,
                                null,
                                null,
                                new pix_icon('relationship', '', 'local_relationship'));
        }
    }
}

function local_relationship_cron() {
    relationship_uniformly_distribute_members();
    return true;
}


//
// TODO: Refactory to "relationship class"
//

function relationship_uniformly_distribute_members($relationshipid=NULL) {
    global $DB;

    $params = array();
    $cond = '';
    if(!empty($relationshipid)) {
        $params['id'] = $relationshipid;
        $cond = 'WHERE rl.id = :id';
    }

    $sql = "SELECT DISTINCT rc.*
              FROM {relationship} rl
              JOIN {relationship_cohorts} rc ON (rc.relationshipid = rl.id AND rc.uniformdistribution = 1)
              JOIN {relationship_groups} rg ON (rg.relationshipid = rl.id AND rc.uniformdistribution = 1)
              {$cond}";
    $rcs = $DB->get_records_sql($sql, $params);
    foreach($rcs AS $rc) {
        $params = array('relationshipid'=>$rc->relationshipid, 'relationshipcohortid'=>$rc->id);
        $sql = " SELECT cm.userid
                   FROM {relationship_cohorts} rc
                   JOIN {cohort} ch ON (ch.id = rc.cohortid)
                   JOIN {cohort_members} cm ON (cm.cohortid = ch.id)
         LEFT JOIN {relationship_members} rm ON (rm.relationshipcohortid = rc.id AND rm.userid = cm.userid)
                  WHERE rc.id = :relationshipcohortid
                    AND ISNULL(rm.userid)";
        $users = $DB->get_records_sql($sql, $params);
        relationship_uniformly_distribute_users($rc, array_keys($users));
    }
}

function relationship_uniformly_distribute_users($relationshipcohort, $userids) {
    global $DB;

    if(empty($userids)) {
        return;
    }

    $sql = "SELECT rg.id, rg.userlimit, count(DISTINCT rm.userid) as count
              FROM {relationship_cohorts} rc
              JOIN {relationship_groups} rg ON (rg.relationshipid = rc.relationshipid AND rg.uniformdistribution = 1)
         LEFT JOIN {relationship_members} rm ON (rm.relationshipgroupid = rg.id AND rm.relationshipcohortid = rc.id)
             WHERE rc.id = :relationshipcohortid
          GROUP BY rg.id";
    $groups = $DB->get_records_sql($sql, array('relationshipcohortid'=>$relationshipcohort->id));
    if(!empty($groups)) {
        foreach($userids AS $userid) {
            $min = 99999999;
            $gmin = 0;
            foreach($groups AS $grpid=>$grp) {
                if($grp->count < $min && ($grp->userlimit == 0 || $grp->count < $grp->userlimit)) {
                    $min = $grp->count;
                    $gmin = $grpid;
                }
            }
            if($gmin == 0) {
                break; // there is no group to add member
            } else {
                relationship_add_member($gmin, $relationshipcohort->id, $userid);
                $groups[$gmin]->count++;
            }
        }
    }
}

/**
 * Add relationship member
 * @param  int $relationshipid
 * @param  int $userid
 * @return void
 */
function relationship_add_member($relationshipgroupid, $relationshipcohortid, $userid) {
    global $DB;

    if ($DB->record_exists('relationship_members', array('relationshipgroupid'=>$relationshipgroupid, 'userid'=>$userid,
            'relationshipcohortid'=> $relationshipcohortid))) {
        // No duplicates!
        return;
    }

    $relationshipgroup = $DB->get_record('relationship_groups', array('id' => $relationshipgroupid), '*', MUST_EXIST);
    $relationshipcohort = $DB->get_record('relationship_cohorts', array('id' => $relationshipcohortid), '*', MUST_EXIST);
    $relationship = $DB->get_record('relationship', array('id' => $relationshipgroup->relationshipid), '*', MUST_EXIST);

    $record = new stdClass();
    $record->relationshipgroupid = $relationshipgroupid;
    $record->relationshipcohortid = $relationshipcohortid;
    $record->userid    = $userid;
    $record->timeadded = time();
    $record->id = $DB->insert_record('relationship_members', $record);

    $event = \local_relationship\event\relationshipgroup_member_added::create(array(
            'context' => context::instance_by_id($relationship->contextid),
            'objectid' => $relationshipgroupid,
            'relateduserid' => $userid,
            'other' => $relationshipcohort->roleid,
    ));
    $event->add_record_snapshot('relationship_groups', $relationshipgroup);
    $event->add_record_snapshot('relationship', $relationship);
    $event->trigger();
}

/**
 * Remove relationshgroupip member
 * @param  int $relationshipgroupid
 * @param  int $userid
 * @param  int $roleid
 * @return void
 */
function relationship_remove_member($relationshipgroupid, $relationshipcohortid, $userid) {
    global $DB;

    $DB->delete_records('relationship_members', array('relationshipgroupid'=>$relationshipgroupid, 'userid'=>$userid,
            'relationshipcohortid'=>$relationshipcohortid));

    $relationshipgroup = $DB->get_record('relationship_groups', array('id' => $relationshipgroupid), '*', MUST_EXIST);
    $relationshipcohort = $DB->get_record('relationship_cohorts', array('id' => $relationshipcohortid), '*', MUST_EXIST);
    $relationship = $DB->get_record('relationship', array('id' => $relationshipgroup->relationshipid), '*', MUST_EXIST);

    $event = \local_relationship\event\relationshipgroup_member_removed::create(array(
            'context' => context::instance_by_id($relationship->contextid),
            'objectid' => $relationshipgroupid,
            'relateduserid' => $userid,
            'other' => $relationshipcohort->roleid,
    ));
    $event->add_record_snapshot('relationship_groups', $relationshipgroup);
    $event->add_record_snapshot('relationship', $relationship);
    $event->trigger();
}

/**
 * @param $relationshipid
 * @return stdClass $relationship
 */
function relationship_get_relationship($relationshipid) {
    global $DB;

    $relationship = $DB->get_record('relationship', array('id' => $relationshipid), '*', MUST_EXIST);
    $relationship->tags = tag_get_tags_array('relationship', $relationshipid);
    return $relationship;
}

/**
 * Add new relationship.
 *
 * @param  stdClass $relationship
 * @return int new relationship id
 * @throws coding_exception
 */
function relationship_add_relationship($relationship) {
    global $DB;

    if (!isset($relationship->name)) {
        throw new coding_exception('Missing relationship name in relationship_add_relationship().');
    }
    $relationship->name = trim($relationship->name);
    if (!isset($relationship->idnumber)) {
        $relationship->idnumber = NULL;
    }
    if (!isset($relationship->description)) {
        $relationship->description = '';
    }
    if (!isset($relationship->descriptionformat)) {
        $relationship->descriptionformat = FORMAT_HTML;
    }
    if (empty($relationship->component)) {
        $relationship->component = '';
    }
    if (!isset($relationship->timecreated)) {
        $relationship->timecreated = time();
    }
    if (!isset($relationship->timemodified)) {
        $relationship->timemodified = $relationship->timecreated;
    }

    $relationship->id = $DB->insert_record('relationship', $relationship);
    tag_set('relationship', $relationship->id, $relationship->tags);

    $event = \local_relationship\event\relationship_created::create(array(
            'context' => context::instance_by_id($relationship->contextid),
            'objectid' => $relationship->id,
    ));
    $event->add_record_snapshot('relationship', $relationship);
    $event->trigger();

    return $relationship->id;
}

/**
 * Update existing relationship.
 * @param  stdClass $relationship
 * @return void
 */
function relationship_update_relationship($relationship) {
    global $DB;

    $relationship->timemodified = time();
    $DB->update_record('relationship', $relationship);
    tag_set('relationship', $relationship->id, $relationship->tags);

    $event = \local_relationship\event\relationship_updated::create(array(
            'context' => context::instance_by_id($relationship->contextid),
            'objectid' => $relationship->id,
    ));
    $event->add_record_snapshot('relationship', $relationship);
    $event->trigger();
}

/**
 * Delete relationship.
 * @param  stdClass $relationship
 * @return void
 */
function relationship_delete_relationship($relationship) {
    global $DB;

    $relationshipgroups = $DB->get_records('relationship_groups', array('relationshipid'=>$relationship->id));
    foreach($relationshipgroups AS $g) {
        relationship_delete_group($g);
    }

    $DB->delete_records('relationship_cohorts', array('relationshipid'=>$relationship->id));

    $tags = tag_get_tags_array('relationship', $relationship->id);
    tag_delete(array_keys($tags));

    $DB->delete_records('relationship', array('id'=>$relationship->id));

    $event = \local_relationship\event\relationship_deleted::create(array(
            'context' => context::instance_by_id($relationship->contextid),
            'objectid' => $relationship->id,
    ));
    $event->add_record_snapshot('relationship', $relationship);
    $event->trigger();
}

//
// Relationship Cohorts
//

function relationship_get_cohorts($relationshipid, $full=true) {
    global $DB;

    $cohorts = $DB->get_records('relationship_cohorts', array('relationshipid'=>$relationshipid));
    if($full) {
        foreach($cohorts AS $ch) {
            $ch->cohort = $DB->get_record('cohort', array('id'=>$ch->cohortid));
            if($role = $DB->get_record('role', array('id'=>$ch->roleid))) {
                $ch->role_name = role_get_name($role);
            } else {
                $ch->role_name = false;
            }
        }
    }
    return $cohorts;
}

function relationship_get_cohort($relationshipcohortid, $full=true) {
    global $DB;

    $cohort = $DB->get_record('relationship_cohorts', array('id'=>$relationshipcohortid), '*', MUST_EXIST);
    if($full) {
        $cohort->cohort = $DB->get_record('cohort', array('id'=>$cohort->cohortid));
        if($role = $DB->get_record('role', array('id'=>$cohort->roleid))) {
            $cohort->role_name = role_get_name($role);
        } else {
            $cohort->role_name = false;
        }
    }
    return $cohort;
}

function relationship_add_cohort($relationshipcohort) {
    global $DB;

    if (!isset($relationshipcohort->timecreated)) {
        $relationshipcohort->timecreated = time();
    }
    if (!isset($relationshipcohort->timemodified)) {
        $relationshipcohort->timemodified = $relationshipcohort->timecreated;
    }

    $relationshipcohort->id = $DB->insert_record('relationship_cohorts', $relationshipcohort);
    return $relationshipcohort->id;
}

function relationship_update_cohort($relationshipcohort) {
    global $DB;

    $relationshipcohort->timemodified = time();
    $DB->update_record('relationship_cohorts', $relationshipcohort);
}

function relationship_delete_cohort($relationshipcohort) {
    global $DB;

    $members = $DB->get_records('relationship_members', array('relationshipcohortid'=>$relationshipcohort->id));
    foreach($members as $m) {
        relationship_remove_member($m->relationshipgroupid, $m->relationshipcohortid, $m->userid);
    }
    $DB->delete_records('relationship_cohorts', array('id'=>$relationshipcohort->id));
}

//
// Relationship Groups
//

function relationship_get_groups($relationshipid) {
    global $DB;

    $sql = "SELECT rg.*, (SELECT count(*) FROM relationship_members WHERE relationshipgroupid = rg.id) as size
              FROM {relationship_groups} rg
             WHERE rg.relationshipid = :relationshipid
          GROUP BY rg.id
          ORDER BY name";
    return $DB->get_records_sql($sql, array('relationshipid'=>$relationshipid));
}

function relationship_add_group($relationshipgroup) {
    global $DB;

    $relationshipgroup->name = trim($relationshipgroup->name);
    if (!isset($relationshipgroup->timecreated)) {
        $relationshipgroup->timecreated = time();
    }
    if (!isset($relationshipgroup->timemodified)) {
        $relationshipgroup->timemodified = $relationshipgroup->timecreated;
    }

    $relationship = $DB->get_record('relationship', array('id' => $relationshipgroup->relationshipid), '*', MUST_EXIST);
    $relationshipgroup->id = $DB->insert_record('relationship_groups', $relationshipgroup);

    $event = \local_relationship\event\relationshipgroup_created::create(array(
            'context' => context::instance_by_id($relationship->contextid),
            'objectid' => $relationshipgroup->id,
    ));
    $event->add_record_snapshot('relationship_groups', $relationshipgroup);
    $event->add_record_snapshot('relationship', $relationship);
    $event->trigger();

    return $relationshipgroup->id;
}

function relationship_update_group($relationshipgroup) {
    global $DB;

    $relationshipgroup->timemodified = time();
    if (isset($relationshipgroup->name)) {
        $relationshipgroup->name = trim($relationshipgroup->name);
    }
    $DB->update_record('relationship_groups', $relationshipgroup);
    $relationship = $DB->get_record('relationship', array('id' => $relationshipgroup->relationshipid), '*', MUST_EXIST);

    $event = \local_relationship\event\relationshipgroup_updated::create(array(
            'context' => context::instance_by_id($relationship->contextid),
            'objectid' => $relationshipgroup->id,
    ));
    $event->add_record_snapshot('relationship_groups', $relationshipgroup);
    $event->add_record_snapshot('relationship', $relationship);
    $event->trigger();
}

function relationship_delete_group($relationshipgroup) {
    global $DB;

    $relationship = $DB->get_record('relationship', array('id' => $relationshipgroup->relationshipid), '*', MUST_EXIST);
    $DB->delete_records('relationship_members', array('relationshipgroupid'=>$relationshipgroup->id));
    $DB->delete_records('relationship_groups', array('id'=>$relationshipgroup->id));

    $event = \local_relationship\event\relationshipgroup_deleted::create(array(
            'context' => context::instance_by_id($relationship->contextid),
            'objectid' => $relationshipgroup->id,
    ));
    $event->add_record_snapshot('relationship_groups', $relationshipgroup);
    $event->add_record_snapshot('relationship', $relationship);
    $event->trigger();
}