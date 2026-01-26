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
 * Event for when a new recording is discovered from Zoom.
 *
 * @package    mod_zoomyt
 * @copyright  2026 TUCC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoomyt\event;

/**
 * Records when a new Zoom cloud recording is discovered and saved.
 */
class recording_discovered extends \core\event\base {
    /**
     * Initializes the event.
     */
    protected function init() {
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['crud'] = 'c';
        $this->data['objecttable'] = 'zoomyt_meeting_recordings';
    }

    /**
     * Returns the name of the event.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('event_recording_discovered', 'mod_zoomyt');
    }

    /**
     * Returns a short description for the event.
     *
     * @return string
     */
    public function get_description() {
        $meetingname = $this->other['meeting_name'] ?? 'Unknown';
        $recordingtype = $this->other['recording_type'] ?? 'Unknown';
        return "New recording discovered: '{$meetingname}' (type: {$recordingtype}) " .
               "in course '{$this->courseid}'. Ready for YouTube upload.";
    }

    /**
     * Returns URL to manage recordings.
     *
     * @return \moodle_url
     */
    public function get_url() {
        return new \moodle_url('/mod/zoomyt/manage_recordings.php', ['id' => $this->contextinstanceid]);
    }
}
