<?php

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/quessor:gradeassignments' => [
        'riskbitmask' => RISK_PERSONAL | RISK_DATALOSS | RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW,
        ],
    ],
    'local/quessor:managebilling' => [
        'riskbitmask' => RISK_CONFIG | RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
