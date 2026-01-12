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
 * YouTube OAuth handler for site-wide default channel.
 *
 * Handles the OAuth flow for connecting a default YouTube channel at site level.
 *
 * @package    mod_zoomyt
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/zoomyt/classes/youtube_service.php');

$action = optional_param('action', '', PARAM_ALPHA);
$code = optional_param('code', '', PARAM_RAW);
$state = optional_param('state', '', PARAM_RAW);
$error = optional_param('error', '', PARAM_RAW);

$context = context_system::instance();

// Require login and site admin capability.
require_login();
require_capability('moodle/site:config', $context);

// Set up the page.
$PAGE->set_context($context);
$PAGE->set_url('/mod/zoomyt/youtube_oauth_site.php');
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('youtube_site_connection', 'zoomyt'));
$PAGE->set_heading(get_string('youtube_site_connection', 'zoomyt'));

$returnurl = new moodle_url('/admin/settings.php', ['section' => 'modsettingzoomyt']);

// Handle disconnect action.
if ($action === 'disconnect') {
    require_sesskey();

    // Clear YouTube settings.
    unset_config('youtube_default_channel_id', 'zoomyt');
    unset_config('youtube_default_channel_name', 'zoomyt');
    unset_config('youtube_default_refresh_token', 'zoomyt');

    \core\notification::success(get_string('youtube_disconnected', 'zoomyt'));
    redirect($returnurl);
}

// Handle error from Google.
if (!empty($error)) {
    \core\notification::error(get_string('youtube_oauth_error', 'zoomyt', $error));
    redirect($returnurl);
}

// Get site-wide YouTube credentials.
$config = get_config('zoomyt');
$clientid = $config->youtube_client_id ?? '';
$clientsecret = $config->youtube_client_secret ?? '';

if (empty($clientid) || empty($clientsecret)) {
    \core\notification::error(get_string('youtube_credentials_required', 'zoomyt'));
    redirect($returnurl);
}

$redirecturi = (new moodle_url('/mod/zoomyt/youtube_oauth_site.php'))->out(false);

// Handle connect action - must be BEFORE any output.
if ($action === 'connect') {
    $state = sesskey() . '_site';
    $authurl = \mod_zoomyt\youtube_service::get_auth_url($clientid, $redirecturi, $state);
    redirect($authurl);
}

// Handle OAuth callback with code.
if (!empty($code)) {
    // Verify state.
    $expectedstate = sesskey() . '_site';
    if ($state !== $expectedstate) {
        \core\notification::error(get_string('youtube_oauth_state_mismatch', 'zoomyt'));
        redirect($returnurl);
    }

    try {
        // Exchange code for tokens.
        $tokens = \mod_zoomyt\youtube_service::exchange_code_for_tokens(
            $code,
            $redirecturi,
            $clientid,
            $clientsecret
        );

        // Get channel info using the new tokens.
        $credentials = (object)[
            'yt_client_id' => $clientid,
            'yt_client_secret' => $clientsecret,
            'yt_refresh_token' => $tokens->refresh_token,
        ];

        // Store access token temporarily for channel lookup.
        $cache = \cache::make('mod_zoomyt', 'oauth');
        $cache->set('yt_site_accesstoken', $tokens->access_token);
        $cache->set('yt_site_expires', time() + ($tokens->expires_in ?? 3600) - 60);

        $ytservice = new \mod_zoomyt\youtube_service($credentials, null, null);
        $channel = $ytservice->get_channel_info();

        // Save to site config.
        set_config('youtube_default_channel_id', $channel->id, 'zoomyt');
        set_config('youtube_default_channel_name', $channel->title, 'zoomyt');
        set_config('youtube_default_refresh_token', $tokens->refresh_token, 'zoomyt');

        \core\notification::success(get_string('youtube_connected_success', 'zoomyt', $channel->title));
        redirect($returnurl);

    } catch (Exception $e) {
        \core\notification::error(get_string('youtube_oauth_error', 'zoomyt', $e->getMessage()));
        redirect($returnurl);
    }
}

// If no action, show connection management page.
echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('youtube_site_connection', 'zoomyt'));

// Show current connection status.
$config = get_config('zoomyt');

echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');

if (!empty($config->youtube_default_channel_name)) {
    // Connected - show channel info and disconnect option.
    echo html_writer::tag('h5', get_string('youtube_site_channel_connected', 'zoomyt', $config->youtube_default_channel_name),
        ['class' => 'card-title text-success']);
    echo html_writer::tag('p', get_string('youtube_site_channel_desc', 'zoomyt'), ['class' => 'card-text']);

    // Disconnect button.
    $disconnecturl = new moodle_url('/mod/zoomyt/youtube_oauth_site.php', [
        'action' => 'disconnect',
        'sesskey' => sesskey(),
    ]);
    echo html_writer::link($disconnecturl, get_string('youtube_disconnect', 'zoomyt'), [
        'class' => 'btn btn-danger',
        'onclick' => "return confirm('" . get_string('youtube_disconnect_confirm', 'zoomyt') . "');",
    ]);

    // Change channel button.
    $connecturl = new moodle_url('/mod/zoomyt/youtube_oauth_site.php', ['action' => 'connect']);
    echo ' ';
    echo html_writer::link($connecturl, get_string('yt_change_channel', 'zoomyt'), ['class' => 'btn btn-outline-secondary']);

} else {
    // Not connected - show connect option.
    echo html_writer::tag('h5', get_string('youtube_site_channel_not_connected', 'zoomyt'),
        ['class' => 'card-title text-muted']);
    echo html_writer::tag('p', get_string('youtube_site_connect_desc', 'zoomyt'), ['class' => 'card-text']);

    if (empty($clientid) || empty($clientsecret)) {
        echo html_writer::tag('div', get_string('youtube_credentials_required', 'zoomyt'), ['class' => 'alert alert-warning']);
    } else {
        // Connect button - redirects to Google OAuth.
        $connecturl = new moodle_url('/mod/zoomyt/youtube_oauth_site.php', ['action' => 'connect']);
        echo html_writer::link($connecturl, get_string('youtube_connect', 'zoomyt'), ['class' => 'btn btn-primary']);
    }
}

echo html_writer::end_div();
echo html_writer::end_div();

// Back to settings link.
echo html_writer::tag('p', html_writer::link($returnurl, '&laquo; ' . get_string('back_to_settings', 'zoomyt')));

echo $OUTPUT->footer();
