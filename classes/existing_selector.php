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
 * Relationship existing assignment selector definition
 *
 * @package local_relationship
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * relationship assignment candidates
 */
class local_relationship_existing_selector extends user_selector_base {
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

        list($usercondition, $params) = users_search_sql($search, 'u', $this->searchanywhere);

        if(!empty($this->validatinguserids)) {
            list($usertest, $userparams) = $DB->get_in_or_equal($this->validatinguserids, SQL_PARAMS_NAMED, 'val');
            $usercondition .= " AND u.id*1000000+rc.id " . $usertest;
            $params = array_merge($params, $userparams);
        }

        $params['relationshipgroupid'] = $this->relationshipgroup->id;

        $countfields  = 'SELECT COUNT(1)';
        $selectfields = "SELECT u.id*1000000+rc.id as id, rc.id AS relationshipcohortid, rc.roleid, u.id AS userid, CONCAT(u.firstname, ' ', u.lastname) AS fullname";

        $from = "FROM {relationship_members} rm
                 JOIN {relationship_cohorts} rc ON (rc.id = rm.relationshipcohortid) 
                 JOIN {user} u ON (u.id = rm.userid)
                WHERE rm.relationshipgroupid = :relationshipgroupid
                  AND {$usercondition}";

        $orderby= "ORDER BY roleid, fullname";

        if (!$this->is_validating()) {
            $sql = $countfields . "\n" . $from;
            $count = $DB->count_records_sql($sql, $params);
            if ($count > $this->maxusersperpage) {
                return $this->too_many_results($search, $count);
            } else if ($count == 0) {
                return array();
            }
        }

        $sql = $selectfields . "\n" . $from .  "\n" . $orderby;

        $users = array();
        $roleid = -1;
        $index = false;
        foreach($DB->get_recordset_sql($sql, $params) AS $cand) {
            if($cand->roleid != $roleid) {
                $role = $DB->get_record('role', array('id'=>$cand->roleid), '*', MUST_EXIST);
                $index = role_get_name($role);
                $users[$index] = array();

                $roleid = $cand->roleid;
            }

            $users[$index][$cand->id] = $cand;
        }
        return $users;
    }

    /**
     * Convert a user object to a string suitable for displaying as an option in the list box.
     *
     * @param object $user the user to display.
     * @return string a string representation of the user.
     */
    public function output_user($user) {
        return $user->fullname;
    }

    protected function get_options() {
        $options = parent::get_options();
        $options['relationshipgroup'] = $this->relationshipgroup;
        return $options;
    }
}
