<?php
// This file is part of Moodle - http://moodle.org/.
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
// You should have received a copy of the GNU General Public License along with Moodle. If not, see <http://www.gnu.org/licenses/>.
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.
// @copyright 2026 Medeasoft
// @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_quessor\task;

defined('MOODLE_INTERNAL') || die();

use local_quessor\local\activity_service;
use local_quessor\local\api_client;

/** Synchronises the newest logical submission for any supported Moodle activity. */
final class sync_submission extends \core\task\adhoc_task {
    public function get_name(): string {
        return get_string('task_sync_submission', 'local_quessor');
    }

    public function execute(): void {
        global $DB;
        $data = $this->get_custom_data();
        $cmid = (int)($data->cmid ?? 0);
        $userid = (int)($data->userid ?? 0);
        if (!$cmid || !$userid) {
            throw new \coding_exception('Quessor activity sync task data is incomplete.');
        }

        [$course, $cm, $context, $instance] = activity_service::load($cmid);
        $item = activity_service::latest_for_user($cmid, $userid);
        if (!$item) {
            return;
        }
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
