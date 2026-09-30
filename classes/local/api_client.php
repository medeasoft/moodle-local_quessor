<?php

namespace local_quessor\local;

defined('MOODLE_INTERNAL') || die();

class api_client {
    private const GLOBAL_PRIVATE_KEY_CONFIG = 'local_quessor_privatekey';

    private string $apiurl;
    private string $siteid;
    private string $privatekey = '';
    private string $publickey = '';
    private bool $registered = false;

    public function __construct() {
        global $CFG;

        $apiurl = getenv('MOODLE_QUESSOR_API_URL') ?: api_defaults::API_URL;
        $siteid = getenv('MOODLE_QUESSOR_SITE_ID') ?: (string)($CFG->siteidentifier ?? '');
        if (trim($siteid) === '') {
            throw new \moodle_exception('configurationrequired', 'local_quessor');
        }

        $this->apiurl = rtrim($apiurl, '/');
        $this->siteid = trim($siteid);
    }

    private function enrich_payload(array $payload): array {
        global $SITE;
        if (!isset($payload['siteName']) && isset($SITE->fullname)) {
            $payload['siteName'] = format_string($SITE->fullname);
        }
        $instituteemail = $this->institute_email();
        if ($instituteemail !== '' && empty($payload['instituteEmail'])) {
            $payload['instituteEmail'] = $instituteemail;
        }
        return $payload;
    }

    private function institute_email(): string {
        global $CFG;
        $configured = trim((string)get_config('local_quessor', 'instituteemail'));
        if ($configured !== '') {
            return $configured;
        }
        return trim((string)($CFG->supportemail ?? ''));
    }

    /** Convert Quessor API errors into messages that are useful in Moodle notifications. */
    private function format_api_error(int $httpcode, string $curlerror, string $response): string {
        $decoded = json_decode($response, true);
        $apierrors = is_array($decoded) ? ($decoded['error']['errors'] ?? []) : [];
        $apierror = is_array($apierrors) && isset($apierrors[0]) && is_string($apierrors[0])
            ? trim($apierrors[0])
            : '';

        if (preg_match(
            '/Insufficient credits\. This operation requires ([0-9]+(?:\.[0-9]+)?) credits, but you have ([0-9]+(?:\.[0-9]+)?) available\./i',
            $apierror,
            $matches,
        )) {
            return get_string('insufficientcredits', 'local_quessor', [
                'required' => $matches[1],
                'available' => $matches[2],
            ]);
        }

        if ($apierror !== '') {
            return 'Quessor API request failed (' . $httpcode . '): ' . $apierror;
        }

        $detail = trim(preg_replace('/\s+/', ' ', strip_tags($response)));
        $message = 'Quessor API request failed (' . $httpcode . '): ' . $curlerror;
        if ($detail !== '') {
            $message .= ($curlerror ? ' ' : '') . substr($detail, 0, 500);
        }
        return $message;
    }

    public function submit(array $payload, array $files): array {
        return $this->submit_files('/api/moodle/submissions', $this->enrich_payload($payload), $files);
    }

    public function sync_submission(array $payload, array $files): array {
        return $this->submit_files('/api/moodle/submissions/sync', $this->enrich_payload($payload), $files);
    }

    private function submit_files(string $path, array $payload, array $files): array {
        $postfields = ['payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)];
        $tempfiles = [];
        try {
            foreach ($files as $index => $file) {
                $tempfile = $file->copy_content_to_temp('local_quessor');
                if (!$tempfile) {
                    throw new \moodle_exception('error', 'core', '', null, 'Unable to prepare a Moodle submission file.');
                }
                $tempfiles[] = $tempfile;
                $postfields['files[' . $index . ']'] = new \CURLFile(
                    $tempfile,
                    $file->get_mimetype(),
                    $file->get_filename()
                );
            }
            return $this->request('POST', $path, $postfields);
        } finally {
            foreach ($tempfiles as $tempfile) {
                @unlink($tempfile);
            }
        }
    }

    public function prepare(array $payload, array $files = []): array {
        if ($files) {
            return $this->submit_files('/api/moodle/assignments/prepare', $this->enrich_payload($payload), $files);
        }
        return $this->request(
            'POST',
            '/api/moodle/assignments/prepare',
            json_encode(['payload' => $this->enrich_payload($payload)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            true,
        );
    }

    public function prepare_sso(array $payload): array {
        return $this->request(
            'POST',
            '/api/moodle/sso/prepare',
            json_encode(['payload' => $this->enrich_payload($payload)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            true,
        );
    }

    /** Exchange the one-time handoff without exposing Quessor tokens to browser JavaScript. */
    public function exchange_sso_url(string $url): array {
        $query = parse_url($url, PHP_URL_QUERY);
        $params = [];
        parse_str((string)$query, $params);
        $token = trim((string)($params['token'] ?? ''));
        if ($token === '') {
            throw new \moodle_exception('configurationrequired', 'local_quessor', '', null, 'Quessor SSO handoff token is missing.');
        }

        return $this->send_request(
            'POST',
            '/api/moodle/sso/exchange',
            json_encode(['token' => $token], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            true,
            30,
            false,
        );
    }

    /**
     * Proxies an authenticated Quessor request for the native Moodle UI.
     * Route scoping is deliberately enforced by native_session before this method is called.
     * The complete ResponseDto envelope is returned so shared frontend services behave exactly
     * as they do in Next.js.
     */
    public function authenticated_request(
        string $method,
        string $path,
        string $accesstoken,
        mixed $body = null,
        bool $multipart = false,
        int $timeout = 90,
    ): array {
        $method = strtoupper($method);
        if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
            throw new \invalid_parameter_exception('Unsupported Quessor proxy method.');
        }
        $decodedpath = (string)(parse_url($path, PHP_URL_PATH) ?? '');
        for ($pass = 0; $pass < 3; $pass++) {
            $next = rawurldecode($decodedpath);
            if ($next === $decodedpath) break;
            $decodedpath = $next;
        }
        if (!str_starts_with($path, '/')
            || str_contains($path, '://')
            || preg_match('/[\x00-\x1F\x7F]/', $path)
            || str_contains($decodedpath, '\\')
            || preg_match('#(?:^|/)\.{1,2}(?:/|$)#', $decodedpath)) {
            throw new \invalid_parameter_exception('Invalid Quessor proxy path.');
        }
        $accesstoken = trim($accesstoken);
        if ($accesstoken === '') {
            throw new \moodle_exception('configurationrequired', 'local_quessor', '', null, 'Quessor native session is missing.');
        }

        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $accesstoken,
            'Accept-Language: ' . bridge::feedback_language(),
        ];
        if (!$multipart && $body !== null) {
            $headers[] = 'Content-Type: application/json';
        }

        $apipath = str_starts_with($path, '/api/') ? $path : '/api' . $path;
        $curl = curl_init($this->apiurl . $apipath);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_ENCODING => '',
        ];
        if ($body !== null && $method !== 'GET') {
            $options[CURLOPT_POSTFIELDS] = $body;
        }
        curl_setopt_array($curl, $options);
        $response = curl_exec($curl);
        $httpcode = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($response === false || $httpcode < 200 || $httpcode >= 300) {
            throw new \moodle_exception(
                'error',
                'core',
                '',
                null,
                $this->format_api_error($httpcode, $error, (string)$response),
            );
        }

        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded)) {
            $detail = trim(preg_replace('/\s+/', ' ', strip_tags(substr((string)$response, 0, 160))));
            throw new \moodle_exception('error', 'core', '', null, 'Quessor API returned an invalid response. ' . $detail);
        }
        return array_key_exists('data', $decoded)
            ? $decoded
            : ['data' => $decoded, 'statusCode' => $httpcode];
    }

    public function activate_course(array $payload): array {
        return $this->request(
            'POST',
            '/api/moodle/courses/activate',
            json_encode(['payload' => $this->enrich_payload($payload)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            true,
        );
    }

    public function course_status(string $moodlecourseid): array {
        return $this->request(
            'POST',
            '/api/moodle/courses/status',
            json_encode(['moodleCourseId' => $moodlecourseid], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            true,
            15,
        );
    }

    public function logout(string $moodleuserid, string $moodleuseremail = ''): array {
        $body = ['moodleUserId' => $moodleuserid];
        if (trim($moodleuseremail) !== '') {
            $body['moodleUserEmail'] = trim($moodleuseremail);
        }
        return $this->request(
            'POST',
            '/api/moodle/sso/logout',
            json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            true,
            5,
        );
    }

    public function status(string $backendid): array {
        return $this->request('GET', '/api/moodle/submissions/' . rawurlencode($backendid));
    }

    public function statuses(array $ids): array {
        return $this->request(
            'POST',
            '/api/moodle/submissions/status',
            json_encode(['ids' => array_values($ids)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            true,
            30,
        );
    }

    public function site_summary(bool $overwriteemail = false): array {
        $body = [
            'siteName' => $this->site_name(),
            'instituteEmail' => $this->institute_email() ?: null,
        ];
        if ($overwriteemail) {
            $body['overwriteInstituteEmail'] = true;
        }
        return $this->request(
            'POST',
            '/api/moodle/site/summary',
            json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            true,
            15,
        );
    }

    public function start_evaluation(string $moodleassignmentid): array {
        return $this->request('POST', '/api/moodle/assignments/' . rawurlencode($moodleassignmentid) . '/evaluate');
    }

    public function mark_resubmitted(string $moodleassignmentid, string $moodleuserid): array {
        return $this->request(
            'POST',
            '/api/moodle/assignments/' . rawurlencode($moodleassignmentid)
                . '/submissions/' . rawurlencode($moodleuserid) . '/resubmitted',
            null,
            false,
            5,
        );
    }

    private function request(
        string $method,
        string $path,
        mixed $postfields = null,
        bool $json = false,
        int $timeout = 90,
    ): array {
        $this->ensure_registered();
        try {
            $result = $this->send_request($method, $path, $postfields, $json, $timeout, true);
            // A successful signed request proves which retained private key
            // Quessor currently trusts, so both durable copies can converge.
            $this->persist_privatekey($this->privatekey);
            return $result;
        } catch (\moodle_exception $e) {
            if (!$this->is_auth_failure($e)) {
                throw $e;
            }
            set_config('registered', '0', 'local_quessor');
            $this->registered = false;
            $this->ensure_registered();
            return $this->send_request($method, $path, $postfields, $json, $timeout, true);
        }
    }

    private function ensure_registered(): void {
        if ($this->registered) {
            return;
        }

        if ((string)get_config('local_quessor', 'registered') === '1') {
            $this->load_or_create_keypair();
            $this->registered = true;
            return;
        }

        $privatekeys = $this->load_persistent_privatekeys();
        if (!$privatekeys) {
            $this->load_or_create_keypair();
            $privatekeys = [$this->privatekey];
        }

        $lastexception = null;
        foreach ($privatekeys as $privatekey) {
            try {
                $this->activate_privatekey($privatekey);
            } catch (\moodle_exception $e) {
                $lastexception = $e;
                continue;
            }
            try {
                $this->register_current_key();
                // Only converge the two storage locations after Quessor has
                // confirmed which retained key belongs to this Moodle site.
                $this->persist_privatekey($privatekey);
                set_config('registered', '1', 'local_quessor');
                $this->registered = true;
                return;
            } catch (\moodle_exception $e) {
                $lastexception = $e;
                if (!$this->is_registration_retryable($e)) {
                    throw $e;
                }
            }
        }

        if ($lastexception === null) {
            throw new \moodle_exception('configurationrequired', 'local_quessor');
        }
        throw $lastexception;
    }

    private function register_current_key(): void {
        $this->send_request(
            'POST',
            '/api/moodle/register',
            json_encode([
                'siteId' => $this->siteid,
                'publicKey' => $this->publickey,
                'siteName' => $this->site_name(),
                'instituteEmail' => $this->institute_email() ?: null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            true,
            15,
            true,
        );
    }

    private function is_auth_failure(\moodle_exception $exception): bool {
        $debug = (string)($exception->debuginfo ?? '');
        return str_contains($debug, '(401)') || str_contains($debug, '(403)');
    }

    private function is_registration_retryable(\moodle_exception $exception): bool {
        $debug = (string)($exception->debuginfo ?? '');
        return $this->is_auth_failure($exception) || str_contains($debug, '(429)');
    }

    private function site_name(): string {
        global $SITE;
        return isset($SITE->fullname) ? format_string($SITE->fullname) : '';
    }

    private function load_or_create_keypair(): void {
        if ($this->privatekey !== '') {
            return;
        }

        $privatekeys = $this->load_persistent_privatekeys();
        if ($privatekeys) {
            $lastexception = null;
            foreach ($privatekeys as $privatekey) {
                try {
                    $this->activate_privatekey($privatekey);
                    return;
                } catch (\moodle_exception $e) {
                    $lastexception = $e;
                }
            }
            throw $lastexception;
        }

        $privatekey = '';
        if (!function_exists('openssl_pkey_new')) {
            throw new \moodle_exception('configurationrequired', 'local_quessor', '', null, 'PHP OpenSSL is required.');
        }
        $key = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        if ($key === false || !openssl_pkey_export($key, $privatekey)) {
            throw new \moodle_exception('configurationrequired', 'local_quessor', '', null, 'Unable to create Moodle integration key.');
        }
        $this->persist_privatekey($privatekey);

        $this->activate_privatekey($privatekey);
    }

    private function activate_privatekey(string $privatekey): void {
        $key = openssl_pkey_get_private($privatekey);
        $details = $key === false ? false : openssl_pkey_get_details($key);
        if (!is_array($details) || empty($details['key']) || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA) {
            throw new \moodle_exception('configurationrequired', 'local_quessor', '', null, 'Moodle integration key is invalid.');
        }

        $this->privatekey = $privatekey;
        $this->publickey = $details['key'];
    }

    private function load_persistent_privatekeys(): array {
        $globalprivatekey = trim((string)get_config(null, self::GLOBAL_PRIVATE_KEY_CONFIG));
        $legacyprivatekey = trim((string)get_config('local_quessor', 'privatekey'));

        $privatekeys = [];
        foreach ([$globalprivatekey, $legacyprivatekey] as $privatekey) {
            if ($privatekey !== '' && !in_array($privatekey, $privatekeys, true)) {
                $privatekeys[] = $privatekey;
            }
        }
        return $privatekeys;
    }

    private function persist_privatekey(string $privatekey): void {
        if (trim((string)get_config(null, self::GLOBAL_PRIVATE_KEY_CONFIG)) !== $privatekey) {
            set_config(self::GLOBAL_PRIVATE_KEY_CONFIG, $privatekey);
        }
        if (trim((string)get_config('local_quessor', 'privatekey')) !== $privatekey) {
            set_config('privatekey', $privatekey, 'local_quessor');
        }
    }

    private function sign(string $data): string {
        $this->load_or_create_keypair();
        $signature = '';
        $key = openssl_pkey_get_private($this->privatekey);
        if ($key === false || !openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new \moodle_exception('configurationrequired', 'local_quessor', '', null, 'Unable to sign Moodle integration request.');
        }
        return base64_encode($signature);
    }

    private function send_request(
        string $method,
        string $path,
        mixed $postfields = null,
        bool $json = false,
        int $timeout = 90,
        bool $signed = true,
    ): array {
        $timestamp = (string)round(microtime(true) * 1000);
        $methodupper = strtoupper($method);
        $rawpath = explode('?', $path)[0];
        $normalizedpath = strlen($rawpath) > 1 ? rtrim($rawpath, '/') : $rawpath;
        $headers = [
            'Accept: application/json',
        ];
        if ($signed) {
            $canonical = $timestamp . '.' . $this->siteid . '.' . $methodupper . '.' . $normalizedpath;
            $headers[] = 'X-Moodle-Site-Id: ' . $this->siteid;
            $headers[] = 'X-Moodle-Timestamp: ' . $timestamp;
            $headers[] = 'X-Moodle-Signature-Version: rsa-sha256';
            $headers[] = 'X-Moodle-Signature: ' . $this->sign($canonical);
        }
        if ($json) {
            $headers[] = 'Content-Type: application/json';
        }

        $url = $this->apiurl . $path;
        $multipart = is_array($postfields);
        if ($multipart) {
            $curl = curl_init($url);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            if ($method === 'POST') {
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, $postfields);
            }
            $response = curl_exec($curl);
            $httpcode = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $error = curl_error($curl);
            curl_close($curl);
        } else {
            global $CFG;
            require_once($CFG->libdir . '/filelib.php');
            $moodlecurl = new \curl();
            $moodlecurl->setHeader($headers);
            $options = ['CURLOPT_TIMEOUT' => $timeout];
            if ($method === 'POST') {
                $response = $moodlecurl->post($url, $postfields ?? '', $options);
            } else {
                $response = $moodlecurl->get($url, [], $options);
            }
            $info = $moodlecurl->get_info();
            $httpcode = (int)($info['http_code'] ?? 0);
            $error = (string)$moodlecurl->error;
        }
        if ($response === false || $httpcode < 200 || $httpcode >= 300) {
            throw new \moodle_exception(
                'error',
                'core',
                '',
                null,
                $this->format_api_error($httpcode, $error, (string)$response),
            );
        }
        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded) || !array_key_exists('data', $decoded)) {
            throw new \moodle_exception('error', 'core', '', null, 'Quessor API returned an invalid response.');
        }
        return $decoded['data'];
    }
}
