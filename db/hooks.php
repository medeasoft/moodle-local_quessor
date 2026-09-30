<?php

defined('MOODLE_INTERNAL') || die();

$callbacks = [
    [
        'hook' => \core\hook\navigation\primary_extend::class,
        'callback' => [\local_quessor\hook_callbacks::class, 'extend_primary_navigation'],
    ],
    [
        'hook' => \core\hook\navigation\secondary_extend::class,
        'callback' => [\local_quessor\hook_callbacks::class, 'extend_secondary_navigation'],
    ],
    [
        'hook' => \core\hook\output\before_http_headers::class,
        'callback' => [\local_quessor\hook_callbacks::class, 'order_activity_secondary_navigation'],
    ],
];
