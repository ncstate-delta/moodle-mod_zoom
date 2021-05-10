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
        $config = get_config('zoom');
        if (empty($config->apikey)) {
            mtrace('Skipping task - ', get_string('zoomerr_apikey_missing', 'zoom'));
            return;
        } else if (empty($config->apisecret)) {
            mtrace('Skipping task - ', get_string('zoomerr_apisecret_missing', 'zoom'));
            return;
        }
        require_once($CFG->dirroot.'/lib/modinfolib.php');
        require_once($CFG->dirroot.'/mod/zoom/lib.php');
        require_once($CFG->dirroot.'/mod/zoom/classes/webservice.php');
        $service = new \mod_zoom_webservice();

        // Show trace message.
        mtrace('Starting to process existing Zoom meeting activities ...');

        // Check all meetings, in case they were deleted/changed on Zoom.
        $zoomstoupdate = $DB->get_records('zoom', array('exists_on_zoom' => ZOOM_MEETING_EXISTS));
        $courseidstoupdate = array();

        foreach ($zoomstoupdate as $zoom) {
            // Show trace message.
            mtrace('Processing next Zoom meeting activity ...');
            mtrace('  Zoom meeting ID: ' . $zoom->meeting_id);
            mtrace('  Zoom meeting title: '. $zoom->name);
            $zoomactivityurl = new \moodle_url('/mod/zoom/view.php', array('n' => $zoom->id));
            mtrace('  Zoom meeting activity URL: '. $zoomactivityurl->out());
            mtrace('  Moodle course ID: '. $zoom->course);

            $gotinfo = false;
            try {
                $response = $service->get_meeting_webinar_info($zoom->meeting_id, $zoom->webinar);
                $gotinfo = true;
            } catch (\zoom_not_found_exception $error) {
                $zoom->exists_on_zoom = ZOOM_MEETING_EXPIRED;
                $DB->update_record('zoom', $zoom);

                // Show trace message.
                mtrace('  => Marked Zoom meeting activity for Zoom meeting ID ' . $zoom->meeting_id .
                        ' as not existing anymore on Zoom');
            } catch (\moodle_exception $error) {
                // Show trace message.
                mtrace('  !! Error updating Zoom meeting activity for Zoom meeting ID ' . $zoom->meeting_id . ': ' . $error);
            }
            if ($gotinfo) {
                $changed = false;
                $newzoom = populate_zoom_from_response($zoom, $response);

                // Iterate over all Zoom meeting fields.
                foreach ((array) $zoom as $field => $value) {
                    // The start_url has a parameter that always changes, so it doesn't really count as a change.
                    // Similarly, the timemodified parameter does not count as change if nothing else has changed.
                    if ($field === 'start_url' || $field === 'timemodified') {
                        continue;
                    }

                    // For doing a better comparison and for easing mtrace() output, convert booleans from the Zoom response
                    // to strings like they are stored in the Moodle database for the existing activity.
                    $newfieldvalue = $newzoom->$field;
                    if (is_bool($newfieldvalue)) {
                        $newfieldvalue = $newfieldvalue ? '1' : '0';
                    }

                    // If the field value has changed.
                    if ($newfieldvalue != $value) {
                        // Show trace message.
                        mtrace('  => Field "' . $field . '" has changed from "' . $value . '" to "' . $newfieldvalue . '"');

                        // Remember this meeting as changed.
                        $changed = true;
                    }
                }

                if ($changed) {
                    $newzoom->timemodified = time();
                    $DB->update_record('zoom', $newzoom);

                    // Show trace message.
                    mtrace('  => Updated Zoom meeting activity for Zoom meeting ID ' . $zoom->meeting_id);

                    // If the topic/title was changed, mark this course for cache clearing.
                    if ($zoom->name != $newzoom->name) {
                        $courseidstoupdate[] = $newzoom->course;
                    }
                } else {
                    // Show trace message.
                    mtrace('  => Skipped Zoom meeting activity for Zoom meeting ID ' . $zoom->meeting_id . ' as unchanged');
                }

                // Update the calendar events.
                zoom_calendar_item_update($newzoom);
            }
        }

        // Show trace message.
        mtrace('Finished to process existing Zoom meetings');

        // Show trace message.
        mtrace('Starting to rebuild course caches ...');

        // Clear caches for meetings whose topic/title changed (and rebuild as needed).
        foreach ($courseidstoupdate as $courseid) {
            rebuild_course_cache($courseid, true);
        }

        // Show trace message.
        mtrace('Finished to rebuild course caches');

        return true;
    }
}
