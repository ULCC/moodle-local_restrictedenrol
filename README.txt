Restricted Enrol
================

Purpose
-------
This plugin restricts the user search results shown in Moodle's "Enrol users"
modal for selected course roles.

When Moodle requests potential enrolment users through:

  core_enrol_get_potential_users

the plugin redirects that AJAX request to its own external function:

  local_restrictedenrol_get_potential_users

Behaviour
---------
- Users with the configured role shortname in the course context, or inherited
  into the course context, get filtered enrolment search results.
- Other users continue to receive the normal matching user results.
- Filtering is based on a custom user profile field shortname and value.

Default settings:

  restricted role shortname = manager
  profile field shortname   = collegecode
  allowed profile value     = ABC

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

Testing
-------
1. Create or identify users with different values in the configured custom
   profile field, for example:

     collegecode = ABC
     collegecode = XYZ

2. Open a course as a user with the configured restricted role, for example
   Manager.
3. Open the "Enrol users" modal and search for users.
4. Confirm that only users matching the configured allowed profile value are
   shown.

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
