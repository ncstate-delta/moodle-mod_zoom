# Zoom YT - Moodle Zoom Plugin with YouTube Integration

**Zoom YT** is an enhanced Moodle activity module that integrates Zoom video conferencing with automatic YouTube video publishing. It provides seamless management of Zoom meetings within Moodle courses, with the added capability to automatically upload Zoom cloud recordings to YouTube for easy student access.

## Key Features

### Core Zoom Functionality
- Create and manage Zoom meetings directly from Moodle
- Synchronize meeting details between Moodle and Zoom
- Support for meeting recordings, grading, and backup/restore
- Full integration with Moodle's activity completion and gradebook

### Category-Level Settings
- Configure different Zoom accounts for different course categories
- Allow departments or schools to use their own Zoom accounts
- Inherit settings from parent categories or use global defaults
- Per-category default meeting settings (waiting room, audio options, etc.)

### YouTube Integration (New in v1.1)
- **Automatic Video Upload**: Zoom cloud recordings are automatically uploaded to YouTube
- **Category-Level YouTube Channels**: Connect different YouTube channels to different course categories
- **Smart Recording Selection**: Prioritizes "Active Speaker" recordings, falls back to "Shared Screen with Active Speaker"
- **Visibility Control**: Set videos as public, unlisted, or private (default: unlisted)
- **Video Gallery**: Beautiful thumbnail-based display of session recordings
- **View Tracking**: Track which students have viewed each video
- **Automatic Cleanup**: Optionally delete Zoom cloud recordings after YouTube upload

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

To enable YouTube integration, you need Google Cloud credentials and a YouTube channel.

#### How Many YouTube OAuth Setups Do I Need?

**One Google Cloud project can serve all YouTube channels.** Unlike Zoom, YouTube OAuth works differently:

- Create **one Google Cloud project** for your Moodle installation
- The OAuth credentials from that project can be used for **any YouTube channel**
- Each department connects their own YouTube channel using those same credentials
- The channel-specific authorization is handled when the admin clicks "Connect to YouTube"

| Component | How Many? |
|-----------|-----------|
| Google Cloud Project | 1 per Moodle installation |
| OAuth Client ID/Secret | 1 pair (reused across all categories) |
| YouTube Channel connections | 1 per category that wants YouTube uploads |

#### Creating Google Cloud Credentials

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Enable the **YouTube Data API v3**
4. Go to **Credentials** → **Create Credentials** → **OAuth client ID**
5. Select **Web application**
6. Add your Moodle's OAuth redirect URL: `https://your-moodle-site/mod/zoom_yt/youtube_oauth.php`
7. Save the **Client ID** and **Client Secret**

#### Connecting YouTube Channels

Each department/category that wants YouTube uploads will:
1. Enter the same Google Cloud Client ID and Secret in their category settings
2. Click "Connect to YouTube"
3. Sign in with the Google account that owns their department's YouTube channel
4. Authorize the connection

This creates a unique refresh token for each channel, stored securely in Moodle.

## Installation

1. [Install the plugin](https://docs.moodle.org/en/Installing_plugins#Installing_a_plugin) to the `/mod/zoom_yt` folder in Moodle.

2. Configure the global Zoom settings:
   - Zoom account ID (`mod_zoom_yt | accountid`)
   - Zoom client ID (`mod_zoom_yt | clientid`)
   - Zoom client secret (`mod_zoom_yt | clientsecret`)

3. (Optional) Configure YouTube integration:
   - Default visibility for YouTube videos
   - Temporary storage directory for video downloads
   - Storage space limit

4. (Optional) Configure category-level settings for departments with their own Zoom/YouTube accounts.

## Category-Level Configuration

Administrators can configure different Zoom and YouTube accounts for different course categories:

1. Navigate to **Site Administration** → **Plugins** → **Activity Modules** → **Zoom YT**
2. Click **Manage Category Zoom Settings**
3. Select a category and configure:
   - Zoom API credentials
   - YouTube OAuth connection
   - Default meeting and video settings

Categories without their own settings will inherit from their parent category, ultimately falling back to global settings.

## YouTube Integration Workflow

1. **Meeting Recording**: When a Zoom meeting with cloud recording enabled ends, the recording is processed by Zoom.

2. **Recording Sync**: The scheduled task `sync_recordings_to_youtube` runs every 2 hours to check for new recordings.

3. **Download**: The recording is downloaded to a temporary directory on the Moodle server.

4. **Upload**: The video is uploaded to the connected YouTube channel with:
   - Title from the Zoom session name
   - Description including the session date
   - Visibility setting (inherited from activity → category → global)

5. **Display**: Videos appear in the activity's video gallery with thumbnails.

6. **Cleanup** (optional): After a configurable number of days, the original Zoom cloud recording can be automatically deleted.

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

## Scheduled Tasks

The plugin includes several scheduled tasks:

| Task | Schedule | Description |
|------|----------|-------------|
| `update_meetings` | Daily 4:30 AM | Sync meeting data from Zoom |
| `get_meeting_reports` | Every 6 hours | Fetch attendance reports |
| `get_meeting_recordings` | Every 3 hours | Fetch recording information |
| `sync_recordings_to_youtube` | Every 2 hours | Upload recordings to YouTube |
| `delete_meeting_recordings` | Daily midnight | Delete old Zoom recordings |

## Storage Requirements

When uploading videos to YouTube, the plugin temporarily stores video files on the server:

- **Default location**: Moodle's temp directory (`$CFG->tempdir/zoom_yt_videos`)
- **Storage limit**: Configurable (default 5GB)
- **Cleanup**: Files are deleted immediately after successful upload

Ensure your server has sufficient disk space for the largest expected recording.

## Events and Logging

The plugin logs the following events:

- `video_uploaded_to_youtube`: When a video is successfully uploaded
- `video_viewed`: When a user clicks to watch a video
- `youtube_connected`: When a YouTube channel is connected to a category

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
3. Wait for the next scheduled sync task to run
4. Verify YouTube is connected for the course's category

## Requirements

- Moodle 3.7 or higher
- PHP 7.2 or higher
- Zoom Educational or Business account
- YouTube channel with API access (for YouTube features)

## License

This plugin is licensed under the [GNU GPL v3 or later](http://www.gnu.org/copyleft/gpl.html).

## Credits

Based on the original mod_zoom plugin by UC Regents.
YouTube integration and category-level settings by Tay Moss at the Innovative Minitry Centre of Toronto United Church Council.
