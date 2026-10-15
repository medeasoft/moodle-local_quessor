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

use local_quessor\local\api_client;

class sync_submission_statuses extends \core\task\scheduled_task {
    public function get_name(): string {
        return get_string('task_sync_submission_statuses', 'local_quessor');
    }

    public function execute(): void {
        global $DB;
        $records = $DB->get_records_select('local_quessor_submission', "status IN ('ready', 'evaluating')");
        if (!$records) {
            return;
        }

        $bybackendid = [];
        foreach ($records as $record) {
            if (!empty($record->backendid)) {
                $bybackendid[$record->backendid] = $record;
            }
        }
        if (!$bybackendid) {
            return;
        }

        $client = new api_client();
        foreach (array_chunk(array_keys($bybackendid), 100) as $chunk) {
            try {
                $results = $client->statuses($chunk);
            } catch (\Throwable $e) {
                debugging('Quessor batch status sync error: ' . $e->getMessage(), DEBUG_DEVELOPER);
                continue;
            }
            if (!is_array($results)) {
                continue;
            }
            foreach ($results as $result) {
                $backendid = (string)($result['id'] ?? '');
                if ($backendid === '' || !isset($bybackendid[$backendid])) {
                    continue;
                }
                $record = $bybackendid[$backendid];
                try {
                    $record->status = $result['state'] ?? $record->status;
                    $record->grade = $result['grade'] ?? $record->grade;
                    $record->feedback = $result['feedback'] ?? $record->feedback;
                    $record->reporturl = $result['reportUrl'] ?? $record->reporturl;
                    $record->errormessage = $result['error'] ?? $record->errormessage;
                    $record->timemodified = time();
                    $DB->update_record('local_quessor_submission', $record);
                } catch (\Throwable $e) {
                    debugging('Quessor status sync error for record ' . $record->id . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
                    $record->errormessage = substr($e->getMessage(), 0, 1000);
                    $record->timemodified = time();
                    $DB->update_record('local_quessor_submission', $record);
                }
            }
        }
    }
}
