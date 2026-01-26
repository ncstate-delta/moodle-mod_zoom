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
 * Event for when participant attendance is recorded.
 *
 * @package    mod_zoomyt
 * @copyright  2026 TUCC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoomyt\event;

/**
 * Records when participant attendance is saved to the database.
 */
class attendance_recorded extends \core\event\base {
    /**
     * Initializes the event.
     */
    protected function init() {
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['crud'] = 'c';
        $this->data['objecttable'] = 'zoomyt_meeting_participants';
    }

    /**
     * Returns the name of the event.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_attendance_recorded', 'mod_zoomyt');
    }

    /**
     * Returns a short description for the event.
     *
     * @return string
     */
    public function get_description() {
        $duration = $this->other['duration'] ?? 0;
        $meetingname = $this->other['meeting_name'] ?? 'Unknown';
        return "Attendance recorded for user '{$this->relateduserid}' in meeting '{$meetingname}' " .
               "(duration: {$duration} minutes) in course '{$this->courseid}'.";
    }

    /**
     * Returns URL to the activity.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/zoomyt/view.php', ['id' => $this->contextinstanceid]);
    }

    /**
     * Custom validation.
     */
    protected function validate_data() {
        parent::validate_data();
        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }
    }
}
