<?php
// This file is part of Moodle - http://moodle.org/
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

defined('MOODLE_INTERNAL') || die();

/**
 * Zoom module test data generator class
 *
 * @package mod_zoom
 * @copyright 2020 UC Regents
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_zoom_generator extends testing_module_generator {

    /**
     * Creates new Zoom module instance.
     * @param array|stdClass $record
     * @param array $options
     * @return stdClass Zoom instance
     */
    public function create_instance($record = null, array $options = null) {
        global $CFG;
        require_once($CFG->dirroot.'/mod/zoom/locallib.php');

        set_config('apikey', 'test', 'zoom');
        set_config('apisecret', 'test', 'zoom');

        // Mock Zoom data for testing.
        $defaultzoomsettings = array(
            'grade' => 0,
            'name' => 'Test Zoom Meeting',
            'meeting_id' => 1,
            'host_id' => 'test',
            'meetingcode' => '',
            'webinar' => 0,
            'option_host_video' => 0,
            'option_audio' => 0,
            'recurring' => 0,
            'option_participants_video' => 0,
            'option_jbh' => 0,
            'option_waiting_room' => 0,
            'option_mute_upon_entry' => 0,
            'start_time' => mktime(0, 0, 0, 2, 22, 2021),
            'duration' => 60,
            'exists_on_zoom' => 0,
        );

        $record = (object) (array) $record;
        foreach ($defaultzoomsettings as $name => $value) {
            if (!isset($record->{$name})) {
                $record->{$name} = $value;
            }
        }
        return parent::create_instance($record, $options);
    }
}
