<?php

namespace local_quessor\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_quessor_submission', [
            'userid' => 'privacy:metadata:local_quessor_submission:userid',
            'submissionid' => 'privacy:metadata:local_quessor_submission:submissionid',
            'status' => 'privacy:metadata:local_quessor_submission:status',
            'grade' => 'privacy:metadata:local_quessor_submission:grade',
            'feedback' => 'privacy:metadata:local_quessor_submission:feedback',
        ], 'privacy:metadata:local_quessor_submission');

        $collection->add_external_location_link('quessor', [
            'moodleUserId' => 'privacy:metadata:quessor:userid',
            'moodleUserEmail' => 'privacy:metadata:quessor:email',
            'studentName' => 'privacy:metadata:quessor:studentname',
            'submissioncontent' => 'privacy:metadata:quessor:submissioncontent',
        ], 'privacy:metadata:quessor');

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {local_quessor_submission} qs
                  JOIN {course_modules} cm ON cm.id = qs.cmid
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :level
                 WHERE qs.userid = :userid";
        $contextlist->add_from_sql($sql, [
            'userid' => $userid,
            'level' => CONTEXT_MODULE,
        ]);
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $userlist->add_from_sql('userid', 'SELECT userid FROM {local_quessor_submission} WHERE cmid = :cmid', [
            'cmid' => $context->instanceid,
        ]);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $records = $DB->get_records('local_quessor_submission', [
                'cmid' => $context->instanceid,
                'userid' => $userid,
            ]);
            foreach ($records as $record) {
                writer::with_context($context)->export_data(
                    [get_string('pluginname', 'local_quessor')],
                    (object)[
                        'submissionid' => $record->submissionid,
                        'status' => $record->status,
                        'grade' => $record->grade,
                        'feedback' => $record->feedback,
                    ]
                );
            }
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context instanceof \context_module) {
            $DB->delete_records('local_quessor_submission', ['cmid' => $context->instanceid]);
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = (int)$contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_module) {
                continue;
            }
            $DB->delete_records('local_quessor_submission', [
                'cmid' => $context->instanceid,
                'userid' => $userid,
            ]);
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['cmid'] = $context->instanceid;
        $DB->delete_records_select('local_quessor_submission', "cmid = :cmid AND userid $insql", $params);
    }
}
