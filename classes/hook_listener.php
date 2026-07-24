<?php

namespace local_restrictedenrol;

defined('MOODLE_INTERNAL') || die();

use core\hook\output\before_footer_html_generation;

class hook_listener {

    /**
     * Add required AMD JavaScript before footer generation.
     *
     * @param before_footer_html_generation $hook
     */
    public static function before_footer_html_generation(before_footer_html_generation $hook): void {
        global $PAGE;

        if (!during_initial_install() && isset($PAGE)) {
            $PAGE->requires->js_call_amd('local_restrictedenrol/override', 'init', [[
                'debug' => !empty(get_config('local_restrictedenrol', 'debuglog')),
            ]]);
        }
    }
}
