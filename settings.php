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

if ($hassiteconfig) {
    global $CFG;

    $defaultemail = trim((string)get_config('local_quessor', 'instituteemail'));
    if ($defaultemail === '') {
        $defaultemail = trim((string)($CFG->supportemail ?? ''));
    }

    // Moodle only puts a root category in the Site administration tab bar when it is
    // not a "short branch" (few leaf links, no nested category). A nested category
    // keeps Quessor as its own tab instead of a heading under General.
    $ADMIN->add('root', new admin_category('quessor', get_string('pluginname', 'local_quessor')));
    $ADMIN->add('quessor', new admin_category('quessor_section', get_string('pluginname', 'local_quessor')));

    $quessorpage = new admin_settingpage('local_quessor', get_string('settings', 'local_quessor'));
    $quessorpage->add(new admin_setting_heading(
        'local_quessor/installheading',
        get_string('installheading', 'local_quessor'),
        get_string('installintro', 'local_quessor'),
    ));
    $quessorpage->add(new \local_quessor\admin\setting_integration_enabled(
        'local_quessor/enabled',
        get_string('integrationenabled', 'local_quessor'),
        get_string('integrationenabled_desc', 'local_quessor'),
        0,
    ));
    $quessorpage->add(new \local_quessor\admin\setting_instituteemail(
        'local_quessor/instituteemail',
        get_string('instituteemail', 'local_quessor'),
        get_string('instituteemail_desc', 'local_quessor'),
        $defaultemail,
        PARAM_EMAIL,
    ));
    $quessorpage->add(new \local_quessor\admin\setting_billing());
    $quessorpage->add(new admin_setting_heading(
        'local_quessor/hublink',
        get_string('hub_page', 'local_quessor'),
        html_writer::link(new moodle_url('/local/quessor/index.php'), get_string('openhub', 'local_quessor'))
    ));
    $ADMIN->add('quessor_section', $quessorpage);
    $ADMIN->add(
        'quessor_section',
        new admin_externalpage(
            'local_quessor_billing',
            get_string('payment_heading', 'local_quessor'),
            new moodle_url('/local/quessor/billing.php'),
            'moodle/site:config'
        )
    );
}
