<?php

namespace local_quessor\local;

defined('MOODLE_INTERNAL') || die();

class prepared_submission_file {
    private ?string $content = null;
    private $generator = null;

    public function __construct(
        string|callable $contentOrGenerator,
        private readonly string $mimetype,
        private readonly string $filename,
    ) {
        if (is_callable($contentOrGenerator)) {
            $this->generator = $contentOrGenerator;
        } else {
            $this->content = $contentOrGenerator;
        }
    }

    public function copy_content_to_temp(string $dir): string|false {
        if ($this->content === null && $this->generator !== null) {
            $this->content = ($this->generator)();
        }
        $tempdir = make_temp_directory($dir);
        $tempfile = tempnam($tempdir, 'prepared_');
        if ($tempfile === false || file_put_contents($tempfile, (string)$this->content) === false) {
            return false;
        }
        return $tempfile;
    }

    public function get_mimetype(): string {
        return $this->mimetype;
    }

    public function get_filename(): string {
        return $this->filename;
    }
}

class assignment_service {
    private const OFFICE_EXTENSIONS = ['docx', 'pptx', 'xlsx', 'odt', 'odp', 'ods'];

    public static function text_to_pdf(string $text, string $title = 'Moodle submission'): prepared_submission_file {
        $generator = static function() use ($text, $title): string {
            global $CFG;
            require_once($CFG->libdir . '/pdflib.php');
            $pdf = new \pdf();
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->SetTitle($title);
            $pdf->AddPage();
            $pdf->writeHTML(nl2br(s($text)), true, false, true, false, '');
            return $pdf->Output('', 'S');
        };
        return new prepared_submission_file($generator, 'application/pdf', clean_filename($title) . '.pdf');
    }

    /** Convert a text response and Moodle stored files into evaluation-ready documents. */
    public static function prepare_content(
        string $text,
        array $files,
        array $fingerprintparts,
        string $title,
        bool $allowpartial = false,
    ): array {
        $text = trim($text);
        $fingerprintparts[] = 'text:' . hash('sha256', $text);
        foreach ($files as $file) {
            $fingerprintparts[] = implode(':', [
                $file->get_filepath(),
                $file->get_filename(),
                $file->get_contenthash(),
                $file->get_timemodified(),
            ]);
        }
        $sourcehash = hash('sha256', implode('|', $fingerprintparts));
        if ($text === '' && !$files) {
            return ['state' => 'empty', 'files' => [], 'sourceHash' => $sourcehash];
        }

        $prepared = $text === '' ? [] : [self::text_to_pdf($text, $title)];
        $converter = new \core_files\converter();
        $skipped = 0;
        foreach ($files as $file) {
            $result = self::prepare_file($file, $converter);
            if ($result['state'] === 'pending') {
                return ['state' => 'pending', 'files' => [], 'sourceHash' => $sourcehash];
            }
            if ($result['state'] !== 'ready') {
                if ($allowpartial) {
                    $skipped++;
                    continue;
                }
                return ['state' => $result['state'], 'files' => [], 'sourceHash' => $sourcehash];
            }
            $prepared[] = $result['file'];
        }
        if (!$prepared) {
            return ['state' => $skipped ? 'unsupported' : 'empty', 'files' => [], 'sourceHash' => $sourcehash];
        }
        return ['state' => 'ready', 'files' => $prepared, 'sourceHash' => $sourcehash];
    }

    public static function get_assign(int $cmid): array {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
        [$course, $cm] = get_course_and_cm_from_cmid($cmid, 'assign');
        $context = \context_module::instance($cm->id);
        return [$course, $cm, $context, new \assign($context, $cm, $course)];
    }

    /**
     * Prepare all content belonging to one Moodle submission attempt.
     *
     * Plain PDF/JPEG/PNG submissions keep the fast path. Online text and
     * Office documents are converted to PDF through Moodle's configured file
     * converter. Online text uses Moodle's built-in PDF renderer.
     */
    public static function prepare_submission(
        \context_module $context,
        \stdClass $submission,
    ): array {
        global $CFG, $DB;

        $fs = get_file_storage();
        $files = array_values($fs->get_area_files(
            $context->id,
            'assignsubmission_file',
            'submission_files',
            $submission->id,
            'filepath, filename',
            false,
        ));
        $onlinefiles = $fs->get_area_files(
            $context->id,
            'assignsubmission_onlinetext',
            'submissions_onlinetext',
            $submission->id,
            'filepath, filename',
            false,
        );
        $files = array_merge($files, array_values($onlinefiles));
        $onlinetext = $DB->get_record('assignsubmission_onlinetext', ['submission' => $submission->id]);
        $onlinetextvalue = trim((string)($onlinetext->onlinetext ?? ''));

        $fingerprintparts = [
            'submission:' . $submission->id,
            'attempt:' . (int)$submission->attemptnumber,
            'modified:' . (int)$submission->timemodified,
            'status:' . (string)$submission->status,
            'online:' . hash('sha256', $onlinetextvalue),
        ];
        foreach ($files as $file) {
            $fingerprintparts[] = implode(':', [
                $file->get_filepath(),
                $file->get_filename(),
                $file->get_contenthash(),
                $file->get_timemodified(),
            ]);
        }
        $sourcehash = hash('sha256', implode('|', $fingerprintparts));

        if (!$files && $onlinetextvalue === '') {
            return ['state' => 'empty', 'files' => [], 'sourceHash' => $sourcehash];
        }

        $preparedfiles = [];
        if ($onlinetextvalue !== '') {
            $plaintext = html_to_text($onlinetextvalue, 0, false);
            $preparedfiles[] = self::text_to_pdf($plaintext, 'online-text');
        }

        $converter = new \core_files\converter();
        foreach ($files as $file) {
            $result = self::prepare_file($file, $converter);
            if ($result['state'] !== 'ready') {
                return ['state' => $result['state'], 'files' => [], 'sourceHash' => $sourcehash];
            }
            $preparedfiles[] = $result['file'];
        }

        return ['state' => 'ready', 'files' => $preparedfiles, 'sourceHash' => $sourcehash];
    }

    /** Prepare one stored file, with a text-preserving Office fallback. */
    private static function prepare_file(\stored_file $file, \core_files\converter $converter): array {
        if (in_array($file->get_mimetype(), ['application/pdf', 'image/jpeg', 'image/png'], true)) {
            return ['state' => 'ready', 'file' => $file];
        }

        if (str_starts_with($file->get_mimetype(), 'text/')) {
            $text = trim($file->get_content());
            if ($text === '') {
                return ['state' => 'ready', 'file' => self::text_to_pdf('', $file->get_filename())];
            }
            return [
                'state' => 'ready',
                'file' => self::text_to_pdf($text, pathinfo($file->get_filename(), PATHINFO_FILENAME)),
            ];
        }

        if ($converter->can_convert_storedfile_to($file, 'pdf')) {
            $conversion = $converter->start_conversion($file, 'pdf');
            $status = (int)$conversion->get('status');
            if ($status === \core_files\conversion::STATUS_PENDING
                || $status === \core_files\conversion::STATUS_IN_PROGRESS) {
                return ['state' => 'pending'];
            }
            if ($status === \core_files\conversion::STATUS_COMPLETE && $conversion->get_destfile()) {
                return ['state' => 'ready', 'file' => $conversion->get_destfile()];
            }
        }

        // Moodle sites without LibreOffice/Google Drive conversion can still
        // grade modern Office/OpenDocument files. Preserve their readable text
        // in a PDF; layout and embedded images require a configured converter.
        $officetext = self::extract_office_text($file);
        if ($officetext !== '') {
            return [
                'state' => 'ready',
                'file' => self::text_to_pdf($officetext, pathinfo($file->get_filename(), PATHINFO_FILENAME)),
            ];
        }
        return ['state' => 'unsupported'];
    }

    /** Prepare teacher-authored activity source files for question extraction. */
    public static function prepare_question_file(\stored_file $file, \core_files\converter $converter): array {
        return self::prepare_file($file, $converter);
    }

    /** Extract readable text from ZIP-based Office/OpenDocument containers. */
    private static function extract_office_text(\stored_file $file): string {
        $extension = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
        if (!in_array($extension, self::OFFICE_EXTENSIONS, true) || !class_exists('ZipArchive')) {
            return '';
        }

        $tempfile = $file->copy_content_to_temp('local_quessor');
        if (!$tempfile) return '';
        $zip = new \ZipArchive();
        $opened = false;
        try {
            $opened = $zip->open($tempfile) === true;
            if (!$opened) return '';
            $entries = [];
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = (string)$zip->getNameIndex($index);
                $include = match ($extension) {
                    'docx' => $name === 'word/document.xml'
                        || preg_match('~^word/(header|footer|footnotes|endnotes).*\.xml$~', $name),
                    'pptx' => preg_match('~^ppt/(slides/slide|notesSlides/notesSlide)\d+\.xml$~', $name),
                    'xlsx' => $name === 'xl/sharedStrings.xml'
                        || preg_match('~^xl/worksheets/sheet\d+\.xml$~', $name),
                    default => $name === 'content.xml',
                };
                if ($include) $entries[] = $name;
            }
            natsort($entries);

            $parts = [];
            if ($extension === 'xlsx') {
                $ssxml = $zip->getFromName('xl/sharedStrings.xml');
                if (is_string($ssxml) && $ssxml !== '') {
                    if (preg_match_all('~<t[^>]*>(.*?)</t>~s', $ssxml, $matches)) {
                        $sstexts = [];
                        foreach ($matches[1] as $val) {
                            $decoded = html_entity_decode(strip_tags($val), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
                            if (trim($decoded) !== '') $sstexts[] = trim($decoded);
                        }
                        if (!empty($sstexts)) {
                            $parts[] = implode("\n", $sstexts);
                        }
                    }
                }
            }

            foreach ($entries as $name) {
                if ($extension === 'xlsx' && $name === 'xl/sharedStrings.xml') continue;
                $xml = $zip->getFromName($name);
                if (!is_string($xml) || $xml === '') continue;
                $xml = preg_replace(
                    ['~</(?:w:p|a:p|text:p|table:table-row|p)>~i', '~<(?:w:tab|text:tab)[^>]*/>~i', '~</(?:w:tc|w:t|a:t|text:span|c)>~i'],
                    ["\n", "\t", ' '],
                    $xml,
                );
                $plain = html_entity_decode(strip_tags((string)$xml), ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
                $plain = preg_replace('/[ \t]+/', ' ', $plain);
                $plain = preg_replace('/\n\s*\n+/', "\n", (string)$plain);
                if (trim((string)$plain) !== '') $parts[] = trim((string)$plain);
            }
            return trim(implode("\n\n", $parts));
        } finally {
            if ($opened) $zip->close();
            @unlink($tempfile);
        }
    }

    public static function rubric_text(\context_module $context): string {
        return self::advanced_grading_text($context, 'mod_assign', 'submissions');
    }

    /**
     * Flatten Moodle advanced grading (rubric or marking guide) into plain text.
     */
    public static function advanced_grading_text(\context_module $context, string $component, string $area): string {
        global $CFG;
        require_once($CFG->dirroot . '/grade/grading/lib.php');
        $manager = get_grading_manager($context, $component, $area);
        $method = $manager->get_active_method();
        if (!$method) {
            return '';
        }
        $definition = $manager->get_controller($method)->get_definition();
        if (!$definition) {
            return '';
        }

        $lines = [];
        $formname = trim(strip_tags((string)($definition->name ?? '')));
        if ($formname !== '') {
            $lines[] = $formname;
        }

        if (!empty($definition->rubric_criteria) && is_array($definition->rubric_criteria)) {
            foreach ($definition->rubric_criteria as $criterion) {
                $criterion = (array)$criterion;
                $levels = [];
                foreach ((array)($criterion['levels'] ?? []) as $level) {
                    $level = (array)$level;
                    $definitiontext = trim(strip_tags((string)($level['definition'] ?? '')));
                    $score = $level['score'] ?? '';
                    $levels[] = $score === '' || $score === null
                        ? $definitiontext
                        : $definitiontext . ' (' . $score . ')';
                }
                $description = trim(strip_tags((string)($criterion['description'] ?? '')));
                $lines[] = $levels ? $description . ': ' . implode('; ', $levels) : $description;
            }
        }

        if (!empty($definition->guide_criteria) && is_array($definition->guide_criteria)) {
            foreach ($definition->guide_criteria as $criterion) {
                $criterion = (array)$criterion;
                $name = trim(strip_tags((string)($criterion['shortname'] ?? '')));
                $description = trim(strip_tags((string)($criterion['description'] ?? '')));
                $markers = trim(strip_tags((string)($criterion['descriptionmarkers'] ?? '')));
                $maxscore = $criterion['maxscore'] ?? '';
                $label = $name !== '' ? $name : $description;
                $detail = [];
                if ($description !== '' && $description !== $label) {
                    $detail[] = $description;
                }
                if ($markers !== '') {
                    $detail[] = $markers;
                }
                if ($maxscore !== '' && $maxscore !== null) {
                    $detail[] = 'max ' . $maxscore;
                }
                $lines[] = $detail ? $label . ': ' . implode('; ', $detail) : $label;
            }
        }

        if (count($lines) <= ($formname !== '' ? 1 : 0)) {
            $fallback = trim(strip_tags((string)($definition->description ?? '')));
            if ($fallback !== '') {
                $lines[] = $fallback;
            }
        }

        return trim(implode("\n", array_filter($lines, static fn(string $line): bool => trim($line) !== '')));
    }

    public static function sync_grade(int $cmid, int $userid, float $grade, ?string $feedback): void {
        [, , , $assign] = self::get_assign($cmid);
        $feedbacktext = $feedback ?? '';
        $feedbackformat = FORMAT_PLAIN;
        $feedbackitemid = 0;
        if ($feedback === null) {
            // The Assignment feedback plugin expects its editor payload even
            // when only the grade changes. Re-submit the stored comment so an
            // unchecked feedback option preserves it instead of clearing it.
            $currentgrade = $assign->get_user_grade($userid, false, -1);
            $commentsplugin = $assign->get_feedback_plugin_by_type('comments');
            $currentcomment = $currentgrade && $commentsplugin
                ? $commentsplugin->get_feedback_comments($currentgrade->id)
                : null;
            if ($currentcomment) {
                $feedbacktext = (string)$currentcomment->commenttext;
                $feedbackformat = (int)$currentcomment->commentformat;
                $feedbackitemid = null;
            }
        }
        $data = (object)[
            'grade' => $grade,
            'attemptnumber' => -1,
            'addattempt' => 0,
            'workflowstate' => '',
            'applytoall' => 0,
            'plugindata' => [],
            'assignfeedbackcomments_editor' => array_filter([
                'text' => $feedbacktext,
                'format' => $feedbackformat,
                'itemid' => $feedbackitemid,
            ], static fn($value): bool => $value !== null),
            'sendstudentnotifications' => false,
        ];
        $assign->save_grade($userid, $data);
    }
}
