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
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.
// @copyright 2026 Medeasoft
// @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_quessor\external;

defined('MOODLE_INTERNAL') || die();

use external_api;
use external_function_parameters;
use external_value;
use local_quessor\local\activity_service;
use local_quessor\local\native_session;

/** External Services entry point for the native evaluation workspace. */
final class native_request extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'action' => new external_value(PARAM_ALPHANUMEXT, 'Native action'),
            'method' => new external_value(PARAM_ALPHA, 'Upstream HTTP method', VALUE_DEFAULT, 'GET'),
            'path' => new external_value(PARAM_RAW_TRIMMED, 'Upstream API path', VALUE_DEFAULT, ''),
            'body' => new external_value(PARAM_RAW, 'Upstream JSON body', VALUE_DEFAULT, ''),
            'ids' => new \external_multiple_structure(
                new external_value(PARAM_TEXT, 'Quessor submission id'),
                'Submission ids to publish',
                VALUE_DEFAULT,
                []
            ),
            'include_feedback' => new external_value(PARAM_BOOL, 'Include feedback when publishing', VALUE_DEFAULT, false),
        ]);
    }

    public static function execute(
        int $cmid,
        string $action,
        string $method = 'GET',
        string $path = '',
        string $body = '',
        array $ids = [],
        bool $includefeedback = false,
    ): string {
        global $DB;

        self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'action' => $action,
            'method' => $method,
            'path' => $path,
            'body' => $body,
            'ids' => $ids,
            'include_feedback' => $includefeedback,
        ]);
        [$course, $cm, $context] = activity_service::load($cmid);
        native_session::require_access($course, $cm, $context);
        if (!activity_service::is_connected($cmid)) {
            throw new \moodle_exception('activitynotconnected', 'local_quessor');
        }

        switch ($action) {
            case 'bootstrap':
                $session = native_session::bootstrap($cmid);
                $instance = $DB->get_record($cm->modname, ['id' => $cm->instance], '*', MUST_EXIST);
                $activityitems = activity_service::submissions($course, $cm, $context, $instance);
                $expectedready = count(array_filter(
                    $activityitems,
                    static fn(array $item): bool => ($item['state'] ?? '') === 'ready',
                ));
                $result = [
                    'examId' => $session['examid'],
                    'courseId' => $session['courseid'],
                    'availablePages' => $session['availablepages'],
                    'instituteName' => $session['institutename'],
                    'questionExtractionPaperId' => $session['questionextractionpaperid'] ?? null,
                    'questionExtractionStatus' => $session['questionextractionstatus'] ?? null,
                    'submissions' => native_session::submission_summary($cmid),
                    'expectedReadySubmissions' => $expectedready,
                ];
                break;
            case 'start_evaluation':
                $result = native_session::start_evaluation($cmid);
                break;
            case 'refresh_grades':
                $result = native_session::refresh_submission_statuses($cmid);
                break;
            case 'publish_grades':
                $result = native_session::publish_grades($cmid, array_map('strval', $ids), $includefeedback);
                break;
            case 'proxy':
                if (!in_array(strtoupper($method), ['GET', 'POST', 'PUT', 'DELETE'], true)) {
                    throw new \invalid_parameter_exception('Unsupported Quessor API method.');
                }
                if ($path === '' || str_contains($path, '://')) {
                    throw new \invalid_parameter_exception('Invalid Quessor API path.');
                }
                $result = native_session::proxy($cmid, strtoupper($method), $path, $body === '' ? null : $body);
                break;
            default:
                throw new \invalid_parameter_exception('Unsupported Quessor native action.');
        }

        return json_encode(['data' => $result, 'statusCode' => 200], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function execute_returns(): external_value {
        return new external_value(PARAM_RAW, 'JSON encoded native response');
    }
}
