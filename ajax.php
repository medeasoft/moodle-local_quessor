<?php

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

use local_quessor\local\activity_service;
use local_quessor\local\native_session;

$cmid = required_param('cmid', PARAM_INT);
$action = required_param('action', PARAM_ALPHANUMEXT);
require_sesskey();

[$course, $cm, $context] = activity_service::load($cmid);
native_session::require_access($course, $cm, $context);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

try {
    if ($action === 'bootstrap') {
        $session = native_session::bootstrap($cmid);
        $instance = $DB->get_record($cm->modname, ['id' => $cm->instance], '*', MUST_EXIST);
        $activityitems = activity_service::submissions($course, $cm, $context, $instance);
        $expectedready = count(array_filter(
            $activityitems,
            static fn(array $item): bool => ($item['state'] ?? '') === 'ready',
        ));
        echo json_encode([
            'data' => [
                'examId' => $session['examid'],
                'courseId' => $session['courseid'],
                'availablePages' => $session['availablepages'],
                'instituteName' => $session['institutename'],
                'questionExtractionPaperId' => $session['questionextractionpaperid'] ?? null,
                'questionExtractionStatus' => $session['questionextractionstatus'] ?? null,
                'submissions' => native_session::submission_summary($cmid),
                'expectedReadySubmissions' => $expectedready,
            ],
            'statusCode' => 200,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'start_evaluation') {
        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            throw new invalid_parameter_exception('Quessor evaluation must be started with POST.');
        }
        echo json_encode([
            'data' => native_session::start_evaluation($cmid),
            'statusCode' => 200,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'retry_question_extraction') {
        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            throw new invalid_parameter_exception('Question extraction retry must be started with POST.');
        }
        echo json_encode([
            'data' => native_session::retry_question_extraction($cmid),
            'statusCode' => 200,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'refresh_grades') {
        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            throw new invalid_parameter_exception('Quessor grades must be refreshed with POST.');
        }
        echo json_encode([
            'data' => native_session::refresh_submission_statuses($cmid),
            'statusCode' => 200,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'publish_grades') {
        if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
            throw new invalid_parameter_exception('Quessor grades must be published with POST.');
        }
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw === false ? '' : $raw, true);
        $ids = is_array($decoded) && is_array($decoded['ids'] ?? null) ? $decoded['ids'] : [];
        $includefeedback = is_array($decoded) && ($decoded['includeFeedback'] ?? false) === true;
        echo json_encode([
            'data' => native_session::publish_grades($cmid, array_map('strval', $ids), $includefeedback),
            'statusCode' => 200,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action !== 'proxy') {
        throw new invalid_parameter_exception('Unsupported Quessor native action.');
    }

    $method = strtoupper(required_param('method', PARAM_ALPHA));
    $path = required_param('path', PARAM_RAW_TRIMMED);
    $contenttype = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
    $multipart = str_starts_with($contenttype, 'multipart/form-data');
    if ($multipart) {
        $body = [];
        foreach ($_POST as $name => $value) {
            $body[$name] = $value;
        }
        foreach ($_FILES as $name => $file) {
            if (is_array($file['tmp_name'] ?? null)) {
                foreach ($file['tmp_name'] as $index => $tmpname) {
                    if (($file['error'][$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
                    $body[$name . '[' . $index . ']'] = new CURLFile(
                        $tmpname,
                        (string)($file['type'][$index] ?? 'application/octet-stream'),
                        (string)($file['name'][$index] ?? 'upload'),
                    );
                }
            } else if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $body[$name] = new CURLFile(
                    (string)$file['tmp_name'],
                    (string)($file['type'] ?? 'application/octet-stream'),
                    (string)($file['name'] ?? 'upload'),
                );
            }
        }
    } else {
        $rawbody = file_get_contents('php://input');
        $body = $rawbody === false || trim($rawbody) === '' ? null : $rawbody;
    }

    $response = native_session::proxy($cmid, $method, $path, $body, $multipart);
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    $status = $exception instanceof required_capability_exception ? 403 : 400;
    http_response_code($status);
    echo json_encode([
        'data' => null,
        'statusCode' => $status,
        'error' => [
            'errors' => [$exception->getMessage()],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
