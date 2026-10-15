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

/** Builds a bounded, plain-text course context without mixing it into student work. */
final class course_context_service {
    private const MAX_LENGTH = 50000;

    public static function build(\stdClass $course, int $excludecmid = 0): string {
        global $DB;

        $parts = [];
        $modinfo = get_fast_modinfo($course);
        foreach ($modinfo->get_cms() as $cm) {
            if ((int)$cm->id === $excludecmid || !$cm->visible || !$cm->uservisible) {
                continue;
            }
            $text = '';
            if ($cm->modname === 'page') {
                $record = $DB->get_record('page', ['id' => $cm->instance], 'name,content,contentformat');
                $text = $record ? html_to_text((string)$record->content, 0, false) : '';
            } else if ($cm->modname === 'book') {
                $chapters = $DB->get_records('book_chapters', ['bookid' => $cm->instance, 'hidden' => 0], 'pagenum ASC');
                foreach ($chapters as $chapter) {
                    $text .= "\n" . format_string($chapter->title) . "\n"
                        . html_to_text((string)$chapter->content, 0, false);
                }
            } else if ($cm->modname === 'label') {
                $record = $DB->get_record('label', ['id' => $cm->instance], 'intro,introformat');
                $text = $record ? html_to_text((string)$record->intro, 0, false) : '';
            } else if ($cm->modname === 'resource') {
                $context = \context_module::instance($cm->id);
                $files = get_file_storage()->get_area_files(
                    $context->id,
                    'mod_resource',
                    'content',
                    0,
                    'filename',
                    false,
                );
                foreach ($files as $file) {
                    if (in_array($file->get_mimetype(), ['text/plain', 'text/markdown', 'text/csv'], true)) {
                        $text .= "\n" . \core_text::substr($file->get_content(), 0, 10000);
                    } else {
                        // Binary material remains traceable even when Moodle has no text extractor.
                        $text .= "\n[Dosya: " . $file->get_filename() . ']';
                    }
                }
            }
            $text = trim($text);
            if ($text !== '') {
                $parts[] = '## ' . format_string($cm->name) . "\n" . $text;
            }
            if (strlen(implode("\n\n", $parts)) >= self::MAX_LENGTH) {
                break;
            }
        }
        return \core_text::substr(implode("\n\n", $parts), 0, self::MAX_LENGTH);
    }
}
