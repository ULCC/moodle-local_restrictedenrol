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

        $restricted = self::user_is_restricted($context, $USER->id);
        $config = get_config('local_restrictedenrol');
        $profilefieldshortname = trim((string)($config->profilefieldshortname ?? 'collegecode'));
        $allowedvalue = trim((string)($config->allowedvalue ?? 'ABC'));

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
        ];

        if ($restricted && $profilefieldshortname !== '') {
            $sql .= "
                JOIN {user_info_field} uif
                  ON uif.shortname = :profilefieldshortname
                JOIN {user_info_data} uid
                  ON uid.userid = u.id
                 AND uid.fieldid = uif.id
            ";
            $sqlparams['profilefieldshortname'] = $profilefieldshortname;
            $sqlparams['allowedvalue'] = $allowedvalue;
            $where[] = 'uid.data = :allowedvalue';
        }

        if ($params['search'] !== '') {
            $like = $params['searchanywhere'] ? "%{$params['search']}%" : "{$params['search']}%";
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
        $requiredfields = ['id', 'fullname', 'profileimageurl', 'profileimageurlsmall', 'email', 'username', 'idnumber'];

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
        $roleshortname = trim((string)(get_config('local_restrictedenrol', 'managershortname') ?: 'manager'));
        if ($roleshortname === '') {
            return false;
        }

        foreach (get_user_roles($context, $userid, true) as $role) {
            if ($role->shortname === $roleshortname) {
                return true;
            }
        }

        return false;
    }
}
