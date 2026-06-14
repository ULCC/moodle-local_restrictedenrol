<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Inject the AMD interceptor on pages.
 *
 * Load it globally; the JS itself only rewrites the
 * core_enrol_get_potential_users AJAX call.
 *
 * @return string
 */
function local_restrictedenrol_before_footer(): string {
    global $PAGE;

    if (!during_initial_install() && isset($PAGE)) {
        $PAGE->requires->js_call_amd('local_restrictedenrol/override', 'init', [[
            'debug' => !empty(get_config('local_restrictedenrol', 'debuglog')),
        ]]);
    }

    return '';
}
