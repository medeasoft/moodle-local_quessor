<?php

defined('MOODLE_INTERNAL') || die();

$tasks = [
    [
        'classname' => '\\local_quessor\\task\\sync_submission_statuses',
        'blocking' => 0,
        'minute' => '*/1',
        'hour' => '*',
        'day' => '*',
        'dayofweek' => '*',
        'month' => '*',
    ],
];
