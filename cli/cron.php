<?php

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options] = cli_get_params(
    [
        'help' => false,
        'keep-alive' => 3600,
        'poll-interval' => 5,
    ],
    [
        'h' => 'help',
        'k' => 'keep-alive',
    ],
);

if ($options['help']) {
    echo <<<'HELP'
Quessor Moodle integration task runner.

Options:
    -h, --help              Show this help.
    -k, --keep-alive=N      Keep polling for N seconds. Defaults to 3600.
        --poll-interval=N   Seconds between queue checks. Defaults to 5.

HELP;
    exit(0);
}

$keepalive = max(0, (int)$options['keep-alive']);
$pollinterval = max(1, (int)$options['poll-interval']);
$deadline = time() + $keepalive;
$nextstatussync = 0;
$classname = '\\local_quessor\\task\\sync_submission';

\core\cron::setup_user();

do {
    $tasks = $DB->get_records_select(
        'task_adhoc',
        'classname = :classname AND nextruntime < :now',
        ['classname' => $classname, 'now' => time()],
        'id ASC',
        'id',
    );
    foreach (array_keys($tasks) as $taskid) {
        \core\cron::run_adhoc_task((int)$taskid);
    }

    if (time() >= $nextstatussync) {
        (new \local_quessor\task\sync_submission_statuses())->execute();
        $nextstatussync = time() + 60;
    }

    if (time() >= $deadline) {
        break;
    }
    sleep(min($pollinterval, max(1, $deadline - time())));
} while (true);
