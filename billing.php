<?php

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

require_login();
$context = context_system::instance();
$PAGE->set_context($context);
require_capability('moodle/site:config', $context);

if (!\local_quessor\local\billing::can_manage()) {
    throw new \moodle_exception('nopermissions', 'error', '', get_string('pluginname', 'local_quessor'));
}

$dest = optional_param('dest', \local_quessor\local\billing::DEST_CREDITS, PARAM_ALPHA);
if ($dest !== \local_quessor\local\billing::DEST_SUBSCRIBE) {
    $dest = \local_quessor\local\billing::DEST_CREDITS;
}

$ssourl = null;
if (data_submitted() && confirm_sesskey()) {
    $ssourl = \local_quessor\local\billing::sso_url($dest);
}

admin_externalpage_setup('local_quessor_billing');
$PAGE->set_url(new moodle_url('/local/quessor/billing.php'));
$PAGE->set_title(get_string('payment_heading', 'local_quessor'));
$PAGE->set_heading(get_string('pluginname', 'local_quessor'));

if ($ssourl) {
    local_quessor_prepare_embed($ssourl, 'local-quessor-billing');
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('payment_heading', 'local_quessor'));
echo \local_quessor\local\billing::action_html();
if ($ssourl) {
    local_quessor_render_embed(
        $ssourl,
        'local-quessor-billing',
        get_string('payment_heading', 'local_quessor'),
        '260px'
    );
}
echo $OUTPUT->footer();
