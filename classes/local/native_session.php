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

namespace local_quessor\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Server-side Quessor session used by the native React wizard.
 *
 * Access and refresh tokens never leave the Moodle PHP session. Browser code
 * talks only to ajax.php on the Moodle origin, so arbitrary Moodle CSP rules do
 * not need connect-src exceptions for Quessor.
 */
final class native_session {
    private const SESSION_KEY = 'local_quessor_native_sessions';
    private const MEDIA_SESSION_KEY = 'local_quessor_native_media';

    public static function bootstrap(int $cmid, bool $force = false): array {
        global $SESSION, $USER;
        if (!activity_service::is_connected($cmid)) {
            throw new \moodle_exception('activitynotconnected', 'local_quessor');
        }

        $key = (string)$cmid;
        $sessions = (array)($SESSION->{self::SESSION_KEY} ?? []);
        $existing = isset($sessions[$key]) ? (array)$sessions[$key] : null;
        $activitysignature = null;
        if ($existing && !$force) {
            [, , , $currentinstance] = activity_service::load($cmid);
            $activitysignature = self::activity_signature($currentinstance);
        }
        if (
            !$force
            && $existing
            && (int)($existing['userid'] ?? 0) === (int)$USER->id
            && (int)($existing['expiresat'] ?? 0) > time() + 60
            && trim((string)($existing['accesstoken'] ?? '')) !== ''
            && array_key_exists('questionextractionpaperid', $existing)
            && ($existing['activitysignature'] ?? null) === $activitysignature
        ) {
            return $existing;
        }

        [$course, $cm, $context, $instance] = activity_service::load($cmid);
        self::require_access($course, $cm, $context);
        if (trim((string)$USER->email) === '') {
            throw new \moodle_exception('emailrequired', 'local_quessor');
        }

        $payload = array_merge(
            activity_service::activity_payload($course, $cm, $context, $instance),
            bridge::actor_fields(),
            ['students' => bridge::visible_students($course)],
        );
        $client = new api_client();
        $prepared = $client->prepare($payload, activity_service::question_source_files(
            $cm,
            $context,
            (string)($payload['assignmentDescription'] ?? ''),
        ));
        $examid = trim((string)($prepared['examId'] ?? ''));
        $wizardurl = trim((string)($prepared['wizardUrl'] ?? ''));
        if ($examid === '' || $wizardurl === '') {
            throw new \moodle_exception('configurationrequired', 'local_quessor');
        }

        $tokens = $client->exchange_sso_url($wizardurl);
        $accesstoken = trim((string)($tokens['accessToken'] ?? ''));
        if ($accesstoken === '') {
            throw new \moodle_exception('configurationrequired', 'local_quessor');
        }

        $examresponse = $client->authenticated_request('GET', '/exams/' . rawurlencode($examid), $accesstoken);
        $exam = (array)($examresponse['data'] ?? []);
        $courseid = trim((string)($exam['courseId'] ?? ''));
        if ($courseid === '') {
            throw new \moodle_exception('configurationrequired', 'local_quessor');
        }

        $session = [
            'userid' => (int)$USER->id,
            'cmid' => $cmid,
            'examid' => $examid,
            'courseid' => $courseid,
            'accesstoken' => $accesstoken,
            'refreshtoken' => trim((string)($tokens['refreshToken'] ?? '')),
            'expiresat' => self::jwt_expiry($accesstoken),
            'availablepages' => $prepared['availablePages'] ?? null,
            'institutename' => $prepared['instituteName'] ?? null,
            'questionextractionpaperid' => $prepared['questionExtractionPaperId'] ?? null,
            'questionextractionstatus' => $prepared['questionExtractionStatus'] ?? null,
            'activitysignature' => self::activity_signature($instance),
        ];
        $sessions[$key] = $session;
        $SESSION->{self::SESSION_KEY} = $sessions;
        return $session;
    }

    private static function activity_signature(\stdClass $instance): string {
        return hash('sha256', serialize([
            (string)($instance->name ?? ''),
            (string)($instance->intro ?? ''),
            (int)($instance->introformat ?? FORMAT_HTML),
            (int)($instance->timemodified ?? 0),
            (float)($instance->grade ?? $instance->grade_forum ?? 0),
        ]));
    }

    public static function proxy(
        int $cmid,
        string $method,
        string $path,
        mixed $body = null,
        bool $multipart = false,
    ): array {
        $session = self::bootstrap($cmid);
        $client = new api_client();
        try {
            self::require_scoped_path($path, $method, $body, $session, $multipart, $client);
            $response = $client->authenticated_request(
                $method,
                $path,
                (string)$session['accesstoken'],
                $body,
                $multipart,
            );
            return self::rewrite_media_urls($cmid, $path, $response);
        } catch (\moodle_exception $exception) {
            $debug = (string)($exception->debuginfo ?? '');
            if (!str_contains($debug, '(401)')) {
                throw $exception;
            }
            $session = self::bootstrap($cmid, true);
            self::require_scoped_path($path, $method, $body, $session, $multipart, $client);
            $response = $client->authenticated_request(
                $method,
                $path,
                (string)$session['accesstoken'],
                $body,
                $multipart,
            );
            return self::rewrite_media_urls($cmid, $path, $response);
        }
    }

    /**
     * Starts the activity's grading run through the signed Moodle integration API.
     *
     * The normal bearer-token evaluation route can grade the stored papers, but it
     * does not move MoodleSubmissionLink records from ready to evaluating. Keeping
     * this action on the signed integration route makes Moodle result/status sync
     * and the native wizard observe the same evaluation run.
     */
    public static function start_evaluation(int $cmid): array {
        [$course, $cm, $context] = activity_service::load($cmid);
        self::require_access($course, $cm, $context);

        $moodleassignmentid = activity_service::backend_activity_id($cm);
        if (trim($moodleassignmentid) === '') {
            throw new \moodle_exception('configurationrequired', 'local_quessor');
        }

        return (new api_client())->start_evaluation($moodleassignmentid);
    }

    /** Explicitly re-run billable question extraction for this activity. */
    public static function retry_question_extraction(int $cmid): array {
        global $SESSION, $USER;

        [$course, $cm, $context, $instance] = activity_service::load($cmid);
        self::require_access($course, $cm, $context);
        if (trim((string)$USER->email) === '') {
            throw new \moodle_exception('emailrequired', 'local_quessor');
        }

        $payload = array_merge(
            activity_service::activity_payload($course, $cm, $context, $instance),
            bridge::actor_fields(),
            ['students' => bridge::visible_students($course), 'forceQuestionExtraction' => true],
        );
        $client = new api_client();
        $prepared = $client->prepare($payload, activity_service::question_source_files(
            $cm,
            $context,
            (string)($payload['assignmentDescription'] ?? ''),
        ));
        // A forced extraction creates a new ExamPaper. Discard the cached
        // native session so the redirected page receives that paper id and
        // polls the new run instead of an older completed attempt.
        $sessions = (array)($SESSION->{self::SESSION_KEY} ?? []);
        unset($sessions[(string)$cmid]);
        $SESSION->{self::SESSION_KEY} = $sessions;
        return $prepared;
    }

    /** Refresh this activity's Quessor results without writing to Moodle Gradebook. */
    public static function refresh_submission_statuses(int $cmid): array {
        global $DB;

        [$course, $cm, $context] = activity_service::load($cmid);
        self::require_access($course, $cm, $context);

        $records = $DB->get_records_select(
            'local_quessor_submission',
            'cmid = :cmid AND status <> :superseded',
            ['cmid' => $cmid, 'superseded' => 'superseded'],
        );
        $bybackendid = [];
        foreach ($records as $record) {
            $backendid = trim((string)($record->backendid ?? ''));
            if ($backendid !== '') {
                $bybackendid[$backendid] = $record;
            }
        }

        $updated = 0;
        $client = new api_client();
        foreach (array_chunk(array_keys($bybackendid), 100) as $chunk) {
            $results = $client->statuses($chunk);
            foreach ($results as $result) {
                $backendid = trim((string)($result['id'] ?? ''));
                if ($backendid === '' || !isset($bybackendid[$backendid])) {
                    continue;
                }

                $record = $bybackendid[$backendid];
                $record->status = $result['state'] ?? $record->status;
                $record->grade = $result['grade'] ?? $record->grade;
                $record->feedback = $result['feedback'] ?? $record->feedback;
                $record->reporturl = $result['reportUrl'] ?? $record->reporturl;
                $record->errormessage = $result['error'] ?? null;
                $record->timemodified = time();
                $DB->update_record('local_quessor_submission', $record);
                $updated++;
            }
        }

        return [
            'updated' => $updated,
            'submissions' => self::submission_summary($cmid),
        ];
    }

    /** Publish selected completed results after teacher confirmation. */
    public static function publish_grades(int $cmid, array $ids = [], bool $includefeedback = false): array {
        global $DB;
        [$course, $cm, $context] = activity_service::load($cmid);
        self::require_access($course, $cm, $context);
        self::refresh_submission_statuses($cmid);
        $records = $DB->get_records_select(
            'local_quessor_submission',
            'cmid = :cmid AND status <> :superseded',
            ['cmid' => $cmid, 'superseded' => 'superseded'],
        );
        if (array_filter($records, static fn(\stdClass $record): bool => $record->status === 'evaluating')) {
            throw new \moodle_exception('evaluationinprogress', 'local_quessor');
        }
        $selected = array_fill_keys(array_map('strval', $ids), true);
        $updated = 0;
        foreach ($records as $record) {
            if ($record->status !== 'completed' || $record->grade === null) {
                continue;
            }
            if (!activity_service::can_access_student((int)$course->id, (int)$record->userid)) {
                continue;
            }
            if ($selected && !isset($selected[(string)$record->id])) {
                continue;
            }
            $feedback = $includefeedback ? (string)($record->feedback ?? '') : null;
            activity_service::sync_grade($cmid, (int)$record->userid, (float)$record->grade, $feedback);
            $updated++;
        }
        return ['updated' => $updated, 'submissions' => self::submission_summary($cmid)];
    }

    /**
     * @return array{
     *   total:int,
     *   ready:int,
     *   evaluating:int,
     *   completed:int,
     *   failed:int,
     *   items:array<int,array{id:string,moodleUserId:string,studentName:string,status:string,grade:?float,feedback:?string}>
     * }
     */
    public static function submission_summary(int $cmid): array {
        global $DB;
        [$course] = activity_service::load($cmid);

        $summary = [
            'total' => 0,
            'ready' => 0,
            'evaluating' => 0,
            'completed' => 0,
            'failed' => 0,
            'items' => [],
        ];
        $records = $DB->get_records_select(
            'local_quessor_submission',
            'cmid = :cmid AND status <> :superseded',
            ['cmid' => $cmid, 'superseded' => 'superseded'],
            'userid ASC',
            'id,userid,status,grade',
        );
        $users = [];
        if ($records) {
            $userids = array_values(array_unique(array_map(
                static fn(\stdClass $record): int => (int)$record->userid,
                $records,
            )));
            $users = $DB->get_records_list(
                'user',
                'id',
                $userids,
                '',
                'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename',
            );
        }
        foreach ($records as $record) {
            if (!activity_service::can_access_student((int)$course->id, (int)$record->userid)) {
                continue;
            }
            $summary['total']++;
            $status = (string)$record->status;
            if (array_key_exists($status, $summary) && $status !== 'total') {
                $summary[$status]++;
            }
            $user = $users[(int)$record->userid] ?? null;
            $summary['items'][] = [
                'id' => (string)$record->id,
                'moodleUserId' => (string)$record->userid,
                'studentName' => $user ? fullname($user) : ('#' . (int)$record->userid),
                'status' => $status,
                'grade' => $record->grade === null ? null : (float)$record->grade,
                'feedback' => $record->feedback === null ? null : (string)$record->feedback,
            ];
        }
        usort($summary['items'], static fn(array $left, array $right): int => strnatcasecmp(
            $left['studentName'],
            $right['studentName'],
        ));
        return $summary;
    }

    public static function resolve_media(int $cmid, string $key): string {
        global $SESSION, $USER;
        $media = (array)($SESSION->{self::MEDIA_SESSION_KEY} ?? []);
        $entry = isset($media[$key]) ? (array)$media[$key] : null;
        if (
            !$entry
            || (int)($entry['cmid'] ?? 0) !== $cmid
            || (int)($entry['userid'] ?? 0) !== (int)$USER->id
            || (int)($entry['expiresat'] ?? 0) < time()
        ) {
            throw new \moodle_exception('invalidparameter', 'error');
        }
        $url = trim((string)($entry['url'] ?? ''));
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new \moodle_exception('invalidparameter', 'error');
        }
        return $url;
    }

    public static function require_access(\stdClass $course, \cm_info $cm, \context_module $context): void {
        require_login($course, false, $cm);
        require_capability('local/quessor:gradeassignments', \context_course::instance($course->id));
        if (!activity_service::can_grade($cm, $context)) {
            throw new \required_capability_exception($context, 'local/quessor:gradeassignments', 'nopermissions', '');
        }
    }

    private static function require_scoped_path(
        string $path,
        string $method,
        mixed $body,
        array $session,
        bool $multipart,
        api_client $client,
    ): void {
        $method = strtoupper($method);
        $examid = (string)$session['examid'];
        $courseid = (string)$session['courseid'];
        $token = (string)$session['accesstoken'];
        $route = (string)(parse_url($path, PHP_URL_PATH) ?? '');
        $allowed = false;

        if (str_starts_with($route, '/exams/' . $examid . '/')) {
            $allowed = true;
        } else if ($route === '/exams/' . $examid && in_array($method, ['GET', 'PUT'], true)) {
            $allowed = true;
        } else if ($route === '/grade-exam/' . $examid && $method === 'POST') {
            $allowed = true;
        } else if ($route === '/questions') {
            $allowed = $method === 'GET' && self::query_has_filter($path, 'examId', $examid);
        } else if ($route === '/questions/ai/generate-assessment') {
            $allowed = $method === 'POST' && self::body_field_equals($body, 'examId', $examid);
        } else if (preg_match('#^/questions/([0-9a-f-]+)$#i', $route, $matches)) {
            $allowed = in_array($method, ['GET', 'PUT'], true)
                && self::remote_item_matches_scope($client, $token, '/questions/' . $matches[1], 'examId', $examid);
            if ($allowed && $method === 'PUT') {
                self::require_related_question_scope($client, $token, $body, $examid);
            }
        } else if ($route === '/answers') {
            $allowed = $method === 'GET' && self::query_has_filter($path, 'question.examId', $examid);
        } else if (preg_match('#^/answers/([0-9a-f-]+)(/match)?$#i', $route, $matches)) {
            $allowed = in_array($method, ['GET', 'PUT', 'DELETE', 'POST'], true)
                && self::remote_item_matches_scope($client, $token, '/answers/' . $matches[1], 'question.examId', $examid);
            if ($allowed && $method === 'PUT') {
                self::require_related_question_scope($client, $token, $body, $examid);
            }
            if ($allowed && ($matches[2] ?? '') === '/match') {
                $allowed = $method === 'POST'
                    && self::related_student_matches_scope($client, $token, $body, $courseid);
            }
        } else if ($route === '/students') {
            $allowed = $method === 'GET' && self::query_has_filter($path, 'courseId', $courseid);
        } else if (preg_match('#^/students/([0-9a-f-]+)$#i', $route, $matches)) {
            $allowed = in_array($method, ['GET', 'PUT', 'DELETE'], true)
                && self::remote_item_matches_scope($client, $token, '/students/' . $matches[1], 'courseId', $courseid);
        } else if ($route === '/program-outcomes' || $route === '/course-objectives') {
            $allowed = ($method === 'GET' && self::query_parameter_equals($path, 'courseId', $courseid))
                || ($method === 'POST' && self::body_field_equals($body, 'courseId', $courseid));
        } else if (
            in_array($route, [
            '/program-outcomes/exam/' . $examid . '/mappings',
            '/course-objectives/exam/' . $examid . '/mappings',
            '/course-objectives/course/' . $courseid . '/po-mappings',
            ], true)
        ) {
            $allowed = in_array($method, ['GET', 'POST'], true);
        }

        if (!$allowed) {
            throw new \invalid_parameter_exception('Quessor native route is outside the Moodle activity scope.');
        }

        if (!$multipart && is_string($body) && trim($body) !== '') {
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                foreach (['examId' => $examid, 'courseId' => $courseid] as $field => $expected) {
                    if (isset($decoded[$field]) && (string)$decoded[$field] !== $expected) {
                        throw new \invalid_parameter_exception('Quessor native request is outside the Moodle activity scope.');
                    }
                }
            }
        }
    }

    private static function query_has_filter(string $path, string $field, string $expected): bool {
        $query = (string)(parse_url($path, PHP_URL_QUERY) ?? '');
        $filters = [];
        foreach (explode('&', $query) as $pair) {
            [$rawname, $rawvalue] = array_pad(explode('=', $pair, 2), 2, '');
            $name = urldecode($rawname);
            if (!preg_match('/^filters(?:\[(\d+)\]|\.(\d+))(?:\.([a-zA-Z]+)|\[([a-zA-Z]+)\])$/', $name, $matches)) {
                continue;
            }
            $index = (int)($matches[1] !== '' ? $matches[1] : $matches[2]);
            $property = $matches[3] !== '' ? $matches[3] : $matches[4];
            if (!in_array($property, ['field', 'operator', 'value'], true)) {
                continue;
            }
            $value = urldecode($rawvalue);
            if (isset($filters[$index][$property]) && $filters[$index][$property] !== $value) {
                return false;
            }
            $filters[$index][$property] = $value;
        }
        foreach ($filters as $filter) {
            if (
                ($filter['field'] ?? null) === $field
                && ($filter['operator'] ?? null) === 'eq'
                && (string)($filter['value'] ?? '') === $expected
            ) {
                return true;
            }
        }
        return false;
    }

    private static function query_parameter_equals(string $path, string $field, string $expected): bool {
        $query = (string)(parse_url($path, PHP_URL_QUERY) ?? '');
        parse_str($query, $parameters);
        return isset($parameters[$field]) && (string)$parameters[$field] === $expected;
    }

    private static function body_field_equals(mixed $body, string $field, string $expected): bool {
        $decoded = self::decoded_body($body);
        return isset($decoded[$field]) && (string)$decoded[$field] === $expected;
    }

    private static function decoded_body(mixed $body): array {
        if (is_array($body)) {
            return $body;
        }
        if (!is_string($body) || trim($body) === '') {
            return [];
        }
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }

    private static function remote_item_matches_scope(
        api_client $client,
        string $token,
        string $path,
        string $fieldpath,
        string $expected,
    ): bool {
        $response = $client->authenticated_request('GET', $path, $token);
        $value = $response['data'] ?? null;
        foreach (explode('.', $fieldpath) as $field) {
            if (!is_array($value) || !array_key_exists($field, $value)) {
                return false;
            }
            $value = $value[$field];
        }
        return (string)$value === $expected;
    }

    private static function require_related_question_scope(
        api_client $client,
        string $token,
        mixed $body,
        string $examid,
    ): void {
        $questionid = trim((string)(self::decoded_body($body)['questionId'] ?? ''));
        if (
            $questionid !== ''
            && !self::remote_item_matches_scope($client, $token, '/questions/' . rawurlencode($questionid), 'examId', $examid)
        ) {
            throw new \invalid_parameter_exception('Question is outside the Moodle activity scope.');
        }
    }

    private static function related_student_matches_scope(
        api_client $client,
        string $token,
        mixed $body,
        string $courseid,
    ): bool {
        $studentid = trim((string)(self::decoded_body($body)['studentId'] ?? ''));
        return $studentid !== ''
            && self::remote_item_matches_scope($client, $token, '/students/' . rawurlencode($studentid), 'courseId', $courseid);
    }

    private static function jwt_expiry(string $token): int {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return time() + 5 * MINSECS;
        }
        $payload = strtr($parts[1], '-_', '+/');
        $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
        $decoded = json_decode((string)base64_decode($payload, true), true);
        $expiry = (int)($decoded['exp'] ?? 0);
        return $expiry > time() ? $expiry : time() + 5 * MINSECS;
    }

    private static function rewrite_media_urls(int $cmid, string $path, array $response): array {
        global $SESSION, $USER;
        $route = (string)(parse_url($path, PHP_URL_PATH) ?? '');
        $allowgenericurl = str_starts_with($route, '/exams/pages/') && str_ends_with($route, '/url');
        $media = (array)($SESSION->{self::MEDIA_SESSION_KEY} ?? []);

        $rewrite = static function (mixed $value, ?string $field = null) use (&$rewrite, &$media, $cmid, $USER, $allowgenericurl): mixed {
            if (is_array($value)) {
                foreach ($value as $key => $child) {
                    $value[$key] = $rewrite($child, is_string($key) ? $key : null);
                }
                return $value;
            }
            $ismediafield = $field === 'imageUrl' || ($field === 'url' && $allowgenericurl);
            if (!$ismediafield || !is_string($value) || !preg_match('#^https?://#i', $value)) {
                return $value;
            }

            $key = bin2hex(random_bytes(24));
            $media[$key] = [
                'url' => $value,
                'cmid' => $cmid,
                'userid' => (int)$USER->id,
                'expiresat' => time() + 10 * MINSECS,
            ];
            return (new \moodle_url('/local/quessor/media.php', ['cmid' => $cmid, 'key' => $key]))->out(false);
        };

        $rewritten = $rewrite($response);
        foreach ($media as $key => $entry) {
            if ((int)(((array)$entry)['expiresat'] ?? 0) < time()) {
                unset($media[$key]);
            }
        }
        $SESSION->{self::MEDIA_SESSION_KEY} = $media;
        return $rewritten;
    }
}
