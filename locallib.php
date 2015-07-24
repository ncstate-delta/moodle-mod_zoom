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
 * Internal library of functions for module zoom
 *
 * All the zoom specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package    mod_zoom
 * @copyright  2015 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/mod/zoom/lib.php');
require_once($CFG->dirroot.'/mod/zoom/classes/webservice.php');

// Constants.
// Audio options.
define('ZOOM_AUDIO_TELEPHONY', 'telephony');
define('ZOOM_AUDIO_VOIP', 'voip');
define('ZOOM_AUDIO_BOTH', 'both');
// Meeting statuses.
define('ZOOM_MEETING_NOT_STARTED', 0);
define('ZOOM_MEETING_STARTED', 1);
define('ZOOM_MEETING_FINISHED', 2);
define('ZOOM_MEETING_EXPIRED', -1);
// Meeting types.
define('ZOOM_INSTANT_MEETING', 1);
define('ZOOM_SCHEDULED_MEETING', 2);
define('ZOOM_RECURRING_MEETING', 3);
// Authentication methods.
define('ZOOM_SNS_FACEBOOK', 0);
define('ZOOM_SNS_GOOGLE', 1);
define('ZOOM_SNS_API', 99);
define('ZOOM_SNS_ZOOM', 100);
define('ZOOM_SNS_SSO', 101);

/**
 * Get the Zoom id of the currently logged-in user.
 *
 * @return string
 */
function zoom_get_user_id() {
    global $USER;
    $service = new mod_zoom_webservice();
    if (!$service->user_getbyemail($USER->email)) {
        zoom_print_error('user/getbyemail', $service->lasterror);
    }
    return $service->lastresponse->id;
}

/**
 * Update local copy of zoom meetings by getting the latest Zoom data through the API.
 *
 * @param Traversable $zooms Traversable collection of zoom objects, perhaps from a recordset
 * (although this function does not close the recordset).
 */
function zoom_update_records(Traversable $zooms) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/course/lib.php');
    require_once($CFG->dirroot.'/lib/modinfolib.php');

    $service = new \mod_zoom_webservice();
    $coursestoupdate = array();
    foreach ($zooms as $z) {
        if ($service->get_meeting_info($z)) {
            // Check for changes.
            foreach ($z as $field => $value) {
                // The start_url has a parameter that always changes, so it doesn't really count as a change.
                if ($field != 'start_url' && $service->lastresponse->$field != $value) {
                    $service->lastresponse->timemodified = time();
                    break;
                }
            }
            // Save in database.
            $DB->update_record('zoom', $service->lastresponse);
            // Update calendar.
            zoom_calendar_item_update($service->lastresponse);
            // If the topic/title was changed, mark this course for cache clearing.
            if ($z->name != $service->lastresponse->name) {
                $coursestoupdate[$z->course] = 1;
            }
        } else {
            $z->status = ZOOM_MEETING_EXPIRED;
            $DB->update_record('zoom', $z);
        }
    }

    // Clear caches for meetings whose topic/title changed (and rebuild as needed).
    foreach (array_flip($coursestoupdate) as $course) {
        rebuild_course_cache($course, true);
    }
}

/**
 * Determine if a zoom meeting is in progress, is available, and/or is finished.
 *
 * @param stdClass $zoom
 * @return array Array of booleans: [in progress, available, finished].
 */
function zoom_get_state($zoom) {
    $config = get_config('mod_zoom');
    $now = time();

    $firstavailable = $zoom->start_time - ($config->firstabletojoin * 60);
    $lastavailable = $zoom->start_time + $zoom->duration;
    $inprogress = ($firstavailable <= $now && $now <= $lastavailable);

    $available = $zoom->type == ZOOM_RECURRING_MEETING || $inprogress;

    $finished = $zoom->type != ZOOM_RECURRING_MEETING && $now > $lastavailable;

    return array($inprogress, $available, $finished);
}
