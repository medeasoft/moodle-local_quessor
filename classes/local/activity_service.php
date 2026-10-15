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

namespace local_quessor\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Normalises Moodle activities into one Quessor evaluation contract.
 *
 * Supported sources: Assignment, Quiz attempts, Workshop submissions,
 * and a student's complete contribution to a Forum.
 */
final class activity_service {
    public const SUPPORTED_TYPES = ['assign', 'quiz', 'workshop', 'forum'];

    /** Persist explicit teacher consent for syncing this activity's submissions. */
    public static function connect(int $cmid): void {
        $connected = json_decode((string)get_config('local_quessor', 'connectedactivities'), true);
        $connected = is_array($connected) ? array_map('intval', $connected) : [];
        $connected[] = $cmid;
        set_config('connectedactivities', json_encode(array_values(array_unique($connected))), 'local_quessor');
    }

    public static function is_connected(int $cmid): bool {
        $connected = json_decode((string)get_config('local_quessor', 'connectedactivities'), true);
        return is_array($connected) && in_array($cmid, array_map('intval', $connected), true);
    }

    public static function load(int $cmid): array {
        global $DB;
        [$course, $cm] = get_course_and_cm_from_cmid($cmid);
        if (!in_array($cm->modname, self::SUPPORTED_TYPES, true)) {
            throw new \moodle_exception('unsupportedactivity', 'local_quessor');
        }
        $instance = $DB->get_record($cm->modname, ['id' => $cm->instance], '*', MUST_EXIST);
        if (!self::supports_evaluation($cm, $instance)) {
            throw new \moodle_exception('unsupportedactivity', 'local_quessor');
        }
        return [$course, $cm, \context_module::instance($cm->id), $instance];
    }

    public static function backend_activity_id(\cm_info $cm): string {
        // Keep legacy Assignment mappings stable; prefixes avoid collisions for
        // the newly supported module tables, whose numeric ids are independent.
        return $cm->modname === 'assign' ? (string)$cm->instance : $cm->modname . ':' . $cm->instance;
    }

    public static function can_grade(\cm_info $cm, \context_module $context): bool {
        $capabilities = [
            'assign' => 'mod/assign:grade',
            'quiz' => 'mod/quiz:grade',
            'workshop' => 'mod/workshop:overridegrades',
            'forum' => 'mod/forum:grade',
        ];
        return has_capability($capabilities[$cm->modname], $context);
    }

    /** Respect separate-group visibility when handling student records. */
    public static function can_access_student(int $courseid, int $userid): bool {
        global $USER;
        $coursecontext = \context_course::instance($courseid);
        $course = get_course($courseid);
        if (
            $userid === (int)$USER->id || (int)groups_get_course_groupmode($course) !== SEPARATEGROUPS
            || has_capability('moodle/site:accessallgroups', $coursecontext)
        ) {
            return true;
        }
        $teachergroups = groups_get_user_groups($courseid, (int)$USER->id)[0] ?? [];
        $studentgroups = groups_get_user_groups($courseid, $userid)[0] ?? [];
        return (bool)array_intersect($teachergroups, $studentgroups);
    }

    /**
     * Gradable activities in a course that the current user can send to Quessor.
     *
     * @return array<int, array{id:int, name:string, submissioncount:int, label:string}>
     */
    public static function list_gradable_activities(\stdClass $course): array {
        global $DB;
        $modinfo = get_fast_modinfo($course);

        $instancesbytype = [];
        $validcms = [];
        foreach (self::SUPPORTED_TYPES as $type) {
            foreach ($modinfo->get_instances_of($type) as $cm) {
                if (!$cm->uservisible) {
                    continue;
                }
                $modulecontext = \context_module::instance($cm->id);
                if (!self::can_grade($cm, $modulecontext)) {
                    continue;
                }
                $instance = $DB->get_record($cm->modname, ['id' => $cm->instance]);
                if (!$instance || !self::supports_evaluation($cm, $instance)) {
                    continue;
                }
                $instancesbytype[$type][] = (int)$cm->instance;
                $validcms[] = $cm;
            }
        }

        if (empty($validcms)) {
            return [];
        }

        $countsbytype = [
            'assign' => [],
            'quiz' => [],
            'workshop' => [],
            'forum' => [],
        ];

        if (!empty($instancesbytype['assign'])) {
            [$insql, $inparams] = $DB->get_in_or_equal($instancesbytype['assign']);
            $records = $DB->get_records_sql(
                "SELECT assignment AS instanceid, COUNT(DISTINCT userid) AS count
                   FROM {assign_submission}
                  WHERE assignment $insql AND status IN ('submitted', 'reopened')
               GROUP BY assignment",
                $inparams
            );
            foreach ($records as $r) {
                $countsbytype['assign'][(int)$r->instanceid] = (int)$r->count;
            }
        }

        if (!empty($instancesbytype['quiz'])) {
            [$insql, $inparams] = $DB->get_in_or_equal($instancesbytype['quiz']);
            $records = $DB->get_records_sql(
                "SELECT quiz AS instanceid, COUNT(DISTINCT userid) AS count
                   FROM {quiz_attempts}
                  WHERE quiz $insql AND state = 'finished' AND preview = 0
               GROUP BY quiz",
                $inparams
            );
            foreach ($records as $r) {
                $countsbytype['quiz'][(int)$r->instanceid] = (int)$r->count;
            }
        }

        if (!empty($instancesbytype['workshop'])) {
            [$insql, $inparams] = $DB->get_in_or_equal($instancesbytype['workshop']);
            $records = $DB->get_records_sql(
                "SELECT workshopid AS instanceid, COUNT(DISTINCT authorid) AS count
                   FROM {workshop_submissions}
                  WHERE workshopid $insql
               GROUP BY workshopid",
                $inparams
            );
            foreach ($records as $r) {
                $countsbytype['workshop'][(int)$r->instanceid] = (int)$r->count;
            }
        }

        if (!empty($instancesbytype['forum'])) {
            [$insql, $inparams] = $DB->get_in_or_equal($instancesbytype['forum']);
            $records = $DB->get_records_sql(
                "SELECT d.forum AS instanceid, COUNT(DISTINCT p.userid) AS count
                   FROM {forum_posts} p
                   JOIN {forum_discussions} d ON d.id = p.discussion
                  WHERE d.forum $insql
               GROUP BY d.forum",
                $inparams
            );
            foreach ($records as $r) {
                $countsbytype['forum'][(int)$r->instanceid] = (int)$r->count;
            }
        }

        $assignments = [];
        foreach ($validcms as $cm) {
            $type = $cm->modname;
            $submittedcount = $countsbytype[$type][(int)$cm->instance] ?? 0;
            $assignments[$cm->id] = [
                'id' => (int)$cm->id,
                'name' => format_string($cm->name),
                'submissioncount' => $submittedcount,
                'label' => get_string('activitywithsubmissions', 'local_quessor', (object)[
                    'type' => get_string('modulename', $type),
                    'name' => format_string($cm->name),
                    'count' => $submittedcount,
                ]),
            ];
        }

        uasort($assignments, static function (array $left, array $right): int {
            return strnatcasecmp($left['name'], $right['name']);
        });
        return $assignments;
    }

    public static function activity_payload(\stdClass $course, \cm_info $cm, \context_module $context, \stdClass $instance): array {
        $intro = html_to_text(format_text(
            (string)($instance->intro ?? ''),
            (int)($instance->introformat ?? FORMAT_HTML),
            ['context' => $context],
        ));
        $hassources = self::has_question_source_files($cm, $context);
        return [
            'moodleCourseId' => (string)$course->id,
            'moodleAssignmentId' => self::backend_activity_id($cm),
            'activityType' => $cm->modname,
            'courseName' => format_string($course->fullname),
            'assignmentTitle' => format_string($instance->name),
            'assignmentDescription' => $intro ?: format_string($instance->name),
            'rubric' => self::rubric($cm, $context, $instance),
            'questions' => $hassources ? [] : self::questions($cm, $instance, $intro),
            'questionSourceAttached' => $hassources,
            'courseContext' => course_context_service::build($course, $cm->id),
            'maxScore' => self::max_score($cm, $instance),
        ];
    }

    /**
     * Prepare all teacher-uploaded source files.
     * Files are converted to PDF where Moodle's configured converter supports
     * them; this keeps Excel/Word/OpenDocument sources available to Quessor.
     */
    public static function question_source_files(\cm_info $cm, \context_module $context, string $intro = ''): array {
        // Only Assignment uses an uploaded brief/exam paper as the canonical
        // question source. Quiz questions and Workshop/Forum instructions are
        // already structured Moodle data and must not be replaced by an intro
        // attachment.
        if ($cm->modname !== 'assign') {
            return [];
        }
        $fs = get_file_storage();
        $component = 'mod_' . $cm->modname;
        $stored = [];
        foreach ($fs->get_area_files($context->id, $component, 'introattachment', 0, 'filepath, filename', false) as $file) {
            $key = $file->get_contenthash() . ':' . $file->get_filename();
            $stored[$key] = $file;
        }

        // The activity description is already sent as structured metadata. It
        // must never become a second PDF because AI can mistake generic
        // delivery instructions for an additional exam question.
        $prepared = [];
        $converter = new \core_files\converter();
        foreach ($stored as $file) {
            $result = assignment_service::prepare_question_file($file, $converter);
            if ($result['state'] === 'pending') {
                throw new \moodle_exception('conversionpendingfile', 'local_quessor', '', $file->get_filename());
            }
            if ($result['state'] !== 'ready') {
                throw new \moodle_exception('conversionunsupportedfile', 'local_quessor', '', $file->get_filename());
            }
            $prepared[] = $result['file'];
        }
        return $prepared;
    }

    private static function has_question_source_files(\cm_info $cm, \context_module $context): bool {
        if ($cm->modname !== 'assign') {
            return false;
        }
        $component = 'mod_' . $cm->modname;
        $fs = get_file_storage();
        return count($fs->get_area_files($context->id, $component, 'introattachment', 0, 'filename', false)) > 0;
    }

    /** Whether an activity has content that Quessor can grade safely. */
    private static function supports_evaluation(\cm_info $cm, \stdClass $instance): bool {
        if ($cm->modname === 'quiz') {
            return self::quiz_questions_for_evaluation($instance) !== [];
        }
        if ($cm->modname === 'forum') {
            // Announcement forums are communication spaces. Other forum types
            // may intentionally be evaluated in Quessor even when the teacher
            // has not enabled Moodle's native whole-forum grading yet.
            return (string)($instance->type ?? '') !== 'news';
        }
        return true;
    }

    public static function submissions(\stdClass $course, \cm_info $cm, \context_module $context, \stdClass $instance): array {
        return match ($cm->modname) {
            'assign' => self::assignment_submissions($context, $instance),
            'quiz' => self::quiz_submissions($instance),
            'workshop' => self::workshop_submissions($context, $instance),
            'forum' => self::forum_submissions($context, $instance),
        };
    }

    public static function latest_for_user(int $cmid, int $userid): ?array {
        [$course, $cm, $context, $instance] = self::load($cmid);
        foreach (self::submissions($course, $cm, $context, $instance) as $submission) {
            if ((int)$submission['userid'] === $userid) {
                return $submission;
            }
        }
        return null;
    }

    public static function sync_grade(int $cmid, int $userid, float $grade, ?string $feedback): void {
        global $CFG, $DB;
        [$course, $cm, $context, $instance] = self::load($cmid);
        if (!self::can_grade($cm, $context) || !self::can_access_student((int)$course->id, $userid)) {
            throw new \required_capability_exception($context, 'local/quessor:gradeassignments', 'nopermissions', '');
        }
        if ($cm->modname === 'assign') {
            assignment_service::sync_grade($cmid, $userid, $grade, $feedback);
            return;
        }
        if ($cm->modname === 'quiz') {
            self::sync_quiz_grade($instance, $userid, $grade, $feedback);
            return;
        } else if ($cm->modname === 'forum') {
            self::sync_forum_grade($cm, $instance, $userid, $grade, $feedback);
            return;
        } else if ($cm->modname === 'workshop') {
            $submission = $DB->get_record('workshop_submissions', [
                'workshopid' => $instance->id,
                'authorid' => $userid,
            ]);
            if ($submission) {
                require_once($CFG->dirroot . '/mod/workshop/locallib.php');
                $workshop = new \workshop($instance, $cm, $course, $context);
                $workshop->evaluate_submission(
                    $submission,
                    (object)[
                        'gradeover' => min(self::max_score($cm, $instance), max(0, $grade)),
                        'feedbackauthor_editor' => [
                            'text' => $feedback ?? '',
                            'format' => FORMAT_PLAIN,
                            'itemid' => 0,
                        ],
                    ],
                    false,
                    true,
                );
                workshop_update_grades($instance, $userid);
            }
            return;
        }
    }

    /** Apply Quessor's score to the latest finished attempt using Quiz question grading APIs. */
    private static function sync_quiz_grade(\stdClass $quiz, int $userid, float $grade, ?string $feedback): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $attempt = $DB->get_record_sql(
            "SELECT * FROM {quiz_attempts}
              WHERE quiz = :quizid AND userid = :userid AND state = 'finished' AND preview = 0
              ORDER BY attempt DESC, timefinish DESC, id DESC",
            ['quizid' => $quiz->id, 'userid' => $userid],
            IGNORE_MULTIPLE,
        );
        if (!$attempt) {
            throw new \moodle_exception('quizgradefailed', 'local_quessor');
        }

        $quizattempt = \mod_quiz\quiz_attempt::create((int)$attempt->id);
        $usage = $quizattempt->get_question_usage();
        $slots = $usage->get_slots();
        $quizmax = (float)$quiz->grade;
        $sumgrades = (float)$quiz->sumgrades;
        if (!$slots || $quizmax <= 0 || $sumgrades <= 0) {
            throw new \moodle_exception('quizgradefailed', 'local_quessor');
        }

        $target = $sumgrades * min($quizmax, max(0, $grade)) / $quizmax;
        $totalmaxmark = 0.0;
        foreach ($slots as $slot) {
            $totalmaxmark += (float)$usage->get_question_attempt($slot)->get_max_mark();
        }
        if ($totalmaxmark <= 0) {
            throw new \moodle_exception('quizgradefailed', 'local_quessor');
        }

        $comment = $feedback ?? '';
        foreach ($slots as $slot) {
            $maxmark = (float)$usage->get_question_attempt($slot)->get_max_mark();
            $mark = $target * $maxmark / $totalmaxmark;
            $usage->manual_grade((int)$slot, $comment, $mark, FORMAT_PLAIN);
        }
        \question_engine::save_questions_usage_by_activity($usage);

        // Moodle's own quiz tests persist the question usage then update this
        // attempt summary before asking the quiz grade calculator to recompute.
        $attempt->sumgrades = $usage->get_total_mark();
        $attempt->timemodified = time();
        $attempt->gradednotificationsenttime = null;
        $DB->update_record('quiz_attempts', $attempt);
        $quizattempt->get_quizobj()->get_grade_calculator()->recompute_final_grade($userid);
        quiz_update_grades($quiz, $userid);
    }

    /** Save a whole-forum grade through the module's grade item API. */
    private static function sync_forum_grade(\cm_info $cm, \stdClass $forum, int $userid, float $grade, ?string $feedback): void {
        global $CFG;
        if ((float)$forum->grade_forum <= 0) {
            throw new \moodle_exception('forumgradeunconfigured', 'local_quessor');
        }
        require_once($CFG->dirroot . '/mod/forum/lib.php');
        $forum->cmidnumber = $cm->idnumber;
        $gradeitem = [
            'userid' => $userid,
            'rawgrade' => min((float)$forum->grade_forum, max(0, $grade)),
        ];
        if ($feedback !== null) {
            $gradeitem['feedback'] = $feedback;
            $gradeitem['feedbackformat'] = FORMAT_PLAIN;
        }
        forum_grade_item_update($forum, null, [$userid => $gradeitem]);
    }

    private static function assignment_submissions(\context_module $context, \stdClass $instance): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        $records = $DB->get_records_select(
            'assign_submission',
            "assignment = :id AND status IN ('submitted', 'reopened')",
            ['id' => $instance->id],
            'attemptnumber DESC, timemodified DESC, id DESC',
        );
        $result = [];
        foreach ($records as $record) {
            if (!self::is_learner($instance->course, $record->userid)) {
                continue;
            }
            if (isset($result[$record->userid])) {
                continue;
            }
            $prepared = assignment_service::prepare_submission($context, $record);
            $result[$record->userid] = self::normalise($record->id, $record->userid, $record->attemptnumber, $prepared);
        }
        return array_values($result);
    }

    private static function quiz_submissions(\stdClass $quiz): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $attempts = $DB->get_records_select(
            'quiz_attempts',
            "quiz = :quizid AND state = 'finished' AND preview = 0",
            ['quizid' => $quiz->id],
            'attempt DESC, timefinish DESC',
        );
        $result = [];
        foreach ($attempts as $attempt) {
            if (!self::is_learner($quiz->course, $attempt->userid)) {
                continue;
            }
            if (isset($result[$attempt->userid])) {
                continue;
            }
            [$text, $files] = self::without_core_deprecation_warnings(
                static function () use ($attempt): array {
                    $quizattempt = \mod_quiz\quiz_attempt::create($attempt->id);
                    return self::quiz_attempt_content($quizattempt);
                }
            );
            $prepared = assignment_service::prepare_content($text, $files, [
                'quizattempt:' . $attempt->id,
                'attempt:' . $attempt->attempt,
                'modified:' . $attempt->timemodified,
            ], 'quiz-attempt-' . $attempt->attempt, true);
            if ($prepared['state'] !== 'empty') {
                $result[$attempt->userid] = self::normalise($attempt->id, $attempt->userid, $attempt->attempt, $prepared);
            }
        }
        return array_values($result);
    }

    private static function workshop_submissions(\context_module $context, \stdClass $workshop): array {
        global $DB;
        $records = $DB->get_records('workshop_submissions', ['workshopid' => $workshop->id], 'timemodified DESC, id DESC');
        $result = [];
        foreach ($records as $record) {
            if (!self::is_learner($workshop->course, $record->authorid)) {
                continue;
            }
            if (isset($result[$record->authorid])) {
                continue;
            }
            $files = array_values(get_file_storage()->get_area_files(
                $context->id,
                'mod_workshop',
                'submission_attachment',
                $record->id,
                'filepath,filename',
                false,
            ));
            $files = array_merge($files, array_values(get_file_storage()->get_area_files(
                $context->id,
                'mod_workshop',
                'submission_content',
                $record->id,
                'filepath,filename',
                false,
            )));
            $text = format_string($record->title) . "\n\n" . html_to_text((string)$record->content, 0, false);
            $prepared = assignment_service::prepare_content($text, $files, [
                'workshop:' . $record->id,
                'modified:' . $record->timemodified,
            ], 'workshop-submission-' . $record->id);
            $result[$record->authorid] = self::normalise($record->id, $record->authorid, 0, $prepared);
        }
        return array_values($result);
    }

    private static function forum_submissions(\context_module $context, \stdClass $forum): array {
        global $DB;
        $posts = $DB->get_records_sql(
            "SELECT p.* FROM {forum_posts} p JOIN {forum_discussions} d ON d.id = p.discussion
              WHERE d.forum = :forumid ORDER BY p.modified ASC, p.id ASC",
            ['forumid' => $forum->id],
        );
        $byuser = [];
        foreach ($posts as $post) {
            if (!self::is_learner($forum->course, $post->userid)) {
                continue;
            }
            $byuser[$post->userid][] = $post;
        }
        $result = [];
        foreach ($byuser as $userid => $userposts) {
            $parts = [];
            $files = [];
            $latest = end($userposts);
            foreach ($userposts as $post) {
                $parts[] = format_string($post->subject) . "\n" . html_to_text((string)$post->message, 0, false);
                $files = array_merge($files, array_values(get_file_storage()->get_area_files(
                    $context->id,
                    'mod_forum',
                    'attachment',
                    $post->id,
                    'filepath,filename',
                    false,
                )), array_values(get_file_storage()->get_area_files(
                    $context->id,
                    'mod_forum',
                    'post',
                    $post->id,
                    'filepath,filename',
                    false,
                )));
            }
            $prepared = assignment_service::prepare_content(implode("\n\n---\n\n", $parts), $files, [
                'forum:' . $forum->id,
                'latest:' . $latest->id . ':' . $latest->modified,
                'count:' . count($userposts),
            ], 'forum-contribution-' . $userid);
            // One stable logical submission per student; sourceHash tracks edits/new posts.
            $result[$userid] = self::normalise($userid, $userid, count($userposts) - 1, $prepared);
        }
        return array_values($result);
    }

    private static function normalise(int $sourceid, int $userid, int $attempt, array $prepared): array {
        return [
            'submissionid' => $sourceid,
            'userid' => $userid,
            'attemptnumber' => max(0, $attempt),
            'state' => $prepared['state'],
            'files' => $prepared['files'],
            'sourceHash' => $prepared['sourceHash'],
        ];
    }

    private static function max_score(\cm_info $cm, \stdClass $instance): float {
        if ($cm->modname === 'quiz') {
            return max(1, (float)$instance->grade);
        }
        $configured = $cm->modname === 'forum'
            ? (float)($instance->grade_forum ?? 0)
            : (float)($instance->grade ?? 0);
        return max(1, $configured > 0 ? $configured : 100);
    }

    /**
     * Return the activity's gradable prompts as separate Quessor questions.
     * Every quiz question is included. Question type is not a reason to drop one.
     *
     * @return array<int, array{name:string, text:string, point:float, gradingInstructions:string}>
     */
    private static function questions(\cm_info $cm, \stdClass $instance, string $intro): array {
        $maxscore = self::max_score($cm, $instance);
        if ($cm->modname !== 'quiz') {
            $prompt = self::activity_prompt($cm, $instance, $intro);
            return [[
                'name' => format_string($instance->name),
                'text' => $prompt !== '' ? $prompt : format_string($instance->name),
                'point' => $maxscore,
                'gradingInstructions' => self::rubric($cm, \context_module::instance($cm->id), $instance),
            ]];
        }

        $quizquestions = self::quiz_questions_for_evaluation($instance);
        if (!$quizquestions) {
            return [];
        }

        $rawtotal = array_sum(array_map(static fn(array $question): float => max(0, $question['maxmark']), $quizquestions));
        $questions = [];
        foreach ($quizquestions as $question) {
            $point = $rawtotal > 0 ? $maxscore * max(0, $question['maxmark']) / $rawtotal : $maxscore / count($quizquestions);
            $questions[] = [
                'name' => $question['name'] !== '' ? $question['name'] : format_string($instance->name),
                'text' => $question['questiontext'] !== '' ? $question['questiontext'] : $question['name'],
                'point' => $point,
                'gradingInstructions' => $question['graderinfo'],
            ];
        }
        return $questions;
    }

    /** Build the complete teacher-authored prompt for non-Quiz activities. */
    private static function activity_prompt(\cm_info $cm, \stdClass $instance, string $intro): string {
        $parts = [];
        if (trim($intro) !== '') {
            $parts[] = trim($intro);
        }
        if ($cm->modname === 'workshop') {
            $authorinstructions = trim(html_to_text((string)($instance->instructauthors ?? ''), 0, false));
            if ($authorinstructions !== '') {
                $parts[] = $authorinstructions;
            }
        } else if ($cm->modname === 'forum') {
            $starterprompts = self::forum_starter_prompts($instance);
            if ($starterprompts !== '') {
                $parts[] = $starterprompts;
            }
        }
        return trim(implode("\n\n", array_unique($parts)));
    }

    /** Include teacher-authored discussion openers that contain the real task. */
    private static function forum_starter_prompts(\stdClass $forum): string {
        global $DB;
        $posts = $DB->get_records_sql(
            "SELECT p.*
               FROM {forum_discussions} d
               JOIN {forum_posts} p ON p.id = d.firstpost
              WHERE d.forum = :forumid
           ORDER BY d.timemodified ASC, d.id ASC",
            ['forumid' => $forum->id],
        );
        $parts = [];
        foreach ($posts as $post) {
            if (self::is_learner((int)$forum->course, (int)$post->userid)) {
                continue;
            }
            $subject = trim(format_string((string)$post->subject));
            $message = trim(html_to_text((string)$post->message, 0, false));
            $prompt = trim($subject . ($message !== '' ? "\n" . $message : ''));
            if ($prompt !== '') {
                $parts[] = $prompt;
            }
        }
        return trim(implode("\n\n---\n\n", array_unique($parts)));
    }

    private static function is_learner(int $courseid, int $userid): bool {
        global $DB;

        $context = \context_course::instance($courseid);
        // The activity record is authoritative evidence that this user owns a
        // response. Historical courses can retain assessable work after an
        // enrolment ends or the Moodle account is deleted. Requiring an active
        // enrolment made those responses appear in the count but vanish at sync.
        return $DB->record_exists('user', ['id' => $userid])
            && !has_capability('local/quessor:gradeassignments', $context, $userid)
            && self::can_access_student($courseid, $userid);
    }

    /** Export every answered slot. Description blocks are not questions. */
    private static function quiz_attempt_content(\mod_quiz\quiz_attempt $quizattempt): array {
        $contextid = $quizattempt->get_quizobj()->get_context()->id;
        $parts = [];
        $filesout = [];
        foreach ($quizattempt->get_slots() as $slot) {
            $qa = $quizattempt->get_question_attempt($slot);
            $question = $qa->get_question();
            $type = strtolower((string)$question->get_type_name());
            if ($type === 'description') {
                continue;
            }
            $name = trim(strip_tags((string)$question->name));
            $header = $name !== '' ? $name : 'Question ' . $slot;
            $text = self::quiz_response_text($qa);
            $files = [];
            try {
                $files = array_merge(
                    array_values($qa->get_last_qt_files('attachments', $contextid) ?: []),
                    array_values($qa->get_last_qt_files('answer', $contextid) ?: []),
                );
            } catch (\Throwable $e) {
                $files = [];
            }
            $parts[] = $header . "\n" . ($text !== '' ? $text : '(no response)');
            $filesout = array_merge($filesout, $files);
        }
        return [implode("\n\n", $parts), $filesout];
    }

    private static function quiz_response_text(\question_attempt $qa): string {
        $summary = trim(html_to_text((string)$qa->get_response_summary(), 0, false));
        if ($summary !== '') {
            return $summary;
        }
        $raw = $qa->get_last_qt_var('answer');
        if (is_array($raw)) {
            $parts = [];
            foreach ($raw as $value) {
                if (is_scalar($value) && trim((string)$value) !== '') {
                    $parts[] = (string)$value;
                }
            }
            $raw = implode("\n", $parts);
        }
        return trim(html_to_text((string)$raw, 0, false));
    }

    private static function rubric(\cm_info $cm, \context_module $context, \stdClass $instance): string {
        $text = match ($cm->modname) {
            'assign' => assignment_service::advanced_grading_text($context, 'mod_assign', 'submissions'),
            'forum' => self::forum_criteria($context, $instance),
            'workshop' => self::workshop_criteria($instance),
            'quiz' => self::quiz_criteria($instance),
            default => '',
        };
        if ($text === '') {
            return '';
        }
        if (\core_text::strlen($text) > 50_000) {
            return \core_text::substr($text, 0, 50_000);
        }
        return $text;
    }

    private static function forum_criteria(\context_module $context, \stdClass $forum): string {
        global $DB;
        $parts = [];
        $grading = assignment_service::advanced_grading_text($context, 'mod_forum', 'forum');
        if ($grading !== '') {
            $parts[] = $grading;
        }
        $grade = (float)($forum->grade_forum ?? 0);
        $scaleid = $grade < 0 ? (int)abs($grade) : 0;
        if ($scaleid === 0 && (int)($forum->assessed ?? 0) !== 0 && (int)($forum->scale ?? 0) < 0) {
            $scaleid = (int)abs((int)$forum->scale);
        }
        if ($scaleid > 0) {
            $scale = $DB->get_record('scale', ['id' => $scaleid]);
            if ($scale && trim((string)$scale->scale) !== '') {
                $items = array_map('trim', explode(',', (string)$scale->scale));
                $parts[] = format_string($scale->name) . ': ' . implode('; ', $items);
            }
        }
        return trim(implode("\n\n", $parts));
    }

    private static function workshop_criteria(\stdClass $workshop): string {
        global $DB;
        $parts = [];
        $review = trim(html_to_text((string)($workshop->instructreviewers ?? ''), 0, false));
        if ($review !== '') {
            $parts[] = $review;
        }

        $strategy = (string)($workshop->strategy ?? 'accumulative');
        $dbman = $DB->get_manager();
        if ($strategy === 'accumulative' && $dbman->table_exists('workshopform_accumulative')) {
            $rows = $DB->get_records('workshopform_accumulative', ['workshopid' => $workshop->id], 'sort ASC, id ASC');
            foreach ($rows as $row) {
                $description = trim(html_to_text((string)$row->description, 0, false));
                if ($description === '') {
                    continue;
                }
                $parts[] = $description . ' (max ' . $row->grade . ', weight ' . $row->weight . ')';
            }
        } else if ($strategy === 'comments' && $dbman->table_exists('workshopform_comments')) {
            $rows = $DB->get_records('workshopform_comments', ['workshopid' => $workshop->id], 'sort ASC, id ASC');
            foreach ($rows as $row) {
                $description = trim(html_to_text((string)$row->description, 0, false));
                if ($description !== '') {
                    $parts[] = $description;
                }
            }
        } else if ($strategy === 'numerrors' && $dbman->table_exists('workshopform_numerrors')) {
            $rows = $DB->get_records('workshopform_numerrors', ['workshopid' => $workshop->id], 'sort ASC, id ASC');
            foreach ($rows as $row) {
                $description = trim(html_to_text((string)$row->description, 0, false));
                if ($description === '') {
                    continue;
                }
                $parts[] = $description . ' (pass: ' . trim(strip_tags((string)($row->grade1 ?? '')))
                    . '; fail: ' . trim(strip_tags((string)($row->grade0 ?? '')))
                    . '; weight ' . $row->weight . ')';
            }
        } else if ($strategy === 'rubric' && $dbman->table_exists('workshopform_rubric')) {
            $dimensions = $DB->get_records('workshopform_rubric', ['workshopid' => $workshop->id], 'sort ASC, id ASC');
            foreach ($dimensions as $dimension) {
                $description = trim(html_to_text((string)$dimension->description, 0, false));
                $levels = [];
                if ($dbman->table_exists('workshopform_rubric_levels')) {
                    $levelrows = $DB->get_records(
                        'workshopform_rubric_levels',
                        ['dimensionid' => $dimension->id],
                        'grade ASC, id ASC',
                    );
                    foreach ($levelrows as $level) {
                        $levels[] = trim(strip_tags((string)$level->definition)) . ' (' . $level->grade . ')';
                    }
                }
                $parts[] = $levels ? $description . ': ' . implode('; ', $levels) : $description;
            }
        }

        return trim(implode("\n", array_filter($parts)));
    }

    private static function quiz_criteria(\stdClass $quiz): string {
        $lines = [];
        foreach (self::quiz_questions_for_evaluation($quiz) as $question) {
            $header = $question['name'] !== ''
                ? $question['name'] . ' (max ' . $question['maxmark'] . ')'
                : 'Question (max ' . $question['maxmark'] . ')';
            $chunks = [$header];
            if ($question['questiontext'] !== '') {
                $chunks[] = $question['questiontext'];
            }
            if ($question['graderinfo'] !== '') {
                $chunks[] = $question['graderinfo'];
            }
            $lines[] = implode("\n", $chunks);
        }
        return trim(implode("\n\n", $lines));
    }

    /**
     * Every configured quiz question. Random slots are filled from a finished
     * attempt because Moodle does not store one fixed question for them.
     *
     * @return array<int, array{questionid:int, name:string, questiontext:string, graderinfo:string, maxmark:float}>
     */
    private static function quiz_questions_for_evaluation(\stdClass $quiz): array {
        $questions = self::normalise_quiz_question_rows(
            self::configured_quiz_question_rows((int)$quiz->id)
        );
        if (self::quiz_has_random_slots((int)$quiz->id)) {
            $seen = [];
            foreach ($questions as $question) {
                if ($question['questionid'] > 0) {
                    $seen[$question['questionid']] = true;
                }
            }
            foreach (self::questions_from_finished_attempt($quiz) as $question) {
                if ($question['questionid'] > 0 && isset($seen[$question['questionid']])) {
                    continue;
                }
                if ($question['questionid'] > 0) {
                    $seen[$question['questionid']] = true;
                }
                $questions[] = $question;
            }
        }
        return self::with_choice_answer_keys($questions);
    }

    /**
     * @return array<int, array{questionid:int, name:string, questiontext:string, graderinfo:string, maxmark:float}>
     */
    private static function questions_from_finished_attempt(\stdClass $quiz): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        $attempt = $DB->get_record_sql(
            "SELECT * FROM {quiz_attempts}
              WHERE quiz = :quizid AND state = 'finished' AND preview = 0
           ORDER BY timefinish DESC, id DESC",
            ['quizid' => $quiz->id],
            IGNORE_MULTIPLE,
        );
        if (!$attempt) {
            return [];
        }
        $questions = self::without_core_deprecation_warnings(
            static function () use ($attempt, $DB): array {
                $questions = [];
                $quizattempt = \mod_quiz\quiz_attempt::create($attempt->id);
                foreach ($quizattempt->get_slots() as $slot) {
                    $qa = $quizattempt->get_question_attempt($slot);
                    $question = $qa->get_question();
                    if (strtolower((string)$question->get_type_name()) === 'description') {
                        continue;
                    }
                    $graderinfo = trim(html_to_text((string)($question->graderinfo ?? ''), 0, false));
                    if (
                        $graderinfo === '' && !empty($question->id)
                        && $DB->get_manager()->table_exists('qtype_essay_options')
                    ) {
                        $graderinfo = trim(html_to_text(
                            (string)$DB->get_field('qtype_essay_options', 'graderinfo', ['questionid' => $question->id]),
                            0,
                            false,
                        ));
                    }
                    $questions[] = [
                        'questionid' => (int)($question->id ?? 0),
                        'name' => trim(strip_tags((string)$question->name)),
                        'questiontext' => trim(html_to_text((string)$question->questiontext, 0, false)),
                        'graderinfo' => $graderinfo,
                        'maxmark' => (float)$qa->get_max_mark(),
                    ];
                }
                return $questions;
            }
        );
        return $questions ?: [];
    }

    /**
     * Append each question's choices and mark the correct ones for Quessor.
     *
     * @param array<int, array{questionid:int, name:string, questiontext:string, graderinfo:string, maxmark:float}> $questions
     * @return array<int, array{questionid:int, name:string, questiontext:string, graderinfo:string, maxmark:float}>
     */
    private static function with_choice_answer_keys(array $questions): array {
        global $DB;
        $ids = array_values(array_unique(array_filter(array_map(
            static fn(array $question): int => (int)($question['questionid'] ?? 0),
            $questions,
        ))));
        if (!$ids || !$DB->get_manager()->table_exists('question_answers')) {
            return $questions;
        }
        [$insql, $params] = $DB->get_in_or_equal($ids);
        $answers = $DB->get_records_sql(
            "SELECT id, question, answer, fraction
               FROM {question_answers}
              WHERE question $insql
           ORDER BY id ASC",
            $params,
        );
        $byquestion = [];
        foreach ($answers as $answer) {
            $text = trim(html_to_text((string)$answer->answer, 0, false));
            if ($text === '') {
                continue;
            }
            $byquestion[(int)$answer->question][] = [
                'text' => $text,
                'fraction' => (float)$answer->fraction,
            ];
        }
        foreach ($questions as &$question) {
            $choices = $byquestion[(int)($question['questionid'] ?? 0)] ?? [];
            if (!$choices) {
                continue;
            }
            $lines = [];
            $correct = [];
            foreach ($choices as $index => $choice) {
                $label = self::choice_label($index) . ') ' . $choice['text'];
                $lines[] = $label;
                if ($choice['fraction'] > 0) {
                    $correct[] = $label;
                }
            }
            $choicetext = implode("\n", $lines);
            $question['questiontext'] = trim($question['questiontext'] . "\n\n" . $choicetext);
            if ($correct) {
                $key = 'Correct answer: ' . implode('; ', $correct);
                $question['graderinfo'] = trim($key . ($question['graderinfo'] !== '' ? "\n" . $question['graderinfo'] : ''));
            }
        }
        unset($question);
        return $questions;
    }

    private static function choice_label(int $index): string {
        if ($index >= 0 && $index < 26) {
            return chr(65 + $index);
        }
        return (string)($index + 1);
    }

    /**
     * Historical quiz attempts can trigger PHP 8.4 deprecations inside
     * Moodle question types while the quiz API hydrates every slot. Quessor
     * only reads the attempt and must not leak those core warnings into its
     * JSON/AJAX response.
     */
    private static function without_core_deprecation_warnings(callable $callback): mixed {
        $previous = error_reporting();
        error_reporting($previous & ~E_DEPRECATED & ~E_USER_DEPRECATED);
        try {
            return $callback();
        } finally {
            error_reporting($previous);
        }
    }

    private static function quiz_has_random_slots(int $quizid): bool {
        global $DB;
        $dbman = $DB->get_manager();
        if ($dbman->table_exists('question_set_references')) {
            return $DB->record_exists_sql(
                "SELECT 1
                   FROM {quiz_slots} slot
                   JOIN {question_set_references} qsr ON qsr.itemid = slot.id
                    AND qsr.component = 'mod_quiz' AND qsr.questionarea = 'slot'
                  WHERE slot.quizid = :quizid",
                ['quizid' => $quizid],
            );
        }

        $slotstable = new \xmldb_table('quiz_slots');
        if (!$dbman->field_exists($slotstable, new \xmldb_field('questionid'))) {
            return false;
        }
        return $DB->record_exists_sql(
            "SELECT 1
               FROM {quiz_slots} slot
               JOIN {question} q ON q.id = slot.questionid
              WHERE slot.quizid = :quizid AND q.qtype = 'random'",
            ['quizid' => $quizid],
        );
    }

    /** Moodle-version-compatible lookup of every real question on the quiz. */
    private static function configured_quiz_question_rows(int $quizid): array {
        global $DB;
        $dbman = $DB->get_manager();
        $params = ['quizid' => $quizid];
        // Description is page text. Random is a placeholder resolved per attempt.
        $qtypesql = " AND q.qtype NOT IN ('description', 'random')";
        if ($dbman->table_exists('question_references')) {
            return array_values($DB->get_records_sql(
                "SELECT slot.id AS recordid, q.id AS questionid, q.name, q.questiontext,
                        slot.maxmark, eo.graderinfo
                   FROM {quiz_slots} slot
                   JOIN {question_references} qr ON qr.itemid = slot.id
                    AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
                   JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
                   JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                    AND qv.version = COALESCE(qr.version, (
                        SELECT MAX(v.version)
                          FROM {question_versions} v
                         WHERE v.questionbankentryid = qbe.id
                    ))
                   JOIN {question} q ON q.id = qv.questionid
              LEFT JOIN {qtype_essay_options} eo ON eo.questionid = q.id
                  WHERE slot.quizid = :quizid $qtypesql
               ORDER BY slot.slot ASC, slot.id ASC",
                $params,
            ));
        }

        $slotstable = new \xmldb_table('quiz_slots');
        if (!$dbman->field_exists($slotstable, new \xmldb_field('questionid'))) {
            return [];
        }
        return array_values($DB->get_records_sql(
            "SELECT slot.id AS recordid, q.id AS questionid, q.name, q.questiontext,
                    slot.maxmark, eo.graderinfo
               FROM {quiz_slots} slot
               JOIN {question} q ON q.id = slot.questionid
          LEFT JOIN {qtype_essay_options} eo ON eo.questionid = q.id
              WHERE slot.quizid = :quizid $qtypesql
           ORDER BY slot.slot ASC, slot.id ASC",
            $params,
        ));
    }

    private static function normalise_quiz_question_rows(array $rows): array {
        $questions = [];
        foreach ($rows as $row) {
            $questions[] = [
                'questionid' => (int)$row->questionid,
                'name' => trim(strip_tags((string)$row->name)),
                'questiontext' => trim(html_to_text((string)$row->questiontext, 0, false)),
                'graderinfo' => trim(html_to_text((string)($row->graderinfo ?? ''), 0, false)),
                'maxmark' => (float)$row->maxmark,
            ];
        }
        return $questions;
    }
}
