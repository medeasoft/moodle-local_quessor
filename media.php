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

define('NO_DEBUG_DISPLAY', true);

require_once(__DIR__ . '/../../config.php');

use local_quessor\local\activity_service;
use local_quessor\local\native_session;

$cmid = required_param('cmid', PARAM_INT);
$key = required_param('key', PARAM_ALPHANUM);
if (!get_config('local_quessor', 'enabled') || !activity_service::is_connected($cmid)) {
    throw new moodle_exception('integrationdisabled', 'local_quessor');
}
[$course, $cm, $context] = activity_service::load($cmid);
native_session::require_access($course, $cm, $context);
$url = native_session::resolve_media($cmid, $key);

require_once($CFG->libdir . '/filelib.php');
$moodlecurl = new curl(['proxy' => true]);
$body = $moodlecurl->get($url, [], [
    'CURLOPT_FOLLOWLOCATION' => false,
    'CURLOPT_CONNECTTIMEOUT' => 10,
    'CURLOPT_TIMEOUT' => 60,
    'CURLOPT_PROTOCOLS' => CURLPROTO_HTTP | CURLPROTO_HTTPS,
]);
$info = $moodlecurl->get_info();
$status = (int)($info['http_code'] ?? 0);
$contenttype = (string)($info['content_type'] ?? '');
$error = (string)$moodlecurl->error;
if ($body === false || $status < 200 || $status >= 300) {
    debugging('Quessor media request failed (' . $status . '): ' . $error, DEBUG_DEVELOPER);
    throw new moodle_exception('mediaunavailable', 'local_quessor');
}

$mediatype = strtolower(trim(strtok($contenttype, ';') ?: ''));
$allowedtypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'];
if (!in_array($mediatype, $allowedtypes, true)) {
    $mediatype = 'application/octet-stream';
    header('Content-Disposition: attachment; filename="quessor-media"');
}
header('Content-Type: ' . $mediatype);
header('Content-Length: ' . strlen($body));
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');
echo $body;
