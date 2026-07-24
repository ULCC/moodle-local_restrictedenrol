<?php
namespace local_restrictedenrol\external;

use context_course;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

defined('MOODLE_INTERNAL') || die();

/**
 * AJAX endpoint mirroring core_enrol_get_potential_users.
 */
class get_potential_users extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'course id'),
            'enrolid' => new external_value(PARAM_INT, 'enrolment id'),
            'search' => new external_value(PARAM_RAW, 'query'),
            'searchanywhere' => new external_value(PARAM_BOOL, 'find a match anywhere, or only at the beginning'),
            'page' => new external_value(PARAM_INT, 'Page number'),
            'perpage' => new external_value(PARAM_INT, 'Number per page'),
        ]);
    }

    /**
     * Execute.
     *
     * @param int $courseid
     * @param int $enrolid
     * @param string $search
     * @param bool $searchanywhere
     * @param int $page
     * @param int $perpage
     * @return array
     */
    public static function execute(int $courseid, int $enrolid, string $search, bool $searchanywhere, int $page, int $perpage): array {
        global $CFG, $DB, $USER;

        require_once($CFG->dirroot . '/user/lib.php');

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'enrolid' => $enrolid,
            'search' => $search,
            'searchanywhere' => $searchanywhere,
            'page' => $page,
            'perpage' => $perpage,
        ]);

        $context = context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('moodle/course:enrolreview', $context);

        $course = $DB->get_record('course', ['id' => $params['courseid']], '*', MUST_EXIST);
        $enrol = $DB->get_record('enrol', ['id' => $params['enrolid'], 'courseid' => $params['courseid']], '*', MUST_EXIST);

        $config = get_config('local_restrictedenrol');
        $cohortidnumber = trim((string)($config->cohortidnumber ?? 'staff'));
        $roleshortname = trim((string)($config->managershortname ?? 'manager'));

        $canenrolanyone = has_capability('local/restrictedenrol:enrolanyone', $context, $USER->id);

        $ismanager = false;
        foreach (get_user_roles($context, $USER->id, true) as $role) {
            if ($role->shortname === $roleshortname) {
                $ismanager = true;
                break;
            }
        }

        $restricted = false;

        if ($ismanager && !$canenrolanyone && $cohortidnumber !== '') {
            $restricted = $DB->record_exists_sql(
                "SELECT 1
               FROM {cohort_members} cm
               JOIN {cohort} c
                 ON c.id = cm.cohortid
              WHERE cm.userid = :userid
                AND c.idnumber = :cohortidnumber",
                [
                    'userid' => $USER->id,
                    'cohortidnumber' => $cohortidnumber,
                ]
            );
        }

        $limitfrom = max(0, $params['page']) * max(1, $params['perpage']);
        $limitnum = max(1, $params['perpage']);

        $namefields = \core_user\fields::for_name()->get_sql('u', false, '', '', false)->selects;

        $sql = "SELECT u.*, {$namefields}
              FROM {user} u
    ";

        $where = [
            'u.deleted = 0',
            'u.confirmed = 1',
            'u.suspended = 0',
            'u.id <> :guestid',
            'u.id <> :currentuserid',
            "NOT EXISTS (
            SELECT 1
              FROM {user_enrolments} ue
              JOIN {enrol} e
                ON e.id = ue.enrolid
             WHERE ue.userid = u.id
               AND e.courseid = :courseid
        )",
        ];

        $sqlparams = [
            'courseid' => $params['courseid'],
            'guestid' => $CFG->siteguest,
            'currentuserid' => $USER->id,
        ];

        if ($restricted) {
            $sql .= "
            JOIN {cohort_members} cm
              ON cm.userid = u.id
            JOIN {cohort} c
              ON c.id = cm.cohortid
        ";

            $where[] = 'c.idnumber = :cohortidnumber';
            $sqlparams['cohortidnumber'] = $cohortidnumber;
        }

        if ($params['search'] !== '') {
            $search = $DB->sql_like_escape($params['search']);
            $like = $params['searchanywhere'] ? "%{$search}%" : "{$search}%";
            $likesql = $DB->sql_like('u.firstname', ':search1', false, false)
                . ' OR ' . $DB->sql_like('u.lastname', ':search2', false, false)
                . ' OR ' . $DB->sql_like('u.email', ':search3', false, false)
                . ' OR ' . $DB->sql_like('u.username', ':search4', false, false)
                . ' OR ' . $DB->sql_like('u.idnumber', ':search5', false, false);
            $where[] = '(' . $likesql . ')';
            $sqlparams['search1'] = $like;
            $sqlparams['search2'] = $like;
            $sqlparams['search3'] = $like;
            $sqlparams['search4'] = $like;
            $sqlparams['search5'] = $like;
        }

        $sql .= ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY u.lastname ASC, u.firstname ASC, u.id ASC';

        $users = $DB->get_records_sql($sql, $sqlparams, $limitfrom, $limitnum);

        $results = [];
        $requiredfields = [
            'id',
            'fullname',
            'profileimageurl',
            'profileimageurlsmall',
            'email',
            'username',
            'idnumber',
        ];

        foreach ($users as $user) {
            $details = user_get_user_details($user, $course, $requiredfields);
            if (!$details) {
                continue;
            }

            $results[] = [
                'id' => (int)$details['id'],
                'fullname' => (string)$details['fullname'],
                'profileimageurl' => (string)($details['profileimageurl'] ?? ''),
                'profileimageurlsmall' => (string)($details['profileimageurlsmall'] ?? ''),
                'email' => (string)($details['email'] ?? ''),
                'username' => (string)($details['username'] ?? ''),
                'idnumber' => (string)($details['idnumber'] ?? ''),
            ];
        }

        return $results;
    }

    /**
     * Returns.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'User id'),
                'fullname' => new external_value(PARAM_TEXT, 'Full name'),
                'profileimageurl' => new external_value(PARAM_URL, 'Profile image URL', VALUE_OPTIONAL),
                'profileimageurlsmall' => new external_value(PARAM_URL, 'Small profile image URL', VALUE_OPTIONAL),
                'email' => new external_value(PARAM_TEXT, 'Email', VALUE_OPTIONAL),
                'username' => new external_value(PARAM_RAW, 'Username', VALUE_OPTIONAL),
                'idnumber' => new external_value(PARAM_RAW, 'ID number', VALUE_OPTIONAL),
            ])
        );
    }

    /**
     * Check whether current user should get restricted results.
     *
     * @param context_course $context
     * @param int $userid
     * @return bool
     */
    protected static function user_is_restricted(context_course $context, int $userid): bool {
        global $DB;

        // Users with enrolanyone capability are never restricted.
        if (has_capability('local/restrictedenrol:enrolanyone', $context, $userid)) {
            return false;
        }

        $roleshortname = trim((string)(get_config('local_restrictedenrol', 'managershortname') ?: 'manager'));
        if ($roleshortname === '') {
            return false;
        }

        $hasmanagerrole = false;
        foreach (get_user_roles($context, $userid, true) as $role) {
            if ($role->shortname === $roleshortname) {
                $hasmanagerrole = true;
                break;
            }
        }

        if (!$hasmanagerrole) {
            return false;
        }

        $cohortidnumber = trim((string)(get_config('local_restrictedenrol', 'cohortidnumber') ?: 'staff'));
        if ($cohortidnumber === '') {
            return false;
        }

        return $DB->record_exists_sql(
            "SELECT 1
           FROM {cohort_members} cm
           JOIN {cohort} c
             ON c.id = cm.cohortid
          WHERE cm.userid = :userid
            AND c.idnumber = :cohortidnumber",
            [
                'userid' => $userid,
                'cohortidnumber' => $cohortidnumber,
            ]
        );
    }


    /**
     * Get a custom user profile field value for a user.
     *
     * @param int $userid
     * @param string $fieldshortname
     * @return string|null
     */
    protected static function get_user_profile_field_value(int $userid, string $fieldshortname): ?string {
        global $DB;

        $sql = "SELECT uid.data
                  FROM {user_info_field} uif
                  JOIN {user_info_data} uid
                    ON uid.fieldid = uif.id
                 WHERE uif.shortname = :fieldshortname
                   AND uid.userid = :userid";

        $value = $DB->get_field_sql($sql, [
            'fieldshortname' => $fieldshortname,
            'userid' => $userid,
        ]);

        if ($value === false) {
            return null;
        }

        return trim((string)$value);
    }
}
