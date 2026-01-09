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
 * @package    mod_zoom_yt
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoom_yt\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');
require_once($CFG->dirroot . '/mod/zoom_yt/locallib.php');

/**
 * Form for editing category-level Zoom settings.
 */
class category_settings_form extends \moodleform {

    /**
     * Define the form elements.
     */
    protected function definition() {
        $mform = $this->_form;
        $categoryid = $this->_customdata['categoryid'];
        $category = $this->_customdata['category'];

        // Hidden category ID.
        $mform->addElement('hidden', 'categoryid', $categoryid);
        $mform->setType('categoryid', PARAM_INT);

        // Header: Inheritance.
        $mform->addElement('header', 'inheritheader', get_string('categorysettings_inherit', 'zoom_yt'));

        // Inherit from parent checkbox.
        $mform->addElement('advcheckbox', 'inherit', get_string('inherit_from_parent', 'zoom_yt'),
            get_string('inherit_from_parent_desc', 'zoom_yt'));
        $mform->setDefault('inherit', 1);
        $mform->addHelpButton('inherit', 'inherit_from_parent', 'zoom_yt');

        // Header: Zoom Account Connection.
        $mform->addElement('header', 'connectionheader', get_string('categorysettings_connection', 'zoom_yt'));

        // Account ID.
        $mform->addElement('text', 'accountid', get_string('accountid', 'zoom_yt'), ['size' => 60]);
        $mform->setType('accountid', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('accountid', 'accountid', 'zoom_yt');
        $mform->hideIf('accountid', 'inherit', 'checked');

        // Client ID.
        $mform->addElement('text', 'clientid', get_string('clientid', 'zoom_yt'), ['size' => 60]);
        $mform->setType('clientid', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('clientid', 'clientid', 'zoom_yt');
        $mform->hideIf('clientid', 'inherit', 'checked');

        // Client Secret.
        $mform->addElement('passwordunmask', 'clientsecret', get_string('clientsecret', 'zoom_yt'), ['size' => 60]);
        $mform->setType('clientsecret', PARAM_RAW);
        $mform->addHelpButton('clientsecret', 'clientsecret', 'zoom_yt');
        $mform->hideIf('clientsecret', 'inherit', 'checked');

        // API Endpoint.
        $apiendpointoptions = [
            'global' => get_string('apiendpoint_global', 'zoom_yt'),
            'eu' => get_string('apiendpoint_eu', 'zoom_yt'),
        ];
        $mform->addElement('select', 'apiendpoint', get_string('apiendpoint', 'zoom_yt'), $apiendpointoptions);
        $mform->setDefault('apiendpoint', 'global');
        $mform->addHelpButton('apiendpoint', 'apiendpoint', 'zoom_yt');
        $mform->hideIf('apiendpoint', 'inherit', 'checked');

        // Zoom URL (optional).
        $mform->addElement('text', 'zoomurl', get_string('zoomurl', 'zoom_yt'), ['size' => 60]);
        $mform->setType('zoomurl', PARAM_URL);
        $mform->addHelpButton('zoomurl', 'zoomurl', 'zoom_yt');
        $mform->hideIf('zoomurl', 'inherit', 'checked');

        // Header: Default Meeting Settings.
        $mform->addElement('header', 'defaultsheader', get_string('categorysettings_defaults', 'zoom_yt'));
        $mform->setExpanded('defaultsheader', false);

        // Default recurring meeting.
        $mform->addElement('advcheckbox', 'defaultrecurring', get_string('recurringmeeting', 'zoom_yt'));
        $mform->setDefault('defaultrecurring', 0);
        $mform->hideIf('defaultrecurring', 'inherit', 'checked');

        // Default waiting room.
        $mform->addElement('advcheckbox', 'defaultwaitingroom', get_string('option_waiting_room', 'zoom_yt'));
        $mform->setDefault('defaultwaitingroom', 1);
        $mform->hideIf('defaultwaitingroom', 'inherit', 'checked');

        // Default join before host.
        $mform->addElement('advcheckbox', 'defaultjoinbeforehost', get_string('option_jbh', 'zoom_yt'));
        $mform->setDefault('defaultjoinbeforehost', 0);
        $mform->hideIf('defaultjoinbeforehost', 'inherit', 'checked');

        // Default audio option.
        $audiooptions = [
            ZOOM_AUDIO_BOTH => get_string('audio_both', 'zoom_yt'),
            ZOOM_AUDIO_TELEPHONY => get_string('audio_telephony', 'zoom_yt'),
            ZOOM_AUDIO_VOIP => get_string('audio_voip', 'zoom_yt'),
        ];
        $mform->addElement('select', 'defaultaudiooption', get_string('option_audio', 'zoom_yt'), $audiooptions);
        $mform->setDefault('defaultaudiooption', ZOOM_AUDIO_BOTH);
        $mform->hideIf('defaultaudiooption', 'inherit', 'checked');

        // Default host video.
        $mform->addElement('advcheckbox', 'defaulthostvideo', get_string('option_host_video', 'zoom_yt'));
        $mform->setDefault('defaulthostvideo', 0);
        $mform->hideIf('defaulthostvideo', 'inherit', 'checked');

        // Default participants video.
        $mform->addElement('advcheckbox', 'defaultparticipantsvideo', get_string('option_participants_video', 'zoom_yt'));
        $mform->setDefault('defaultparticipantsvideo', 0);
        $mform->hideIf('defaultparticipantsvideo', 'inherit', 'checked');

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

        // If not inheriting, require at least account ID and client ID.
        if (empty($data['inherit'])) {
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
