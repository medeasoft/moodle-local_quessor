<?php

namespace local_quessor\local;

defined('MOODLE_INTERNAL') || die();

/** Builds a bounded, plain-text course context without mixing it into student work. */
final class course_context_service {
    private const MAX_LENGTH = 50000;

    public static function build(\stdClass $course, int $exclude_cmid = 0): string {
        global $DB;

        $parts = [];
        $modinfo = get_fast_modinfo($course);
        foreach ($modinfo->get_cms() as $cm) {
            if ((int)$cm->id === $exclude_cmid || !$cm->visible || !$cm->uservisible) {
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
