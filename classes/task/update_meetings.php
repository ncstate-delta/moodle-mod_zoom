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
 * Library of interface functions and constants for module zoom
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 *
 * All the zoom specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package    mod_zoom
 * @copyright  2018 UC Regents
 * @author     Rohan Khajuria
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoom\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/zoom/locallib.php');

/**
 * Scheduled task to sychronize meeting data.
 *
 * @package   mod_zoom
 * @copyright 2018 UC Regents
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_meetings extends \core\task\scheduled_task {

    /**
     * Returns name of task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('updatemeetings', 'mod_zoom');
    }

    /**
     * Updates meetings that are not expired.
     *
     * @return boolean
     */
    public function execute() {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/lib/modinfolib.php');
        require_once($CFG->dirroot.'/mod/zoom/lib.php');
        require_once($CFG->dirroot.'/mod/zoom/classes/webservice.php');
        $service = new \mod_zoom_webservice();

        // Check all meetings, in case they were deleted/changed on Zoom.
        $recordstoupdate = $DB->get_records('zoom', array('exists_on_zoom' => true));
        $courseidstoupdate = array();
        $calendarfields = array('intro', 'introformat', 'start_time', 'duration', 'recurring');

        foreach ($recordstoupdate as $record) {
            $instance = mod_zoom_instance::factory($record->webinar);
            $instance->populate_from_database_record($record);

            try {
                $response = $service->get_instance_info($instance->get_instance_id(), $instance->is_webinar());
            } catch (\moodle_exception $error) {
                // Outputs error and then goes to next meeting.
                $instance->set_exists_on_zoom(false);
                $DB->update_record('zoom', $instance->export_to_database_format());
                mtrace('Error updating Zoom meeting with meeting_id ' . $zoom->meeting_id . ': ' . $error);
                continue;
            }

            if (!$instance->equalToResponse($response)) {
                $instance->populate_from_api_response($response);
                $DB->update_record('zoom', $instance->export_to_database_format());

                // If the topic/title was changed, mark this course for cache clearing.
                if (!$instance->equalToResponseName($response)) {
                    $courseidstoupdate[] = $instance->get_course();
                }

                // Check if calendar needs updating.
                if (!$instance->equalToResponseCalendar($response)) {
                    zoom_calendar_item_update($instance);
                }
            }
        }

        // Clear caches for meetings whose topic/title changed (and rebuild as needed).
        foreach (array_unique($courseidstoupdate) as $courseid) {
            rebuild_course_cache($courseid, true);
        }

        return true;
    }
}
