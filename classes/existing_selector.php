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

    protected function get_options() {
        $options = parent::get_options();
        $options['relationshipgroup'] = $this->relationshipgroup;
        $options['file'] = 'local/relationship/locallib.php';
        return $options;
    }
}