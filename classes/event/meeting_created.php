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
 * Event for when a new Zoom meeting is created.
 *
 * @package    mod_zoomyt
 * @copyright  2026 TUCC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoomyt\event;

/**
 * Records when a new Zoom meeting is created via the API.
 */
class meeting_created extends \core\event\base {
    /**
     * Initializes the event.
     */
    protected function init() {
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['crud'] = 'c';
        $this->data['objecttable'] = 'zoomyt';
    }

    /**
     * Returns the name of the event.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_meeting_created', 'mod_zoomyt');
    }

    /**
     * Returns a short description for the event.
     *
     * @return string
     */
    public function get_description() {
        $meetingname = $this->other['meeting_name'] ?? 'Unknown';
        $meetingid = $this->other['meeting_id'] ?? 'Unknown';
        return "User '{$this->userid}' created Zoom meeting '{$meetingname}' " .
               "(Meeting ID: {$meetingid}) in course '{$this->courseid}'.";
    }

    /**
     * Returns URL to the activity.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/zoomyt/view.php', ['id' => $this->contextinstanceid]);
    }
}
