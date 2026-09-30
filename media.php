<?php

define('NO_DEBUG_DISPLAY', true);

require_once(__DIR__ . '/../../config.php');

use local_quessor\local\activity_service;
use local_quessor\local\native_session;

$cmid = required_param('cmid', PARAM_INT);
$key = required_param('key', PARAM_ALPHANUM);
[$course, $cm, $context] = activity_service::load($cmid);
native_session::require_access($course, $cm, $context);
$url = native_session::resolve_media($cmid, $key);

$curl = curl_init($url);
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 60,
    CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
]);
$body = curl_exec($curl);
$status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
$contenttype = (string)curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
$error = curl_error($curl);
curl_close($curl);
if ($body === false || $status < 200 || $status >= 300) {
    throw new moodle_exception('error', 'core', '', null, 'Quessor media request failed: ' . $error);
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
