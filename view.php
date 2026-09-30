<?php

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use local_quessor\local\activity_service;
use local_quessor\local\api_client;

$cmid = required_param('cmid', PARAM_INT);
$native = optional_param('native', 1, PARAM_BOOL);
[$course, $cm, $context, $instance] = activity_service::load($cmid);
require_login($course, false, $cm);
require_capability('local/quessor:gradeassignments', context_course::instance($course->id));
if (!activity_service::can_grade($cm, $context)) {
    $gradecapability = [
        'assign' => 'mod/assign:grade',
        'quiz' => 'mod/quiz:grade',
        'workshop' => 'mod/workshop:overridegrades',
        'forum' => 'mod/forum:grade',
    ][$cm->modname] ?? 'local/quessor:gradeassignments';
    throw new required_capability_exception($context, $gradecapability, 'nopermissions', '');
}

$PAGE->set_context($context);
$PAGE->set_cm($cm, $course);
$PAGE->set_url(new moodle_url('/local/quessor/view.php', ['cmid' => $cmid]));
$PAGE->set_title(get_string('quessor', 'local_quessor') . ': ' . format_string($cm->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_secondary_active_tab('local_quessor');
// The Moodle activity description is already imported as Quessor's question text.
// Do not render the same long instructions above the native wizard as a duplicate.
$PAGE->activityheader->set_description('');
$PAGE->requires->css(new moodle_url('/local/quessor/styles.css'));
if ($native) {
    $assetversion = (string)(get_config('local_quessor', 'version') ?: '2026082515');
    $PAGE->requires->css(new moodle_url('/local/quessor/native/evaluation-ui.css', ['v' => $assetversion]));
    $PAGE->requires->js(new moodle_url('/local/quessor/native/evaluation-ui.js', ['v' => $assetversion]));
}

$payload = array_merge(
    activity_service::activity_payload($course, $cm, $context, $instance),
    \local_quessor\local\bridge::actor_fields(),
);
if (trim((string)$USER->email) === '') {
    throw new \moodle_exception('emailrequired', 'local_quessor');
}
$payload['students'] = \local_quessor\local\bridge::visible_students($course);

$items = activity_service::submissions($course, $cm, $context, $instance);
$ready = [];
$pendingcount = 0;
$unsupportedcount = 0;
foreach ($items as $item) {
    if ($item['state'] === 'ready') $ready[] = $item;
    else if ($item['state'] === 'pending') $pendingcount++;
    else if ($item['state'] === 'unsupported') $unsupportedcount++;
}

$wizardurl = null;
$availablepages = null;
$client = new api_client();
try {
    if ($native) {
        $nativesession = \local_quessor\local\native_session::bootstrap($cmid);
        $wizardurl = 'native';
        $availablepages = isset($nativesession['availablepages'])
            ? local_quessor_format_available_pages($nativesession['availablepages'])
            : null;
    } else {
        $prepared = $client->prepare($payload, activity_service::question_source_files(
            $cm,
            $context,
            (string)($payload['assignmentDescription'] ?? ''),
        ));
        $wizardurl = $prepared['wizardUrl'] ?? null;
        $availablepages = isset($prepared['availablePages'])
            ? local_quessor_format_available_pages($prepared['availablePages'])
            : null;
    }
} catch (Throwable $e) {
    \core\notification::add($e->getMessage(), \core\output\notification::NOTIFY_ERROR);
}

$action = optional_param('action', '', PARAM_ALPHAEXT);
$explicitlaunch = $action === 'launch' && data_submitted() && confirm_sesskey();
$explicitquestionretry = $action === 'retry_question_extraction' && data_submitted() && confirm_sesskey();
if ($explicitquestionretry) {
    try {
        \local_quessor\local\native_session::retry_question_extraction($cmid);
        \core\notification::add(get_string('question_extraction_restarted', 'local_quessor'), \core\output\notification::NOTIFY_SUCCESS);
    } catch (Throwable $e) {
        \core\notification::add($e->getMessage(), \core\output\notification::NOTIFY_ERROR);
    }
    redirect(new moodle_url('/local/quessor/view.php', ['cmid' => $cmid]));
}
$reconcilecurrent = !$explicitlaunch && $wizardurl && $ready;
if ($explicitlaunch || $reconcilecurrent) {
    try {
        $submissionids = array_column($ready, 'submissionid');
        $existingsubmissions = [];
        if (!empty($submissionids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($submissionids, SQL_PARAMS_NAMED);
            $inparams['cmid'] = $cmid;
            $existingsubmissions = $DB->get_records_select(
                'local_quessor_submission',
                "cmid = :cmid AND submissionid $insql",
                $inparams,
                '',
                'submissionid, id, sourcehash'
            );
        }

        $queuedcount = 0;
        $inlined = false;
        foreach ($ready as $item) {
            $existing = $existingsubmissions[$item['submissionid']] ?? null;
            if (!$existing || $existing->sourcehash !== $item['sourceHash']) {
                if (!$inlined) {
                    try {
                        \local_quessor\task\sync_submission::sync_now($cmid, (int)$item['userid']);
                        $inlined = true;
                        $queuedcount++;
                        continue;
                    } catch (Throwable $e) {
                        debugging('Quessor inline submission sync failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
                    }
                }
                $task = new \local_quessor\task\sync_submission();
                $task->set_custom_data(['cmid' => $cmid, 'userid' => (int)$item['userid']]);
                $task->set_attempts_available(10);
                \core\task\manager::queue_adhoc_task($task, true);
                $queuedcount++;
            }
        }
        if ($explicitlaunch) {
            if ($queuedcount > 0) {
                \core\notification::add(get_string('task_sync_submission', 'local_quessor') . " ({$queuedcount})", \core\output\notification::NOTIFY_SUCCESS);
            }
            redirect(new moodle_url('/local/quessor/view.php', ['cmid' => $cmid]));
        }
    } catch (Throwable $e) {
        \core\notification::add($e->getMessage(), \core\output\notification::NOTIFY_ERROR);
    }
}
if ($wizardurl && !$native) {
    local_quessor_prepare_embed($wizardurl, 'local-quessor-wizard');
}

echo $OUTPUT->header();
// The native wizard owns the visible page heading. Keep a single accessible
// H1 for Moodle's document outline without rendering a duplicate title.
echo html_writer::tag('h1', format_string($instance->name), ['class' => 'visually-hidden']);
echo html_writer::div(get_string('activitysource', 'local_quessor', get_string('modulename', $cm->modname)), 'alert alert-secondary');

$sourceinfo = (object)[
    'name' => format_string($instance->name),
    'maxscore' => rtrim(rtrim(number_format((float)$payload['maxScore'], 2, '.', ''), '0'), '.'),
    'readycount' => count($ready),
    'studentcount' => count($payload['students']),
    'contextlength' => \core_text::strlen((string)$payload['courseContext']),
];
$criteriakey = trim((string)$payload['rubric']) !== ''
    ? 'datasource_criteria_' . $cm->modname . '_rubric'
    : 'datasource_criteria_' . $cm->modname;
$sources = [
    ['question', 'datasource_question_' . $cm->modname],
    ['criteria', $criteriakey],
    ['pages', 'datasource_pages_' . $cm->modname],
    ['students', 'datasource_students_description'],
    ['context', 'datasource_context_description'],
];
echo html_writer::start_tag('details', ['class' => 'local-quessor-source-card mb-3']);
echo html_writer::start_tag('summary', ['class' => 'local-quessor-source-card__summary']);
echo html_writer::tag('span', html_writer::tag('i', '', [
    'class' => 'fa fa-question-circle',
    'aria-hidden' => 'true',
]), ['class' => 'local-quessor-source-card__icon', 'aria-hidden' => 'true']);
echo html_writer::tag('span', get_string('datasources_title', 'local_quessor'), ['class' => 'local-quessor-source-card__title']);
echo html_writer::tag('span', html_writer::tag('i', '', [
    'class' => 'fa fa-chevron-down',
    'aria-hidden' => 'true',
]), ['class' => 'local-quessor-source-card__chevron', 'aria-hidden' => 'true']);
echo html_writer::end_tag('summary');

echo html_writer::start_div('local-quessor-source-card__content');
echo html_writer::tag('p', get_string('datasources_intro', 'local_quessor'), [
    'class' => 'local-quessor-source-card__intro',
]);
echo html_writer::start_div('local-quessor-source-card__grid');
foreach ($sources as [$labelkey, $descriptionkey]) {
    echo html_writer::start_div('local-quessor-source-card__item');
    echo html_writer::tag('strong', get_string('datasource_' . $labelkey, 'local_quessor'), [
        'class' => 'local-quessor-source-card__label',
    ]);
    echo html_writer::tag('span', get_string($descriptionkey, 'local_quessor', $sourceinfo), [
        'class' => 'local-quessor-source-card__value',
    ]);
    echo html_writer::end_div();
}
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_tag('details');

if ($pendingcount) echo html_writer::div(get_string('conversionpending', 'local_quessor', $pendingcount), 'alert alert-info');
if ($unsupportedcount) echo html_writer::div(get_string('conversionunsupported', 'local_quessor', $unsupportedcount), 'alert alert-warning');
if (!$ready && $cm->modname === 'quiz') {
    echo html_writer::div(get_string('quiznoreadyresponses', 'local_quessor'), 'alert alert-warning');
}
if ($availablepages !== null) {
    echo html_writer::div(get_string('availablepages', 'local_quessor', $availablepages), 'alert alert-info');
}
if ($wizardurl && $native) {
    local_quessor_render_native_evaluation($cmid, !empty($payload['questionSourceAttached']));
} else if ($wizardurl) {
    local_quessor_render_embed($wizardurl, 'local-quessor-wizard', get_string('quessor', 'local_quessor'));
} else {
    echo html_writer::tag('p', get_string('launchreadygeneric', 'local_quessor', count($ready)));
    echo html_writer::start_tag('form', ['method' => 'post']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'launch']);
    echo html_writer::tag('button', get_string('launchwizard', 'local_quessor'), ['type' => 'submit', 'class' => 'btn btn-primary']);
    echo html_writer::end_tag('form');
}
echo $OUTPUT->footer();
