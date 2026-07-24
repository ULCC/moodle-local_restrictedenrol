<?php

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_restrictedenrol', get_string('pluginname', 'local_restrictedenrol'));

    $settings->add(new admin_setting_configtext(
        'local_restrictedenrol/managershortname',
        get_string('managershortname', 'local_restrictedenrol'),
        get_string('managershortname_desc', 'local_restrictedenrol'),
        'manager',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_restrictedenrol/cohortidnumber',
        get_string('cohortidnumber', 'local_restrictedenrol'),
        get_string('cohortidnumber_desc', 'local_restrictedenrol'),
        '',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_restrictedenrol/debuglog',
        get_string('debuglog', 'local_restrictedenrol'),
        get_string('debuglog_desc', 'local_restrictedenrol'),
        1
    ));

    $ADMIN->add('localplugins', $settings);
}
