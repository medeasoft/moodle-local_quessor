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

namespace local_quessor\admin;

defined('MOODLE_INTERNAL') || die();

/** Explicit site-wide consent before any data is sent to Quessor. */
class setting_integration_enabled extends \admin_setting_configcheckbox {
    public function write_setting($data) {
        $result = parent::write_setting($data);
        if ($result !== '' || empty($data)) {
            return $result;
        }
        try {
            (new \local_quessor\local\api_client())->site_summary();
        } catch (\Throwable $e) {
            debugging('Quessor registration failed after administrator opt-in: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        return '';
    }
}
