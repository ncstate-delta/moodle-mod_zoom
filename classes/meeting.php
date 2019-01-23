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
 * A class to represent general zoom instances (either meetings or webinars).
 *
 * @package    mod_zoom
 * @copyright  2015 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class zoom_instance {
    // Recurrence constants.
    const NO_RECURRENCE = 0; // Defined by CCLE, not Zoom.
    const DAILY = 1;
    const WEEKLY = 2;
    const MONTHLY = 3;

    /**
     * The instance host's ID on Zoom servers.
     * @var string
     */
    protected $hostid;

    /**
     * The instance's name.
     * 'topic' on Zoom API.
     * @var string
     */
    protected $name;

    /**
     * The instance type (with respect to timing).
     * Uses class constants.
     * @var int
     */
    protected $type;

    /**
     * The time at which the instance starts.
     * Stored in epoch time format.
     * @var int
     */
    protected $starttime;

    /**
     * The meeting instance in seconds.
     * @var int
     */
    protected $duration;

    /**
     * The password required to join the meeting.
     * @var string
     */
    protected $password;

    /**
     * The meeting's description.
     * 'agenda' on Zoom API.
     * 'intro' in database.
     * @var string
     */
    protected $description;

    /**
     * The ID of the course to which the meeting belongs.
     * @var string
     */
    protected $course;

    /**
     * The instance's ID on Zoom servers.
     * TODO 'uuid' or 'id' on Zoom API.
     * @var int
     */
    protected $id;

    /**
     * The URL to start the meeting.
     * @var string
     */
    protected $startURL;

    /**
     * The URL to join the meeting.
     * @var string
     */
    protected $joinURL;

    /**
     * Whether to start video when the host joins the meeting.
     * @var bool
     */
    protected $host_video;

    /**
     * How participants can join the audio portion of the meeting.
     * Possible values: both, telephony, voip.
     * @var string
     */
    protected $audio;

    /**
     * Other users that can start the meeting.
     * @var string
     */
    protected $alternative_hosts;

    /**
     * Whether the meeting occurs daily, monthly, weekly, or not at all.
     * @var int
     */
    protected $recurrence_type;

    /**
     * Populate this meeting's fields using data returned by a Zoom API call.
     */
    public function populate_from_API_data() {
    }

    /**
     * Converts this meeting's data fields to a format that the Zoom API accepts.
     */
    protected function export_to_API() {
        global $CFG;

        $data = array(
            'topic' => $this->name,
            'type' => $this->type,
            'settings' => array(
                'host_video' => (bool) ($this->host_video),
                'audio' => $this->audio
            )
        );
        if (isset($this->description)) {
            $data['agenda'] = strip_tags($this->description);
        }
        if (isset($CFG->timezone) && !empty($CFG->timezone)) {
            $data['timezone'] = $CFG->timezone;
        } else {
            $data['timezone'] = date_default_timezone_get();
        }
        if (isset($this->password)) {
            $data['password'] = $this->password;
        }
        if (isset($this->alternative_hosts)) {
            $data['settings']['alternative_hosts'] = $this->alternative_hosts;
        }

        if ($data['type'] == ZOOM_SCHEDULED_MEETING || $data['type'] == ZOOM_SCHEDULED_WEBINAR) {
            // Convert timestamp to ISO-8601. The API seems to insist that it end with 'Z' to indicate UTC.
            $data['start_time'] = gmdate('Y-m-d\TH:i:s\Z', $this->start_time);
            $data['duration'] = (int) ceil($this->duration / 60);
        }

        return $data;
    }

}

/**
 * A class to represent zoom meetings.
 *
 * @package    mod_zoom
 * @copyright  2015 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class zoom_meeting extends zoom_instance {
    // Type constants.
    const SCHEDULED_MEETING = 2;
    const RECURRING_MEETING_WITHOUT_FIXED_TIME = 3;
    const RECURRING_MEETING_WITH_FIXED_TIME = 8;

    /**
     * Whether to start video when participants join the meeting.
     * @var bool
     */
    protected $participants_video;

    /**
     * Whether participants can join the meeting before the host starts it.
     * @var bool
     */
    protected $join_before_host;

    public function export_to_API() {
        $data = parent::export_to_API();
        $data['settings']['join_before_host'] = (bool) ($this->join_before_host);
        $data['settings']['participant_video'] = (bool) ($this->participants_video);
        return $data;
    }
}

/**
 * A class to represent zoom webinars.
 *
 * @package    mod_zoom
 * @copyright  2015 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class zoom_webinar extends zoom_instance {
    // Type constants.
    const SCHEDULED_WEBINAR = 5;
    const RECURRING_WEBINAR_WITHOUT_FIXED_TIME = 6;
    const RECURRING_WEBINAR_WITH_FIXED_TIME = 9;

    public function export_to_API() {
        $data = parent::export_to_API();
        // Insert logic here.
        return $data;
    }
}