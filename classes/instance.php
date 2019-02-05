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
abstract class mod_zoom_instance {
    // Recurrence constants.
    const NO_RECURRENCE = 0; // Defined by CCLE, not Zoom.
    const DAILY = 1;
    const WEEKLY = 2;
    const MONTHLY = 3;

    // Other constants.
    const INTROFORMAT = 1; // A moodle requirement for descriptions. Will always be 1.

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
     * The time at which the instance was created.
     * Stored in epoch time format.
     * @var int
     * TODO: how to store it? naturally in string but everything else is int. be consistent or easy?
     */
    protected $createdat;

    /**
     * The timezone that the meeting is in.
     * Stored as a string, specified by @see https://zoom.github.io/api/#timezones.
     * @var string
     */
    protected $timezone;

    /**
     * The instance duration in seconds.
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
     * 'id' on Zoom API (not 'uuid').
     * @var int
     */
    protected $id;

    /**
     * The instance's ID in the Moodle database.
     * @var int
     */
    protected $databaseid;

    /**
     * The URL to start the meeting.
     * @var string
     */
    protected $starturl;

    /**
     * The URL to join the meeting.
     * @var string
     */
    protected $joinurl;

    /**
     * Whether to start video when the host joins the meeting.
     * @var bool
     */
    protected $hostvideo;

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
    protected $alternativehosts;

    /**
     * Whether the instance occurs daily, monthly, weekly, or not at all.
     * @var int
     */
    protected $recurrencetype;

    /**
     * Whether the instance supports grading on Moodle.
     */
    protected $supportsgrading;

    /**
     * Populate this instance's fields using data returned by a Zoom API call.
     */
    public function populate_from_api_response($response) {
        $samefields = array('start_url', 'join_url', 'created_at', 'timezone', 'id');
        foreach ($samefields as $field) {
            if (isset($response->$field)) {
                $this->$field = $response->$field;
            }
        }
        if (isset($response->duration)) {
            // Multiply by 60 because we store it in seconds and Zoom returns it in minutes.
            $this->duration = $response->duration * 60;
        }
        if (isset($response->topic)) {
            $this->name = $response->topic;
        }
        if (isset($response->agenda)) {
            $this->description = $response->agenda;
        }
        if (isset($response->start_time)) {
            // We store the start time in epoch format, but Zoom returns it in string format.
            $this->starttime = strtotime($response->start_time);
        }
        // TODO: ADD ALL THAT RECURRING STUFF
        if (isset($response->settings->alternative_hosts)) {
            $this->alternativehosts = $response->settings->alternative_hosts;
        }
    }

    /**
     * Converts this instance's data fields to a format that the Zoom API accepts.
     */
    public function export_to_api_format() {
        global $CFG;

        $data = array(
            'topic' => $this->name,
            'type' => $this->type,
            'settings' => array(
                'host_video' => (bool) ($this->hostvideo),
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
        if (isset($this->alternativehosts)) {
            $data['settings']['alternative_hosts'] = $this->alternativehosts;
        }

        // TODO: check this recurring/type stuff
        if ($data['type'] == ZOOM_SCHEDULED_MEETING || $data['type'] == ZOOM_SCHEDULED_WEBINAR) {
            // Convert timestamp to ISO-8601. The API seems to insist that it end with 'Z' to indicate UTC.
            $data['start_time'] = gmdate('Y-m-d\TH:i:s\Z', $this->starttime);
            $data['duration'] = (int) ceil($this->duration / 60);
        }

        return $data;
    }

    /**
     * Populate this instance's fields using data returned by mod_form.php.
     * TODO: abstract redundant 'fieldalignment' variables into inherited constants.
     */
    public function populate_from_mod_form($formdata) {
        $this->course = (int) $formdata->course;
        // Stores the name equality between fields i.e. 'form' => 'object'.
        $fieldalignment = array(
            'name' => 'name',
            'intro' => 'description',
            'start_time' => 'starttime',
            'duration' => 'duration',
            'password' => 'password',
            'alternative_hosts' => 'alternativehosts',
            'option_host_video' => 'hostvideo',
            'option_audio' => 'audio',
            'grade' => 'supportsgrading'
        );
        foreach ($fieldalignment as $formfield => $objectfield) {
            $this->objectfield = $formdata->formfield;
        }
    }

    /**
     * Converts this instance's data fields to a format used by the Moodle database.
     */
    public function export_to_database_format() {
        $data = array();
        // Stores the name equality between fields i.e. 'database' => 'object'.
        $fieldalignment = array(
            'course' => 'course',
            'intro' => 'description',
            'introformat' => 'INTROFORMAT',
            'grade' => 'supportsgrading',
            'meeting_id' => 'id',
            'start_url' => 'starturl',
            'join_url' => 'joinurl',
            'created_at' => 'createdat',
            'host_id' => 'hostid',
            'name' => 'name',
            'start_time' => 'starttime',
            'timemodified' => 'timemodified',
            'recurring' => 'recurrencetype', // TODO: figure this out
            'duration' => 'duration',
            'timezone' => 'timezone',
            'password' => 'password',
            'option_host_video' => 'hostvideo',
            'option_audio' => 'audio',
            'alternative_hosts' => 'alternativehosts'
        );
        foreach ($fieldalignment as $databasefield => $objectfield) {
            $data->databasefield = $this->objectfield;
        }
        return $data;
    }

    /**
     * Converts this instance's data fields to a format used by the Moodle calendar interface.
     * @param bool $new Whether the event is new, as opposed to being an update to an existing one.
     */
    public function export_to_calendar_format($new) {
        $data = array();
        // Stores the name equality between fields i.e. 'event' => 'object'.
        $fieldalignment = array(
            'name' => 'name',
            'description' => 'description',
            'format' => 'INTROFORMAT',
            'timestart' => 'starttime',
        );
        if ($new) {
            $fieldalignment['courseid'] = 'course';
        }
        foreach ($fieldalignment as $eventfield => $objectfield) {
            $data->eventfield = $this->objectfield;
        }
        $data->visible = !$this->recurrencetype; // TODO: figure this out
        return $data;
    }

    /**
     * Setter function for the database ID.
     */
    public function set_database_id($newid) {
        $this->databaseid = $newid;
    }

}
