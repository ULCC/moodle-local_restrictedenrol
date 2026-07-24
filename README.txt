Restricted Enrol
================

Purpose
-------
This plugin restricts the user search results shown in Moodle's "Enrol users"
modal for selected course managers.

When Moodle requests potential enrolment users through:

  core_enrol_get_potential_users

the plugin redirects that AJAX request to its own external function:

  local_restrictedenrol_get_potential_users

Behaviour
---------
- Users with the configured manager role who are members of the configured
  cohort have their enrolment search results restricted.
- Restricted users only see enrolment candidates who are members of the same
  configured cohort.
- Users with the capability:

      local/restrictedenrol:enrolanyone

  are never restricted, regardless of their role or cohort membership.
- Managers who are not members of the configured cohort are not restricted.
- Users without the configured manager role are not affected by this plugin and
  continue to use Moodle's normal enrolment permissions.
- The logged-in user is excluded from their own enrolment search results.
- Search terms have SQL LIKE wildcards escaped before matching.

Default settings
----------------
  manager role shortname = manager
  cohort idnumber        = staff

Install
-------
1. Copy the plugin folder to:

     local/restrictedenrol

2. Visit:

     Site administration > Notifications

3. Complete the plugin installation or upgrade.
4. Purge Moodle caches.
5. Configure settings from:

     Site administration > Plugins > Local plugins > Restricted enrol

Configuration
-------------
The plugin provides the following settings:

- Manager role shortname
  The course role whose users may be subject to enrolment restrictions.

- Cohort idnumber
  The idnumber of the cohort whose members are subject to the restriction.
  Restricted managers can only enrol users who are also members of this cohort.

Capability
----------
The plugin defines the capability:

  local/restrictedenrol:enrolanyone

Users granted this capability bypass all restrictions and can search for and
enrol any eligible user.

Testing
-------
1. Create a cohort (for example, "staff") and configure its idnumber in the
   plugin settings.

2. Add one or more managers to the cohort.

3. Add users both inside and outside the cohort.

4. As a manager who is a member of the configured cohort and does not have the
   `local/restrictedenrol:enrolanyone` capability, open the "Enrol users" modal.

5. Verify that only users belonging to the configured cohort are returned.

6. Grant the manager the `local/restrictedenrol:enrolanyone` capability and
   verify that all eligible users are returned.

7. Test with a manager who is not a member of the configured cohort and verify
   that all eligible users are returned.

Developer verification
----------------------
Open browser DevTools > Network, search inside the "Enrol users" modal, and
inspect the AJAX request to:

  /lib/ajax/service.php

The request payload should contain:

  methodname: local_restrictedenrol_get_potential_users

If debug console logging is enabled in the plugin settings, the browser console
will also show messages when the AJAX request is intercepted.

Compatibility notes
-------------------
- The plugin is designed for Moodle 4.5 or later.
- It depends on Moodle's current manual enrolment user selector using the AJAX
  service call for potential users.
- After changing plugin files or settings, purge Moodle caches before testing.
