<?php

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

use local_quessor\local\activity_service;
use local_quessor\local\api_client;

$courseid = required_param('courseid', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);
require_login($course);
require_capability('local/quessor:gradeassignments', $context);

$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_url(new moodle_url('/local/quessor/course.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('coursequessortitle', 'local_quessor', format_string($course->fullname)));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_secondary_active_tab('local_quessor_course');
$PAGE->requires->css(new moodle_url('/local/quessor/styles.css'));

$client = new api_client();
$action = optional_param('action', '', PARAM_ALPHAEXT);
if ($action === 'activate' && data_submitted()) {
    require_sesskey();
    if (trim((string)$USER->email) === '') {
        throw new moodle_exception('emailrequired', 'local_quessor');
    }
    try {
        $client->activate_course(array_merge(
            \local_quessor\local\bridge::actor_fields(),
            [
                'moodleCourseId' => (string)$course->id,
                'courseName' => format_string($course->fullname),
                'students' => \local_quessor\local\bridge::visible_students($course),
            ],
        ));
        redirect(
            new moodle_url('/local/quessor/course.php', ['courseid' => $courseid]),
            get_string('coursequessoractivated', 'local_quessor'),
            null,
            \core\output\notification::NOTIFY_SUCCESS,
        );
    } catch (Throwable $exception) {
        \core\notification::add(
            get_string('activatecourseerror', 'local_quessor', $exception->getMessage()),
            \core\output\notification::NOTIFY_ERROR,
        );
    }
}

// Local submission history is a safe fallback while an older Quessor API is
// being upgraded; the signed status endpoint remains authoritative.
$localactive = $DB->record_exists_sql(
    "SELECT 1
       FROM {local_quessor_submission} qs
       JOIN {course_modules} cm ON cm.id = qs.cmid
      WHERE cm.course = :courseid",
    ['courseid' => $courseid],
);
$coursestatus = [
    'activated' => $localactive,
    'courseId' => null,
    'courseName' => null,
];
try {
    $remotestatus = $client->course_status((string)$courseid);
    $coursestatus = array_merge($coursestatus, $remotestatus);
} catch (Throwable $exception) {
    if (!$localactive) {
        \core\notification::add(
            get_string('coursequessorstatuserror', 'local_quessor'),
            \core\output\notification::NOTIFY_WARNING,
        );
    }
}

$activities = activity_service::list_gradable_activities($course);
$activitystatuses = [];
$records = $DB->get_records_sql(
    "SELECT qs.id, qs.cmid, qs.status
       FROM {local_quessor_submission} qs
       JOIN {course_modules} cm ON cm.id = qs.cmid
      WHERE cm.course = :courseid AND qs.status <> :superseded",
    ['courseid' => $courseid, 'superseded' => 'superseded'],
);
foreach ($records as $record) {
    $cmid = (int)$record->cmid;
    if (!isset($activitystatuses[$cmid])) {
        $activitystatuses[$cmid] = ['total' => 0];
    }
    $activitystatuses[$cmid]['total']++;
    $status = (string)$record->status;
    $activitystatuses[$cmid][$status] = ($activitystatuses[$cmid][$status] ?? 0) + 1;
}
$modinfo = get_fast_modinfo($course);

echo $OUTPUT->header();
echo html_writer::start_div('local-quessor-course-page');

echo html_writer::start_div('local-quessor-course-heading');
echo html_writer::start_div();
echo html_writer::tag('span', get_string('coursequessoreyebrow', 'local_quessor'), [
    'class' => 'local-quessor-eyebrow local-quessor-eyebrow--muted',
]);
echo html_writer::tag('h2', format_string($course->fullname), ['class' => 'local-quessor-course-heading__title']);
echo html_writer::tag('p', get_string('coursequessorintro', 'local_quessor'), [
    'class' => 'local-quessor-course-heading__description',
]);
echo html_writer::end_div();
echo html_writer::tag(
    'span',
    get_string(!empty($coursestatus['activated']) ? 'coursequessorstatusactive' : 'coursequessorstatusinactive', 'local_quessor'),
    ['class' => 'local-quessor-course-status ' . (!empty($coursestatus['activated']) ? 'is-active' : 'is-inactive')],
);
echo html_writer::end_div();

if (empty($coursestatus['activated'])) {
    echo html_writer::start_div('local-quessor-course-gateway');
    echo html_writer::tag('span', 'Q', ['class' => 'local-quessor-course-gateway__mark', 'aria-hidden' => 'true']);
    echo html_writer::tag('h3', get_string('coursequessorinactivetitle', 'local_quessor'));
    echo html_writer::tag('p', get_string('coursequessorinactivedescription', 'local_quessor'));
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => (new moodle_url('/local/quessor/course.php', ['courseid' => $courseid]))->out(false),
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'activate']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::tag('button', get_string('startquessorforcourse', 'local_quessor'), [
        'type' => 'submit',
        'class' => 'btn btn-primary btn-lg',
    ]);
    echo html_writer::end_tag('form');
    echo html_writer::end_div();
} else {
    echo html_writer::start_div('local-quessor-course-active-banner');
    echo html_writer::tag('span', '&#10003;', ['aria-hidden' => 'true']);
    echo html_writer::start_div();
    echo html_writer::tag('strong', get_string('coursequessoractivetitle', 'local_quessor'));
    echo html_writer::tag('p', get_string('coursequessoractivedescription', 'local_quessor'));
    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::start_div('local-quessor-course-activities-heading');
    echo html_writer::start_div();
    echo html_writer::tag('h3', get_string('coursequessoractivities', 'local_quessor'));
    echo html_writer::tag('p', get_string('coursequessoractivitiesdescription', 'local_quessor'));
    echo html_writer::end_div();
    echo html_writer::tag('span', (string)count($activities), ['class' => 'local-quessor-count']);
    echo html_writer::end_div();

    if (!$activities) {
        echo html_writer::div(get_string('noactivitiesavailable', 'local_quessor'), 'local-quessor-empty-state');
    } else {
        echo html_writer::start_div('local-quessor-course-activity-list');
        foreach ($activities as $activity) {
            $cmid = (int)$activity['id'];
            $summary = $activitystatuses[$cmid] ?? ['total' => 0];
            $cm = $modinfo->get_cm($cmid);
            $hasprocess = (int)$summary['total'] > 0;
            $statusparts = [];
            foreach (['ready', 'evaluating', 'completed', 'failed'] as $status) {
                if (!empty($summary[$status])) {
                    $statusparts[] = get_string('native_status_' . $status, 'local_quessor') . ': ' . (int)$summary[$status];
                }
            }

            echo html_writer::start_div('local-quessor-course-activity');
            echo html_writer::tag('span', strtoupper(substr($cm->modname, 0, 1)), [
                'class' => 'local-quessor-course-activity__icon',
                'aria-hidden' => 'true',
            ]);
            echo html_writer::start_div('local-quessor-course-activity__body');
            echo html_writer::tag('strong', format_string($activity['name']));
            echo html_writer::tag('small', get_string('modulename', 'mod_' . $cm->modname) . ' · '
                . get_string('coursequessorsubmissioncount', 'local_quessor', (int)$activity['submissioncount']));
            echo html_writer::tag(
                'span',
                $statusparts ? implode(' · ', $statusparts) : get_string('coursequessornotstarted', 'local_quessor'),
                ['class' => 'local-quessor-course-activity__status ' . ($hasprocess ? 'is-started' : '')],
            );
            echo html_writer::end_div();
            echo html_writer::link(
                new moodle_url('/local/quessor/view.php', ['cmid' => $cmid]),
                get_string($hasprocess ? 'coursequessoropenevaluation' : 'coursequessorstartevaluation', 'local_quessor'),
                ['class' => 'btn btn-outline-primary local-quessor-course-activity__action'],
            );
            echo html_writer::end_div();
        }
        echo html_writer::end_div();
    }
}

echo html_writer::end_div();
echo $OUTPUT->footer();
