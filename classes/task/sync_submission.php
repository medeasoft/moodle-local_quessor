<?php

namespace local_quessor\task;

defined('MOODLE_INTERNAL') || die();

use local_quessor\local\activity_service;
use local_quessor\local\api_client;

/** Synchronises the newest logical submission for any supported Moodle activity. */
final class sync_submission extends \core\task\adhoc_task {
    public function get_name(): string {
        return get_string('task_sync_submission', 'local_quessor');
    }

    public static function sync_now(int $cmid, int $userid): void {
        global $USER;
        $original = $USER;
        $task = new self();
        $task->set_custom_data(['cmid' => $cmid, 'userid' => $userid]);
        try {
            $task->execute();
        } finally {
            if ($original) {
                \core\session\manager::set_user($original);
            }
        }
    }

    public function execute(): void {
        global $DB;
        \core\session\manager::set_user(get_admin());
        $data = $this->get_custom_data();
        $cmid = (int)($data->cmid ?? 0);
        $userid = (int)($data->userid ?? 0);
        if (!$cmid || !$userid) {
            throw new \coding_exception('Quessor activity sync task data is incomplete.');
        }

        [$course, $cm, $context, $instance] = activity_service::load($cmid);
        $item = activity_service::latest_for_user($cmid, $userid);
        if (!$item) return;
        if ($item['state'] === 'pending') {
            throw new \moodle_exception('conversionpending', 'local_quessor', '', 1);
        }
        if ($item['state'] !== 'ready') {
            mtrace('Quessor skipped an empty or unsupported ' . $cm->modname . ' submission for user ' . $userid);
            return;
        }

        $student = $DB->get_record('user', ['id' => $userid], 'id,firstname,lastname,email', MUST_EXIST);
        $payload = array_merge(
            activity_service::activity_payload($course, $cm, $context, $instance),
            \local_quessor\local\bridge::actor_fields(),
            [
                'moodleSubmissionId' => (string)$item['submissionid'],
                'studentMoodleUserId' => (string)$student->id,
                'attemptNumber' => (int)$item['attemptnumber'],
                'sourceHash' => $item['sourceHash'],
                'studentFirstName' => $student->firstname,
                'studentLastName' => $student->lastname,
                'studentEmail' => $student->email,
            ],
        );
        if (trim((string)($payload['moodleUserEmail'] ?? '')) === '') {
            throw new \moodle_exception('emailrequired', 'local_quessor');
        }
        $result = (new api_client())->sync_submission($payload, $item['files']);

        $record = $DB->get_record('local_quessor_submission', [
            'cmid' => $cmid,
            'submissionid' => $item['submissionid'],
        ]);
        $recorddata = (object)[
            'cmid' => $cmid,
            'activitytype' => $cm->modname,
            'submissionid' => $item['submissionid'],
            'userid' => $student->id,
            'backendid' => $result['id'],
            'status' => $result['state'],
            'attemptnumber' => (int)$item['attemptnumber'],
            'sourcehash' => $item['sourceHash'],
            'timemodified' => time(),
        ];
        if ($record) {
            $recorddata->id = $record->id;
            $DB->update_record('local_quessor_submission', $recorddata);
        } else {
            $recorddata->timecreated = time();
            $DB->insert_record('local_quessor_submission', $recorddata);
        }
    }
}
