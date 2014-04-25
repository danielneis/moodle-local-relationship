<?php

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/selector/lib.php');
require_once($CFG->dirroot . '/tag/lib.php');

function relationship_set_header($context, $url, $relationship=null, $module=null) {
    global $PAGE, $COURSE, $DB;

    if ($context->contextlevel != CONTEXT_COURSECAT) {
        print_error('invalidcontext');
    }
    $category = $DB->get_record('course_categories', array('id'=>$context->instanceid), '*', MUST_EXIST);
    $navtitle = get_string('relationships', 'local_relationship');

    $PAGE->set_pagelayout('standard');
    $PAGE->set_context($context);
    $PAGE->set_url($url);
    $PAGE->set_heading($COURSE->fullname);
    $PAGE->set_title($navtitle);

    $PAGE->navbar->add($category->name, new moodle_url('/course/index.php', array('categoryid'=>$category->id)));
    $PAGE->navbar->add($navtitle, new moodle_url('/local/relationship/index.php', array('contextid'=>$context->id)));
    if($module) {
        $PAGE->navbar->add(get_string($module, 'local_relationship'),
                           new moodle_url("/local/relationship/{$module}.php", array('relationshipid'=>$relationship->id)));
    }
}

function relationship_set_title($relationship=null, $action=null, $param=null) {
    global $OUTPUT;

    echo $OUTPUT->header();
    if($relationship) {
        echo $OUTPUT->heading(get_string('relationship', 'local_relationship') . ': ' . format_string($relationship->name));
        echo html_writer::empty_tag('BR');
    }
    if($action) {
        echo $OUTPUT->heading(get_string($action, 'local_relationship', $param), '4');
    }
}

function relationship_get_role_options() {
    $all_roles = role_get_names();
    $ctx_roles = get_roles_for_contextlevels(CONTEXT_COURSE) + get_roles_for_contextlevels(CONTEXT_COURSECAT);
    $roles = array();
    foreach($ctx_roles AS $id=>$roleid) {
        if($roleid > 2) {
            $roles[$roleid] = $all_roles[$roleid]->localname;
        }
    }
    return $roles;
}

function relationship_get_cohort_options($relationshipid) {
    global $DB;

    $relationship = $DB->get_record('relationship', array('id' => $relationshipid), '*', MUST_EXIST);
    $context = context::instance_by_id($relationship->contextid);

    $contextids = array();
    foreach($context->get_parent_context_ids(true) as $ctxid) {
        $context = context::instance_by_id($ctxid);
        if (has_capability('moodle/cohort:view', $context)) {
            $contextids[] = $ctxid;
        }
    }
    list($in_sql, $params) = $DB->get_in_or_equal($contextids, SQL_PARAMS_NAMED);
    $sql = "SELECT id, name FROM {cohort} WHERE contextid {$in_sql} ORDER BY name";
    return $DB->get_records_sql_menu($sql, $params);
}

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
 */
function relationship_add_relationship($relationship) {
    global $DB;

    if (!isset($relationship->name)) {
        throw new coding_exception('Missing relationship name in relationship_add_relationship().');
    }
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

    if (property_exists($relationship, 'component') and empty($relationship->component)) {
        // prevent NULLs
        $relationship->component = '';
    }
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

    if ($relationship->component) {
        // TODO: add component delete callback
    }

    $relationshipgroups = $DB->get_records('relationship_groups', array('relationshipid'=>$relationship->id));
    foreach($relationshipgroups AS $g) {
        $DB->delete_records('relationship_members', array('relationshipgroupid'=>$g->id));
        $DB->delete_records('relationship_groups', array('id'=>$g->id));
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

function relationship_add_group($relationshipgroup) {
    global $DB;

    if (!isset($relationshipgroup->name)) {
        throw new coding_exception('Missing relationshipgroup name in relationshipgroup_add_group().');
    }
    $relationshipgroup->name = trim($relationshipgroup->name);
    if (!isset($relationshipgroup->timecreated)) {
        $relationshipgroup->timecreated = time();
    }
    if (!isset($relationshipgroup->timemodified)) {
        $relationshipgroup->timemodified = $relationshipgroup->timecreated;
    }

    $relationshipgroup->id = $DB->insert_record('relationship_groups', $relationshipgroup);
    $relationship = $DB->get_record('relationship', array('id' => $relationshipgroup->relationshipid), '*', MUST_EXIST);

    $event = \local_relationship\event\relationshipgroup_created::create(array(
        'context' => context::instance_by_id($relationship->contextid),
        'objectid' => $relationshipgroup->id,
    ));
    $event->add_record_snapshot('relationship_groups', $relationshipgroup);
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
    $event->trigger();
}

function relationship_delete_group($relationshipgroup) {
    global $DB;

    $DB->delete_records('relationship_members', array('relationshipgroupid'=>$relationshipgroup->id));
    $DB->delete_records('relationship_groups', array('id'=>$relationshipgroup->id));
    $relationship = $DB->get_record('relationship', array('id' => $relationshipgroup->relationshipid), '*', MUST_EXIST);

    $event = \local_relationship\event\relationshipgroup_deleted::create(array(
        'context' => context::instance_by_id($relationship->contextid),
        'objectid' => $relationshipgroup->id,
    ));
    $event->add_record_snapshot('relationship_groups', $relationshipgroup);
    $event->trigger();
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

    $DB->delete_records('relationship_members', array('relationshipcohortid'=>$relationshipcohort->id));
    $DB->delete_records('relationship_cohorts', array('id'=>$relationshipcohort->id));
/*
    $relationship = $DB->get_record('relationship', array('id' => $relationshipcohort->relationshipid), '*', MUST_EXIST);

    $event = \local_relationship\event\relationshipcohort_deleted::create(array(
        'context' => context::instance_by_id($relationship->contextid),
        'objectid' => $relationshipcohort->id,
    ));
    $event->add_record_snapshot('relationship_cohorts', $relationshipcohort);
    $event->trigger();
    */
}

/**
 * Somehow deal with relationships when deleting course category,
 * we can not just delete them because they might be used in enrol
 * plugins or referenced in external systems.
 * @param  stdClass|coursecat $category
 * @return void
 */
function relationship_delete_category($category) {
    global $DB;
    // TODO: make sure that relationships are really, really not used anywhere and delete, for now just move to parent or system context

    $oldcontext = context_coursecat::instance($category->id);

    if ($category->parent and $parent = $DB->get_record('course_categories', array('id'=>$category->parent))) {
        $parentcontext = context_coursecat::instance($parent->id);
        $sql = "UPDATE {relationship} SET contextid = :newcontext WHERE contextid = :oldcontext";
        $params = array('oldcontext'=>$oldcontext->id, 'newcontext'=>$parentcontext->id);
    } else {
        $syscontext = context_system::instance();
        $sql = "UPDATE {relationship} SET contextid = :newcontext WHERE contextid = :oldcontext";
        $params = array('oldcontext'=>$oldcontext->id, 'newcontext'=>$syscontext->id);
    }

    $DB->execute($sql, $params);
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

    $record = new stdClass();
    $record->relationshipgroupid = $relationshipgroupid;
    $record->relationshipcohortid = $relationshipcohortid;
    $record->userid    = $userid;
    $record->timeadded = time();
    $record->id = $DB->insert_record('relationship_members', $record);

/*
    $relationshipgroup = $DB->get_record('relationship_groups', array('id' => $relationshipgroupid), '*', MUST_EXIST);
    $relationshipcohort = $DB->get_record('relationship_cohorts', array('id' => $relationshipcohortid), '*', MUST_EXIST);
    $relationship = $DB->get_record('relationship', array('id' => $relationshipgroup->relationshipid), '*', MUST_EXIST);

    $event = \local_relationship\event\relationshipgroup_member_added::create(array(
        'context' => context::instance_by_id($relationship->contextid),
        'objectid' => $relationshipgroupid,
        'relateduserid' => $userid,
        'other' => $relationshipcohort->roleid,
    ));
    $event->add_record_snapshot('relationship_groups', $relationshipgroup);
    $event->trigger();
*/
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

/*
    $relationshipgroup = $DB->get_record('relationship_groups', array('id' => $relationshipgroupid), '*', MUST_EXIST);

    $event = \local_relationship\event\relationshipgroup_member_removed::create(array(
        'context' => context::instance_by_id($relationship->contextid),
        'objectid' => $relationshipgroupid,
        'relateduserid' => $userid,
        'other' => $roleid,
    ));
    $event->add_record_snapshot('relationship_groups', $relationshipgroup);
    $event->trigger();
*/
}

/**
 * Returns list of relationships from course parent contexts.
 *
 * Note: this function does not implement any capability checks,
 *       it means it may disclose existence of relationships,
 *       make sure it is displayed to users with appropriate rights only.
 *
 * @param  stdClass $course
 * @param  bool $onlyenrolled true means include only relationships with enrolled users
 * @return array of relationship names with number of enrolled users
 */
function relationship_get_visible_list($course, $onlyenrolled=true) {
    global $DB;

    $context = context_course::instance($course->id);
    list($esql, $params) = get_enrolled_sql($context);
    list($parentsql, $params2) = $DB->get_in_or_equal($context->get_parent_context_ids(), SQL_PARAMS_NAMED);
    $params = array_merge($params, $params2);

    if ($onlyenrolled) {
        $left = "";
        $having = "HAVING COUNT(u.id) > 0";
    } else {
        $left = "LEFT";
        $having = "";
    }

    $sql = "SELECT c.id, c.name, c.contextid, c.idnumber, COUNT(u.id) AS cnt
              FROM {relationship} c
        $left JOIN ({relationship_members} rm
                   JOIN ($esql) u ON u.id = rm.userid) ON rm.relationshipid = c.id
             WHERE c.contextid $parentsql
          GROUP BY c.id, c.name, c.contextid, c.idnumber
           $having
          ORDER BY c.name, c.idnumber";

    $relationships = $DB->get_records_sql($sql, $params);

    foreach ($relationships as $cid=>$relationship) {
        $relationships[$cid] = format_string($relationship->name, true, array('context'=>$relationship->contextid));
        if ($relationship->cnt) {
            $relationships[$cid] .= ' (' . $relationship->cnt . ')';
        }
    }

    return $relationships;
}

/**
 * Get all the relationships defined in given context.
 *
 * @param int $contextid
 * @param int $page number of the current page
 * @param int $perpage items per page
 * @param string $search search string
 * @return array    Array(totalrelationships => int, relationships => array, allrelationships => int)
 */
function relationship_search_relationships($contextid, $page = 0, $perpage = 25, $search = '') {
    global $DB;

    // Add some additional sensible conditions
    $tests = array('contextid = ?');
    $params = array($contextid);

    if (!empty($search)) {
        $conditions = array('name', 'idnumber', 'description');
        $searchparam = '%' . $DB->sql_like_escape($search) . '%';
        foreach ($conditions as $key=>$condition) {
            $conditions[$key] = $DB->sql_like($condition, "?", false);
            $params[] = $searchparam;
        }
        $tests[] = '(' . implode(' OR ', $conditions) . ')';
    }
    $wherecondition = implode(' AND ', $tests);

    $fields = "SELECT *";
    $countfields = "SELECT COUNT(1)";
    $sql = " FROM {relationship}
             WHERE $wherecondition";
    $order = " ORDER BY name ASC";
    $allrelationships = $DB->count_records('relationship', array('contextid'=>$contextid));
    $totalrelationships = $DB->count_records_sql($countfields . $sql, $params);
    $relationships = $DB->get_records_sql($fields . $sql . $order, $params, $page*$perpage, $perpage);
    foreach($relationships as $rl) {
        $rl->tags = tag_get_tags_array('relationship', $rl->id);
    }

    return array('totalrelationships' => $totalrelationships, 'relationships' => $relationships, 'allrelationships'=>$allrelationships);
}

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

function relationship_get_groups($relationshipid) {
    global $DB;

    $sql = "SELECT rg.*, (SELECT count(*) FROM relationship_members WHERE relationshipgroupid = rg.id) as size
              FROM {relationship_groups} rg
             WHERE rg.relationshipid = :relationshipid
          GROUP BY rg.id
          ORDER BY name";
    return $DB->get_records_sql($sql, array('relationshipid'=>$relationshipid));
}

function relationship_get_courses($relationshipid) {
    global $DB;

    $sql = "SELECT DISTINCT c.id, c.fullname
              FROM {enrol} e
              JOIN {course} c ON (c.id = e.courseid)
             WHERE e.enrol = 'relationship'
               AND e.customint1 = :relationshipid
          ORDER BY c.fullname";
    return $DB->get_records_sql($sql, array('relationshipid'=>$relationshipid));
}

function relationship_uniformly_distribute_users($relationship, $userids=array()) {
    global $DB;

    if(empty($userids)) {
        return;
    }
    $sql = "SELECT rg.id, count(DISTINCT rm.userid) as count
              FROM relationship rl
              JOIN relationship_groups rg ON (rg.relationshipid = rl.id AND rg.uniformdistribution = 1)
         LEFT JOIN relationship_members rm ON (rm.relationshipgroupid = rg.id AND rm.roletype = 2)
             WHERE rl.id = :relationshipid
          GROUP BY rg.id";
    $groups = $DB->get_records_sql($sql, array('relationshipid'=>$relationship->id));
    if(!empty($groups)) {
        foreach($userids AS $userid) {
            $min = 99999999;
            $gmin = 0;
            foreach($groups AS $grpid=>$grp) {
                if($grp->count < $min) {
                    $min = $grp->count;
                    $gmin = $grpid;
                }
            }
            relationshipgroup_add_member($gmin, $userid, $relationship->roleid2);
            $groups[$gmin]->count++;
        }
    }
}

function relationship_uniformly_distribute_members($relationshipid=NULL) {
    global $DB;

    $params = array('uniformdistribution'=>1);
    if(!empty($relationshipid)) {
        $params['id'] = $relationshipid;
    }
    $relationships = $DB->get_records('relationship', $params);
    foreach($relationships AS $rl) {
        $sql = "SELECT rm.userid
                  FROM relationship rl
                  JOIN cohort ch ON (ch.id = rl.cohortid2)
                  JOIN cohort_members rm ON (rm.cohortid = ch.id)
             LEFT JOIN (SELECT rm.userid
                          FROM relationship rlj
                          JOIN relationship_groups rg ON (rg.relationshipid = rlj.id)
                          JOIN relationship_members rm ON (rm.relationshipgroupid = rg.id AND rm.roletype = 2)
                         WHERE rlj.id = :jrelationshipid) rmj
                    ON (rmj.userid = rm.userid)
                 WHERE rl.id = :relationshipid
                   AND rmj.userid IS NULL";
        $users = $DB->get_records_sql($sql, array('relationshipid'=>$rl->id, 'jrelationshipid'=>$rl->id));
        relationship_uniformly_distribute_users($rl, array_keys($users));
    }
}

/**
 * relationship assignment candidates
 */
class relationship_candidate_selector extends user_selector_base {
    protected $relationshipgroup;

    public function __construct($name, $options) {
        $this->relationshipgroup = $options['relationshipgroup'];
        parent::__construct($name, $options);
    }

    public function find_users($search) {
        global $DB;

        // By default wherecondition retrieves all users except the deleted, not confirmed and guest.
        list($usercondition, $params) = $this->search_sql($search, 'u');
        $params['relationshipid'] = $this->relationshipgroup->relationshipid;
        $params['relationshipgroupid'] = $this->relationshipgroup->id;

        $fields      = 'SELECT ' . $this->required_fields_sql('u') . ', :relationshipcohortid as relationshipcohortid';
        $countfields = 'SELECT COUNT(1)';

        $from = " FROM {cohort} ch
                  JOIN {cohort_members} chm ON (chm.cohortid = ch.id)
                  JOIN {user} u ON (u.id = chm.userid)";

        $where = " WHERE ch.id = :cohortid
                     AND {$usercondition}
                     AND jrm.userid IS NULL";

        list($sort, $sortparams) = users_order_by_sql('u', $search, $this->accesscontext);
        $order = " ORDER BY {$sort}";

        $join_not_alloc_grp = " JOIN (SELECT DISTINCT rm.userid
                                        FROM {relationship_groups} rg
                                        JOIN {relationship_members} rm ON (rm.relationshipgroupid = rg.id)
                                       WHERE rg.relationshipid = :relationshipid) jrm1
                                  ON (jrm1.userid = u.id)
                           LEFT JOIN (SELECT DISTINCT rm.userid
                                        FROM {relationship_groups} rg
                                        JOIN {relationship_members} rm ON (rm.relationshipgroupid = rg.id)
                                       WHERE rg.id = :relationshipgroupid) jrm
                                  ON (jrm.userid = u.id)";
        $join_not_alloc_all = " LEFT JOIN (SELECT DISTINCT rm.userid
                                             FROM {relationship_groups} rg
                                             JOIN {relationship_members} rm ON (rm.relationshipgroupid = rg.id)
                                            WHERE rg.relationshipid = :relationshipid) jrm
                                       ON (jrm.userid = u.id)";

        $role_cohorts = $DB->get_records('relationship_cohorts', array('relationshipid'=>$this->relationshipgroup->relationshipid));

        if (!$this->is_validating()) {
            $count = 0;
            foreach($role_cohorts AS $rc) {
                $params['cohortid'] = $rc->cohortid;
                if($rc->allowdupsingroups) {
                    $count += $DB->count_records_sql($countfields . $from . $join_not_alloc_grp . $where, $params);
                }
                $count += $DB->count_records_sql($countfields . $from . $join_not_alloc_all . $where, $params);
            }

            if ($count > $this->maxusersperpage) {
                return $this->too_many_results($search, $count);
            } else if ($count == 0) {
                return array();
            }
        }

        $users = array();
        foreach($role_cohorts AS $rc) {
            $params['relationshipcohortid'] = $rc->id;
            $params['cohortid'] = $rc->cohortid;
            $role = $DB->get_record('role', array('id'=>$rc->roleid), '*', MUST_EXIST);
            $role_name = role_get_name($role);
            if($rc->allowdupsingroups) {
                $someusers = $DB->get_records_sql($fields . $from . $join_not_alloc_all . $where . $order, array_merge($params, $sortparams));
                if(count($someusers) > 0) {
                    $users[$role_name . get_string('notallocated', 'local_relationship')] = $someusers;
                }
                $someusers = $DB->get_records_sql($fields . $from . $join_not_alloc_grp . $where . $order, array_merge($params, $sortparams));
                if(count($someusers) > 0) {
                    $users[$role_name . get_string('allocated', 'local_relationship')] = $someusers;
                }
            } else {
                $someusers = $DB->get_records_sql($fields . $from . $join_not_alloc_all . $where . $order, array_merge($params, $sortparams));
                if(count($someusers) > 0) {
                    $users[$role_name] = $someusers;
                }
            }
        }
        return $users;
    }
}


/**
 * relationship assignment candidates
 */
class relationship_existing_selector extends user_selector_base {
    protected $relationshipgroup;

    public function __construct($name, $options) {
        $this->relationshipgroup = $options['relationshipgroup'];
        parent::__construct($name, $options);
    }

    /**
     * Candidate users
     * @param string $search
     * @return array
     */
    public function find_users($search) {
        global $DB;

        // By default wherecondition retrieves all users except the deleted, not confirmed and guest.
        list($wherecondition, $params) = $this->search_sql($search, 'u');
        $params['relationshipgroupid'] = $this->relationshipgroup->id;

        $fields      = 'SELECT ' . $this->required_fields_sql('u') . ', rm.relationshipcohortid';
        $countfields = 'SELECT COUNT(1)';

        $sql = " FROM {user} u
                 JOIN {relationship_members} rm ON (rm.userid = u.id AND rm.relationshipgroupid = :relationshipgroupid)
                WHERE $wherecondition";

        list($sort, $sortparams) = users_order_by_sql('u', $search, $this->accesscontext);
        $order = ' ORDER BY ' . $sort;

        if (!$this->is_validating()) {
            $potentialmemberscount = $DB->count_records_sql($countfields . $sql, $params);
            if ($potentialmemberscount > $this->maxusersperpage) {
                return $this->too_many_results($search, $potentialmemberscount);
            } else if ($potentialmemberscount == 0) {
                return array();
            }
        }

        $users = array();
        $role_cohorts = $DB->get_records('relationship_cohorts', array('relationshipid'=>$this->relationshipgroup->relationshipid));
        $where = ' AND rm.relationshipcohortid = :relationshipcohortid';
        foreach($role_cohorts AS $rc) {
            $params['relationshipcohortid'] = $rc->id;
            $availableusers = $DB->get_records_sql($fields . $sql . $where . $order, array_merge($params, $sortparams));
            if(count($availableusers) > 0) {
                $role = $DB->get_record('role', array('id'=>$rc->roleid), '*', MUST_EXIST);
                $users[role_get_name($role)] = $availableusers;
            }
        }
        return $users;
    }
}

/**
 * Event handler for relationship local plugin.
 *
 * We try to keep everything in sync via listening to events,
 * it may fail sometimes, so we always do a full sync in cron too.
 */
class local_relationship_handler {

    /**
     * Event processor - cohort member added.
     * @param \core\event\cohort_member_added $event
     * @return bool
     */
    public static function member_added(\core\event\cohort_member_added $event) {
        global $DB;

        if($rels = $DB->get_records('relationship', array('uniformdistribution'=>1, 'cohortid2'=>$event->objectid))) {
            foreach($rels AS $rel) {
                relationship_uniformly_distribute_users($rel, array($event->relateduserid));
            }
        }
        return true;
    }

    /**
     * Event processor - cohort member removed.
     * @param \core\event\cohort_member_removed $event
     * @return bool
     */
    public static function member_removed(\core\event\cohort_member_removed $event) {
        global $DB;

        $cohortid = $event->objectid;
        $userid = $event->relateduserid;
        $sql = "SELECT rm.relationshipgroupid, rc.relationshipcohortid, rm.userid
                  FROM relationship_cohorts rc
                  JOIN relationship rl ON (rl.id = rc.relationshipid)
                  JOIN relationship_groups rg ON (rg.relationshipid = rc.relationshipid)
                  JOIN relationship_members rm ON (rm.relationshipgroupid = rg.id AND rm.relationshipcohortid = rc.id)
                 WHERE rc.cohortid = :cohortid
                   AND rm.userid = :userid
                   AND rl.enabled = 1";
        $rs = $DB->get_recordset_sql($sql, array('cohortid'=>$cohortid, 'userid'=>$userid));
        foreach($rs AS $rec) {
            relationship_remove_member($rec->relationshipgroupid, $rec->relationshipcohortid, $userid);
        }
        return true;
    }
}
