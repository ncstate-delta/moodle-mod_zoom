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
 * Category settings form for Zoom YT.
 *
 * @package    mod_zoomyt
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoomyt\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/mod/zoomyt/locallib.php');

/**
 * Form for editing category-level Zoom settings.
 */
class category_settings_form extends \moodleform {

    /**
     * Define the form elements.
     */
    protected function definition() {
        global $CFG;

        $mform = $this->_form;
        $categoryid = $this->_customdata['categoryid'];
        $category = $this->_customdata['category'];

        // Hidden category ID.
        $mform->addElement('hidden', 'categoryid', $categoryid);
        $mform->setType('categoryid', PARAM_INT);

        // Legacy inherit field (hidden, for backwards compatibility).
        $mform->addElement('hidden', 'inherit', 1);
        $mform->setType('inherit', PARAM_INT);

        // =====================================
        // SECTION 1: Zoom Account Connection
        // =====================================
        $mform->addElement('header', 'connectionheader', get_string('categorysettings_connection', 'zoomyt'));

        // Inherit Zoom settings checkbox.
        $mform->addElement('advcheckbox', 'inherit_zoom', get_string('inherit_zoom_settings', 'zoomyt'),
            get_string('inherit_zoom_settings_desc', 'zoomyt'));
        $mform->setDefault('inherit_zoom', 1);
        $mform->addHelpButton('inherit_zoom', 'inherit_zoom_settings', 'zoomyt');

        // Account ID.
        $mform->addElement('text', 'accountid', get_string('accountid', 'zoomyt'), ['size' => 60]);
        $mform->setType('accountid', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('accountid', 'accountid', 'zoomyt');
        $mform->hideIf('accountid', 'inherit_zoom', 'checked');

        // Client ID.
        $mform->addElement('text', 'clientid', get_string('clientid', 'zoomyt'), ['size' => 60]);
        $mform->setType('clientid', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('clientid', 'clientid', 'zoomyt');
        $mform->hideIf('clientid', 'inherit_zoom', 'checked');

        // Client Secret.
        $mform->addElement('passwordunmask', 'clientsecret', get_string('clientsecret', 'zoomyt'), ['size' => 60]);
        $mform->setType('clientsecret', PARAM_RAW);
        $mform->addHelpButton('clientsecret', 'clientsecret', 'zoomyt');
        $mform->hideIf('clientsecret', 'inherit_zoom', 'checked');

        // API Endpoint.
        $apiendpointoptions = [
            'global' => get_string('apiendpoint_global', 'zoomyt'),
            'eu' => get_string('apiendpoint_eu', 'zoomyt'),
        ];
        $mform->addElement('select', 'apiendpoint', get_string('apiendpoint', 'zoomyt'), $apiendpointoptions);
        $mform->setDefault('apiendpoint', 'global');
        $mform->addHelpButton('apiendpoint', 'apiendpoint', 'zoomyt');
        $mform->hideIf('apiendpoint', 'inherit_zoom', 'checked');

        // Zoom URL (optional).
        $mform->addElement('text', 'zoomurl', get_string('zoomurl', 'zoomyt'), ['size' => 60]);
        $mform->setType('zoomurl', PARAM_URL);
        $mform->addHelpButton('zoomurl', 'zoomurl', 'zoomyt');
        $mform->hideIf('zoomurl', 'inherit_zoom', 'checked');

        // =====================================
        // SECTION 2: Default Meeting Settings
        // =====================================
        $mform->addElement('header', 'defaultsheader', get_string('categorysettings_defaults', 'zoomyt'));
        $mform->setExpanded('defaultsheader', false);

        // Inherit Meeting Defaults checkbox.
        $mform->addElement('advcheckbox', 'inherit_meeting_defaults', get_string('inherit_meeting_defaults', 'zoomyt'),
            get_string('inherit_meeting_defaults_desc', 'zoomyt'));
        $mform->setDefault('inherit_meeting_defaults', 1);
        $mform->addHelpButton('inherit_meeting_defaults', 'inherit_meeting_defaults', 'zoomyt');

        // Default recurring meeting.
        $mform->addElement('advcheckbox', 'defaultrecurring', get_string('recurringmeeting', 'zoomyt'));
        $mform->setDefault('defaultrecurring', 0);
        $mform->hideIf('defaultrecurring', 'inherit_meeting_defaults', 'checked');

        // Default waiting room.
        $mform->addElement('advcheckbox', 'defaultwaitingroom', get_string('option_waiting_room', 'zoomyt'));
        $mform->setDefault('defaultwaitingroom', 1);
        $mform->hideIf('defaultwaitingroom', 'inherit_meeting_defaults', 'checked');

        // Default join before host.
        $mform->addElement('advcheckbox', 'defaultjoinbeforehost', get_string('option_jbh', 'zoomyt'));
        $mform->setDefault('defaultjoinbeforehost', 0);
        $mform->hideIf('defaultjoinbeforehost', 'inherit_meeting_defaults', 'checked');

        // Default audio option.
        $audiooptions = [
            ZOOM_AUDIO_BOTH => get_string('audio_both', 'zoomyt'),
            ZOOM_AUDIO_TELEPHONY => get_string('audio_telephony', 'zoomyt'),
            ZOOM_AUDIO_VOIP => get_string('audio_voip', 'zoomyt'),
        ];
        $mform->addElement('select', 'defaultaudiooption', get_string('option_audio', 'zoomyt'), $audiooptions);
        $mform->setDefault('defaultaudiooption', ZOOM_AUDIO_BOTH);
        $mform->hideIf('defaultaudiooption', 'inherit_meeting_defaults', 'checked');

        // Default host video.
        $mform->addElement('advcheckbox', 'defaulthostvideo', get_string('option_host_video', 'zoomyt'));
        $mform->setDefault('defaulthostvideo', 0);
        $mform->hideIf('defaulthostvideo', 'inherit_meeting_defaults', 'checked');

        // Default participants video.
        $mform->addElement('advcheckbox', 'defaultparticipantsvideo', get_string('option_participants_video', 'zoomyt'));
        $mform->setDefault('defaultparticipantsvideo', 0);
        $mform->hideIf('defaultparticipantsvideo', 'inherit_meeting_defaults', 'checked');

        // Default auto recording.
        $autorecordingoptions = [
            '' => get_string('usedefault', 'zoomyt'),
            ZOOM_AUTORECORDING_NONE => get_string('autorecording_none', 'zoomyt'),
            ZOOM_AUTORECORDING_LOCAL => get_string('autorecording_local', 'zoomyt'),
            ZOOM_AUTORECORDING_CLOUD => get_string('autorecording_cloud', 'zoomyt'),
        ];
        $mform->addElement('select', 'defaultautorecording', get_string('option_auto_recording', 'zoomyt'), $autorecordingoptions);
        $mform->setDefault('defaultautorecording', '');
        $mform->addHelpButton('defaultautorecording', 'option_auto_recording', 'zoomyt');
        $mform->hideIf('defaultautorecording', 'inherit_meeting_defaults', 'checked');

        // =====================================
        // SECTION 3: YouTube Integration
        // =====================================
        $mform->addElement('header', 'youtubeheader', get_string('youtube_settings', 'zoomyt'));
        $mform->setExpanded('youtubeheader', true);

        // Inherit YouTube settings checkbox.
        $mform->addElement('advcheckbox', 'inherit_youtube', get_string('inherit_youtube_settings', 'zoomyt'),
            get_string('inherit_youtube_settings_desc', 'zoomyt'));
        $mform->setDefault('inherit_youtube', 1);
        $mform->addHelpButton('inherit_youtube', 'inherit_youtube_settings', 'zoomyt');

        // Check if site-wide YouTube credentials are configured.
        $siteconfig = get_config('zoomyt');
        $hascredentials = !empty($siteconfig->youtube_client_id) && !empty($siteconfig->youtube_client_secret);

        if (!$hascredentials) {
            // Show message that site-wide credentials are required.
            $mform->addElement('static', 'yt_no_credentials', '',
                \html_writer::tag('div', get_string('youtube_site_credentials_required', 'zoomyt'),
                    ['class' => 'alert alert-warning']));
        } else {
            // Show current channel connection status.
            if (!empty($this->_customdata['yt_channel_name'])) {
                // Channel is connected.
                $channelinfo = \html_writer::tag('span',
                    get_string('youtube_category_channel_connected', 'zoomyt', $this->_customdata['yt_channel_name']),
                    ['class' => 'text-success']);
                $mform->addElement('static', 'yt_channel_display', get_string('youtube_channel', 'zoomyt'), $channelinfo);
                $mform->hideIf('yt_channel_display', 'inherit_youtube', 'checked');

                // Disconnect button.
                $disconnecturl = new \moodle_url('/mod/zoomyt/youtube_oauth.php', [
                    'categoryid' => $categoryid,
                    'action' => 'disconnect',
                    'sesskey' => sesskey(),
                ]);
                $mform->addElement('static', 'youtube_disconnect', '',
                    \html_writer::link($disconnecturl, get_string('youtube_disconnect', 'zoomyt'),
                        ['class' => 'btn btn-outline-danger btn-sm']));
                $mform->hideIf('youtube_disconnect', 'inherit_youtube', 'checked');

                // Change channel button.
                $connecturl = new \moodle_url('/mod/zoomyt/youtube_oauth.php', ['categoryid' => $categoryid]);
                $mform->addElement('static', 'youtube_change', '',
                    \html_writer::link($connecturl, get_string('yt_change_channel', 'zoomyt'),
                        ['class' => 'btn btn-outline-secondary btn-sm']));
                $mform->hideIf('youtube_change', 'inherit_youtube', 'checked');

            } else {
                // No channel connected - show connect button.
                $mform->addElement('static', 'yt_channel_display', get_string('youtube_channel', 'zoomyt'),
                    \html_writer::tag('span', get_string('youtube_category_channel_not_connected', 'zoomyt'),
                        ['class' => 'text-muted']));
                $mform->hideIf('yt_channel_display', 'inherit_youtube', 'checked');

                $connecturl = new \moodle_url('/mod/zoomyt/youtube_oauth.php', ['categoryid' => $categoryid]);
                $mform->addElement('static', 'youtube_connect', '',
                    \html_writer::link($connecturl, get_string('youtube_connect', 'zoomyt'),
                        ['class' => 'btn btn-primary btn-sm']));
                $mform->hideIf('youtube_connect', 'inherit_youtube', 'checked');
            }
        }

        // Hidden fields for YouTube channel data.
        $mform->addElement('hidden', 'yt_channel_id');
        $mform->setType('yt_channel_id', PARAM_RAW);

        $mform->addElement('hidden', 'yt_channel_name');
        $mform->setType('yt_channel_name', PARAM_RAW);

        $mform->addElement('hidden', 'yt_refresh_token');
        $mform->setType('yt_refresh_token', PARAM_RAW);

        // Default YouTube visibility.
        $visibilityoptions = [
            'unlisted' => get_string('youtube_visibility_unlisted', 'zoomyt'),
            'public' => get_string('youtube_visibility_public', 'zoomyt'),
            'private' => get_string('youtube_visibility_private', 'zoomyt'),
        ];
        $mform->addElement('select', 'yt_default_visibility', get_string('youtube_default_visibility', 'zoomyt'), $visibilityoptions);
        $mform->setDefault('yt_default_visibility', 'unlisted');
        $mform->addHelpButton('yt_default_visibility', 'youtube_default_visibility', 'zoomyt');
        $mform->hideIf('yt_default_visibility', 'inherit_youtube', 'checked');

        // Days to keep Zoom recordings after upload.
        $deleteoptions = [
            '' => get_string('never_delete', 'zoomyt'),
            '1' => '1 ' . get_string('day'),
            '7' => '7 ' . get_string('days'),
            '14' => '14 ' . get_string('days'),
            '30' => '30 ' . get_string('days'),
            '60' => '60 ' . get_string('days'),
            '90' => '90 ' . get_string('days'),
        ];
        $mform->addElement('select', 'zoom_recording_delete_days', get_string('zoom_recording_delete_days', 'zoomyt'), $deleteoptions);
        $mform->setDefault('zoom_recording_delete_days', '');
        $mform->addHelpButton('zoom_recording_delete_days', 'zoom_recording_delete_days', 'zoomyt');
        $mform->hideIf('zoom_recording_delete_days', 'inherit_youtube', 'checked');

        // Buttons.
        $this->add_action_buttons(true, get_string('savechanges'));
    }

    /**
     * Validate the form data.
     *
     * @param array $data Form data.
     * @param array $files Uploaded files.
     * @return array Validation errors.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // If not inheriting Zoom settings, require credentials.
        if (empty($data['inherit_zoom'])) {
            // Allow empty settings (to clear), but if any credential is provided, all must be.
            $hasany = !empty($data['accountid']) || !empty($data['clientid']) || !empty($data['clientsecret']);
            $hasall = !empty($data['accountid']) && !empty($data['clientid']) && !empty($data['clientsecret']);

            if ($hasany && !$hasall) {
                if (empty($data['accountid'])) {
                    $errors['accountid'] = get_string('required');
                }
                if (empty($data['clientid'])) {
                    $errors['clientid'] = get_string('required');
                }
                if (empty($data['clientsecret'])) {
                    $errors['clientsecret'] = get_string('required');
                }
            }
        }

        return $errors;
    }
}
