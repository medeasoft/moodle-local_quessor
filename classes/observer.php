<?php

namespace local_quessor;

defined('MOODLE_INTERNAL') || die();

use local_quessor\local\activity_service;
use local_quessor\local\api_client;

final class observer {
    public static function assignment_submitted(\mod_assign\event\assessable_submitted $event): void {
        $snapshot = $event->get_record_snapshot('assign_submission', $event->objectid);
        if ($snapshot && $snapshot->userid) self::queue_latest($event->contextinstanceid, $snapshot->userid);
    }

    public static function quiz_submitted(\mod_quiz\event\attempt_submitted $event): void {
        self::queue_latest($event->contextinstanceid, $event->relateduserid);
    }

    public static function workshop_changed(\core\event\base $event): void {
        global $DB;
        $submission = $DB->get_record('workshop_submissions', ['id' => $event->objectid], 'authorid');
        if ($submission) self::queue_latest($event->contextinstanceid, $submission->authorid);
    }

    public static function forum_changed(\core\event\base $event): void {
        global $DB;
        $post = $DB->get_record('forum_posts', ['id' => $event->objectid], 'userid');
        if ($post) {
            self::queue_latest($event->contextinstanceid, (int)$post->userid);
        }
    }

    private static function queue_latest(int $cmid, int $userid): void {
        global $DB;
        if (!$cmid || !$userid) return;

        // A first submission has no local_quessor_submission row yet. It must
        // still be queued; otherwise only submissions discovered while a
        // teacher opens the evaluation page are ever synchronised.
        $hasexisting = $DB->record_exists('local_quessor_submission', ['cmid' => $cmid, 'userid' => $userid]);
        if ($hasexisting) {
            $DB->set_field_select(
                'local_quessor_submission',
                'status',
                'resubmitted',
                'cmid = :cmid AND userid = :userid',
                ['cmid' => $cmid, 'userid' => $userid],
            );
            try {
                [, $cm] = get_course_and_cm_from_cmid($cmid);
                (new api_client())->mark_resubmitted(activity_service::backend_activity_id($cm), (string)$userid);
            } catch (\Throwable $e) {
                debugging('Quessor revision notification failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        $task = new \local_quessor\task\sync_submission();
        $task->set_custom_data(['cmid' => $cmid, 'userid' => $userid]);
        $task->set_attempts_available(10);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    public static function user_loggedout(\core\event\user_loggedout $event): void {
        global $DB;
        if (!$event->objectid) return;
        try {
            $user = $DB->get_record('user', ['id' => $event->objectid], 'id,email');
            $email = $user ? (string)$user->email : '';
            (new api_client())->logout((string)$event->objectid, $email);
        } catch (\Throwable $e) {
            debugging('Quessor logout notification failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
