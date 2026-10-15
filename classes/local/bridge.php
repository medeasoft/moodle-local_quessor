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

/** Shared Moodle → Quessor identity, roster, and language helpers. */
final class bridge {
    public static function is_billing_admin(): bool {
        global $USER;
        return is_siteadmin($USER) || has_capability('local/quessor:managebilling', \context_system::instance());
    }

    public static function actor_fields(): array {
        global $USER;
        return [
            'moodleUserId' => (string)$USER->id,
            'moodleUserEmail' => (string)$USER->email,
            'moodleUserFirstName' => $USER->firstname,
            'moodleUserLastName' => $USER->lastname,
            'isMoodleAdmin' => self::is_billing_admin(),
            'feedbackLanguage' => self::feedback_language(),
        ];
    }

    public static function feedback_language(): string {
        $lang = current_language();
        if (str_starts_with($lang, 'tr')) {
            return 'tr';
        }
        if (str_starts_with($lang, 'bg')) {
            return 'bg';
        }
        return 'en';
    }

    /**
     * Enrolled students visible to the current teacher, excluding graders.
     * Separate groups are honoured; suspended accounts are omitted.
     *
     * @return array<int, array{moodleUserId: string, studentFirstName: string, studentLastName: string, studentEmail: string}>
     */
    public static function visible_students(\stdClass $course): array {
        global $USER;

        $coursecontext = \context_course::instance($course->id);
        $fields = 'u.id,u.firstname,u.lastname,u.email';
        $order = 'u.lastname ASC, u.firstname ASC';
        $groupmode = groups_get_course_groupmode($course);
        $roster = [];

        if ((int)$groupmode === SEPARATEGROUPS && !has_capability('moodle/site:accessallgroups', $coursecontext)) {
            $groups = groups_get_user_groups($course->id, $USER->id);
            foreach ($groups[0] ?? [] as $groupid) {
                $roster += get_enrolled_users($coursecontext, '', (int)$groupid, $fields, $order, 0, 0, true);
            }
        } else {
            $roster = get_enrolled_users($coursecontext, '', 0, $fields, $order, 0, 0, true);
        }

        $students = [];
        foreach ($roster as $student) {
            if (has_capability('local/quessor:gradeassignments', $coursecontext, $student->id)) {
                continue;
            }
            $students[] = [
                'moodleUserId' => (string)$student->id,
                'studentFirstName' => $student->firstname,
                'studentLastName' => $student->lastname,
                'studentEmail' => $student->email,
            ];
        }
        return $students;
    }
}
