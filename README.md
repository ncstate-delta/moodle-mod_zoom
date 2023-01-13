# Intro

[Zoom](https://zoom.us) is a web- and app-based video conferencing service. This
plugin offers tight integration with Moodle, supporting meeting creation,
synchronization, grading and backup/restore.

## Prerequisites

This plugin is designed for Educational or Business Zoom accounts.

To connect to the Zoom APIs, this plugin requires an account-level app to be
created.

### Server-to-Server OAuth
To [create an account-level Server-to-Server OAuth app](https://marketplace.zoom.us/docs/guides/build/server-to-server-oauth-app), the `Server-to-server OAuth app`
permission is required.

The Server-to-Server OAuth app will generate a client ID, client secret and account ID.

At a minimum, the following scopes are required by this plugin:

- meeting:read:admin (Read meeting details)
- meeting:write:admin (Create/Update meetings)
- user:read:admin (Read user details)

Additional scopes are required for certain functionality:

- Reports for meetings / webinars
    - dashboard_meetings:read:admin (Business accounts and higher)
    - dashboard_webinars:read:admin  (Business accounts and higher)
    - report:read:admin (Pro user and up)
- Allow recordings to be viewed (zoom | viewrecordings)
    - recording:read:admin
- Tracking fields (zoom | defaulttrackingfields)
    - tracking_fields:read:admin
- Recycle licenses (zoom | utmost), (zoom | recycleonjoin)
    - user:write:admin
- Webinars (zoom | showwebinars), (zoom | webinardefault)
    - webinar:read:admin
    - webinar:write:admin

### JWT
JWT will be deprecated in June 2023. To create an account-level JWT app the 'JWT' permission is
required.

See https://marketplace.zoom.us/docs/guides/build/jwt-app. You will need to
create a JWT app and that will generate the API key and secret.

## Installation

1. Install plugin to mod/zoom. More details at https://docs.moodle.org/en/Installing_plugins#Installing_a_plugin
2. Once you install the plugin you need to set the following settings to enable the plugin:

- Zoom account ID (mod_zoom | accountid)
- Zoom client ID (mod_zoom | clientid)
- Zoom client secret (mod_zoom | clientsecret)

JWT will be deprecated in June 2023. For a JWT app, you need to set the following settings to enable the plugin:

- Zoom API key (mod_zoom | apikey)
- Zoom API secret (mod_zoom | apisecret)

Please note that the API key and secret is not the same as the LTI key/secret.

If you get "Access token is expired" errors, make sure the date/time on your
server is properly synchronized with the time servers.

- Zoom home page URL (mod_zoom | zoomurl), Link to your organization's custom Zoom landing page.

## Changelog

v4.9.2

- Bugfix: New meetings did not know which user to check for security settings #438 (thanks @haietza)
- Bugfix: Use select field so registration option saves correctly #448 (thanks @paulandm)
- Compatibility: grunt rebuild against Moodle 4.1 #446

v4.9.1

- Regression: Administrators without Zoom account were unable to edit #422 (thanks @juanbrunetmf)
  - Introduced in v4.7.0 when adding automatic recording options.
- Bugfix: Respect host settings for meeting options and reduce unnecessary API calls #422
- Bugfix: Always request JSON API responses and show error details #426 (thanks @sascha-serwe)
- Bugfix: Default start time should be in the future and be a multiple of 5 minutes #427

v4.9.0

- Feature: Allow Registration #412 (thanks @paulandm, @haietza, @MoleLR, @lcollong, @louisaoc)
  - New settings `zoom/defaultregistration`
  - New per activity setting `registration`
- Bugfix: Update meetings task was throwing an exception #421 (thanks @lexxkoto)
- Bugfix: Add missing cache definition language string #408 (thanks @aspark21)
- Bugfix: Use user-level meeting security configuration #408
  - Removed OAuth scope: `account:read:admin`
- Regression: Moodle < 3.4 does not support hideIf()
  - Introduced in v3.5 of this plugin while tidying the form UI.
  - Minimum required Moodle version officially increased to 3.4.

v4.8.1

- Bugfix: Moodle 4 was displaying the activity description twice #417 (thanks @Laur0r, @haietza)
- Bugfix: Avoid HTTP/2 error when using Server-to-Server OAuth #418 (thanks @phette23)

v4.8.0

- Feature: Support Server-to-Server OAuth app #387 (thanks @haietza, @mhughes2k)
  - New settings `zoom/accountid`, `zoom/clientid`, `zoom/clientsecret`
  - Reminder: You must [switch from JWT to Server-to-Server OAuth by June 2023](https://marketplace.zoom.us/docs/guides/build/jwt-app/jwt-faq/).
- Regression: Locked settings were not being applied #407 (thanks @krab-stik)
  - Introduced in v4.7.0 while adding support for automatic recording.

v4.7.0

- Feature: Allow automatic recording #390 (thanks @aduranterres, @lcollong)
  - New settings `zoom/recordingoption`, `zoom/allowrecordingchangeoption`
  - New per activity setting `option_auto_recording`
  - Known issue: Causes pre-existing events to turn off automatic recording when edited.
- Performance: Static caching of repeated API calls #402 (thanks @aduranterres)

v4.6.2

- Regression: Rename mustache templates for backward compatibility #398 (thanks @PhilipBeacon)
  - Introduced in v4.6.0 by new mustache templates in sub-directories (a Moodle 3.8 feature).
- Bugfix: Recognize the Webinar capabilities of a Zoom Events license #338 (thanks @dottbarbieri)
- Bugfix: Avoid PHP Warning when restoring Zoom activities without breakout room data #399

v4.6.1

- Bugfix: Avoid JavaScript error when 'Show More' button does not exist #392 (thanks @mwithheld)
- Bugfix: Add missing privacy coverage for breakout rooms; fix privacy data deletion #395 (thanks @hdagheda)

v4.6.0

- Feature: Pre-assign Breakout Rooms #371 (thanks @annouarf, @levemar, University of Montreal, @mhughes2k)
- Bugfix: Validate start times and duration for timed recurring meetings #389 (thanks @nchan31, @jwalits)

v4.5.3

- Bugfix: Allow plugin settings to update without a configuration exception #386 (thanks @acquaalta)

v4.5.2

- Regression: Avoid requiring paid accounts for meeting default settings #383 (thanks @nstefanski, @nickchen, @valeriy67, @obook)
  - Introduced in v3.5 to determine passcode requirements.
- Bugfix: Allow course restore to complete even when Zoom is not fully configured #378
- Code quality: Require PHPUnit to pass without warnings #379

v4.5.1

- Regression: Fix Zoom activity icon visibility #375 (thanks @foxlapinou)
- Compatibility: Fix PHPUnit deprecation warnings #373

v4.5.0

- Feature: Support for Reset course functionality #370 (thanks @izendegi)
- Compatibility: Improved support for Moodle 4.0 #369

v4.4.0

- Feature: Allow configuration of section visibility on the view page #363 (thanks @aduranterres, @rayjbarrett1)
  - New settings `zoom/defaultshowschedule`, `zoom/defaultshowsecurity`, `zoom/defaultshowmedia`
  - New per activity settings `show_schedule`, `show_security`, `show_media`
- Feature: Allow administrator to set webinar by default (when available) #367 (thanks @marcellobarile)
  - New setting `zoom/webinardefault`
- Code quality: specify code coverage for tests #367

v4.3.4

- Privacy: Add tests, support recordings, fix existing code #345 (thanks @jwalits, @tuanngocnguyen, @mattporritt, @marcghaly)
- Compatibility: grunt rebuild for MDL-73915 #364

v4.3.3

- Fix recording table database schema definitions #358 (thanks @jwalits)
- Compatibility: Moodle upstream upgraded to php-jwt v6.0 #359
- Renamed primary branch in GitHub to `main` #353

v4.3.2

- Only cache successful Zoom user ID values #350 (thanks @merrill-oakland)
- Code quality: Align with moodle-local_codechecker v3.0.5 #351

v4.3.1

- Fix database schema alignment and associated code #335 (thanks @TomoTsuyuki)
- Run "Update Meetings" task once per day by default #342 (thanks @deraadt for reporting)
  - Note: You may need to manually adjust your task schedule on existing installs.

v4.3

- Add support for Zoom Cloud Recordings #292 (thanks @jwalits, @nstefanski, @abias, ETH Zürich)
  - New setting `zoom/viewrecordings`
  - New per activity setting `recordings_visible_default`
- Fix tracking field PHP notices #337 (thanks @alina-kiz, @ndunand, @haietza)

v4.2.1

- Fix PHP 8 deprecation warning #332 (thanks @ndunand)
- Fix duplicate column name on "All Meetings" page #330

v4.2

- Add support for Zoom Tracking Fields #308 (thanks @haietza, @porcospino)
  - New setting `zoom/defaulttrackingfields`
- Send plaintext version of Moodle intro to Zoom #290 (thanks @Ottendahl, @abias, @yanus for reporting)
  - Note: To avoid losing Moodle's rich text, we no longer synchronize Zoom's topic back to Moodle.
- Reduce zoom_refresh_events overreach; fix 'quick edit' issue #320 (thanks @alina-kiz, @jwalits for testing)
- Add error handling and improve consistency in Zoom activity restore #328 (thanks @jonof)

v4.1.3

- Always use a fresh copy of start_url #316 (thanks @ShilVita for reporting)
- Synchronize calendar events consistently #319 (thanks @martinoesterreicher for reporting)
- Update JWT library to v5.4.0 #312

v4.1.2

- Make loadmeeting consistent via web and mobile (event, completion, grade, etc) #307 (thanks @nstefanski)

v4.1.1

- Fix invitation class not found exception #296 (thanks @byvamo for reporting)

v4.1

- Allow configuration of Zoom identifier #280 (thanks @jwalits, @abias, @jonlan)
  - New setting `zoom/apiidentifier`
- Allow configuration of Zoom API endpoint #293 (thanks @abias, @didier63)
  - New setting `zoom/apiendpoint`
- Use case-insensitive email comparison for schedule_for #295 (thanks @stopfstedt, @briannwar)

v4.0

- Fully support recurring meetings #258 (thanks @abias, @jwalits, ETH Zürich)
  - New setting `zoom/invitationremoveicallink`
  - Backward incompatible change: exported iCal events now match Moodle's uid format
- Retroactively fix database schema defaults #291 (thanks @foxlapinou for reporting)

v3.8.1

- Only allow real host to use start_url #285 (thanks @abias for reporting)

v3.8

- Add support for Ionic 5 #269 (thanks @dpalou)
- Improve update_meetings scheduled task #263 (thanks @abias)
- Re-enable mustache continuous integration #276
- Treat alternative hosts as a possible host #275
- Update `exists_on_zoom` consistently #273 (thanks @abias for reporting)
- Update `timemodified` only when needed #279 (thanks @abias for reporting)
- Fix meeting invitation issues #267, #274 (thanks @abias, @nstefanski, @andrewmadden for feedback)

v3.7

- Allow administrators to selectively remove Meeting Invitation details #235 (thanks @andrewmadden)
  - New capabilities `mod/zoom:viewjoinurl` and `mod/zoom:viewdialin`
- Track completion for mobile users #238 (thanks @nstefanski, @tzerafnx)
- Fix backup and restore of several zoom activity-level fields #247 (thanks @abias)
- Fix meeting reports task for some already-numeric end times #236 (thanks @lcollong)
- Fix list of alternative hosts to only include active users #252 (thanks @abias)
- Fix PHP 7.1 compatibility issue #243
- Fix encryption type validation #232 (thanks @abias)
- Clean up error messages / efficiency on the view page #245 (thanks @abias)

v3.6

- Fixed fatal regression on settings.php for Moodle < 3.7 (Thanks abias)
- Fixed debugging messages that occur for users without webinar licenses
- Various string improvements

v3.5

- Removed language translations. Please submit language translations to AMOS (https://lang.moodle.org/)
- Fixed bug causing downloading of meeting participation reports to fail
- Added new settings for E2EE, Webinars, Alternative hosts, Download iCal,
  Meeting capacity warning, and Enable meeting links (Thanks abias)
- Improved UI for admin and module settings (Thanks abias)
- Support for admins to update Zoom meeting participation reports
- Quick editing Zoom meeting name will now update calendar event
- Support for more advanced passcode requirements
- This will be the last supported release by UCLA. This plugin will now be maintained by jrchamp and NC State DELTA.

v3.4

- Used Dashboard API to improve get_meeting_reports task
- Added meeting invite text to calendar and meeting page to provide phone details
- Zoom meetings now appear in Timeline block (Thanks nstefanski)
- Added basic Analytic indicators (Thanks danmarsden)
- Fixed calendar icon not showing up for non-Boost themes (Thanks danowar2k)
- Added support for Moodle 3.10
- Allow privileged users without Zoom to edit meetings (Thanks jrchamp)
- Fixed bugs related to scheduler support (Thanks jrchamp)
- Fixed participant count for meeting sessions so it only counts unique users
- Zoom descriptions keep HTML formatting (Thanks mhughes2k)
- Fixed failing DB schema checks (Thanks dvdcastro)
- Requiring passcodes is now a site wide configuration

v3.3

- Fixed problems with error handling (Thanks kbowlerarden and jrchamp)
- Added language translations for uk, pl, and ru (Thanks mkikets99)
- Thanks to kubilayagi for all his work on the Zoom plugin these past 2.5 years and good luck on future endeavors

v3.2

- Password/Passcode changes
  - Renamed passwords to passcodes
  - Added passcodes to Webinars (Thanks jrchamp)
  - Passcodes are now required
- Implement completion viewed when user joins meeting (Thanks nstefanski)
- License recycling improvement (Thanks mrvinceo)
- Added scheduler support (Thanks mhughes2k)
- Added support for Zoom API changes related to next_page_token and rate limiting
- Fixed error handling for non-English Zoom deployments
- Added Travis CI support

v3.1

- Added site config to mask participant data form appearing in reports (useful for sites that mask participant data, e.g., for HIPAA) (Thanks stopfstedt)

v3.0

- Support Retry-After header in Zoom API
- Supports longer Zoom meeting ids
- Added more meeting options: Mute upon entry, Enable waiting room, Only authenticated users. - Changed to be Host/Participant video to off by default
- Meeting have passwords set by default
- Improvements to "Get meeting report" task to better handle data errors
- Removed "Attendee attention" column in participant report, because it has been removed by Zoom
- Added a new setting 'proxyurl' that can be used to set a proxy as hostname:port. This will be used for communication with the Zoom API (but not anywhere else in Moodle). (Thanks pefeigl)
- Fixed meeting dates during restore (Thanks nstefanski)
- Added German translation (Thanks pefeigl)

v2.2

- Resized svg icon (Thanks stopfstedt)
- Fixed error handling for 'User not found on this account' (Thanks nstefanski and tzerafnx)
- Incorrect return value for zoom_update_instance (Thanks jrchamp)
- Added global search support
- Fixed inconsistent "start_time" column (Thanks tuanngocnguyen)

v2.1

- Moodle 3.7 support (Thanks danmarsden)
- Privacy API support
- Moodle mobile support fixed for 3.5 (Thanks nstefanski)
- iCal generation
- Various bug fixes/improvements.

v2.0.1

- Fixing conflicts with Firebase\JWT library. If more conflicts are found,
  please contact plugin maintainer to add to list in classes/webservice.php.

v2.0

- Updated to support Zoom API V2
- Added SVG icon for resolution independence (Thanks rrusso)
- Additional logging
- License recycling (Thanks tigusigalpa)
- Participant reports improved (local storage and added attentiveness score)
- GDPR compliance
- Support for alternative hosts

v1.7

- Lang string BOM fix (Thanks roperto/tonyjbutler)
- Support for proxy servers (Thanks jonof)
- Improved handling of meetings not found on Zoom
- Exporting of session participants to xls
- Improved participants report
- Fixing coding issues

v1.6

- Addressed coding issues brought up by a MoodleRooms review done for CSUN.

v1.5

- Fixed upgrade issues with PostgreSQL

v1.4

- Added missing lang string for cache.
- Updated activity chooser help text.
- Added support for webinars.
- Fixing Unicode issues.

v1.3

- Fixed join before host option.
- Added Zoom user reports.
- Added connection status checking on settings page.

v1.2

- Allowing Zoom users to be found by other login types than just SSO.

v1.1

- Issue #1: allow underscores in API key and secret.
- Issue #2: Fix language strings to not use concatenation.
- Added support for "group members only".

v1.0

- Initial release
