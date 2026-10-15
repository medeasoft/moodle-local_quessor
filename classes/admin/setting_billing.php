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
