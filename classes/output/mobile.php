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

namespace local_quessor\output;

defined('MOODLE_INTERNAL') || die();

class mobile {
    public static function mobile_main_menu(array $args): array {
        global $CFG;
        return [
            'templates' => [[
                'id' => 'main',
                'html' => '<ion-card><ion-card-header><ion-card-title>{{ \'plugin.local_quessor.pluginname\' | translate }}</ion-card-title></ion-card-header><ion-card-content>{{ \'plugin.local_quessor.mobilemessage\' | translate }}</ion-card-content></ion-card>',
            ]],
            'otherdata' => ['url' => $CFG->wwwroot . '/local/quessor/index.php'],
        ];
    }
}
