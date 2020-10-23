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

/**
 * Zoom module data generator.
 *
 * @package    mod_zoom
 * @category   test
 * @copyright  2020 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Zoom module data generator class.
 *
 * @package    mod_zoom
 * @category   test
 * @copyright  2020 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_zoom_generator extends testing_module_generator {

    /**
     * Creates Zoom meeting record shell.
     *
     * @param object $record
     * @param array $options
     * @return object
     */
    public function create_instance($record = null, array $options = null) {
        $record = (object)(array)$record;

        if (!isset($record->name)) {
            $record->name = 'Test Zoom meeting';
        }
        if (!isset($record->meeting_id)) {
            $record->meeting_id = rand(10000000000, 99999999999);
        }
        if (!isset($record->join_url)) {
            $record->join_url = 'https://zoom.us/j/' . $record->meeting_id;
        }
        if (!isset($record->start_url)) {
            $record->start_url = 'https://zoom.us/s/' . $record->meeting_id .
                    '?zak=' . uniqid();
        }
        if (!isset($record->requirepasscode)) {
            $record->requirepasscode = 1;
        }
        if (!isset($record->meetingcode)) {
            $record->meetingcode = rand(100000, 999999);
        }
        if (!isset($record->timezone)) {
            $record->timezone = 'America/Los_Angeles';
        }
        if (!isset($record->recurring)) {
            $record->recurring = 1;
        }
        if (!isset($record->start_time)) {
            $record->start_time = time();
        }
        if (!isset($record->duration)) {
            $record->duration = HOURSECS;
        }
        if (!isset($record->host_id)) {
            $record->host_id = uniqid();
        }
        if (!isset($record->grade)) {
            $record->grade = 0;
        }

        return parent::create_instance($record, (array)$options);
    }
}
