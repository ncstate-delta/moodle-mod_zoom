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
 * YouTube OAuth callback handler.
 *
 * Handles the OAuth flow for connecting a YouTube channel to a category.
 *
 * @package    mod_zoom_yt
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/zoom_yt/classes/youtube_service.php');
require_once($CFG->dirroot . '/mod/zoom_yt/classes/category_settings.php');

$categoryid = required_param('categoryid', PARAM_INT);
$code = optional_param('code', '', PARAM_RAW);
$state = optional_param('state', '', PARAM_RAW);
$error = optional_param('error', '', PARAM_RAW);

// Get the category.
$category = $DB->get_record('course_categories', ['id' => $categoryid], '*', MUST_EXIST);
$context = context_coursecat::instance($categoryid);

// Require login and capability.
require_login();
require_capability('mod/zoom_yt:managecategorysettings', $context);

// Set up the page.
$PAGE->set_context($context);
$PAGE->set_url('/mod/zoom_yt/youtube_oauth.php', ['categoryid' => $categoryid]);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('youtube_connect', 'zoom_yt'));
$PAGE->set_heading($category->name);

$returnurl = new moodle_url('/mod/zoom_yt/categorysettings.php', ['categoryid' => $categoryid]);

// Handle error from Google.
if (!empty($error)) {
    \core\notification::error(get_string('youtube_oauth_error', 'zoom_yt', $error));
    redirect($returnurl);
}

// Get category settings.
$settingsmanager = new \mod_zoom_yt\category_settings($categoryid);
$settings = $settingsmanager->get_raw_settings();

if (!$settings || empty($settings->yt_client_id) || empty($settings->yt_client_secret)) {
    \core\notification::error(get_string('youtube_credentials_required', 'zoom_yt'));
    redirect($returnurl);
}

$redirecturi = (new moodle_url('/mod/zoom_yt/youtube_oauth.php', ['categoryid' => $categoryid]))->out(false);

// Handle OAuth callback with code.
if (!empty($code)) {
    // Verify state.
    $expectedstate = sesskey() . '_' . $categoryid;
    if ($state !== $expectedstate) {
        \core\notification::error(get_string('youtube_oauth_state_mismatch', 'zoom_yt'));
        redirect($returnurl);
    }

    try {
        // Exchange code for tokens.
        $tokens = \mod_zoom_yt\youtube_service::exchange_code_for_tokens(
            $code,
            $redirecturi,
            $settings->yt_client_id,
            $settings->yt_client_secret
        );

        // Update settings with refresh token.
        $updatedata = new stdClass();
        $updatedata->yt_client_id = $settings->yt_client_id;
        $updatedata->yt_client_secret = $settings->yt_client_secret;
        $updatedata->yt_refresh_token = $tokens->refresh_token;
        $updatedata->inherit = 0;

        // Get channel info.
        $credentials = (object)[
            'yt_client_id' => $settings->yt_client_id,
            'yt_client_secret' => $settings->yt_client_secret,
            'yt_refresh_token' => $tokens->refresh_token,
        ];

        // Store access token temporarily for channel lookup.
        $cache = \cache::make('mod_zoom_yt', 'oauth');
        $cache->set('yt_' . $categoryid . '_accesstoken', $tokens->access_token);
        $cache->set('yt_' . $categoryid . '_expires', time() + ($tokens->expires_in ?? 3600) - 60);

        $ytservice = new \mod_zoom_yt\youtube_service($credentials, $categoryid);
        $channel = $ytservice->get_channel_info();

        $updatedata->yt_channel_id = $channel->id;
        $updatedata->yt_channel_name = $channel->title;

        $settingsmanager->save_settings($updatedata);

        // Log the connection event.
        $event = \mod_zoom_yt\event\youtube_connected::create([
            'context' => $context,
            'other' => [
                'channel_id' => $channel->id,
                'channel_name' => $channel->title,
            ],
        ]);
        $event->trigger();

        \core\notification::success(get_string('youtube_connected_success', 'zoom_yt', $channel->title));
        redirect($returnurl);

    } catch (Exception $e) {
        \core\notification::error(get_string('youtube_oauth_error', 'zoom_yt', $e->getMessage()));
        redirect($returnurl);
    }
}

// Start OAuth flow - redirect to Google.
$state = sesskey() . '_' . $categoryid;
$authurl = \mod_zoom_yt\youtube_service::get_auth_url($settings->yt_client_id, $redirecturi, $state);
redirect($authurl);
