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

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

use local_quessor\local\activity_service;
use local_quessor\local\native_session;

$cmid = required_param('cmid', PARAM_INT);
$action = required_param('action', PARAM_ALPHANUMEXT);
require_sesskey();

[$course, $cm, $context] = activity_service::load($cmid);
native_session::require_access($course, $cm, $context);
if (!activity_service::is_connected($cmid)) {
    throw new moodle_exception('activitynotconnected', 'local_quessor');
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

try {
    if ($action !== 'proxy') {
        throw new invalid_parameter_exception('Unsupported Quessor upload action.');
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
                    if (($file['error'][$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        continue;
                    }
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
        throw new invalid_parameter_exception('Only multipart uploads are accepted by this endpoint.');
    }

    $response = native_session::proxy($cmid, $method, $path, $body, true);
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
