<?php

namespace local_quessor;

defined('MOODLE_INTERNAL') || die();

class hook_callbacks {
    public static function extend_primary_navigation(\core\hook\navigation\primary_extend $hook): void {
        global $USER;
        if (!isloggedin() || isguestuser()) {
            return;
        }

        $courses = get_user_capability_course('local/quessor:gradeassignments', null, false, '', '', 1);
        $canmanagebilling = \local_quessor\local\bridge::is_billing_admin();
        if (!$courses && !$canmanagebilling) {
            return;
        }

        $hook->get_primaryview()->add(
            get_string('hub', 'local_quessor'),
            new \moodle_url('/local/quessor/index.php'),
            \navigation_node::TYPE_CUSTOM,
            null,
            'local_quessor_hub'
        );
    }

    /** Add Quessor as a real course-level secondary navigation tab. */
    public static function extend_secondary_navigation(\core\hook\navigation\secondary_extend $hook): void {
        global $PAGE, $SITE;

        $course = $PAGE->course;
        if (!$course || (int)$course->id === (int)$SITE->id) {
            return;
        }
        $context = \context_course::instance((int)$course->id);
        if (!has_capability('local/quessor:gradeassignments', $context)) {
            return;
        }

        $secondary = $hook->get_secondaryview();
        if ($secondary->find('local_quessor_course', \navigation_node::TYPE_CUSTOM)) {
            return;
        }
        $node = $secondary->add(
            get_string('quessor', 'local_quessor'),
            new \moodle_url('/local/quessor/course.php', ['courseid' => $course->id]),
            \navigation_node::TYPE_CUSTOM,
            null,
            'local_quessor_course',
        );
        $node->set_force_into_more_menu(false);
    }

    /** Keep Quessor as the second tab in supported activity navigation. */
    public static function order_activity_secondary_navigation(
        \core\hook\output\before_http_headers $hook,
    ): void {
        global $PAGE;

        $cm = $PAGE->cm;
        if (!$cm || !in_array($cm->modname, \local_quessor\local\activity_service::SUPPORTED_TYPES, true)) {
            return;
        }

        $coursecontext = \context_course::instance((int)$cm->course);
        if (!has_capability('local/quessor:gradeassignments', $coursecontext)
            || !\local_quessor\local\activity_service::can_grade($cm, $cm->context)) {
            return;
        }

        // Activity secondary navigation is assembled from settings navigation.
        // Reinsert Quessor immediately after the activity's main tab, before Settings.
        $secondary = $PAGE->secondarynav;
        $node = $secondary->get('local_quessor', \navigation_node::TYPE_SETTING);
        if (!$node) {
            return;
        }

        $node->set_force_into_more_menu(false);
        $node->remove();
        $keys = $secondary->get_children_key_list();
        $beforekey = $keys[1] ?? null;
        $secondary->add_node($node, $beforekey);
    }
}
