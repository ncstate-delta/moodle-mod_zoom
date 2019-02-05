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
 * A class to represent zoom webinars.
 *
 * @package    mod_zoom
 * @copyright  2015 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_zoom_webinar extends mod_zoom_instance {
    // Type constants.
    const SCHEDULED_WEBINAR = 5;
    const RECURRING_WEBINAR_WITHOUT_FIXED_TIME = 6;
    const RECURRING_WEBINAR_WITH_FIXED_TIME = 9;

    public function export_to_API() {
        $data = parent::export_to_API();
        // Insert logic here.
        return $data;
    }

    /**
     * Converts this instance's data fields to a format used by the Moodle database.
     */
    public function export_to_database_format() {
        $data = parent::export_to_database_format();
        $data->webinar = 1;
        return $data;
    }
}
