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
 * relationship UI related functions and classes.
 *
 * @package    local_relationship
 * @copyright  2012 Petr Skoda  {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/user/selector/lib.php');

function relationship_get_assignable_roles($relationship) {
    global $DB;

    $roles = array();

    $role = $DB->get_record('role', array('id'=>$relationship->roleid1), '*', MUST_EXIST);
    $roles[$relationship->roleid1] = role_get_name($role);

    $role = $DB->get_record('role', array('id'=>$relationship->roleid2), '*', MUST_EXIST);
    $roles[$relationship->roleid2] = role_get_name($role);

    return $roles;
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

    $sql = "UPDATE {relationship} r
              JOIN {relationship_groups} rg ON (rg.relationshipid = r.id)
              JOIN {relationship_members} rm ON (rm.relationshipgroupid = rg.id)
               SET rm.roleid = IF(rm.roletype = 1, r.roleid1, r.roleid2)
             WHERE r.id = :relationshipid";
    $DB->execute($sql, array('relationshipid'=>$relationship->id));

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
function relationshipgroup_add_member($relationshipgroupid, $userid, $roleid) {
    global $DB;

    if ($DB->record_exists('relationship_members', array('relationshipgroupid'=>$relationshipgroupid, 'userid'=>$userid))) {
        // No duplicates!
        return;
    }

    $relationshipgroup = $DB->get_record('relationship_groups', array('id' => $relationshipgroupid), '*', MUST_EXIST);
    $relationship = $DB->get_record('relationship', array('id' => $relationshipgroup->relationshipid), '*', MUST_EXIST);

    $record = new stdClass();
    $record->relationshipgroupid = $relationshipgroupid;
    $record->userid    = $userid;
    $record->roleid    = $roleid;
    $record->roletype  = ($roleid == $relationship->roleid1) ? 1 : 2;
    $record->timeadded = time();
    $record->id = $DB->insert_record('relationship_members', $record);

    $event = \local_relationship\event\relationshipgroup_member_added::create(array(
        'context' => context::instance_by_id($relationship->contextid),
        'objectid' => $relationshipgroupid,
        'relateduserid' => $userid,
        'other' => $roleid,
    ));
    $event->add_record_snapshot('relationship', $relationship);
    $event->add_record_snapshot('relationship_groups', $relationshipgroup);
    $event->trigger();
}

/**
 * Remove relationship member
 * @param  int $relationshipid
 * @param  int $userid
 * @return void
 */
function relationshipgroup_remove_member($relationshipgroupid, $userid) {
    global $DB;

    $DB->delete_records('relationship_members', array('relationshipgroupid'=>$relationshipgroupid, 'userid'=>$userid));

    $relationshipgroup = $DB->get_record('relationship_groups', array('id' => $relationshipgroupid), '*', MUST_EXIST);
    $relationship = $DB->get_record('relationship', array('id' => $relationshipgroup->relationshipid), '*', MUST_EXIST);

    $event = \local_relationship\event\relationshipgroup_member_removed::create(array(
        'context' => context::instance_by_id($relationship->contextid),
        'objectid' => $relationshipgroupid,
        'relateduserid' => $userid,
    ));
    $event->add_record_snapshot('relationship', $relationship);
    $event->add_record_snapshot('relationship_groups', $relationshipgroup);
    $event->trigger();
}

/**
 * Is this user a relationship member?
 * @param int $relationshipid
 * @param int $userid
 * @return bool
 */
function relationshipgroup_is_member($relationshipgroupid, $userid) {
    global $DB;

    return $DB->record_exists('relationship_members', array('relationshipgroupid'=>$relationshipgroupid, 'userid'=>$userid));
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
        $left JOIN ({relationship_members} cm
                   JOIN ($esql) u ON u.id = cm.userid) ON cm.relationshipid = c.id
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
function relationship_get_relationships($contextid, $page = 0, $perpage = 25, $search = '') {
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

    return array('totalrelationships' => $totalrelationships, 'relationships' => $relationships, 'allrelationships'=>$allrelationships);
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

function uniformly_distribute_members($relationshipid=NULL) {
    global $DB;

    $params = array('uniformdistribution'=>1,
                    'disableuniformdistribution'=>0);
    if(!empty($relationshipid)) {
        $params['id'] = $relationshipid;
    }
    $relationships = $DB->get_records('relationship', $params);
    foreach($relationships AS $rl) {
        $sql = "SELECT cm.userid
                  FROM relationship rl
                  JOIN cohort ch ON (ch.id = rl.cohortid2)
                  JOIN cohort_members cm ON (cm.cohortid = ch.id)
             LEFT JOIN (SELECT rm.userid
                          FROM relationship rlj
                          JOIN relationship_groups rg ON (rg.relationshipid = rlj.id)
                          JOIN relationship_members rm ON (rm.relationshipgroupid = rg.id AND rm.roletype = 2)
                         WHERE rlj.id = :jrelationshipid) rmj
                    ON (rmj.userid = cm.userid)
                 WHERE rl.id = :relationshipid
                   AND rmj.userid IS NULL";
        $users = $DB->get_records_sql($sql, array('relationshipid'=>$rl->id, 'jrelationshipid'=>$rl->id));
        if(!empty($users)) {
            $sql = "SELECT rg.id, count(DISTINCT rm.userid) as count
                      FROM relationship rl
                      JOIN relationship_groups rg ON (rg.relationshipid = rl.id AND rg.disableuniformdistribution = 0)
                 LEFT JOIN relationship_members rm ON (rm.relationshipgroupid = rg.id AND rm.roletype = 2)
                     WHERE rl.id = :relationshipid
                  GROUP BY rg.id";
            $groups = $DB->get_records_sql($sql, array('relationshipid'=>$rl->id));
            if(!empty($groups)) {
                foreach($users AS $userid=>$us) {
                    $min = 99999999;
                    $gmin = 0;
                    foreach($groups AS $grpid=>$grp) {
                        if($grp->count < $min) {
                            $min = $grp->count;
                            $gmin = $grpid;
                        }
                    }
                    relationshipgroup_add_member($gmin, $userid, $rl->roleid2);
                    $groups[$gmin]->count++;
                }
            }
        }

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

    /**
     * Candidate users
     * @param string $search
     * @return array
     */
    public function find_users($search) {
        global $DB;

        // By default wherecondition retrieves all users except the deleted, not confirmed and guest.
        list($wherecondition, $params) = $this->search_sql($search, 'u');
        $relationship = $DB->get_record('relationship', array('id' => $this->relationshipgroup->relationshipid), '*', MUST_EXIST);
        $params['relationshipid'] = $relationship->id;
        $params['relationshipgroupid'] = $this->relationshipgroup->id;

        $fields      = 'SELECT ' . $this->required_fields_sql('u') . ', :roleid AS roleid';
        $countfields = 'SELECT COUNT(1)';

        $sql = " FROM {cohort} ch
                 JOIN {cohort_members} chm ON (chm.cohortid = ch.id)
                 JOIN {user} u ON (u.id = chm.userid)";
        $join_nalloc_grp = " JOIN (SELECT DISTINCT rm.userid
                              FROM {relationship} rs
                              JOIN {relationship_groups} rg ON (rg.relationshipid = rs.id)
                              JOIN {relationship_members} rm ON (rm.relationshipgroupid = rg.id)
                             WHERE rs.id = :relationshipid) jrm1
                          ON (jrm1.userid = u.id)
                        LEFT JOIN (SELECT DISTINCT rm.userid
                              FROM {relationship_groups} rg
                              JOIN {relationship_members} rm ON (rm.relationshipgroupid = rg.id)
                             WHERE rg.id = :relationshipgroupid) jrm
                          ON (jrm.userid = u.id)";
        $join_nalloc_all = " LEFT JOIN (SELECT DISTINCT rm.userid
                              FROM {relationship} rs
                              JOIN {relationship_groups} rg ON (rg.relationshipid = rs.id)
                              JOIN {relationship_members} rm ON (rm.relationshipgroupid = rg.id)
                             WHERE rs.id = :relationshipid) jrm
                        ON (jrm.userid = u.id)";
        $where = " WHERE ch.id = :cohortid
                    AND $wherecondition
                    AND jrm.userid IS NULL";

        list($sort, $sortparams) = users_order_by_sql('u', $search, $this->accesscontext);
        $order = ' ORDER BY ' . $sort;

        if (!$this->is_validating()) {
            $params['cohortid'] = $relationship->cohortid1;
            $potentialmemberscount = $DB->count_records_sql($countfields . $sql . $join_nalloc_all . $where, $params);
            $potentialmemberscount += $DB->count_records_sql($countfields . $sql . $join_nalloc_grp . $where, $params);
            $params['cohortid'] = $relationship->cohortid2;
            $potentialmemberscount += $DB->count_records_sql($countfields . $sql . $join_nalloc_all . $where, $params);
            if ($potentialmemberscount > $this->maxusersperpage) {
                return $this->too_many_results($search, $potentialmemberscount);
            } else if ($potentialmemberscount == 0) {
                return array();
            }
        }

        $users = array();

        $role = $DB->get_record('role', array('id'=>$relationship->roleid1), '*', MUST_EXIST);
        $params['cohortid'] = $relationship->cohortid1;
        $params['roleid'] = $relationship->roleid1;
        $availableusers1_nalloc = $DB->get_records_sql($fields . $sql . $join_nalloc_all . $where . $order, array_merge($params, $sortparams));
        $users[role_get_name($role) . get_string('notallocated', 'local_relationship')] = $availableusers1_nalloc;
        $availableusers1_alloc = $DB->get_records_sql($fields . $sql . $join_nalloc_grp . $where . $order, array_merge($params, $sortparams));
        $users[role_get_name($role) . get_string('allocated', 'local_relationship')] = $availableusers1_alloc;

        $role = $DB->get_record('role', array('id'=>$relationship->roleid2), '*', MUST_EXIST);
        $params['cohortid'] = $relationship->cohortid2;
        $params['roleid'] = $relationship->roleid2;
        $availableusers2 = $DB->get_records_sql($fields . $sql . $join_nalloc_all . $where . $order, array_merge($params, $sortparams));
        $users[role_get_name($role) . get_string('notallocated', 'local_relationship')] = $availableusers2;

        return $users;
    }

    protected function get_options() {
        $options = parent::get_options();
        $options['relationshipid'] = $this->relationshipgroup->relationshipid;
        $options['file'] = 'relationship/locallib.php';
        return $options;
    }
}


/**
 * relationship assignment candidates
 */
class relationship_existing_selector extends user_selector_base {
    protected $relationship;

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
        $relationship = $DB->get_record('relationship', array('id' => $this->relationshipgroup->relationshipid), '*', MUST_EXIST);

        $fields      = 'SELECT ' . $this->required_fields_sql('u');
        $countfields = 'SELECT COUNT(1)';

        $sql = " FROM {user} u
                 JOIN {relationship_members} cm ON (cm.userid = u.id AND cm.relationshipgroupid = :relationshipgroupid)
                WHERE $wherecondition";

        list($sort, $sortparams) = users_order_by_sql('u', $search, $this->accesscontext);
        $order = ' ORDER BY ' . $sort;

        if (!$this->is_validating()) {
            $potentialmemberscount = $DB->count_records_sql($countfields . $sql, $params);
            if ($potentialmemberscount > 100) {
                return $this->too_many_results($search, $potentialmemberscount);
            }
        }

        $where_role = ' AND cm.roleid = ' . $relationship->roleid1;
        $availableusers1 = $DB->get_records_sql($fields . $sql . $where_role . $order, array_merge($params, $sortparams));
        $where_role = ' AND cm.roleid = ' . $relationship->roleid2;
        $availableusers2 = $DB->get_records_sql($fields . $sql . $where_role . $order, array_merge($params, $sortparams));

        if (empty($availableusers1) && empty($availableusers2)) {
            return array();
        }

        $users = array();
        $role = $DB->get_record('role', array('id'=>$relationship->roleid1), '*', MUST_EXIST);
        $users[role_get_name($role)] = $availableusers1;
        $role = $DB->get_record('role', array('id'=>$relationship->roleid2), '*', MUST_EXIST);
        $users[role_get_name($role)] = $availableusers2;

        return $users;
    }

    protected function get_options() {
        $options = parent::get_options();
        $options['relationshipid'] = $this->relationshipgroup->relationshipid;
        $options['file'] = 'relationship/locallib.php';
        return $options;
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
            foreach($rels AS $r) {
                uniformly_distribute_members($r->id);
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

        return true;
    }
}
