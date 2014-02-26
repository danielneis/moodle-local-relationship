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
    uniformly_distribute_members();
    return true;
}
