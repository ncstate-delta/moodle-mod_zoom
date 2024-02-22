# Intro

[Zoom](https://zoom.us) is a web- and app-based video conferencing service. This
plugin offers tight integration with Moodle, supporting meeting creation,
synchronization, grading and backup/restore.

## Prerequisites

This plugin is designed for Educational or Business Zoom accounts.

To connect to the Zoom APIs, this plugin requires an account-level app to be
created.

### Server-to-Server OAuth
To [create an account-level Server-to-Server OAuth app](https://developers.zoom.us/docs/internal-apps/create/), the `Server-to-server OAuth app`
permission is required. You should create a separate Server-to-Server OAuth app for each Moodle install.

The Server-to-Server OAuth app will generate a client ID, client secret and account ID.

At a minimum, the following scopes are required by this plugin:

- meeting:read:admin (Read meeting details)
- meeting:write:admin (Create/Update meetings)
- user:read:admin (Read user details)

Optional functionality can be enabled by granting additional scopes:

- Reports for meetings / webinars
    - dashboard_meetings:read:admin (Business accounts and higher)
    - dashboard_webinars:read:admin  (Business accounts and higher)
    - report:read:admin (Pro accounts and higher)
- Allow recordings to be viewed (zoom | viewrecordings)
    - recording:read:admin
- Tracking fields (zoom | defaulttrackingfields)
    - tracking_fields:read:admin
- Recycle licenses (zoom | utmost), (zoom | recycleonjoin)
    - user:write:admin
- Webinars (zoom | showwebinars), (zoom | webinardefault)
    - webinar:read:admin
    - webinar:write:admin

## Installation

1. [Install plugin](https://docs.moodle.org/en/Installing_plugins#Installing_a_plugin) to the /mod/zoom folder in Moodle.
2. After installing the plugin, the following settings need to be configured to use the plugin:

- Zoom account ID (mod_zoom | accountid)
- Zoom client ID (mod_zoom | clientid)
- Zoom client secret (mod_zoom | clientsecret)

If you get "Access token is expired" errors, make sure the date/time on your
server is properly synchronized with the time servers.
