<?php

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\\mod_assign\\event\\assessable_submitted',
        'callback' => '\\local_quessor\\observer::assignment_submitted',
        'priority' => 9999,
    ],
    [
        'eventname' => '\\mod_quiz\\event\\attempt_submitted',
        'callback' => '\\local_quessor\\observer::quiz_submitted',
        'priority' => 9999,
    ],
    [
        'eventname' => '\\mod_workshop\\event\\submission_created',
        'callback' => '\\local_quessor\\observer::workshop_changed',
        'priority' => 9999,
    ],
    [
        'eventname' => '\\mod_workshop\\event\\submission_updated',
        'callback' => '\\local_quessor\\observer::workshop_changed',
        'priority' => 9999,
    ],
    [
        'eventname' => '\\mod_forum\\event\\post_created',
        'callback' => '\\local_quessor\\observer::forum_changed',
        'priority' => 9999,
    ],
    [
        'eventname' => '\\mod_forum\\event\\post_updated',
        'callback' => '\\local_quessor\\observer::forum_changed',
        'priority' => 9999,
    ],
    [
        'eventname' => '\\core\\event\\user_loggedout',
        'callback' => '\\local_quessor\\observer::user_loggedout',
        'priority' => 9999,
    ],
];
