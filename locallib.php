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
 * @package    core_relationship
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

function relationship_add_group($group) {
    global $DB;

    if (!isset($group->name)) {
        throw new coding_exception('Missing group name in group_add_group().');
    }
    if (!isset($group->timecreated)) {
        $group->timecreated = time();
    }
    if (!isset($group->timemodified)) {
        $group->timemodified = $group->timecreated;
    }

    $group->id = $DB->insert_record('relationship_groups', $group);

    return $group->id;
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

function relationship_update_group($group) {
    global $DB;

    $group->timemodified = time();
    $DB->update_record('relationship_groups', $group);
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

    $event = \local_relationship\event\relationship_updated::create(array(
        'context' => context::instance_by_id($relationship->contextid),
        'objectid' => $relationship->id,
    ));
    $event->add_record_snapshot('relationship', $relationship);
    $event->trigger();
}

function relationship_delete_group($group) {
    global $DB;

    $DB->delete_records('relationship_members', array('relationshipgroupid'=>$group->id));
    $DB->delete_records('relationship_groups', array('id'=>$group->id));
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

    $DB->delete_records('relationship_members', array('relationshipid'=>$relationship->id));
    $DB->delete_records('relationship', array('id'=>$relationship->id));

    $event = \local_relationship\event\relationship_deleted::create(array(
        'context' => context::instance_by_id($relationship->contextid),
        'objectid' => $relationship->id,
    ));
    $event->add_record_snapshot('relationship', $relationship);
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
function relationship_group_add_member($relationshipgroupid, $userid, $roleid) {
    global $DB;
    if ($DB->record_exists('relationship_members', array('relationshipgroupid'=>$relationshipgroupid, 'userid'=>$userid))) {
        // No duplicates!
        return;
    }
    $record = new stdClass();
    $record->relationshipgroupid  = $relationshipgroupid;
    $record->userid    = $userid;
    $record->roleid    = $roleid;
    $record->timeadded = time();
    $DB->insert_record('relationship_members', $record);

/*
    $relationship = $DB->get_record('relationship', array('id' => $relationshipid), '*', MUST_EXIST);

    $event = \local_relationship\event\relationship_member_added::create(array(
        'context' => context::instance_by_id($relationship->contextid),
        'objectid' => $relationshipid,
        'relateduserid' => $userid,
        'other' => $roleid,
    ));
    $event->add_record_snapshot('relationship', $relationship);
    $event->trigger();
    */
}

/**
 * Remove relationship member
 * @param  int $relationshipid
 * @param  int $userid
 * @return void
 */
function relationship_group_remove_member($relationshipgroupid, $userid) {
    global $DB;

    $DB->delete_records('relationship_members', array('relationshipgroupid'=>$relationshipgroupid, 'userid'=>$userid));

/*
    $relationship = $DB->get_record('relationship', array('id' => $relationshipid), '*', MUST_EXIST);

    $event = \local_relationship\event\relationship_member_removed::create(array(
        'context' => context::instance_by_id($relationship->contextid),
        'objectid' => $relationshipid,
        'relateduserid' => $userid,
    ));
    $event->add_record_snapshot('relationship', $relationship);
    $event->trigger();
    */
}

/**
 * Is this user a relationship member?
 * @param int $relationshipid
 * @param int $userid
 * @return bool
 */
function relationship_group_is_member($relationshipgroupid, $userid) {
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
              FROM relationship_groups rg
             WHERE rg.relationshipid = :relationshipid
          GROUP BY rg.id
          ORDER BY name";
    return $DB->get_records_sql($sql, array('relationshipid'=>$relationshipid));
}

/**
 * relationship assignment candidates
 */
class relationship_candidate_selector extends user_selector_base {
    protected $group;

    public function __construct($name, $options) {
        $this->group = $options['group'];
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
        $params['relationshipid'] = $this->group->relationshipid;
        $relationship = $DB->get_record('relationship', array('id' => $this->group->relationshipid), '*', MUST_EXIST);

        $fields      = 'SELECT ' . $this->required_fields_sql('u');
        $countfields = 'SELECT COUNT(1)';

        $sql = " FROM {cohort} ch
                 JOIN {cohort_members} chm ON (chm.cohortid = ch.id)
                 JOIN {user} u ON (u.id = chm.userid)
            LEFT JOIN (SELECT DISTINCT rm.userid
                         FROM {relationship} rs
                         JOIN {relationship_groups} rg ON (rg.relationshipid = rs.id)
                         JOIN {relationship_members} rm ON (rm.relationshipgroupid = rg.id)
                        WHERE rs.id = :relationshipid) jrm
                   ON (jrm.userid = u.id)
                WHERE ch.id = :cohortid
                  AND jrm.userid IS NULL
                  AND $wherecondition";

        list($sort, $sortparams) = users_order_by_sql('u', $search, $this->accesscontext);
        $order = ' ORDER BY ' . $sort;

        if (!$this->is_validating()) {
            $params['cohortid'] = $relationship->cohortid1;
            $potentialmemberscount = $DB->count_records_sql($countfields . $sql, $params);
            $params['cohortid'] = $relationship->cohortid2;
            $potentialmemberscount += $DB->count_records_sql($countfields . $sql, $params);
            if ($potentialmemberscount > $this->maxusersperpage) {
                return $this->too_many_results($search, $potentialmemberscount);
            }
        }

        $params['cohortid'] = $relationship->cohortid1;
        $availableusers1 = $DB->get_records_sql($fields . $sql . $order, array_merge($params, $sortparams));
        $params['cohortid'] = $relationship->cohortid2;
        $availableusers2 = $DB->get_records_sql($fields . $sql . $order, array_merge($params, $sortparams));

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
        $options['relationshipid'] = $this->group->relationshipid;
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
        $this->group = $options['group'];
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
        $params['relationshipgroupid'] = $this->group->id;
        $relationship = $DB->get_record('relationship', array('id' => $this->group->relationshipid), '*', MUST_EXIST);

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
        $options['relationshipid'] = $this->group->relationshipid;
        $options['file'] = 'relationship/locallib.php';
        return $options;
    }
}

