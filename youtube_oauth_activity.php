<?php
// This file is part of the Zoom YT plugin for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * YouTube OAuth callback handler for activity-level channel connection.
 *
 * Handles the OAuth flow for connecting a YouTube channel to an individual activity.
 * Uses site-wide YouTube API credentials.
 *
 * The activity ID (cmid) is passed via the OAuth state parameter to avoid issues
 * with Google's redirect URI validation (which requires exact matches).
 *
 * @package    mod_zoomyt
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/zoomyt/classes/youtube_service.php');

// Get parameters - id (cmid) can come from URL or from state parameter.
$cmid = optional_param('id', 0, PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$code = optional_param('code', '', PARAM_RAW);
$state = optional_param('state', '', PARAM_RAW);
$error = optional_param('error', '', PARAM_RAW);

// The redirect URI must be clean (no query parameters) to match Google's registered URI.
$redirecturi = (new moodle_url('/mod/zoomyt/youtube_oauth_activity.php'))->out(false);

// Get site-wide YouTube credentials (always use these).
$siteconfig = get_config('zoomyt');
$clientid = $siteconfig->youtube_client_id ?? '';
$clientsecret = $siteconfig->youtube_client_secret ?? '';

// If we have a code (OAuth callback), extract activity ID from state.
if (!empty($code) && !empty($state)) {
    // State format: sesskey_activity_cmid
    $stateparts = explode('_activity_', $state);
    if (count($stateparts) === 2) {
        $cmid = (int) $stateparts[1];
    }
}

// Course module ID is required.
if (empty($cmid)) {
    throw new moodle_exception('missingparam', '', '', 'id');
}

// Get the course module and activity.
$cm = get_coursemodule_from_id('zoomyt', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$zoom = $DB->get_record('zoomyt', ['id' => $cm->instance], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

// Require login and capability.
require_login($course, false, $cm);
require_capability('mod/zoomyt:addinstance', $context);

// Set up the page.
$PAGE->set_context($context);
$PAGE->set_url('/mod/zoomyt/youtube_oauth_activity.php', ['id' => $cmid]);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('youtube_connect', 'zoomyt'));
$PAGE->set_heading($course->fullname);

$returnurl = new moodle_url('/course/modedit.php', ['update' => $cmid]);

// Check credentials.
if (empty($clientid) || empty($clientsecret)) {
    \core\notification::error(get_string('youtube_site_credentials_required', 'zoomyt'));
    redirect($returnurl);
}

// Handle disconnect action.
if ($action === 'disconnect') {
    require_sesskey();

    // Clear YouTube channel settings for this activity.
    $DB->update_record('zoomyt', (object)[
        'id' => $zoom->id,
        'yt_use_category' => 1,
        'yt_channel_id' => '',
        'yt_channel_name' => '',
        'yt_refresh_token' => '',
    ]);

    \core\notification::success(get_string('youtube_disconnected', 'zoomyt'));
    redirect($returnurl);
}

// Handle error from Google.
if (!empty($error)) {
    \core\notification::error(get_string('youtube_oauth_error', 'zoomyt', $error));
    redirect($returnurl);
}

// Handle OAuth callback with code.
if (!empty($code)) {
    // Verify state - should start with current sesskey.
    $expectedprefix = sesskey() . '_activity_';
    if (strpos($state, $expectedprefix) !== 0) {
        \core\notification::error(get_string('youtube_oauth_state_mismatch', 'zoomyt'));
        redirect($returnurl);
    }

    try {
        // Exchange code for tokens using site-wide credentials.
        $tokens = \mod_zoomyt\youtube_service::exchange_code_for_tokens(
            $code,
            $redirecturi,
            $clientid,
            $clientsecret
        );

        // Get channel info.
        $credentials = (object)[
            'yt_client_id' => $clientid,
            'yt_client_secret' => $clientsecret,
            'yt_refresh_token' => $tokens->refresh_token,
        ];

        // Store access token temporarily for channel lookup.
        $cache = \cache::make('mod_zoomyt', 'oauth');
        $cache->set('yt_activity_' . $zoom->id . '_accesstoken', $tokens->access_token);
        $cache->set('yt_activity_' . $zoom->id . '_expires', time() + ($tokens->expires_in ?? 3600) - 60);

        $ytservice = new \mod_zoomyt\youtube_service($credentials, null, $zoom->id);
        $channel = $ytservice->get_channel_info();

        // Update activity with YouTube channel info.
        $DB->update_record('zoomyt', (object)[
            'id' => $zoom->id,
            'yt_use_category' => 0, // Now using own channel.
            'yt_channel_id' => $channel->id,
            'yt_channel_name' => $channel->title,
            'yt_refresh_token' => $tokens->refresh_token,
        ]);

        \core\notification::success(get_string('youtube_connected_success', 'zoomyt', $channel->title));
        redirect($returnurl);

    } catch (Exception $e) {
        \core\notification::error(get_string('youtube_oauth_error', 'zoomyt', $e->getMessage()));
        redirect($returnurl);
    }
}

// Start OAuth flow - redirect to Google using site-wide credentials.
// Pass activity ID in the state parameter (not in redirect URI).
$oauthstate = sesskey() . '_activity_' . $cmid;
$authurl = \mod_zoomyt\youtube_service::get_auth_url($clientid, $redirecturi, $oauthstate);
redirect($authurl);
