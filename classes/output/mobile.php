<?php

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
