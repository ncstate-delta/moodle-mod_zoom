# Intro
Zoom is the web and app based video conferencing service (http://zoom.us). This plugin offers tight integration with Moodle, supporting meeting creation, synchronization, grading, and backup/restore.

## Installation
Requires zoom API key and secret.
See https://support.zoom.us/hc/en-us/articles/201363043-Getting-Started-with-REST-API

## Settings
Must set the following settings to enable the plugin:

* Zoom API url (mod_zoom | apiurl), Default: https://api.zoom.us/v1/
* Zoom API key (mod_zoom | apikey)
* Zoom API secret (mod_zoom | apisecret)
* Zoom home page URL (mod_zoom | zoomurl), Link to your organization's custom Zoom landing page.
* Login types (mod_zoom | logintypes), Depending on your Zoom instance, how should the plug-in find users from Moodle in Zoom?

## Changelog

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