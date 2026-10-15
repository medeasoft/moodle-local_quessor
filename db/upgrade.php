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

function xmldb_local_quessor_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026080402) {
        $table = new xmldb_table('local_quessor_submission');

        $attemptnumber = new xmldb_field(
            'attemptnumber',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'status',
        );
        if (!$dbman->field_exists($table, $attemptnumber)) {
            $dbman->add_field($table, $attemptnumber);
        }

        $sourcehash = new xmldb_field(
            'sourcehash',
            XMLDB_TYPE_CHAR,
            '64',
            null,
            null,
            null,
            null,
            'attemptnumber',
        );
        if (!$dbman->field_exists($table, $sourcehash)) {
            $dbman->add_field($table, $sourcehash);
        }

        upgrade_plugin_savepoint(true, 2026080402, 'local', 'quessor');
    }

    if ($oldversion < 2026080403) {
        // Refresh event/task discovery for automatic resubmission sync.
        upgrade_plugin_savepoint(true, 2026080403, 'local', 'quessor');
    }

    if ($oldversion < 2026080404) {
        $table = new xmldb_table('local_quessor_submission');
        $activitytype = new xmldb_field(
            'activitytype',
            XMLDB_TYPE_CHAR,
            '20',
            null,
            XMLDB_NOTNULL,
            null,
            'assign',
            'cmid',
        );
        if (!$dbman->field_exists($table, $activitytype)) {
            $dbman->add_field($table, $activitytype);
        }
        upgrade_plugin_savepoint(true, 2026080404, 'local', 'quessor');
    }

    if ($oldversion < 2026080405) {
        // Refresh language and UI caches for data provenance information.
        upgrade_plugin_savepoint(true, 2026080405, 'local', 'quessor');
    }

    if ($oldversion < 2026080406) {
        // Refresh integration behaviour and language caches. No schema change.
        upgrade_plugin_savepoint(true, 2026080406, 'local', 'quessor');
    }

    if ($oldversion < 2026080601) {
        // Fix page context initialisation. No schema change.
        upgrade_plugin_savepoint(true, 2026080601, 'local', 'quessor');
    }

    if ($oldversion < 2026082200) {
        // Marketplace installs now self-register an RSA public key on first use.
        upgrade_plugin_savepoint(true, 2026082200, 'local', 'quessor');
    }

    if ($oldversion < 2026082201) {
        upgrade_plugin_savepoint(true, 2026082201, 'local', 'quessor');
    }

    if ($oldversion < 2026082202) {
        upgrade_plugin_savepoint(true, 2026082202, 'local', 'quessor');
    }

    if ($oldversion < 2026082203) {
        $table = new xmldb_table('local_quessor_submission');
        $index = new xmldb_index('cmid_userid_idx', XMLDB_INDEX_NOTUNIQUE, ['cmid', 'userid']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        upgrade_plugin_savepoint(true, 2026082203, 'local', 'quessor');
    }

    if ($oldversion < 2026082204) {
        upgrade_plugin_savepoint(true, 2026082204, 'local', 'quessor');
    }

    if ($oldversion < 2026082205) {
        upgrade_plugin_savepoint(true, 2026082205, 'local', 'quessor');
    }

    if ($oldversion < 2026082206) {
        unset_config('apiurl', 'local_quessor');
        unset_config('siteid', 'local_quessor');
        upgrade_plugin_savepoint(true, 2026082206, 'local', 'quessor');
    }

    if ($oldversion < 2026082207) {
        upgrade_plugin_savepoint(true, 2026082207, 'local', 'quessor');
    }

    if ($oldversion < 2026082208) {
        upgrade_plugin_savepoint(true, 2026082208, 'local', 'quessor');
    }

    if ($oldversion < 2026082223) {
        upgrade_plugin_savepoint(true, 2026082223, 'local', 'quessor');
    }

    if ($oldversion < 2026082300) {
        upgrade_plugin_savepoint(true, 2026082300, 'local', 'quessor');
    }

    if ($oldversion < 2026082302) {
        $legacyprivatekey = trim((string)get_config('local_quessor', 'privatekey'));
        if ($legacyprivatekey !== '' && trim((string)get_config(null, 'local_quessor_privatekey')) === '') {
            set_config('local_quessor_privatekey', $legacyprivatekey);
        }
        upgrade_plugin_savepoint(true, 2026082302, 'local', 'quessor');
    }

    if ($oldversion < 2026082601) {
        // Queue first-time submissions from activity events as well as
        // revisions of submissions already known to Quessor.
        upgrade_plugin_savepoint(true, 2026082601, 'local', 'quessor');
    }

    if ($oldversion < 2026082602) {
        // Add the localhost-only evaluation reset control to the hub.
        upgrade_plugin_savepoint(true, 2026082602, 'local', 'quessor');
    }

    if ($oldversion < 2026082603) {
        // Poll queued imports during the initial native evaluation bootstrap.
        upgrade_plugin_savepoint(true, 2026082603, 'local', 'quessor');
    }

    if ($oldversion < 2026082604) {
        // Replace the native stepper with an all-in-one evaluation workspace.
        upgrade_plugin_savepoint(true, 2026082604, 'local', 'quessor');
    }

    if ($oldversion < 2026082605) {
        // Refine native evaluation actions into a consistent visual hierarchy.
        upgrade_plugin_savepoint(true, 2026082605, 'local', 'quessor');
    }

    if ($oldversion < 2026082606) {
        // Hide technical submission-transfer status from the teaching workflow.
        upgrade_plugin_savepoint(true, 2026082606, 'local', 'quessor');
    }

    if ($oldversion < 2026082607) {
        // Show live, teacher-facing AI evaluation progress in the native workspace.
        upgrade_plugin_savepoint(true, 2026082607, 'local', 'quessor');
    }

    if ($oldversion < 2026082608) {
        // Keep the live progress card visible only while AI work is active.
        upgrade_plugin_savepoint(true, 2026082608, 'local', 'quessor');
    }

    if ($oldversion < 2026082609) {
        // Give question editing more horizontal room than general criteria.
        upgrade_plugin_savepoint(true, 2026082609, 'local', 'quessor');
    }

    if ($oldversion < 2026082610) {
        // Show the latest AI evaluation time beside score and feedback.
        upgrade_plugin_savepoint(true, 2026082610, 'local', 'quessor');
    }

    if ($oldversion < 2026082611) {
        // Send assignment intro PDFs as the authoritative question source.
        upgrade_plugin_savepoint(true, 2026082611, 'local', 'quessor');
    }

    if ($oldversion < 2026082612) {
        upgrade_plugin_savepoint(true, 2026082612, 'local', 'quessor');
    }

    if ($oldversion < 2026082613) {
        // Keep long extracted question lists inside a bounded scroll area.
        upgrade_plugin_savepoint(true, 2026082613, 'local', 'quessor');
    }

    if ($oldversion < 2026082614) {
        // Increase the question viewport and tighten horizontal configuration spacing.
        upgrade_plugin_savepoint(true, 2026082614, 'local', 'quessor');
    }

    if ($oldversion < 2026082615) {
        // Refresh student papers immediately as queued Moodle imports complete.
        upgrade_plugin_savepoint(true, 2026082615, 'local', 'quessor');
    }

    if ($oldversion < 2026082616) {
        // Place evaluation criteria below Questions at full width.
        upgrade_plugin_savepoint(true, 2026082616, 'local', 'quessor');
    }

    if ($oldversion < 2026082617) {
        // Let question text consume remaining table width without horizontal scrolling.
        upgrade_plugin_savepoint(true, 2026082617, 'local', 'quessor');
    }

    if ($oldversion < 2026090301) {
        // Preserve and reconcile the site signing key across plugin upgrades.
        upgrade_plugin_savepoint(true, 2026090301, 'local', 'quessor');
    }

    if ($oldversion < 2026092300) {
        // Quizzes without an essay question can be evaluated in Quessor.
        upgrade_plugin_savepoint(true, 2026092300, 'local', 'quessor');
    }

    return true;
}
