# Intro
Zoom is the web and app based video conferencing service (http://zoom.us). This plugin offers tight integration with Moodle, supporting meeting creation, synchronization, grading, and backup/restore.

## Installation
Requires Zoom API key and secret.
See https://marketplace.zoom.us/docs/guides/authorization/jwt/jwt-with-zoom. You will need to create a JWT app and that will generate the API key and secret.

You will need to have Zoom administrator access. Please note that the API key and secret is not the same as the LTI key/secret.

## Settings
Must set the following settings to enable the plugin:

* Zoom API key (mod_zoom | apikey)
* Zoom API secret (mod_zoom | apisecret)
* Zoom home page URL (mod_zoom | zoomurl), Link to your organization's custom Zoom landing page.

## Changelog

v2.2
* Resized svg icon (Thanks stopfstedt)
* Fixed error handling for 'User not found on this account' (Thanks nstefanski and tzerafnx)
* Incorrect return value for zoom_update_instance (Thanks jrchamp)
* Added global search support
* Fixed inconsistent "start_time" column (Thanks tuanngocnguyen)

v2.1
* Moodle 3.7 support (Thanks danmarsden) 
* Privacy API support
* Moodle mobile support fixed for 3.5 (Thanks nstefanski)
* iCal generation
* Various bug fixes/improvements.

v2.0.1

* Fixing conflicts with Firebase\JWT library. If more conflicts are found,
please contact plugin maintainer to add whitelist in classes/webservice.php.

v2.0

* Updated to support Zoom API V2
* Added SVG icon for resolution independence (Thanks rrusso)
* Additional logging
* License recycling (Thanks tigusigalpa)
* Participant reports improved (local storage and added attentiveness score)
* GDPR compliance
* Support for alternative hosts

v1.7

* Lang string BOM fix (Thanks roperto/tonyjbutler)
* Support for proxy servers (Thanks jonof)
* Improved handling of meetings not found on Zoom
* Exporting of session participants to xls
* Improved participants report
* Fixing coding issues

v1.6

* Addressed coding issues brought up by a MoodleRooms review done for CSUN.

v1.5

* Fixed upgrade issues with PostgreSQL

v1.4

* Added missing lang string for cache.
* Updated activity chooser help text.
* Added support for webinars.
* Fixing Unicode issues.

v1.3

* Fixed join before host option.
* Added Zoom user reports.
* Added connection status checking on settings page.

v1.2

* Allowing Zoom users to be found by other login types than just SSO.

v1.1

* Issue #1: allow underscores in API key and secret.
* Issue #2: Fix language strings to not use concatenation.
* Added support for "group members only".

v1.0

* Initial release
