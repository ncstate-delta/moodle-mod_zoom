# Intro

[Zoom](https://zoom.us) is a web- and app-based video conferencing service. This
plugin offers tight integration with Moodle, supporting meeting creation,
synchronization, grading and backup/restore.

## Try in Moodle Playground

Click the badge below to open the `main` branch instantly in [Moodle Playground](https://moodle-playground.com) with `mod_zoom` pre-installed. The playground boots a full Moodle 5.2 site with a demo course (`ZOOMDEMO01`) that contains a stub Zoom activity so you can preview the activity view, the `mod_form`, and the plugin's admin settings without any local setup. Every same-repo pull request also automatically generates a playground preview link appended to the PR description so reviewers can test changes in a live Moodle instance.

**Note:** the Zoom REST API is not reachable from the Playground sandbox. The Join / Start buttons will not work and you cannot create real meetings — this preview is intended for UI and admin-settings inspection only.

<a href="https://moodle-playground.com/?blueprint-url=https://raw.githubusercontent.com/ncstate-delta/moodle-mod_zoom/refs/heads/main/blueprint.json" target="_blank" rel="noopener"><img src="https://raw.githubusercontent.com/ateeducacion/action-moodle-playground-pr-preview/refs/heads/main/assets/playground-preview-button.svg" alt="Preview in Moodle Playground" width="200"></a>

The PR preview links are produced by the [ateeducacion/action-moodle-playground-pr-preview](https://github.com/ateeducacion/action-moodle-playground-pr-preview) GitHub Action, configured via `blueprint.json` at the repository root.

## Prerequisites

This plugin is designed for Educational or Business Zoom accounts.

To connect to the Zoom APIs, this plugin requires an account-level app to be
created.

### Server-to-Server OAuth
To [create an account-level Server-to-Server OAuth app](https://developers.zoom.us/docs/internal-apps/create/), the `Server-to-server OAuth app`
permission is required. You should create a separate Server-to-Server OAuth app for each Moodle install.

The Server-to-Server OAuth app will generate a client ID, client secret and account ID.

#### Granular scopes
At a minimum, the following scopes are required:

- meeting:read:meeting:admin (Get meeting)
- meeting:read:invitation:admin (Get meeting invitation)
- meeting:delete:meeting:admin (Delete meeting)
- meeting:update:meeting:admin (Update meeting)
- meeting:write:meeting:admin (Create meeting)
- user:read:list_schedulers:admin (List schedulers)
- user:read:settings:admin (Get user settings)
- user:read:user:admin (Get user)

Optional functionality can be enabled by granting additional scopes:

- Meeting registrations
    - meeting:read:list_registrants:admin (Get registrants)
- Reports for meetings / webinars (Licensed accounts and higher)
    - report:read:list_meeting_participants:admin
    - report:read:list_webinar_participants:admin
    - report:read:list_users:admin
    - report:read:user:admin
- Faster reports for meetings / webinars (Business accounts and higher)
    - dashboard:read:list_meeting_participants:admin
    - dashboard:read:list_meetings:admin
    - dashboard:read:list_webinar_participants:admin
    - dashboard:read:list_webinars:admin
- Allow recordings to be viewed (zoom | viewrecordings)
    - cloud_recording:read:list_recording_files:admin
    - cloud_recording:read:list_user_recordings:admin
    - cloud_recording:read:recording_settings:admin
- Tracking fields (zoom | defaulttrackingfields)
    - tracking_field:read:list_tracking_fields:admin
- Recycle licenses (zoom | utmost), (zoom | recycleonjoin), (zoom | protectedgroups)
    - group:read:list_groups:admin
    - user:read:list_users:admin
    - user:update:user:admin
- Webinars (zoom | showwebinars), (zoom | webinardefault)
    - webinar:read:list_registrants:admin
    - webinar:read:webinar:admin
    - webinar:delete:webinar:admin
    - webinar:update:webinar:admin
    - webinar:write:webinar:admin

#### Classic scopes
At a minimum, the following scopes are required:

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
- Recycle licenses (zoom | utmost), (zoom | recycleonjoin), (zoom | protectedgroups)
    - group:read:admin
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
