<?php

defined('MOODLE_INTERNAL') || die();

require_once('locallib.php');

function local_relationship_extends_settings_navigation(navigation_node $navigation) {
    global $PAGE;

    if (is_a($PAGE->context, 'context_coursecat') && has_capability('local/relationship:manage' ,$PAGE->context)) {
        $category_node = $navigation->get('categorysettings');
        if ($category_node) {
            $node = $category_node->add(get_string('pluginname', 'local_relationship'), null, navigation_node::TYPE_CONTAINER);
            $node->add(get_string('relationship:manage', 'local_relationship'),
                       new moodle_url('/local/relationship/index.php', array('contextid' => $PAGE->context->id)));
        }
    }
}


function local_relationship_cron() {
    uniformly_distribute_members();
    return true;
}
