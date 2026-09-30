<?php

namespace local_quessor\admin;

defined('MOODLE_INTERNAL') || die();

class setting_instituteemail extends \admin_setting_configtext {
    public function write_setting($data) {
        $result = parent::write_setting($data);
        if ($result !== '') {
            return $result;
        }
        try {
            (new \local_quessor\local\api_client())->site_summary(true);
        } catch (\Throwable $e) {
            debugging('Quessor could not save the institution email remotely: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        return '';
    }
}
