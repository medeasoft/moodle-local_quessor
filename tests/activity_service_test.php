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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License along with Moodle. If not, see <http://www.gnu.org/licenses/>.
//
// @copyright 2026 Medeasoft
// @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later

namespace local_quessor;

defined('MOODLE_INTERNAL') || die();

use advanced_testcase;
use context_course;
use grade_grade;
use grade_item;
use local_quessor\local\activity_service;

/** Tests activity grade synchronization through Moodle's module APIs.
 *
 * @package local_quessor
 */
final class activity_service_test extends advanced_testcase {
    /** @var \stdClass The test course. */
    private $course;

    /** @var \stdClass The teacher who publishes grades. */
    private $teacher;

    /** @var \stdClass The learner whose grade is published. */
    private $student;

    /** Prepare a course, teacher, and learner for each test. */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        $this->teacher = $this->getDataGenerator()->create_user();
        $this->student = $this->getDataGenerator()->create_user();
        $coursecontext = context_course::instance($this->course->id);
        $teacherroles = get_archetype_roles('teacher');
        $teacherrole = reset($teacherroles)->id;
        role_assign($teacherrole, $this->teacher->id, $coursecontext->id);
        assign_capability('local/quessor:gradeassignments', CAP_ALLOW, $teacherrole, $coursecontext->id);
        assign_capability('moodle/site:accessallgroups', CAP_PROHIBIT, $teacherrole, $coursecontext->id);
        $this->getDataGenerator()->enrol_user($this->teacher->id, $this->course->id, $teacherrole);
        $studentroles = get_archetype_roles('student');
        $this->getDataGenerator()->enrol_user($this->student->id, $this->course->id, reset($studentroles)->id);
        $this->setUser($this->teacher);
    }

    /** A published Quiz grade updates the attempt and the activity gradebook item. */
    public function test_quiz_grade_updates_question_attempt_and_gradebook(): void {
        global $DB;

        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $this->course->id,
            'grade' => 10,
        ]);
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');
        $questiongenerator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $questiongenerator->create_question_category();
        $question = $questiongenerator->create_question('essay', null, ['category' => $category->id]);
        quiz_add_quiz_question($question->id, $quiz, 0, 1);
        $quiz->sumgrades = 1;
        $DB->update_record('quiz', $quiz);
        $this->setUser($this->student);
        $attempt = $quizgenerator->create_attempt($quiz->id, $this->student->id);
        $quizgenerator->submit_responses($attempt->id, [], false, true);
        $this->setUser($this->teacher);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $this->course->id, false, MUST_EXIST);

        activity_service::sync_grade((int)$cm->id, (int)$this->student->id, 7.5, 'Teacher feedback');

        $attempt = $DB->get_record('quiz_attempts', ['id' => $attempt->id], '*', MUST_EXIST);
        $this->assertEqualsWithDelta(0.75, (float)$attempt->sumgrades, 0.01);
        $quizattempt = \mod_quiz\quiz_attempt::create((int)$attempt->id);
        $questionattempt = $quizattempt->get_question_usage()->get_question_attempt(1);
        [$comment, , $step] = $questionattempt->get_manual_comment();
        $this->assertEquals('Teacher feedback', $comment);
        $this->assertEquals((int)$this->teacher->id, (int)$step->get_user_id());
        $item = grade_item::fetch([
            'courseid' => $this->course->id,
            'itemtype' => 'mod',
            'itemmodule' => 'quiz',
            'iteminstance' => $quiz->id,
            'itemnumber' => 0,
        ]);
        $grade = grade_grade::fetch(['itemid' => $item->id, 'userid' => $this->student->id]);
        $this->assertEqualsWithDelta(7.5, (float)$grade->rawgrade, 0.01);
        $this->assertEquals((int)$this->teacher->id, (int)$grade->usermodified);
    }

    /** A published Forum grade uses the whole-Forum grade item API and keeps feedback. */
    public function test_forum_grade_uses_forum_grade_item_api(): void {
        $forum = $this->getDataGenerator()->create_module('forum', [
            'course' => $this->course->id,
            'grade_forum' => 10,
        ]);
        $cm = get_coursemodule_from_instance('forum', $forum->id, $this->course->id, false, MUST_EXIST);

        activity_service::sync_grade((int)$cm->id, (int)$this->student->id, 7.5, 'Forum feedback');

        $item = grade_item::fetch([
            'courseid' => $this->course->id,
            'itemtype' => 'mod',
            'itemmodule' => 'forum',
            'iteminstance' => $forum->id,
            'itemnumber' => 1,
        ]);
        $grade = grade_grade::fetch(['itemid' => $item->id, 'userid' => $this->student->id]);
        $this->assertEqualsWithDelta(7.5, (float)$grade->rawgrade, 0.01);
        $this->assertEquals('Forum feedback', $grade->feedback);
        $this->assertEquals((int)$this->teacher->id, (int)$grade->usermodified);
    }

    /** A published Workshop grade is scaled and attributed to the teacher. */
    public function test_workshop_grade_uses_override_api_and_teacher_attribution(): void {
        global $DB;

        $workshop = $this->getDataGenerator()->create_module('workshop', [
            'course' => $this->course->id,
            'grade' => 10,
            'gradinggrade' => 0,
        ]);
        $workshopgenerator = $this->getDataGenerator()->get_plugin_generator('mod_workshop');
        $submissionid = $workshopgenerator->create_submission($workshop->id, $this->student->id);
        $cm = get_coursemodule_from_instance('workshop', $workshop->id, $this->course->id, false, MUST_EXIST);

        activity_service::sync_grade((int)$cm->id, (int)$this->student->id, 7.5, 'Workshop feedback');

        $submission = $DB->get_record('workshop_submissions', ['id' => $submissionid], '*', MUST_EXIST);
        $this->assertEqualsWithDelta(75, (float)$submission->gradeover, 0.01);
        $this->assertEquals((int)$this->teacher->id, (int)$submission->gradeoverby);
        $this->assertEquals('Workshop feedback', $submission->feedbackauthor);
        $item = grade_item::fetch([
            'courseid' => $this->course->id,
            'itemtype' => 'mod',
            'itemmodule' => 'workshop',
            'iteminstance' => $workshop->id,
            'itemnumber' => 0,
        ]);
        $grade = grade_grade::fetch(['itemid' => $item->id, 'userid' => $this->student->id]);
        $this->assertEqualsWithDelta(7.5, (float)$grade->rawgrade, 0.01);
        $this->assertEquals((int)$this->teacher->id, (int)$grade->usermodified);
    }

    /** A non-editing teacher cannot publish grades outside their separate groups. */
    public function test_separate_groups_restrict_grade_access(): void {
        global $COURSE, $DB;

        $this->course->groupmode = SEPARATEGROUPS;
        $DB->update_record('course', $this->course);
        if ((int)$COURSE->id === (int)$this->course->id) {
            $COURSE->groupmode = SEPARATEGROUPS;
        }
        $teachergroup = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        $studentgroup = $this->getDataGenerator()->create_group(['courseid' => $this->course->id]);
        groups_add_member($teachergroup->id, $this->teacher->id);
        groups_add_member($studentgroup->id, $this->student->id);

        $this->assertFalse(activity_service::can_access_student($this->course->id, $this->student->id));
    }
}
