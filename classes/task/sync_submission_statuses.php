<?php

namespace local_quessor\task;

defined('MOODLE_INTERNAL') || die();

use local_quessor\local\api_client;

class sync_submission_statuses extends \core\task\scheduled_task {
    public function get_name(): string {
        return get_string('task_sync_submission_statuses', 'local_quessor');
    }

    public function execute(): void {
        global $DB;
        \core\session\manager::set_user(get_admin());
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
