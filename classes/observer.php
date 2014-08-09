<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Event handler for relationship local plugin.
 *
 * We try to keep everything in sync via listening to events,
 * it may fail sometimes, so we always do a full sync in cron too.
 */
class local_relationship_observer
{

    /**
     * Event processor - cohort removed.
     * @param \core\event\cohort_deleted $event
     * @return bool
     */
    public static function cohort_removed(\core\event\cohort_deleted $event)
    {
        global $DB;

        /*
        if($rels = $DB->get_records('relationship', array('uniformdistribution'=>1, 'cohortid2'=>$event->objectid))) {
        foreach($rels AS $rel) {
        relationship_uniformly_distribute_users($rel, array($event->relateduserid));
        }
        }
        */

        return true;
    }

    /**
     * Event processor - cohort member added.
     * @param \core\event\cohort_member_added $event
     * @return bool
     */
    public static function member_added(\core\event\cohort_member_added $event)
    {
        global $DB;

        if ($rcs = $DB->get_records('relationship_cohorts', array('uniformdistribution' => 1, 'cohortid' => $event->objectid))) {
            $user = array($event->relateduserid);
            foreach ($rcs AS $rc) {
                relationship_uniformly_distribute_users($rc, $user);
            }
        }

        return true;
    }

    /**
     * Event processor - cohort member removed.
     * @param \core\event\cohort_member_removed $event
     * @return bool
     */
    public static function member_removed(\core\event\cohort_member_removed $event)
    {
        global $DB;

        $sql = "SELECT rm.relationshipgroupid, rm.relationshipcohortid, rm.userid
                  FROM {relationship_cohorts} rc
                  JOIN {relationship_groups} rg
                    ON (rg.relationshipid = rc.relationshipid)
                  JOIN {relationship_members} rm
                    ON (rm.relationshipgroupid = rg.id AND rm.relationshipcohortid = rc.id)
                 WHERE rc.cohortid = :cohortid
                   AND rm.userid = :userid";

        $params = array('cohortid' => $event->objectid, 'userid' => $event->relateduserid);
        $rs = $DB->get_records_sql($sql, $params);
        foreach ($rs AS $rec) {
            relationship_remove_member($rec->relationshipgroupid, $rec->relationshipcohortid, $rec->userid);
        }

        return true;
    }
}