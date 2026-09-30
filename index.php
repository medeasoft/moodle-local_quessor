<?php

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
        throw new \moodle_exception('error', 'core', '', null, 'Quessor SSO URL is missing.');
    }
    redirect($quessorinfo['ssoUrl']);
}

if (trim($accountemail) === '') {
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
$PAGE->requires->css(new moodle_url('/local/quessor/styles.css'));

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

$modinfoMap = [];
$evaluations = [];
foreach ($records as $record) {
    if (!isset($modinfoMap[$record->courseid])) {
        $modinfoMap[$record->courseid] = get_fast_modinfo($record->courseid);
    }
    $modinfo = $modinfoMap[$record->courseid];
    if (!isset($modinfo->cms[$record->cmid])) {
        continue;
    }
    $cm = $modinfo->get_cm($record->cmid);

    $coursecontext = context_course::instance($record->courseid);
    $modulecontext = context_module::instance($record->cmid);
    if (!$canmanagebilling && (!has_capability('local/quessor:gradeassignments', $coursecontext)
        || !\local_quessor\local\activity_service::can_grade($cm, $modulecontext))) {
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

$PAGE->requires->js_init_code('
(() => {
    const activitiesUrl = ' . json_encode((new moodle_url('/local/quessor/index.php'))->out(false)) . ';
    const sesskey = ' . json_encode(sesskey()) . ';
    const modal = document.getElementById("local-quessor-start-modal");
    if (!modal) {
        return;
    }

    const courseSelect = modal.querySelector("[data-searchable-select=\"course\"]");
    const assignmentSelect = modal.querySelector("[data-searchable-select=\"assignment\"]");
    const startButton = modal.querySelector("[data-action=\"start\"]");
    const emptyMessage = modal.querySelector("[data-region=\"empty-assignments\"]");

    const getFieldParts = (field) => ({
        input: field.querySelector("[data-role=\"value\"]"),
        trigger: field.querySelector("[data-action=\"toggle-select\"]"),
        label: field.querySelector("[data-role=\"selected-label\"]"),
        menu: field.querySelector("[data-role=\"menu\"]"),
        search: field.querySelector("[data-role=\"search\"]"),
        options: field.querySelector("[data-role=\"options\"]"),
        noResults: field.querySelector("[data-role=\"no-results\"]")
    });

    const closeMenus = () => {
        modal.querySelectorAll("[data-role=\"menu\"]").forEach((menu) => menu.classList.add("d-none"));
        modal.querySelectorAll("[data-action=\"toggle-select\"]").forEach((trigger) => trigger.setAttribute("aria-expanded", "false"));
    };

    const setFieldValue = (field, value, label) => {
        const parts = getFieldParts(field);
        parts.input.value = value;
        parts.label.textContent = label || parts.label.dataset.placeholder;
        parts.label.classList.toggle("is-placeholder", !value);
        parts.trigger.classList.toggle("has-value", Boolean(value));
        parts.options.querySelectorAll("[data-action=\"select-option\"]").forEach((option) => {
            option.setAttribute("aria-selected", option.dataset.value === value ? "true" : "false");
        });
    };

    const createOption = (field, value, label) => {
        const option = document.createElement("button");
        const labelElement = document.createElement("span");
        const checkElement = document.createElement("span");
        option.type = "button";
        option.className = "local-quessor-search-select__option";
        option.dataset.action = "select-option";
        option.dataset.value = String(value);
        option.setAttribute("role", "option");
        option.setAttribute("aria-selected", "false");
        labelElement.textContent = label;
        checkElement.className = "local-quessor-search-select__check";
        checkElement.setAttribute("aria-hidden", "true");
        checkElement.textContent = "✓";
        option.append(labelElement, checkElement);
        field.querySelector("[data-role=\"options\"]").append(option);
    };

    const filterOptions = (field) => {
        const parts = getFieldParts(field);
        const query = parts.search.value.trim().toLocaleLowerCase();
        let visibleCount = 0;
        parts.options.querySelectorAll("[data-action=\"select-option\"]").forEach((option) => {
            const visible = option.textContent.toLocaleLowerCase().includes(query);
            option.classList.toggle("d-none", !visible);
            if (visible) visibleCount++;
        });
        parts.noResults.classList.toggle("d-none", visibleCount > 0);
    };

    const openMenu = (field) => {
        if (getFieldParts(field).trigger.disabled) return;
        closeMenus();
        const parts = getFieldParts(field);
        parts.menu.classList.remove("d-none");
        parts.trigger.setAttribute("aria-expanded", "true");
        parts.search.value = "";
        filterOptions(field);
        window.setTimeout(() => parts.search.focus(), 0);
    };

    const updateCourseState = () => {
        populateAssignments();
    };

    const updateAssignmentState = () => {
        const assignmentParts = getFieldParts(assignmentSelect);
        const hasAssignment = Boolean(assignmentParts.input.value);
        startButton.disabled = !hasAssignment;
        assignmentParts.trigger.disabled = !courseSelect.querySelector("[data-role=\"value\"]").value;
    };

    const populateAssignments = async () => {
        const courseValue = getFieldParts(courseSelect).input.value;
        const assignmentParts = getFieldParts(assignmentSelect);
        assignmentParts.options.replaceChildren();
        setFieldValue(assignmentSelect, "", "");
        emptyMessage.classList.add("d-none");
        if (!courseValue) {
            assignmentParts.trigger.disabled = true;
            assignmentParts.search.disabled = true;
            updateAssignmentState();
            return;
        }
        assignmentParts.trigger.disabled = true;
        assignmentParts.search.disabled = true;
        try {
            const params = new URLSearchParams({
                action: "course_activities",
                courseid: courseValue,
                sesskey
            });
            const response = await fetch(activitiesUrl + "?" + params.toString(), { credentials: "same-origin" });
            const data = await response.json();
            const assignments = Array.isArray(data.activities) ? data.activities : [];
            assignments.forEach((assignment) => createOption(assignmentSelect, assignment.id, assignment.label));
            emptyMessage.classList.toggle("d-none", assignments.length > 0);
            assignmentParts.trigger.disabled = assignments.length === 0;
            assignmentParts.search.disabled = assignments.length === 0;
        } catch (error) {
            emptyMessage.classList.remove("d-none");
        }
        filterOptions(assignmentSelect);
        updateAssignmentState();
    };

    modal.querySelectorAll("[data-searchable-select]").forEach((field) => {
        const parts = getFieldParts(field);
        parts.trigger.addEventListener("click", () => {
            if (parts.menu.classList.contains("d-none")) openMenu(field);
            else closeMenus();
        });
        parts.search.addEventListener("input", () => filterOptions(field));
        parts.options.addEventListener("click", (event) => {
            const option = event.target.closest("[data-action=\"select-option\"]");
            if (!option) return;
            setFieldValue(field, option.dataset.value, option.firstElementChild.textContent);
            closeMenus();
            if (field === courseSelect) updateCourseState();
            else updateAssignmentState();
        });
        parts.trigger.addEventListener("keydown", (event) => {
            if (event.key === "ArrowDown" || event.key === "Enter" || event.key === " ") {
                event.preventDefault();
                openMenu(field);
            }
        });
        parts.search.addEventListener("keydown", (event) => {
            if (event.key === "Escape") {
                closeMenus();
                parts.trigger.focus();
            }
            if (event.key === "Enter") {
                const firstVisible = [...parts.options.querySelectorAll("[data-action=\"select-option\"]")]
                    .find((option) => !option.classList.contains("d-none"));
                if (firstVisible) firstVisible.click();
            }
        });
    });

    document.addEventListener("click", (event) => {
        if (!modal.contains(event.target)) return;
        if (!event.target.closest("[data-searchable-select]")) closeMenus();
    });

    modal.querySelector("form").addEventListener("submit", (event) => {
        if (!getFieldParts(courseSelect).input.value || !getFieldParts(assignmentSelect).input.value) event.preventDefault();
    });

    modal.addEventListener("show.bs.modal", () => {
        closeMenus();
        populateAssignments();
    });
    courseSelect.querySelector("[data-role=\"options\"]").querySelectorAll("[data-action=\"select-option\"]").forEach((option) => {
        option.setAttribute("aria-selected", "false");
    });
    updateAssignmentState();
})();
');

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
echo html_writer::tag('button',
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
    echo html_writer::tag('button',
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
echo html_writer::tag('button',
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
