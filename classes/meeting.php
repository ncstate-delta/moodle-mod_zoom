<?php
// This file is part of the Zoom plugin for Moodle - http://moodle.org/
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
 * Contains the class for Zoom meetings
 *
 * @package   mod_zoom
 * @copyright 2015 UC Regents
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

define('API_URL', 'https://api.zoom.us/v2/');

/**
 * A class to represent zoom meetings.
 *
 * @package    mod_zoom
 * @copyright  2015 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_zoom_meeting extends mod_zoom_instance {
    // Type constants.
    const SCHEDULED_MEETING = 2;
    const RECURRING_MEETING_WITHOUT_FIXED_TIME = 3;
    const RECURRING_MEETING_WITH_FIXED_TIME = 8;

    /**
     * Whether to start video when participants join the meeting.
     * @var bool
     */
    protected $participantsvideo;

    /**
     * Whether participants can join the meeting before the host starts it.
     * @var bool
     */
    protected $joinbeforehost;

    /**
     * Converts this instance's data fields to a format that the Zoom API accepts.
     */
    public function export_to_api_format() {
        $data = parent::export_to_api_format();
        $data['settings']['join_before_host'] = (bool) ($this->joinbeforehost);
        $data['settings']['participant_video'] = (bool) ($this->participantsvideo);
        return $data;
    }

    /**
     * Populate this meeting's fields using data returned by a Zoom API call.
     */
    public function populate_from_API_response($response) {
        parent::populate_from_API_response($response);
        if (isset($response->password)) {
            $this->password = $response->password;
        }
        if (isset($response->settings->join_before_host)) {
            $this->joinbeforehost = $response->settings->join_before_host;
        }
        if (isset($response->settings->participant_video)) {
            $this->participantsvideo = $response->settings->participant_video;
        }
    }

    /**
     * Populate this instance's fields using data returned by mod_form.php.
     */
    protected function populate_from_mod_form($formdata) {
        parent::populate_from_mod_form($formdata);
        // Stores the name equality between fields i.e. 'form' => 'object'.
        $fieldalignment = array(
            'option_participants_video' => 'participantsvideo',
            'option_jbh' => 'joinbeforehost'
        );
        foreach ($fieldalignment as $formfield => $objectfield) {
            $this->objectfield = $formdata->formfield;
        }
    }

    /**
     * Converts this instance's data fields to a format used by the Moodle database.
     */
    public function export_to_database_format() {
        $data = parent::export_to_database_format();
        // Stores the name equality between fields i.e. 'database' => 'object'.
        $fieldalignment = array(
            'option_jbh' =>'joinbeforehost',
            'option_participants_video' => 'participantsvideo'
        );
        foreach ($fieldalignment as $databasefield => $objectfield) {
            $data->databasefield = $this->objectfield;
        }
        $data->webinar = 0;
        return $data;
    }

    /**
     * Simply returns whether the instance is a webinar.
     */
    public static function is_webinar() {
        return False;
    }
}
