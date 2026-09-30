<?php

namespace local_quessor\local;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../lib.php');

/** Moodle admin SSO into Quessor billing (self-serve credits or subscription plans). */
final class billing {
    public const DEST_CREDITS = 'credits';
    public const DEST_SUBSCRIBE = 'subscribe';

    public static function redirect_path(string $dest): string {
        $path = $dest === self::DEST_SUBSCRIBE
            ? '/app/my-institutes/subscribe'
            : '/app/my-institutes/buy-credits';
        return $path . '?embed=moodle&theme=light';
    }

    public static function sso_url(string $dest): string {
        global $USER;

        if (!self::can_manage()) {
            throw new \moodle_exception('nopermissions', 'error', '', get_string('pluginname', 'local_quessor'));
        }
        if (trim((string)$USER->email) === '') {
            throw new \moodle_exception('emailrequired', 'local_quessor');
        }

        $result = (new api_client())->prepare_sso(array_merge(
            bridge::actor_fields(),
            ['redirectPath' => self::redirect_path($dest)]
        ));
        if (empty($result['ssoUrl'])) {
            throw new \moodle_exception('error', 'core', '', null, 'Quessor SSO URL is missing.');
        }
        return (string)$result['ssoUrl'];
    }

    public static function can_manage(): bool {
        return bridge::is_billing_admin();
    }

    public static function action_html(): string {
        $available = null;
        try {
            $summary = (new api_client())->site_summary();
            if (isset($summary['availablePages'])) {
                $available = local_quessor_format_available_pages($summary['availablePages']);
            }
        } catch (\Throwable $e) {
            $available = null;
        }

        $out = \html_writer::tag('p', get_string('payment_desc', 'local_quessor'), ['class' => 'text-muted mb-2']);
        if ($available !== null) {
            $out .= \html_writer::tag('p', get_string('currentcredits', 'local_quessor', s($available)), [
                'class' => 'mb-3',
            ]);
        }

        $action = new \moodle_url('/local/quessor/billing.php');
        $out .= self::post_button($action, self::DEST_CREDITS, get_string('buycredits', 'local_quessor'), 'btn btn-primary');
        $out .= ' ';
        $out .= self::post_button($action, self::DEST_SUBSCRIBE, get_string('opensubscribe', 'local_quessor'), 'btn btn-outline-secondary');
        return $out;
    }

    private static function post_button(\moodle_url $action, string $dest, string $label, string $class): string {
        $form = \html_writer::start_tag('form', [
            'method' => 'post',
            'action' => $action,
            'class' => 'd-inline',
        ]);
        $form .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        $form .= \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'dest', 'value' => $dest]);
        $form .= \html_writer::tag('button', $label, ['type' => 'submit', 'class' => $class]);
        $form .= \html_writer::end_tag('form');
        return $form;
    }
}
