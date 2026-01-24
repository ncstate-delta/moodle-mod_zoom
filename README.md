# Zoom YT - Moodle Zoom Plugin with YouTube Integration

**Zoom YT** is an enhanced Moodle activity module that integrates Zoom video conferencing with automatic YouTube video publishing. It provides seamless management of Zoom meetings within Moodle courses, with the added capability to automatically upload Zoom cloud recordings to YouTube for easy student access.

## Key Features

### Core Zoom Functionality
- Create and manage Zoom meetings directly from Moodle
- Synchronize meeting details between Moodle and Zoom
- Support for meeting recordings, grading, and backup/restore
- Full integration with Moodle's activity completion and gradebook
- **Custom completion rules**: Mark activity complete based on attendance duration

### Category-Level Settings
- Configure different Zoom accounts for different course categories
- Allow departments or schools to use their own Zoom accounts
- **Granular inheritance**: Separately inherit Zoom API, Meeting Defaults, or YouTube settings
- Per-category default meeting settings (waiting room, audio options, auto-recording, etc.)

### YouTube Integration
- **Automatic Video Upload**: Zoom cloud recordings are automatically uploaded to YouTube
- **Multi-Level YouTube Channels**: Connect YouTube channels at site, category, or activity level
- **Smart Recording Selection**: Prioritizes "Active Speaker" recordings, falls back to "Shared Screen with Active Speaker"
- **Visibility Control**: Set videos as public, unlisted, or private (default: unlisted)
- **Video Gallery**: Beautiful thumbnail-based display of session recordings
- **View Tracking**: Track which students have viewed each video
- **Automatic Cleanup**: Optionally delete Zoom cloud recordings after YouTube upload
- **Manual Sync Buttons**: Trigger recording sync and YouTube upload on demand

### Meeting Access Control
- **Host/Teacher Early Access**: Hosts and teachers can start/join meetings early (default: 15 minutes before)
- **Configurable Participant Access**: Set how early participants can join (global, category, or per-activity)
- **Join Before Host Support**: Respects Zoom's "Join Anytime" setting
- **Course Page Join Button**: Optional "Join Now" button on the course page below activity description

### Auto Cloud Recording
- **Automatic Recording**: Meetings can be set to automatically start cloud recording
- **Configurable Defaults**: Set auto-recording at global, category, or activity level
- **Options**: None, Local recording, Cloud recording, or User's Zoom account default

## Prerequisites

This plugin is designed for Educational or Business Zoom accounts.

### Zoom API Setup

To connect to the Zoom APIs, this plugin requires a Server-to-Server OAuth app.

#### How Many OAuth Apps Do I Need?

**One OAuth app per Zoom account (subscription).** The OAuth app is created *within* a Zoom account and authenticates API calls for that account.

| Scenario | OAuth Apps Needed |
|----------|------------------|
| One Zoom account for entire Moodle site | 1 app (configure in global settings) |
| 3 departments, each with their own Zoom subscription | 3 apps (one per Zoom account, each configured at category level) |
| 3 departments sharing one Zoom account | 1 app (credentials can be reused across categories) |

**Example:** If your university has separate Zoom subscriptions for the Business School and Engineering School, each school's Zoom admin would create an OAuth app in their respective Zoom accounts. The credentials would then be entered in the Moodle category settings for each school's course category.

#### Creating the OAuth App
[Create an account-level Server-to-Server OAuth app](https://developers.zoom.us/docs/internal-apps/create/) with the required permissions. 

> **Note:** You only need to create the app once per Zoom account, regardless of how many Moodle sites or categories use that account.

The Server-to-Server OAuth app will generate:
- **Account ID** - Identifies the Zoom account
- **Client ID** - Used for OAuth authentication
- **Client Secret** - Keep this secure!

#### Required Scopes (Granular)

At a minimum:
- `meeting:read:meeting:admin` (Get meeting)
- `meeting:read:invitation:admin` (Get meeting invitation)
- `meeting:delete:meeting:admin` (Delete meeting)
- `meeting:update:meeting:admin` (Update meeting)
- `meeting:write:meeting:admin` (Create meeting)
- `user:read:list_schedulers:admin` (List schedulers)
- `user:read:settings:admin` (Get user settings)
- `user:read:user:admin` (Get user)

For YouTube integration (cloud recordings):
- `cloud_recording:read:list_recording_files:admin`
- `cloud_recording:read:list_user_recordings:admin`
- `cloud_recording:read:recording_settings:admin`
- `cloud_recording:delete:recording_file:admin` (optional, for auto-cleanup)

See the full list of optional scopes for additional features like webinars, reports, and tracking fields.

### YouTube API Setup

To enable YouTube integration, you need Google Cloud credentials. **YouTube API credentials are configured site-wide only** - categories and activities use the same credentials but can connect different YouTube channels.

#### Setting Up Google Cloud

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Enable the **YouTube Data API v3**
4. Go to **Credentials** → **Create Credentials** → **OAuth client ID**
5. Select **Web application**
6. Add your Moodle's OAuth redirect URLs:
   - `https://your-moodle-site/mod/zoomyt/youtube_oauth_site.php` (site-level)
   - `https://your-moodle-site/mod/zoomyt/youtube_oauth.php` (category-level)
   - `https://your-moodle-site/mod/zoomyt/youtube_oauth_activity.php` (activity-level)
7. Save the **Client ID** and **Client Secret**
8. Enter these in Moodle: **Site Administration** → **Plugins** → **Zoom YT** → **YouTube Integration**

#### Connecting YouTube Channels

YouTube channels can be connected at three levels:

| Level | Who Can Configure | Inherits From |
|-------|------------------|---------------|
| **Site** | Site administrators | N/A (default for all) |
| **Category** | Category managers | Site default |
| **Activity** | Teachers | Category → Site |

**Site-Level Connection:**
1. Go to **Site Administration** → **Plugins** → **Zoom YT**
2. Click "Connect to YouTube" in the YouTube Integration section
3. Sign in with your organization's YouTube account

**Category-Level Connection:**
1. Go to **Manage Category Zoom Settings**
2. Select a category
3. Uncheck "Inherit YouTube settings"
4. Click "Connect to YouTube"

**Activity-Level Connection:**
1. Edit the Zoom YT activity
2. Expand "YouTube Integration"
3. Uncheck "Use inherited YouTube channel"
4. Click "Connect to YouTube"

## Installation

1. [Install the plugin](https://docs.moodle.org/en/Installing_plugins#Installing_a_plugin) to the `/mod/zoomyt` folder in Moodle.

2. Configure the global Zoom settings:
   - Zoom account ID (`mod_zoomyt | accountid`)
   - Zoom client ID (`mod_zoomyt | clientid`)
   - Zoom client secret (`mod_zoomyt | clientsecret`)

3. (Optional) Configure YouTube integration:
   - YouTube Client ID and Secret
   - Connect default YouTube channel
   - Default visibility for YouTube videos
   - Temporary storage directory for video downloads
   - Storage space limit

4. (Optional) Configure category-level settings for departments with their own Zoom/YouTube accounts.

## Category-Level Configuration

Administrators can configure different Zoom and YouTube accounts for different course categories:

1. Navigate to **Site Administration** → **Plugins** → **Activity Modules** → **Zoom YT**
2. Click **Manage Category Zoom Settings**
3. Select a category and configure:
   - **Zoom API Settings**: Use inherited or configure separate Zoom account
   - **Default Meeting Settings**: Waiting room, audio, video, auto-recording, etc.
   - **YouTube Settings**: Connect a different YouTube channel

### Granular Inheritance

Categories can selectively inherit settings:

| Setting Group | What It Controls |
|--------------|------------------|
| Inherit Zoom API | Account ID, Client ID, Client Secret, API endpoint |
| Inherit Meeting Defaults | Waiting room, audio, video, auto-recording, etc. |
| Inherit YouTube | YouTube channel connection and visibility settings |

## YouTube Integration Workflow

1. **Meeting Recording**: When a Zoom meeting with cloud recording enabled ends, the recording is processed by Zoom.

2. **Recording Sync**: The scheduled task `get_meeting_recordings` runs every 3 hours to fetch recording info.

3. **YouTube Sync**: The task `sync_recordings_to_youtube` runs every 2 hours to upload recordings.

4. **Download**: The recording is downloaded to a temporary directory on the Moodle server.

5. **Upload**: The video is uploaded to the connected YouTube channel with:
   - Title from the Zoom session name
   - Description including the session date
   - Visibility setting (inherited from activity → category → site)

6. **Display**: Videos appear in the activity's video gallery with thumbnails.

7. **Cleanup** (optional): After a configurable number of days, the original Zoom cloud recording can be automatically deleted.

### Manual Sync

Teachers can manually trigger sync from the **Manage Recordings** page:
- **Check for Zoom Recordings**: Immediately fetch new cloud recordings
- **Sync to YouTube**: Immediately upload pending recordings

## Completion by Attendance

The plugin supports custom completion rules based on meeting attendance:

1. Enable completion tracking for the course
2. Edit the Zoom YT activity
3. In **Activity Completion**, set "Completion tracking" to a manual or automatic option
4. Set **Minimum attendance duration** (in minutes)

Students who attend for at least the specified duration will have the activity marked complete.

## Video Gallery

Both teachers and students see a video gallery on the activity page:

- **Tile View**: Thumbnails arranged in a grid (default for multiple videos)
- **List View**: Table with title, date, and duration columns
- **Click to Play**: Opens video in an embedded YouTube player
- **View Tracking**: Records when students watch videos

Teachers additionally can:
- Toggle video visibility (show/hide from students)
- View upload status for pending videos
- See all past Zoom sessions and their recording status
- Manually trigger recording sync and YouTube upload

## Scheduled Tasks

The plugin includes several scheduled tasks:

| Task | Schedule | Description |
|------|----------|-------------|
| `update_meetings` | Daily 4:30 AM | Sync meeting data from Zoom |
| `get_meeting_reports` | Every 2 hours | Fetch attendance reports |
| `get_meeting_recordings` | Every 3 hours | Fetch recording information from Zoom |
| `sync_recordings_to_youtube` | Every 2 hours | Upload recordings to YouTube |
| `delete_meeting_recordings` | Daily midnight | Delete old Zoom recordings |
| `send_ical_notifications` | Every 5 minutes | Send calendar invites |

## Storage Requirements

When uploading videos to YouTube, the plugin temporarily stores video files on the server:

- **Default location**: Moodle's temp directory (`$CFG->tempdir/zoomyt_videos`)
- **Storage limit**: Configurable (default 5GB)
- **Cleanup**: Files are deleted immediately after successful upload

Ensure your server has sufficient disk space for the largest expected recording.

## Events and Logging

The plugin logs the following events:

- `video_uploaded_to_youtube`: When a video is successfully uploaded
- `video_viewed`: When a user clicks to watch a video
- `youtube_connected`: When a YouTube channel is connected

## Troubleshooting

### "Access token is expired" errors
Ensure your server's date/time is properly synchronized with time servers.

### YouTube upload fails
1. Check the scheduled task logs for error details
2. Verify YouTube OAuth credentials are correct
3. Ensure the YouTube channel has permission to upload videos
4. Check available disk space for temporary storage

### Videos not appearing
1. Verify Zoom cloud recording is enabled for meetings
2. Check that the recording has been processed by Zoom
3. Wait for the next scheduled sync task to run, or use "Check for Zoom Recordings" button
4. Verify YouTube is connected for the activity's category (or site default)

### redirect_uri_mismatch error
Ensure all three redirect URIs are registered in Google Cloud Console:
- `https://your-site/mod/zoomyt/youtube_oauth_site.php`
- `https://your-site/mod/zoomyt/youtube_oauth.php`
- `https://your-site/mod/zoomyt/youtube_oauth_activity.php`

### 403: access_denied (app not verified)
If your Google Cloud app is in testing mode, add your Google account as a test user in the OAuth consent screen settings.

## Requirements

- Moodle 3.7 or higher
- PHP 7.2 or higher
- Zoom Educational or Business account
- YouTube channel with API access (for YouTube features)

## Planned Features / Roadmap

The following UI and UX improvements are planned for future releases:

### Activity Page Redesign
- [ ] **Compact Schedule Card**: Replace the current table-based schedule display with a modern, horizontal badge-based layout that takes less vertical space
- [ ] **Single Video Embed**: When a session has ended and there's only one recorded video, embed it directly on the activity page instead of requiring users to click through to a gallery
- [ ] **Dedicated Video Page**: Create a new page (`showvideo.php`) for viewing individual videos with full Moodle navigation, embedded player, and video metadata (title, date, description)

### Video Gallery Improvements
- [ ] **Direct Video Links**: Instead of opening videos in a modal, link directly to the dedicated video viewing page
- [ ] **Multiple Session Support**: For recurring meetings, display a tile/list view of all past session recordings below the schedule info

### Course Page Enhancements
- [ ] **Smart Activity Button**: On the main course page, if no future Zoom session is scheduled for the activity, replace the "Join Now" / "Meeting not yet available" buttons with a "Watch Recorded Session" button that links directly to the video gallery or embedded video

### Recording Management
- [ ] **Bulk Actions**: Select multiple recordings for bulk visibility changes or deletion
- [ ] **Recording Previews**: Show video thumbnails inline in the session list

## Version History

See [CHANGES.md](CHANGES.md) for detailed version history.

**Current Version**: v1.6.25

## License

This plugin is licensed under the [GNU GPL v3 or later](http://www.gnu.org/copyleft/gpl.html).

## Credits

Based on the original mod_zoom plugin by UC Regents.
YouTube integration and category-level settings by Tay Moss at the Innovative Ministry Centre of Toronto United Church Council.
