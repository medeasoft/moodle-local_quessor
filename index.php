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

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$action = optional_param('action', '', PARAM_ALPHAEXT);
$courseid = optional_param('courseid', 0, PARAM_INT);
if ($courseid > 0) {
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    require_login($course);
    $PAGE->set_context(context_course::instance($courseid));
} else {
    require_login();
    $PAGE->set_context(context_system::instance());
}

$canmanagebilling = \local_quessor\local\bridge::is_billing_admin();
$accessiblecourses = get_user_capability_course('local/quessor:gradeassignments', null, false, '', '', 1) ?: [];
if (!$canmanagebilling && !$accessiblecourses) {
    throw new \moodle_exception('nopermissions', 'error', '', get_string('quessor', 'local_quessor'));
}
if ($courseid > 0 && !$canmanagebilling) {
    require_capability('local/quessor:gradeassignments', context_course::instance($courseid));
}

$accountname = fullname($USER);
$accountemail = (string)$USER->email;
$institutionname = format_string($SITE->fullname);
$availablepages = null;

if ($action === 'course_activities') {
    require_sesskey();
    $reqcourseid = required_param('courseid', PARAM_INT);
    if (!$canmanagebilling) {
        require_capability('local/quessor:gradeassignments', context_course::instance($reqcourseid));
    }
    $reqcourse = $DB->get_record('course', ['id' => $reqcourseid], '*', MUST_EXIST);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'activities' => array_values(\local_quessor\local\activity_service::list_gradable_activities($reqcourse)),
    ], JSON_UNESCAPED_UNICODE);
    die;
}

if ($action === 'open_quessor') {
    require_sesskey();
    if (trim($accountemail) === '') {
        throw new \moodle_exception('emailrequired', 'local_quessor');
    }
    $quessorinfo = (new \local_quessor\local\api_client())->prepare_sso(
        \local_quessor\local\bridge::actor_fields()
    );
    if (empty($quessorinfo['ssoUrl'])) {
        throw new \moodle_exception('configurationrequired', 'local_quessor');
    }
    redirect($quessorinfo['ssoUrl']);
}

if (!get_config('local_quessor', 'enabled')) {
    \core\notification::add(get_string('integrationdisabled', 'local_quessor'), \core\output\notification::NOTIFY_INFO);
} else if (trim($accountemail) === '') {
    \core\notification::add(get_string('emailrequired', 'local_quessor'), \core\output\notification::NOTIFY_ERROR);
} else {
    try {
        $summary = (new \local_quessor\local\api_client())->site_summary();
        $institutionname = $summary['instituteName'] ?? $institutionname;
        $availablepages = isset($summary['availablePages']) ? (int)floor(max(0, (float)$summary['availablePages'])) : null;
    } catch (Throwable $e) {
        // The Moodle account and site name are still useful if Quessor is offline.
    }
}

if ($action === 'activate_course' && $courseid > 0) {
    try {
        require_sesskey();
        if (!$canmanagebilling) {
            require_capability('local/quessor:gradeassignments', context_course::instance($courseid));
        }
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $result = (new \local_quessor\local\api_client())->activate_course(array_merge(
            \local_quessor\local\bridge::actor_fields(),
            [
                'moodleCourseId' => (string)$course->id,
                'courseName' => format_string($course->fullname),
                'students' => \local_quessor\local\bridge::visible_students($course),
            ]
        ));
        if (!empty($result['ssoUrl'])) {
            redirect($result['ssoUrl']);
        }
    } catch (Throwable $e) {
        \core\notification::add(get_string('activatecourseerror', 'local_quessor', $e->getMessage()), \core\output\notification::NOTIFY_ERROR);
    }
}

$PAGE->set_url(new moodle_url('/local/quessor/index.php', ['courseid' => $courseid]));
$PAGE->set_title(get_string('quessor', 'local_quessor'));
$PAGE->set_heading(get_string('quessor', 'local_quessor'));
$PAGE->set_primary_active_tab('local_quessor_hub');

if ($canmanagebilling) {
    $courses = $DB->get_records_select(
        'course',
        'id <> :siteid',
        ['siteid' => $SITE->id],
        'fullname ASC',
        'id, fullname, shortname'
    ) ?: [];
} else {
    $courses = get_user_capability_course(
        'local/quessor:gradeassignments',
        null,
        false,
        'fullname,shortname',
        'fullname ASC'
    ) ?: [];
}

$courseoptions = [];
foreach ($courses as $course) {
    $context = context_course::instance($course->id);
    if ($canmanagebilling || has_capability('local/quessor:gradeassignments', $context)) {
        $courseoptions[$course->id] = format_string($course->fullname);
    }
}

if ($courseid && !isset($courseoptions[$courseid])) {
    \core\notification::add(get_string('coursenotavailable', 'local_quessor'), \core\output\notification::NOTIFY_ERROR);
    $courseid = 0;
}

$records = [];
$courseids = array_map('intval', array_keys($courseoptions));
if ($courseids) {
    [$insql, $inparams] = $DB->get_in_or_equal($courseids);
    $records = $DB->get_records_sql(
        "SELECT qs.*, c.id AS courseid, c.fullname AS coursefullname
           FROM {local_quessor_submission} qs
           JOIN {course_modules} cm ON cm.id = qs.cmid
           JOIN {course} c ON c.id = cm.course
          WHERE c.id $insql AND qs.timemodified >= ?
       ORDER BY qs.timemodified DESC",
        array_merge($inparams, [time() - (DAYSECS * 180)]),
        0,
        2000
    );
}

$modinfomap = [];
$evaluations = [];
foreach ($records as $record) {
    if (!\local_quessor\local\activity_service::can_access_student((int)$record->courseid, (int)$record->userid)) {
        continue;
    }
    if (!isset($modinfomap[$record->courseid])) {
        $modinfomap[$record->courseid] = get_fast_modinfo($record->courseid);
    }
    $modinfo = $modinfomap[$record->courseid];
    if (!isset($modinfo->cms[$record->cmid])) {
        continue;
    }
    $cm = $modinfo->get_cm($record->cmid);

    $coursecontext = context_course::instance($record->courseid);
    $modulecontext = context_module::instance($record->cmid);
    if (
        !$canmanagebilling && (!has_capability('local/quessor:gradeassignments', $coursecontext)
        || !\local_quessor\local\activity_service::can_grade($cm, $modulecontext))
    ) {
        continue;
    }

    if (!isset($evaluations[$record->cmid])) {
        $evaluations[$record->cmid] = (object)[
            'cmid' => $record->cmid,
            'coursefullname' => $record->coursefullname,
            'assignmentname' => $cm->name,
            'submissioncount' => 0,
            'latestmodified' => 0,
            'statuses' => [],
        ];
    }

    $evaluation = $evaluations[$record->cmid];
    $evaluation->submissioncount++;
    $evaluation->latestmodified = max($evaluation->latestmodified, (int)$record->timemodified);
    $evaluation->statuses[$record->status] = ($evaluation->statuses[$record->status] ?? 0) + 1;
}

$PAGE->requires->js_call_amd('local_quessor/activity_picker', 'init');

echo $OUTPUT->header();

$availablepagestext = null;
if ($availablepages !== null) {
    $availablepagestext = local_quessor_format_available_pages($availablepages);
}

echo html_writer::start_div('local-quessor-dashboard');
echo html_writer::start_div('local-quessor-hero');
echo html_writer::start_div('local-quessor-hero__content');
echo html_writer::tag('span', get_string('dashboardeyebrow', 'local_quessor'), ['class' => 'local-quessor-eyebrow']);
echo html_writer::tag('h2', get_string('dashboardtitle', 'local_quessor', s($accountname)), [
    'class' => 'local-quessor-hero__title',
]);
echo html_writer::tag('p', get_string('dashboardintro', 'local_quessor'), ['class' => 'local-quessor-hero__description']);
if (trim($accountemail) !== '') {
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => new moodle_url('/local/quessor/index.php', ['courseid' => $courseid]),
        'target' => '_blank',
        'class' => 'mt-3',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'open_quessor']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::tag('button', get_string('openpanel', 'local_quessor'), [
        'type' => 'submit',
        'class' => 'btn btn-light btn-sm font-weight-bold',
    ]);
    echo html_writer::end_tag('form');
}
echo html_writer::end_div();
echo html_writer::tag('span', '&#10003;', ['class' => 'local-quessor-hero__mark', 'aria-hidden' => 'true']);
echo html_writer::end_div();

echo html_writer::start_div('local-quessor-stats');
echo html_writer::start_div('local-quessor-stat');
echo html_writer::tag('span', get_string('institutionname', 'local_quessor'), [
    'class' => 'local-quessor-stat__label',
]);
echo html_writer::tag('strong', s($institutionname), ['class' => 'local-quessor-stat__value']);
echo html_writer::end_div();
echo html_writer::start_div('local-quessor-stat');
echo html_writer::tag('span', get_string('accountemail', 'local_quessor'), [
    'class' => 'local-quessor-stat__label',
]);
echo html_writer::tag('strong', s($accountemail), ['class' => 'local-quessor-stat__value local-quessor-stat__value--small']);
echo html_writer::end_div();
if ($availablepagestext !== null) {
    echo html_writer::start_div('local-quessor-stat local-quessor-stat--accent');
    echo html_writer::tag('span', get_string('availablepageslabel', 'local_quessor'), [
        'class' => 'local-quessor-stat__label',
    ]);
    echo html_writer::tag('strong', s($availablepagestext), ['class' => 'local-quessor-stat__value']);
    echo html_writer::end_div();
}
echo html_writer::end_div();

echo html_writer::start_div('local-quessor-action-card');
echo html_writer::start_div('local-quessor-action-card__body');
echo html_writer::tag('span', '&#10022;', ['class' => 'local-quessor-action-card__icon', 'aria-hidden' => 'true']);
echo html_writer::start_div();
echo $OUTPUT->heading(get_string('startnewevaluation', 'local_quessor'), 3, 'local-quessor-action-card__title');
echo html_writer::tag('p', get_string('startnewevaluation_desc', 'local_quessor'), ['class' => 'local-quessor-action-card__description']);
echo html_writer::end_div();
echo html_writer::end_div();
$startbuttonattributes = [
    'type' => 'button',
    'class' => 'btn btn-primary local-quessor-action-card__button',
    'data-bs-toggle' => 'modal',
    'data-bs-target' => '#local-quessor-start-modal',
];
if (!$courseoptions) {
    $startbuttonattributes['disabled'] = 'disabled';
}
echo html_writer::tag('button', get_string('startprocess', 'local_quessor'), $startbuttonattributes);
if (!$courseoptions) {
    echo html_writer::div(get_string('nocoursesavailable', 'local_quessor'), 'alert alert-warning local-quessor-action-card__notice');
}
echo html_writer::end_div();

echo html_writer::start_div('modal fade', [
    'id' => 'local-quessor-start-modal',
    'data-activities-url' => (new moodle_url('/local/quessor/index.php'))->out(false),
    'data-sesskey' => sesskey(),
    'tabindex' => '-1',
    'aria-labelledby' => 'local-quessor-start-title',
    'aria-hidden' => 'true',
]);
echo html_writer::start_div('modal-dialog modal-dialog-centered');
echo html_writer::start_tag('form', [
    'method' => 'get',
    'action' => new moodle_url('/local/quessor/view.php'),
    'class' => 'modal-content local-quessor-start-modal',
]);
echo html_writer::start_div('modal-header');
echo html_writer::tag('h5', get_string('startnewevaluation', 'local_quessor'), [
    'id' => 'local-quessor-start-title',
    'class' => 'modal-title',
]);
echo html_writer::tag('button', '', [
    'type' => 'button',
    'class' => 'btn-close',
    'data-bs-dismiss' => 'modal',
    'aria-label' => get_string('closebuttontitle'),
]);
echo html_writer::end_div();
echo html_writer::start_div('modal-body');
echo html_writer::start_div('', ['data-region' => 'course-step']);
echo html_writer::tag('p', get_string('choosecourse_desc', 'local_quessor'), ['class' => 'text-muted']);
echo html_writer::start_div('form-group mb-0');
echo html_writer::tag('label', get_string('choosecourse', 'local_quessor'), [
    'for' => 'local-quessor-modal-course',
    'class' => 'form-label',
]);
echo html_writer::start_div('local-quessor-search-select', [
    'data-searchable-select' => 'course',
]);
echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'courseid',
    'value' => '',
    'data-role' => 'value',
]);
echo html_writer::tag(
    'button',
    html_writer::tag('span', get_string('selectcourse', 'local_quessor'), [
        'data-role' => 'selected-label',
        'data-placeholder' => get_string('selectcourse', 'local_quessor'),
        'class' => 'is-placeholder',
    ]) . html_writer::tag('span', '&#8964;', [
        'class' => 'local-quessor-search-select__chevron',
        'aria-hidden' => 'true',
    ]),
    [
        'id' => 'local-quessor-modal-course',
        'type' => 'button',
        'class' => 'local-quessor-search-select__trigger',
        'data-action' => 'toggle-select',
        'aria-haspopup' => 'listbox',
        'aria-expanded' => 'false',
        'aria-controls' => 'local-quessor-course-options',
    ]
);
echo html_writer::start_div('local-quessor-search-select__menu d-none', [
    'data-role' => 'menu',
]);
echo html_writer::start_div('local-quessor-search-select__search');
echo html_writer::tag('span', '', [
    'class' => 'local-quessor-search-select__search-icon',
    'aria-hidden' => 'true',
]);
echo html_writer::empty_tag('input', [
    'type' => 'search',
    'class' => 'local-quessor-search-select__search-input',
    'placeholder' => get_string('searchcourses', 'local_quessor'),
    'aria-label' => get_string('searchcourses', 'local_quessor'),
    'autocomplete' => 'off',
    'data-role' => 'search',
]);
echo html_writer::end_div();
echo html_writer::start_div('', [
    'id' => 'local-quessor-course-options',
    'class' => 'local-quessor-search-select__options',
    'role' => 'listbox',
    'data-role' => 'options',
]);
foreach ($courseoptions as $courseoptionid => $courseoptionname) {
    echo html_writer::tag(
        'button',
        html_writer::tag('span', $courseoptionname) . html_writer::tag('span', '&#10003;', [
            'class' => 'local-quessor-search-select__check',
            'aria-hidden' => 'true',
        ]),
        [
            'type' => 'button',
            'class' => 'local-quessor-search-select__option',
            'data-action' => 'select-option',
            'data-value' => $courseoptionid,
            'role' => 'option',
            'aria-selected' => 'false',
        ]
    );
}
echo html_writer::tag('div', get_string('noresultsfound', 'local_quessor'), [
    'class' => 'local-quessor-search-select__no-results d-none',
    'data-role' => 'no-results',
]);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::start_div('mt-4', ['data-region' => 'assignment-step']);
echo html_writer::tag('p', get_string('chooseactivity_desc', 'local_quessor'), ['class' => 'text-muted']);
echo html_writer::start_div('form-group mb-0');
echo html_writer::tag('label', get_string('chooseactivity', 'local_quessor'), [
    'for' => 'local-quessor-modal-assignment',
    'class' => 'form-label',
]);
echo html_writer::start_div('local-quessor-search-select', [
    'data-searchable-select' => 'assignment',
]);
echo html_writer::empty_tag('input', [
    'type' => 'hidden',
    'name' => 'cmid',
    'value' => '',
    'data-role' => 'value',
]);
echo html_writer::tag(
    'button',
    html_writer::tag('span', get_string('selectactivity', 'local_quessor'), [
        'data-role' => 'selected-label',
        'data-placeholder' => get_string('selectactivity', 'local_quessor'),
        'class' => 'is-placeholder',
    ]) . html_writer::tag('span', '&#8964;', [
        'class' => 'local-quessor-search-select__chevron',
        'aria-hidden' => 'true',
    ]),
    [
        'id' => 'local-quessor-modal-assignment',
        'type' => 'button',
        'class' => 'local-quessor-search-select__trigger',
        'data-action' => 'toggle-select',
        'aria-haspopup' => 'listbox',
        'aria-expanded' => 'false',
        'aria-controls' => 'local-quessor-assignment-options',
        'disabled' => 'disabled',
    ]
);
echo html_writer::start_div('local-quessor-search-select__menu d-none', [
    'data-role' => 'menu',
]);
echo html_writer::start_div('local-quessor-search-select__search');
echo html_writer::tag('span', '', [
    'class' => 'local-quessor-search-select__search-icon',
    'aria-hidden' => 'true',
]);
echo html_writer::empty_tag('input', [
    'type' => 'search',
    'class' => 'local-quessor-search-select__search-input',
    'placeholder' => get_string('searchactivities', 'local_quessor'),
    'aria-label' => get_string('searchactivities', 'local_quessor'),
    'autocomplete' => 'off',
    'data-role' => 'search',
    'disabled' => 'disabled',
]);
echo html_writer::end_div();
echo html_writer::start_div('', [
    'id' => 'local-quessor-assignment-options',
    'class' => 'local-quessor-search-select__options',
    'role' => 'listbox',
    'data-role' => 'options',
]);
echo html_writer::tag('div', get_string('noresultsfound', 'local_quessor'), [
    'class' => 'local-quessor-search-select__no-results d-none',
    'data-role' => 'no-results',
]);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::div(get_string('noactivitiesavailable', 'local_quessor'), 'alert alert-warning mt-3 d-none', [
    'data-region' => 'empty-assignments',
]);
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::start_div('modal-footer');
echo html_writer::tag('button', get_string('startprocess', 'local_quessor'), [
    'type' => 'submit',
    'class' => 'btn btn-primary',
    'data-action' => 'start',
    'disabled' => 'disabled',
]);
echo html_writer::end_div();
echo html_writer::end_tag('form');
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('local-quessor-section-heading');
echo html_writer::start_div();
echo html_writer::tag('span', get_string('activityeyebrow', 'local_quessor'), ['class' => 'local-quessor-eyebrow local-quessor-eyebrow--muted']);
echo $OUTPUT->heading(get_string('previousevaluations', 'local_quessor'), 3, 'local-quessor-section-heading__title');
echo html_writer::end_div();
echo html_writer::tag('span', (string)count($evaluations), ['class' => 'local-quessor-count', 'title' => get_string('previousevaluations', 'local_quessor')]);
echo html_writer::end_div();


if ($evaluations) {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable table local-quessor-evaluations-table';
    $table->head = [
        get_string('course'),
        get_string('activitylabel', 'local_quessor'),
        get_string('submissioncount', 'local_quessor'),
        get_string('status', 'local_quessor'),
        get_string('lastmodified'),
        get_string('actions'),
    ];

    foreach ($evaluations as $evaluation) {
        $statuses = [];
        foreach ($evaluation->statuses as $status => $count) {
            $statuses[] = html_writer::span(s($status) . ': ' . $count, 'badge local-quessor-status-badge');
        }

        $table->data[] = [
            format_string($evaluation->coursefullname),
            format_string($evaluation->assignmentname),
            $evaluation->submissioncount,
            implode(' ', $statuses),
            userdate($evaluation->latestmodified),
            html_writer::link(
                new moodle_url('/local/quessor/view.php', ['cmid' => $evaluation->cmid]),
                get_string('openevaluation', 'local_quessor'),
                ['class' => 'btn btn-sm btn-outline-primary local-quessor-open-button']
            ),
        ];
    }

    echo html_writer::start_div('local-quessor-table-card');
    echo html_writer::table($table);
    echo html_writer::end_div();
} else {
    echo html_writer::start_div('local-quessor-empty-state');
    echo html_writer::tag('span', '&#128203;', ['class' => 'local-quessor-empty-state__icon', 'aria-hidden' => 'true']);
    echo html_writer::tag('p', get_string('nopreviousevaluations', 'local_quessor'), ['class' => 'mb-0']);
    echo html_writer::end_div();
}

echo html_writer::end_div();
echo $OUTPUT->footer();
