[![Build Status](https://travis-ci.org/ucla/moodle-mod_zoom.svg?branch=master)](https://travis-ci.org/github/ucla/moodle-mod_zoom/branches)

# Intro

Zoom is the web and app based video conferencing service (http://zoom.us). This plugin offers tight integration with Moodle, supporting meeting creation, synchronization, grading, and backup/restore.

# Prerequisites

This plugin is designed for Educational or Business Zoom accounts.

To connec to the Zoom APIs this plugin requires an account level JWT app to be
created. To create an account-level JWT app the Developer Role Permission is
required.

See https://marketplace.zoom.us/docs/guides/build/jwt-app. You will need to create a JWT app and that will generate the API key and secret.

## Installation

1. Install plugin to mod/zoom. More details at https://docs.moodle.org/39/en/Installing_plugins#Installing_a_plugin
2. Once you install the plugin you need to set the following set the following
   settings to enable the plugin:

- Zoom API key (mod_zoom | apikey)
- Zoom API secret (mod_zoom | apisecret)
- Zoom home page URL (mod_zoom | zoomurl), Link to your organization's custom Zoom landing page.

Please note that the API key and secret is not the same as the LTI key/secret.

## Changelog

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
  please contact plugin maintainer to add whitelist in classes/webservice.php.

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
