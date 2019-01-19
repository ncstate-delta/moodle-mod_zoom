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
 * Zoom meeting class.
 *
 * @package    mod_zoom
 * @copyright  2015 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_zoom_webservice {

    /**
     * The meeting host's ID on Zoom servers
     * @var string
     */
    protected $hostid;

    /**
     * The meeting's name
     * 'topic' on Zoom API
     * @var string
     */
    protected $name;

    /**
     * The meeting's description
     * 'agenda' on Zoom API
     * 'intro' in database
     * @var string
     */
    protected $description;

    /**
     * The course ID that the meeting is in
     * @var string
     */
    protected $course;

    /**
     * The meeting's ID on Zoom servers
     * TODO 'uuid' or 'id' on Zoom API
     * @var int
     */
    protected $meetingid;

    /**
     * The time at which the meeting starts
     * Stored in epoch time format
     * @var int
     */
    protected $starttime;

    /**
     * The URL to start the meeting
     * @var string
     */
    protected $startURL;

    /**
     * The URL to join the meeting
     * @var string
     */
    protected $joinURL;

    /**
     * The meeting type (scheduled, recurring with time, recurring without time)
     * Uses class meeting_types to simulate enumeration for types
     * @var int
     */
    protected $meetingtype;

    /**
     * Populate this meeting's fields using data returned by a Zoom API call.
     */
    public function populate_from_API_data() {
    }

    /**
     * Converts this meeting's data fields to a format that the Zoom API accepts.
     */
    public function export_to_API() {
    }

}

abstract class meeting_types {
    const SCHEDULED = 0;
    const RECURRING_WITHOUT_FIXED_TIME = 1;
    const RECURRING_WITH_FIXED_TIME = 1;
}