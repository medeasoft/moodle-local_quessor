<?php

namespace local_quessor\admin;

defined('MOODLE_INTERNAL') || die();

class setting_billing extends \admin_setting {
    public function __construct() {
        $this->nosave = true;
        parent::__construct(
            'local_quessor/billing',
            get_string('payment_heading', 'local_quessor'),
            get_string('billingintro', 'local_quessor'),
            ''
        );
    }

    public function get_setting() {
        return true;
    }

    public function get_defaultsetting() {
        return true;
    }

    public function write_setting($data) {
        return '';
    }

    public function output_html($data, $query = '') {
        return \format_admin_setting(
            $this,
            $this->visiblename,
            \local_quessor\local\billing::action_html(),
            $this->description,
            false,
            '',
            '',
            $query
        );
    }
}
