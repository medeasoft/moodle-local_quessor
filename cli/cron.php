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
