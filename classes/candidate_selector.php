<?php

defined('MOODLE_INTERNAL') || die();

/**
 * relationship assignment candidates
 */
class local_relationship_candidate_selector extends user_selector_base {
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

    protected function get_options() {
        $options = parent::get_options();
        $options['relationshipgroup'] = $this->relationshipgroup;
        $options['file'] = 'local/relationship/locallib.php';
        return $options;
    }

}