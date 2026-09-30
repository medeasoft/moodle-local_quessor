<?php

defined('MOODLE_INTERNAL') || die();

$addons = [
    'local_quessor' => [
        'handlers' => [
            'quessor' => [
                'delegate' => 'CoreMainMenuDelegate',
                'method' => 'mobile_main_menu',
                'displaydata' => [
                    'title' => 'Quessor',
                    'icon' => 'fas-graduation-cap',
                ],
            ],
        ],
        'lang' => [
            ['pluginname', 'local_quessor'],
            ['mobilemessage', 'local_quessor'],
        ],
    ],
];
