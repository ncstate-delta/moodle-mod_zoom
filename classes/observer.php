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
 * Callbacks for pertinent events.
 *
 * @package    mod_zoom
 * @copyright  2016 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Observer for Zoom activity.
 *
 * @package    mod_zoom
 * @copyright  2016 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_zoom_observer {

    /**
     * Listen for course_module_updated events.
     *
     * @param \core\event\course_module_updated $event
     */
    public static function course_module_updated(\core\event\course_module_updated $event) {
        global $DB;
        // Only care about events for Zoom caused by AJAX:
        // non-AJAX events should already be handled in lib.php.
        if ($event->other['modulename'] !== 'zoom' || !defined('AJAX_SCRIPT') || !AJAX_SCRIPT) {
            return;
        }

        $zoom = $DB->get_record('zoom', array('id' => $event->other['instanceid']));
        // The ID is set as "instance" in the edit form.
        $zoom->instance = $zoom->id;
        $service = new mod_zoom_webservice();
        $service->meeting_update($zoom);
    }
}
