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

defined('MOODLE_INTERNAL') || die();

function local_quessor_format_available_pages($pages): string {
    $whole = (int)floor(max(0, (float)$pages));
    return number_format($whole, 0, ',', '.');
}

function local_quessor_embed_origin(string $url): string {
    $scheme = parse_url($url, PHP_URL_SCHEME);
    $host = parse_url($url, PHP_URL_HOST);
    if (!$scheme || !$host) {
        return '';
    }
    $origin = $scheme . '://' . $host;
    $port = parse_url($url, PHP_URL_PORT);
    if ($port) {
        $origin .= ':' . $port;
    }
    return $origin;
}

/**
 * Registers iframe postMessage handling and CSP-block fallback. Call before $OUTPUT->header().
 */
function local_quessor_prepare_embed(string $url, string $iframeid): void {
    global $PAGE;

    $origin = local_quessor_embed_origin($url);
    if ($origin === '') {
        return;
    }

    $PAGE->requires->js_call_amd('local_quessor/embed', 'init', [
        $iframeid,
        $iframeid . '-fallback',
        $origin,
    ]);
}

function local_quessor_render_embed(string $url, string $iframeid, string $title, string $minheight = '210px'): void {
    $origin = local_quessor_embed_origin($url);
    echo html_writer::div(
        html_writer::tag('p', get_string('embedblocked', 'local_quessor', s($origin ?: $url)))
        . html_writer::link($url, get_string('openinnewtab', 'local_quessor'), [
            'class' => 'btn btn-primary',
            'target' => '_blank',
            'rel' => 'noopener noreferrer',
        ]),
        'alert alert-warning d-none mb-3',
        ['id' => $iframeid . '-fallback']
    );
    echo html_writer::tag('p', html_writer::link($url, get_string('openinnewtab', 'local_quessor'), [
        'class' => 'small',
        'target' => '_blank',
        'rel' => 'noopener noreferrer',
    ]), ['class' => 'text-end mb-2']);
    echo html_writer::tag('iframe', '', [
        'id' => $iframeid,
        'src' => $url,
        'title' => $title,
        'style' => 'width:100%; min-height:calc(100vh - ' . $minheight . '); border:0; border-radius:8px; background:#fff;',
        'referrerpolicy' => 'strict-origin-when-cross-origin',
    ]);
}

/** Mount the full same-origin Quessor evaluation wizard without an iframe. */
function local_quessor_render_native_evaluation(int $cmid, bool $questionsourceattached = false): void {
    global $PAGE;

    $PAGE->requires->js_call_amd('local_quessor/native_services', 'init');
    $config = [
        'endpoint' => (new moodle_url('/local/quessor/ajax.php'))->out(false),
        'cmid' => $cmid,
        'sesskey' => sesskey(),
        'questionSourceAttached' => $questionsourceattached,
        'locale' => str_starts_with(current_language(), 'tr') ? 'tr' : 'en',
        'labels' => [
            'loading' => get_string('native_loading', 'local_quessor'),
            'loadError' => get_string('native_load_error', 'local_quessor'),
            'stepUnavailable' => get_string('native_step_unavailable', 'local_quessor'),
            'eyebrow' => get_string('native_eyebrow', 'local_quessor'),
            'subtitle' => get_string('native_full_intro', 'local_quessor'),
            'steps' => get_string('native_steps', 'local_quessor'),
            'openPanel' => get_string('native_open_panel', 'local_quessor'),
            'students' => get_string('native_students_tab', 'local_quessor'),
            'questions' => get_string('native_questions_tab', 'local_quessor'),
            'criteria' => get_string('native_criteria_tab', 'local_quessor'),
            'preview' => get_string('native_preview_tab', 'local_quessor'),
            'result' => get_string('native_result_tab', 'local_quessor'),
            'back' => get_string('native_back', 'local_quessor'),
            'next' => get_string('native_next', 'local_quessor'),
            'points' => get_string('native_points', 'local_quessor'),
            'correctAnswer' => get_string('native_correct_answer', 'local_quessor'),
            'ready' => get_string('native_ready', 'local_quessor'),
            'pages' => get_string('native_pages', 'local_quessor'),
            'details' => get_string('native_details', 'local_quessor'),
            'questionsDescription' => get_string('native_questions_description', 'local_quessor'),
            'retryQuestionExtraction' => get_string('retry_question_extraction', 'local_quessor'),
            'edit' => get_string('native_edit', 'local_quessor'),
            'questionName' => get_string('native_question_name', 'local_quessor'),
            'questionText' => get_string('native_question_text', 'local_quessor'),
            'sharedContext' => get_string('native_shared_context', 'local_quessor'),
            'showSharedContext' => get_string('native_show_shared_context', 'local_quessor'),
            'hideSharedContext' => get_string('native_hide_shared_context', 'local_quessor'),
            'order' => get_string('native_question_order', 'local_quessor'),
            'questionType' => get_string('native_question_type', 'local_quessor'),
            'refresh' => get_string('native_refresh', 'local_quessor'),
            'noQuestions' => get_string('native_no_questions', 'local_quessor'),
            'noQuestionsHelp' => get_string('native_no_questions_help', 'local_quessor'),
            'preparingQuestions' => get_string('native_preparing_questions', 'local_quessor'),
            'preparingQuestionsHelp' => get_string('native_preparing_questions_help', 'local_quessor'),
            'aiGenerateAnswer' => get_string('native_ai_generate_answer', 'local_quessor'),
            'aiAnswerGenerating' => get_string('native_ai_answer_generating', 'local_quessor'),
            'aiAnswerGenerated' => get_string('native_ai_answer_generated', 'local_quessor'),
            'aiAnswerFailed' => get_string('native_ai_answer_failed', 'local_quessor'),
            'addRubricFile' => get_string('native_add_rubric_file', 'local_quessor'),
            'rubricUploadTitle' => get_string('native_rubric_upload_title', 'local_quessor'),
            'rubricUploadDescription' => get_string('native_rubric_upload_description', 'local_quessor'),
            'chooseRubricFile' => get_string('native_choose_rubric_file', 'local_quessor'),
            'rubricFileHelp' => get_string('native_rubric_file_help', 'local_quessor'),
            'pdfPage' => get_string('native_pdf_page', 'local_quessor'),
            'extractRubric' => get_string('native_extract_rubric', 'local_quessor'),
            'rubricExtracting' => get_string('native_rubric_extracting', 'local_quessor'),
            'rubricExtracted' => get_string('native_rubric_extracted', 'local_quessor'),
            'rubricExtractFailed' => get_string('native_rubric_extract_failed', 'local_quessor'),
            'rubricNotFound' => get_string('native_rubric_not_found', 'local_quessor'),
            'rubricFileRequired' => get_string('native_rubric_file_required', 'local_quessor'),
            'rubricFileInvalid' => get_string('native_rubric_file_invalid', 'local_quessor'),
            'questionSaved' => get_string('native_question_saved', 'local_quessor'),
            'questionSaveFailed' => get_string('native_question_save_failed', 'local_quessor'),
            'type_MULTIPLE_CHOICE' => get_string('native_type_multiple_choice', 'local_quessor'),
            'type_ESSAY' => get_string('native_type_essay', 'local_quessor'),
            'type_TRUE_FALSE' => get_string('native_type_true_false', 'local_quessor'),
            'type_SHORT_ANSWER' => get_string('native_type_short_answer', 'local_quessor'),
            'type_MATCHING' => get_string('native_type_matching', 'local_quessor'),
            'type_FILL_IN_THE_BLANK' => get_string('native_type_fill_blank', 'local_quessor'),
            'type_MULTI_SELECT' => get_string('native_type_multi_select', 'local_quessor'),
            'cancel' => get_string('native_cancel', 'local_quessor'),
            'save' => get_string('native_save', 'local_quessor'),
            'criteriaDescription' => get_string('native_criteria_description', 'local_quessor'),
            'feedbackLanguage' => get_string('native_feedback_language', 'local_quessor'),
            'feedbackLanguageHelp' => get_string('native_feedback_language_help', 'local_quessor'),
            'feedbackLanguageTr' => get_string('native_feedback_language_tr', 'local_quessor'),
            'feedbackLanguageEn' => get_string('native_feedback_language_en', 'local_quessor'),
            'feedbackLanguageBg' => get_string('native_feedback_language_bg', 'local_quessor'),
            'evaluationSettingsSaveFailed' => get_string('native_evaluation_settings_save_failed', 'local_quessor'),
            'selectStudent' => get_string('native_select_student', 'local_quessor'),
            'startEvaluation' => get_string('startevaluation', 'local_quessor'),
            'evaluateRemaining' => get_string('native_evaluate_remaining', 'local_quessor'),
            'evaluationRunning' => get_string('native_evaluation_running', 'local_quessor'),
            'evaluationQueued' => get_string('native_evaluation_queued', 'local_quessor'),
            'evaluationStartFailed' => get_string('native_evaluation_start_failed', 'local_quessor'),
            'evaluationProgressTitle' => get_string('native_evaluation_progress_title', 'local_quessor'),
            'evaluationProgressPending' => get_string('native_evaluation_progress_pending', 'local_quessor'),
            'evaluationProgressProcessing' => get_string('native_evaluation_progress_processing', 'local_quessor'),
            'evaluationProgressCompleted' => get_string('native_evaluation_progress_completed', 'local_quessor'),
            'evaluationProgressCompletedErrors' => get_string('native_evaluation_progress_completed_errors', 'local_quessor'),
            'evaluationProgressDone' => get_string('native_evaluation_progress_done', 'local_quessor'),
            'evaluationProgressWaiting' => get_string('native_evaluation_progress_waiting', 'local_quessor'),
            'evaluationProgressFailed' => get_string('native_evaluation_progress_failed', 'local_quessor'),
            'syncedSubmissions' => get_string('native_synced_submissions', 'local_quessor'),
            'submissionListTitle' => get_string('native_submission_list_title', 'local_quessor'),
            'submissionListDescription' => get_string('native_submission_list_description', 'local_quessor'),
            'submissions' => get_string('native_submissions', 'local_quessor'),
            'noSubmissions' => get_string('native_no_submissions', 'local_quessor'),
            'status' => get_string('status', 'local_quessor'),
            'statusReady' => get_string('native_status_ready', 'local_quessor'),
            'statusEvaluating' => get_string('native_status_evaluating', 'local_quessor'),
            'statusCompleted' => get_string('native_status_completed', 'local_quessor'),
            'statusFailed' => get_string('native_status_failed', 'local_quessor'),
            'combinedEvaluationTitle' => get_string('native_combined_evaluation_title', 'local_quessor'),
            'combinedEvaluationDescription' => get_string('native_combined_evaluation_description', 'local_quessor'),
            'totalStudents' => get_string('native_total_students', 'local_quessor'),
            'evaluatedStudents' => get_string('native_evaluated_students', 'local_quessor'),
            'averageScore' => get_string('native_average_score', 'local_quessor'),
            'totalQuestions' => get_string('native_total_questions', 'local_quessor'),
            'searchStudents' => get_string('native_search_students', 'local_quessor'),
            'noSearchResults' => get_string('native_no_search_results', 'local_quessor'),
            'studentEvaluation' => get_string('native_student_evaluation', 'local_quessor'),
            'evaluatedQuestions' => get_string('native_evaluated_questions', 'local_quessor'),
            'previousStudent' => get_string('native_previous_student', 'local_quessor'),
            'nextStudent' => get_string('native_next_student', 'local_quessor'),
            'evaluateStudent' => get_string('native_evaluate_student', 'local_quessor'),
            'reevaluateStudent' => get_string('native_reevaluate_student', 'local_quessor'),
            'studentEvaluationQueued' => get_string('native_student_evaluation_queued', 'local_quessor'),
            'studentPages' => get_string('native_student_pages', 'local_quessor'),
            'page' => get_string('native_page', 'local_quessor'),
            'zoomIn' => get_string('native_zoom_in', 'local_quessor'),
            'zoomOut' => get_string('native_zoom_out', 'local_quessor'),
            'resetZoom' => get_string('native_zoom_reset', 'local_quessor'),
            'dragToPan' => get_string('native_drag_to_pan', 'local_quessor'),
            'loadingStudentPages' => get_string('native_loading_student_pages', 'local_quessor'),
            'noStudentPages' => get_string('native_no_student_pages_for_student', 'local_quessor'),
            'studentPagesUnavailable' => get_string('native_student_pages_unavailable', 'local_quessor'),
            'scoreAndFeedback' => get_string('native_score_feedback', 'local_quessor'),
            'evaluatedAt' => get_string('native_evaluated_at', 'local_quessor'),
            'notEvaluatedYet' => get_string('native_not_evaluated_yet', 'local_quessor'),
            'saveScoreFeedback' => get_string('native_save_score_feedback', 'local_quessor'),
            'saving' => get_string('native_saving', 'local_quessor'),
            'answersSaved' => get_string('native_answers_saved', 'local_quessor'),
            'answerSaveFailed' => get_string('native_answer_save_failed', 'local_quessor'),
            'gradeSyncFailed' => get_string('native_grade_sync_failed', 'local_quessor'),
            'invalidScore' => get_string('native_invalid_score', 'local_quessor'),
            'student' => get_string('student', 'local_quessor'),
            'grade' => get_string('grade', 'local_quessor'),
            'feedback' => get_string('feedback', 'local_quessor'),
            'noResults' => get_string('native_no_results', 'local_quessor'),
            'reviewAndPublishGrades' => get_string('native_review_publish_grades', 'local_quessor'),
            'publishGradesIntro' => get_string('native_publish_grades_intro', 'local_quessor'),
            'includeFeedback' => get_string('native_include_feedback', 'local_quessor'),
            'includeFeedbackHelp' => get_string('native_include_feedback_help', 'local_quessor'),
            'confirmPublishGrades' => get_string('native_confirm_publish_grades', 'local_quessor'),
            'publishingGrades' => get_string('native_publishing_grades', 'local_quessor'),
            'gradesPublished' => get_string('native_grades_published', 'local_quessor'),
            'gradePublishFailed' => get_string('native_grade_publish_failed', 'local_quessor'),
            'noFeedback' => get_string('native_no_feedback', 'local_quessor'),
        ],
    ];
    echo html_writer::div('', '', [
        'id' => 'local-quessor-app',
        'data-qev-mount' => '1',
        'data-config' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
    return;

    $records = $DB->get_records('local_quessor_submission', ['cmid' => $cmid], 'userid ASC');
    $users = [];
    if ($records) {
        $userids = array_values(array_unique(array_map(static fn($record) => (int)$record->userid, $records)));
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $users = $DB->get_records_select(
            'user',
            "id $insql",
            $params,
            '',
            'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename'
        );
    }

    $statuslabels = [
        'ready' => get_string('native_status_ready', 'local_quessor'),
        'evaluating' => get_string('native_status_evaluating', 'local_quessor'),
        'completed' => get_string('native_status_completed', 'local_quessor'),
        'failed' => get_string('native_status_failed', 'local_quessor'),
    ];
    $completed = count(array_filter($records, static fn($record) => $record->status === 'completed'));

    $model = [
        'title' => (string)($payload['assignmentTitle'] ?? get_string('quessor', 'local_quessor')),
        'description' => (string)($payload['assignmentDescription'] ?? ''),
        'maxScore' => (float)($payload['maxScore'] ?? 0),
        'questions' => [[
            'id' => 'moodle-' . $cmid,
            'name' => (string)($payload['assignmentTitle'] ?? ''),
            'text' => (string)($payload['assignmentDescription'] ?? ''),
            'points' => (float)($payload['maxScore'] ?? 0),
            'criteria' => trim((string)($payload['rubric'] ?? '')) ?: null,
        ]],
        'criteria' => trim((string)($payload['rubric'] ?? '')) ?: null,
        'context' => trim((string)($payload['courseContext'] ?? '')) ?: null,
        'readyCount' => count($ready),
        'completedCount' => $completed,
        'submissions' => array_values(array_map(static function ($record) use ($users, $statuslabels): array {
            $user = $users[(int)$record->userid] ?? null;
            return [
                'id' => (string)$record->id,
                'studentName' => $user ? fullname($user) : ('#' . (int)$record->userid),
                'status' => $statuslabels[$record->status] ?? (string)$record->status,
                'grade' => $record->grade === null ? null : (float)$record->grade,
                'feedback' => trim((string)$record->feedback) ?: null,
                'reportUrl' => trim((string)$record->reporturl) ?: null,
            ];
        }, $records)),
        'labels' => [
            'questions' => get_string('native_questions_tab', 'local_quessor'),
            'gradePreview' => get_string('native_preview_tab', 'local_quessor'),
            'evaluationQuestion' => get_string('native_question_label', 'local_quessor'),
            'maximumScore' => get_string('native_score_label', 'local_quessor'),
            'points' => get_string('native_points', 'local_quessor'),
            'criteria' => get_string('native_criteria_label', 'local_quessor'),
            'context' => get_string('native_context_label', 'local_quessor'),
            'submissions' => get_string('native_submissions', 'local_quessor'),
            'ready' => get_string('native_ready', 'local_quessor'),
            'completed' => get_string('native_completed', 'local_quessor'),
            'status' => get_string('status', 'local_quessor'),
            'grade' => get_string('grade', 'local_quessor'),
            'feedback' => get_string('feedback', 'local_quessor'),
            'report' => get_string('report', 'local_quessor'),
            'noCriteria' => get_string('native_no_criteria', 'local_quessor'),
            'noContext' => get_string('native_no_context', 'local_quessor'),
            'noSubmissions' => get_string('native_no_submissions', 'local_quessor'),
            'startGrading' => get_string('launchwizard', 'local_quessor'),
        ],
    ];
    echo html_writer::div('', '', [
        'id' => 'local-quessor-app',
        'data-qev-mount' => '1',
        'data-config' => json_encode($model, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'data-action-url' => (new moodle_url('/local/quessor/view.php', ['cmid' => $cmid]))->out(false),
        'data-sesskey' => sesskey(),
    ]);
    return;

    echo html_writer::start_div('local-quessor-native', ['data-native-evaluation' => '1']);
    echo html_writer::tag('span', get_string('native_eyebrow', 'local_quessor'), ['class' => 'local-quessor-eyebrow']);
    echo html_writer::tag('h2', get_string('native_title', 'local_quessor'), ['class' => 'local-quessor-native__title']);
    echo html_writer::tag('p', get_string('native_intro', 'local_quessor'), ['class' => 'local-quessor-native__intro']);

    echo html_writer::start_div('local-quessor-native__progress');
    echo html_writer::start_div('local-quessor-native__progress-line');
    echo html_writer::div('', 'local-quessor-native__progress-fill');
    echo html_writer::end_div();
    foreach (['questions' => 'native_questions_tab', 'preview' => 'native_preview_tab'] as $index => $label) {
        echo html_writer::start_div('local-quessor-native__step' . ($index === 'questions' ? ' is-active' : ''), ['data-native-step' => $index]);
        echo html_writer::tag('span', $index === 'questions' ? '1' : '2', ['class' => 'local-quessor-native__step-number']);
        echo html_writer::tag('span', get_string($label, 'local_quessor'), ['class' => 'local-quessor-native__step-label']);
        echo html_writer::end_div();
    }
    echo html_writer::end_div();

    echo html_writer::start_div('local-quessor-native__tabs', ['role' => 'tablist']);
    foreach (['questions' => 'native_questions_tab', 'preview' => 'native_preview_tab'] as $tab => $label) {
        echo html_writer::tag('button', get_string($label, 'local_quessor'), [
            'type' => 'button', 'class' => 'local-quessor-native__tab' . ($tab === 'questions' ? ' is-active' : ''),
            'data-native-tab' => $tab, 'aria-selected' => $tab === 'questions' ? 'true' : 'false',
        ]);
    }
    echo html_writer::end_div();

    echo html_writer::start_div('local-quessor-native__panel is-active', ['data-native-panel' => 'questions']);
    echo html_writer::start_div('local-quessor-native-grid');
    echo html_writer::start_div('local-quessor-native-card local-quessor-native-card--wide');
    echo html_writer::tag('span', get_string('native_question_label', 'local_quessor'), ['class' => 'local-quessor-native-card__eyebrow']);
    echo html_writer::tag('h3', format_string((string)$payload['assignmentTitle']), ['class' => 'local-quessor-native-card__title']);
    echo html_writer::tag('div', format_text((string)$payload['assignmentDescription'], FORMAT_PLAIN), ['class' => 'local-quessor-native-card__body']);
    echo html_writer::end_div();
    echo html_writer::start_div('local-quessor-native-card');
    echo html_writer::tag('span', get_string('native_score_label', 'local_quessor'), ['class' => 'local-quessor-native-card__eyebrow']);
    echo html_writer::tag('strong', format_float((float)$payload['maxScore'], 2), ['class' => 'local-quessor-native-score']);
    echo html_writer::tag('span', get_string('native_points', 'local_quessor'), ['class' => 'local-quessor-native-card__muted']);
    echo html_writer::end_div();
    foreach (
        [
        ['native_criteria_label', (string)($payload['rubric'] ?? ''), 'native_no_criteria'],
        ['native_context_label', (string)($payload['courseContext'] ?? ''), 'native_no_context'],
        ] as [$label, $value, $empty]
    ) {
        echo html_writer::start_div('local-quessor-native-card local-quessor-native-card--wide');
        echo html_writer::tag('span', get_string($label, 'local_quessor'), ['class' => 'local-quessor-native-card__eyebrow']);
        echo html_writer::tag('div', trim($value) !== '' ? format_text($value, FORMAT_PLAIN) : get_string($empty, 'local_quessor'), ['class' => 'local-quessor-native-card__body']);
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
    echo html_writer::end_div();

    echo html_writer::start_div('local-quessor-native__panel', ['data-native-panel' => 'preview', 'hidden' => 'hidden']);
    echo html_writer::start_div('local-quessor-native__summary');
    foreach ([[count($records), 'native_submissions'], [count($ready), 'native_ready'], [$completed, 'native_completed']] as [$number, $label]) {
        echo html_writer::div(html_writer::tag('strong', (string)$number) . html_writer::tag('span', get_string($label, 'local_quessor')), 'local-quessor-native-stat');
    }
    echo html_writer::end_div();
    if (!$records) {
        echo html_writer::div(get_string('native_no_submissions', 'local_quessor'), 'local-quessor-native-empty');
    } else {
        echo html_writer::start_div('local-quessor-native-table-wrap');
        echo html_writer::start_tag('table', ['class' => 'local-quessor-native-table']);
        echo html_writer::tag('thead', html_writer::tag('tr', html_writer::tag('th', get_string('student', 'local_quessor')) . html_writer::tag('th', get_string('status', 'local_quessor')) . html_writer::tag('th', get_string('grade', 'local_quessor')) . html_writer::tag('th', get_string('feedback', 'local_quessor')) . html_writer::tag('th', '')));
        echo html_writer::start_tag('tbody');
        foreach ($records as $record) {
            $user = $users[(int)$record->userid] ?? null;
            $name = $user ? fullname($user) : ('#' . (int)$record->userid);
            $status = $statuslabels[$record->status] ?? $record->status;
            $report = trim((string)$record->reporturl);
            $reportlink = $report !== '' ? html_writer::link($report, get_string('report', 'local_quessor'), ['target' => '_blank', 'rel' => 'noopener']) : '—';
            echo html_writer::tag('tr', html_writer::tag('td', format_string($name)) . html_writer::tag('td', $status) . html_writer::tag('td', $record->grade === null ? '—' : format_float((float)$record->grade, 2)) . html_writer::tag('td', trim((string)$record->feedback) !== '' ? format_text((string)$record->feedback, FORMAT_PLAIN) : '—') . html_writer::tag('td', $reportlink));
        }
        echo html_writer::end_tag('tbody') . html_writer::end_tag('table') . html_writer::end_div();
    }
    echo html_writer::start_div('local-quessor-native__actions');
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => (new moodle_url('/local/quessor/view.php', ['cmid' => $cmid]))->out(false)]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'launch']);
    echo html_writer::tag('button', get_string('launchwizard', 'local_quessor'), ['type' => 'submit', 'class' => 'btn btn-primary']);
    echo html_writer::end_tag('form');
    echo html_writer::link($wizardurl, get_string('openinnewtab', 'local_quessor'), ['class' => 'btn btn-outline-secondary', 'target' => '_blank', 'rel' => 'noopener']);
    echo html_writer::end_div() . html_writer::end_div() . html_writer::end_div();

    $PAGE->requires->js_call_amd('local_quessor/native_tabs', 'init');
}

/**
 * Adds Quessor to Moodle's primary/global navigation for users who can grade
 * at least one course assignment.
 */
function local_quessor_extend_navigation(global_navigation $navigation): void {
    global $USER;
    if (!isloggedin() || isguestuser()) {
        return;
    }

    $courses = get_user_capability_course('local/quessor:gradeassignments', null, false, '', '', 1);
    $canmanagebilling = \local_quessor\local\bridge::is_billing_admin();
    if (!$courses && !$canmanagebilling) {
        return;
    }

    $navigation->add(
        get_string('hub', 'local_quessor'),
        new moodle_url('/local/quessor/index.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_quessor_hub'
    );
}

function local_quessor_extend_navigation_course(navigation_node $navigation, stdClass $course, context_course $context): void {
    if (!has_capability('local/quessor:gradeassignments', $context)) {
        return;
    }
    $url = new moodle_url('/local/quessor/course.php', ['courseid' => $course->id]);
    $node = $navigation->add(
        get_string('quessor', 'local_quessor'),
        $url,
        navigation_node::TYPE_CUSTOM,
        null,
        'local_quessor_course',
    );
    $node->set_show_in_secondary_navigation(true);
    $node->set_force_into_more_menu(false);
}

/**
 * Adds a direct link to Quessor on an assignment's secondary navigation.
 */
function local_quessor_extend_settings_navigation(settings_navigation $settingsnav, context $context): void {
    $page = $settingsnav->get_page();
    $cm = $page->cm;
    if (!$cm || !in_array($cm->modname, \local_quessor\local\activity_service::SUPPORTED_TYPES, true)) {
        return;
    }

    $coursecontext = context_course::instance($cm->course);
    if (
        !has_capability('local/quessor:gradeassignments', $coursecontext)
        || !\local_quessor\local\activity_service::can_grade($cm, $cm->context)
    ) {
        return;
    }

    $modulenavigation = $settingsnav->find('modulesettings', navigation_node::TYPE_SETTING);
    if (!$modulenavigation) {
        return;
    }

    $node = $modulenavigation->add(
        get_string('quessor', 'local_quessor'),
        new moodle_url('/local/quessor/view.php', ['cmid' => $cm->id]),
        navigation_node::TYPE_SETTING,
        null,
        'local_quessor',
    );
    $node->set_force_into_more_menu(false);
}
