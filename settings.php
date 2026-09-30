<?php

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
