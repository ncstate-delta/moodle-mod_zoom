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

/**
 * A class to represent general zoom instances (either meetings or webinars).
 *
 * @package    mod_zoom
 * @copyright  2015 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class mod_zoom_instance {
    // Other constants.
    const INTROFORMAT = 1; // A moodle requirement for descriptions. Will always be 1. TODO: check if this is actually constant.

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
    public $name;

    /**
     * The most recent time at which the instance was modified.
     * Stored in epoch time format.
     * @var int
     */
    protected $timemodified;

    /**
     * The time at which the instance was created.
     * Stored in epoch time format.
     * @var int
     * TODO: how to store it? naturally in string but everything else is int. be consistent or easy?
     */
    protected $createdat;

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
    public $databaseid;

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
     * Other users that can start the meeting (stores user emails).
     * @var array
     */
    protected $alternativehosts;

    /**
     * Populates $alternativehosts from a comma-separated string.
     * @param string $from The comma-separated string.
     */
    protected function set_alternativehosts_from_string($from) {
        $this->alternativehosts = explode(",", $from);
    }

    /**
     * Converts $alternativehosts to a comma-separated string.
     * @return string $from The comma-separated string.
     */
    protected function get_string_from_alternativehosts() {
        return implode(",", $this->alternativehosts);
    }

    /**
     * Whether the instance supports grading on Moodle.
     * @var bool ??
     */
    protected $supportsgrading;

    /**
     * Whether we could find the instance on Zoom's servers.
     * @var bool
     */
    public $existsonzoom;

    /**
     * Makes all private variables read-only.
     */
    public function __get($variable) {
        if (isset($this->$variable)) {
            return $this->$variable;
        } else {
            // TODO: throw exception?
        }
    }

    /**
     * Factory method for Zoom instances.
     * @see https://en.wikipedia.org/wiki/Factory_method_pattern
     * @see https://stackoverflow.com/questions/6622214/how-to-return-subclass-from-constructor-in-php
     * @param int $webinar Whether the instance is a webinar (as opposed to a meeting).
     * @return mod_zoom\meeting|mod_zoom\webinar The correct instance.
     */
    public static function factory($webinar) {
        if ($webinar) {
            return new mod_zoom_webinar();
        } else {
            return new mod_zoom_meeting();
        }
    }




    // ---------- RESPONSE TO INSTANCE INTERACTIONS ----------

    // Stores the name equality between the response and object fields i.e. 'response' => 'object'.
    const RESPONSETOOBJECTFIELDALIGNMENT = array(
        'start_url' => 'starturl',
        'join_url' => 'joinurl',
        'created_at' => 'createdat',
        'timezone' => 'timezone',
        'id' => 'id',
        'topic' => 'name',
        'agenda' => 'description'
    );

    /**
     * Compares this instance to a response to check whether they differ or are equal.
     * @param $response The response against which to compare.
     * @return bool Whether the instance and response have equal fields.
     * // TODO: remove start_url thing? why not check it?
     */
    public function equalToResponse($response) {
        foreach (self::RESPONSETOOBJECTFIELDALIGNMENT as $responsefield => $objectfield) {
            if($this->$objectfield != $response->$responsefield/* && $this->objectfield != 'start_url'*/) {
                return false;
            }
        }
        if ($this->duration != $response->duration * 60) {
            return false;
        }
        if ($this->starttime != strtotime($response->start_time)) {
            return false;
        }
        if ($this->get_string_from_alternativehosts() != $response->settings->alternative_hosts) {
            return false;
        }
        return true;
    }

    /**
     * Compares this instance to a response to check whether they differ or are equal, but just in calendar-related fields.
     * @param $response The response against which to compare.
     * @return bool Whether the instance and response have equal fields.
     */
    public function equalToResponseCalendar($response) {
        return $this->starttime == strtotime($response->start_time) && $this->duration += $response->duration * 60;
    }

    public function equalToResponseName($response, $justname = false) {
        return $response->topic == $this->name;
    }

    /**
     * Populate this instance's fields using data returned by a Zoom API call.
     * @param $response The response from the API.
     */
    public function populate_from_api_response($response) {
        foreach (self::RESPONSETOOBJECTFIELDALIGNMENT as $responsefield => $objectfield) {
            if(isset($response->$responsefield)) {
                $this->$objectfield = $response->$responsefield;
            }
        }
        if (isset($response->duration)) {
            // Multiply by 60 because we store it in seconds and Zoom returns it in minutes.
            $this->duration = $response->duration * 60;
        }
        if (isset($response->start_time)) {
            // We store the start time in epoch format, but Zoom returns it in string format.
            $this->starttime = strtotime($response->start_time);
        }
        // TODO: ADD ALL THAT RECURRING STUFF
        if (isset($response->settings->alternative_hosts)) {
            $this->set_alternativehosts_from_string($response->settings->alternative_hosts);
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
            $data['settings']['alternative_hosts'] = $this->get_string_from_alternativehosts();
        }

        // TODO: check this recurring/type stuff
        if ($data['type'] == $this->ZOOM_SCHEDULED_MEETING || $data['type'] == $this->ZOOM_SCHEDULED_WEBINAR) {
            // Convert timestamp to ISO-8601. The API seems to insist that it end with 'Z' to indicate UTC.
            $data['start_time'] = gmdate('Y-m-d\TH:i:s\Z', $this->starttime);
            $data['duration'] = (int) ceil($this->duration / 60);
        }

        return $data;
    }




    // ---------- FORM TO INSTANCE INTERACTIONS ----------

    /**
     * Populate this instance's fields using data returned by mod_form.php.
     */
    public function populate_from_mod_form($formdata) {
        // Stores the name equality between fields i.e. 'form' => 'object'.
        $fieldalignment = array(
            'name' => 'name',
            'intro' => 'description',
            'start_time' => 'starttime',
            'duration' => 'duration',
            'password' => 'password',
            'option_host_video' => 'hostvideo',
            'option_audio' => 'audio',
            'grade' => 'supportsgrading',
            'instance' => 'databaseid',
            'host_id' => 'hostid'
        );
        foreach ($fieldalignment as $formfield => $objectfield) {
            if(isset($formdata->$formfield)) {
                $this->$objectfield = $formdata->$formfield;
            }
        }

        $this->course = (int) $formdata->course;
        if (isset($formdata->alternative_hosts)) {
            $this->set_alternativehosts_from_string($formdata->alternative_hosts);
        }
    }




    // ---------- DATABASE TO INSTANCE INTERACTIONS ----------

    /**
     * Stores the name equality between the database and object fields i.e. 'database' => 'object'.
     * Doesn't include alternative hosts.
     */
    const DATABASETOINSTANCEFIELDALIGNMENT = array(
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
        'recurrencetype' => 'recurrencetype',
        'duration' => 'duration',
        'timezone' => 'timezone',
        'password' => 'password',
        'option_host_video' => 'hostvideo',
        'option_audio' => 'audio',
        'id' => 'databaseid',
        'meeting_id' => 'id',
        'exists_on_zoom' => 'existsonzoom'
    );

    /**
     * Converts this instance's data fields to a format used by the Moodle database.
     * @return stdClass $data An object with fields populated according to the database schema.
     */
    public function export_to_database_format() {
        $data = new stdClass();
        foreach (self::DATABASETOINSTANCEFIELDALIGNMENT as $databasefield => $objectfield) {
            if(isset($this->$objectfield)) {
                $data->$databasefield = $this->$objectfield;
            }
        }

        // DATABASETOINSTANCEFIELDALIGNMENT doesn't include alternativehosts because of the string/array conversion.
        $data->alternative_hosts = $this->get_string_from_alternativehosts();
        return $data;
    }

    /**
     * Populate this instance's fields using a record from the database.
     * @param stdClass $record A record from the database.
     */
    public function populate_from_database_record($record) {
        foreach (self::DATABASETOINSTANCEFIELDALIGNMENT as $databasefield => $objectfield) {
            if(isset($record->$databasefield)) {
                $this->$objectfield = $record->$databasefield;
            }
        }
        // DATABASETOINSTANCEFIELDALIGNMENT doesn't include alternativehosts because of the string/array conversion.
        $this->set_alternativehosts_from_string($record->alternative_hosts);
    }




    // ---------- CALENDAR TO INSTANCE INTERACTIONS ----------

    /**
     * Converts this instance's data fields to a format used by the Moodle calendar interface.
     * @param bool $new Whether the event is new, as opposed to being an update to an existing one.
     * @return stdClass $event An event object with populated fields.
     */
    public function export_to_calendar_format($new) {
        $event = new stdClass();
        // Stores the name equality between fields i.e. 'event' => 'object'.
        $fieldalignment = array(
            'name' => 'name',
            'description' => 'description',
            'format' => 'INTROFORMAT',
            'timestart' => 'starttime',
            'timeduration' => 'duration'
        );
        if ($new) {
            $fieldalignment['courseid'] = 'course';
            $fieldalignment['instance'] = 'databaseid';
            $event->modulename = 'zoom';
            $event->eventtype = 'zoom';
        }
        foreach ($fieldalignment as $eventfield => $objectfield) {
            if(isset($this->$objectfield)) {
                $event->$eventfield = $this->$objectfield;
            }
        }
        $event->visible = $this->recurrencetype == self::NOT_RECURRING; // TODO: include RECURRING_WITH_FIXED_TIME?
        return $event;
    }




    // ---------- RECURRENCE VARIABLES/FUNCTIONS ----------

    // Constants for $recurrencetype.
    const NOT_RECURRING = 0;
    const RECURRING_WITHOUT_FIXED_TIME = 1;
    const RECURRING_WITH_FIXED_TIME = 2;

    // Constants for $recurrencerepeattype.
    const DAILY = 0;
    const WEEKLY = 1;
    const MONTHLY = 2;

    /**
     * The time at which the instance starts.
     * Stored in epoch time format.
     * @var int
     */
    protected $starttime;

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
     * The manner in which the instance recures.
     * Uses class constants.
     * TODO: make mapping in each subclass to Zoom's API type
     * @var int
     */
    protected $recurrencetype;

    /**
     * Whether the instance occurs daily, monthly, weekly, or not at all.
     * Uses class constants.
     * TODO: what should i call this variable
     * @var int
     */
    protected $recurrencerepeattype;

    /**
     * The interval in which the instance recurs.
     * Equals -1 if instance does not recur in intervals.
     * @var int
     */
    protected $intervals;

    /**
     * The day of the month on which a monthly-recurring instance recurs.
     * Equals -1 if instance does not recur on days of the month.
     * Only applies if {@see $recurrencerepeattype} is monthly.
     * @var int
     */
    protected $mday;

    /**
     * The weeks during which a monthly-recurring instance recurs.
     * @var int
     */
    protected $mweek;

    /**
     * The weekdays on which a monthly-recurring instance recurs.
     * @var int
     */
    protected $mweekday;

    /**
     * The weekdays on which a weekly-recurring instance recurs.
     * @var int
     */
    protected $weekday;

    /**
     * The date and time which after an recurring meeting will not recur.
     * 'end_date_time' in Zoom API.
     * empty string if $lastrecurrence is not used. TODO: should use separate bool?
     * @var string
     */
    protected $lastrecurrence;

    /**
     * The number of times for which a recurring meeting should recur.
     * 'end_times' in Zoom API.
     * @var int
     */
    protected $numrecurrences;

    /**
     * Checks whether the instance is in progress.
     * TODO: i don't think we need this function.
     * @return bool Whether the instance is in progress.
     */
    public function is_in_progress() {
        $config = get_config('mod_zoom');
        $now = time();

        $firstavailable = $this->starttime - ($config->firstabletojoin * 60);
        $lastavailable = $this->starttime + $zoom->duration;
        return $firstavailable <= $now && $now <= $lastavailable;
    }

    /**
     * Checks whether the instance is available to join.
     * @return bool Whether the instance is available to join.
     */
    public function can_join() {
        // RECURRING_WITHOUT_FIXED_TIME meetings are technically always running.
        if ($this->recurrencetype == self::RECURRING_WITHOUT_FIXED_TIME) {
            return true;
        }

        $config = get_config('mod_zoom');
        $now = time();

        // If meeting is NOT_RECURRING we just need to check simple bounds.
        if ($this->recurrencetype == self::NOT_RECURRING) {
            $firstavailable = $this->starttime - ($config->firstabletojoin * 60);
            $lastavailable = $this->starttime + $this->duration;
            return $firstavailable <= $now && $now <= $lastavailable;
        }

        // TODO: implement this. it will be hard.
        if ($this->recurrencetype == self::RECURRING_WITH_FIXED_TIME) {
            return false;
        }
    }

    /**
     * Returns whether the meeting is recurring.
     * @return bool Whether the meeting is recurring.
     */
    public function is_recurring() {
        return $this->recurrencetype != self::NOT_RECURRING;
    }




    // ---------- OTHER FUNCTIONS TO INSTANCE INTERACTIONS ----------

    /**
     * Updates the timemodified field to now.
     */
    public function make_modified_now() {
        $timemodified = time();
    }

    /**
     * Checks if a user is either the primary host or an alternative host.
     * @param string $userid The user's id.
     * @param string $email The user's email.
     */
    public function is_any_host($userid, $email) {
        return $userid == $this->hostid || in_array($email, $this->alternativehosts);
    }
}
