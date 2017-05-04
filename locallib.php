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
define('ZOOM_WEBINAR', 5);
define('ZOOM_RECURRING_WEBINAR', 6);
// Authentication methods.
define('ZOOM_SNS_FACEBOOK', 0);
define('ZOOM_SNS_GOOGLE', 1);
define('ZOOM_SNS_API', 99);
define('ZOOM_SNS_ZOOM', 100);
define('ZOOM_SNS_SSO', 101);
// Number of meetings per page from zoom's get user report.
define('ZOOM_DEFAULT_RECORDS_PER_CALL', 30);
define('ZOOM_MAX_RECORDS_PER_CALL', 300);

/**
 * Get course/cm/zoom objects from url parameters, and check for login/permissions.
 *
 * @return array Array of ($course, $cm, $zoom)
 */
function zoom_get_instance_setup() {
    global $DB;

    $id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
    $n  = optional_param('n', 0, PARAM_INT);  // ... zoom instance ID - it should be named as the first character of the module.

    if ($id) {
        $cm         = get_coursemodule_from_id('zoom', $id, 0, false, MUST_EXIST);
        $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
        $zoom  = $DB->get_record('zoom', array('id' => $cm->instance), '*', MUST_EXIST);
    } else if ($n) {
        $zoom  = $DB->get_record('zoom', array('id' => $n), '*', MUST_EXIST);
        $course     = $DB->get_record('course', array('id' => $zoom->course), '*', MUST_EXIST);
        $cm         = get_coursemodule_from_instance('zoom', $zoom->id, $course->id, false, MUST_EXIST);
    } else {
        print_error('You must specify a course_module ID or an instance ID');
    }

    require_login($course, true, $cm);

    $context = context_module::instance($cm->id);
    require_capability('mod/zoom:view', $context);

    return array($course, $cm, $zoom);
}

/**
 * Get the user report for display and caching.
 *
 * @param stdClass $zoom
 * @param string $from same format as webservice->get_user_report
 * @param string $to
 * @return class->sessions[meetingid][starttime]
 *              ->reqfrom string same as param
 *              ->reqto string same as param
 *              ->resfrom array string "from" field of zoom response
 */
function zoom_get_sessions_for_display($zoom, $from, $to) {
    $service = new mod_zoom_webservice();
    $return = new stdClass();

    // If the from or to fields change, report.php will issue a new request.
    $return->reqfrom = $from;
    $return->reqto = $to;

    $hostsessions = array();

    if ($zoom->webinar) {
        if (!$service->webinar_uuid_list($zoom)) {
            zoom_print_error('webinar/uuid/list', $service->lasterror);
        }
        $result = $service->lastresponse;

        foreach ($result->webinars as $session) {
            // Get attendees for this particular uuid/session.
            if (!$service->webinar_attendees_list($zoom, $session->uuid)) {
                continue;
            }
            // Create a 'meeting' like the one from the report API.
            $meeting = new stdClass();
            $meeting->number = $zoom->meeting_id;
            $meeting->topic = $zoom->name;
            $meeting->start_time = $session->start_time;
            $meeting->end_time = '';
            // Format 'attendee' returned by webinar/attendees/list to be the same
            // as 'participant' from meeting report API.
            $meeting->participants = array_map(function($attendee) {
                $participant = new stdClass();
                $participant->name = $attendee->first_name;
                if (!empty($attendee->last_name)) {
                    $participant->name .= " {$attendee->last_name}";
                }
                $join = $attendee->join_time;
                $leave = $attendee->leave_time;
                // For some reason, the Zoom API returns the join/leave times in Pacific time
                // (regardless of timezone settings) but marks them as UTC.
                $join = substr($join, 0, strlen($join) - 1) . ' America/Los_Angeles';
                $leave = substr($leave, 0, strlen($leave) - 1) . ' America/Los_Angeles';
                $participant->join_time = $join;
                $participant->leave_time = $leave;

                return $participant;
            }, $service->lastresponse->attendees);

            $hostsessions[$zoom->meeting_id][strtotime($meeting->start_time)] = $meeting;
        }
        $return->resfrom = sscanf($from, '%u-%u-%u');
    } else {
        if (!$service->get_user_report($zoom->host_id, $from, $to, ZOOM_MAX_RECORDS_PER_CALL, 1)) {
            zoom_print_error('report/getuserreport', $service->lasterror);
        }
        $result = $service->lastresponse;

        // Zoom may return multiple pages of results.
        $numpages = $result->page_count;
        $i = $result->page_number;
        do {
            foreach ($result->meetings as $meet) {
                $starttime = strtotime($meet->start_time);
                $hostsessions[$meet->id][$starttime] = $meet;
            }

            $i++;
        } while ($i <= $numpages && $result = $service->get_user_report(
                $zoom->host_id, $from, $to, ZOOM_MAX_RECORDS_PER_CALL, $i));

        // If the time period is longer than a month, Zoom will only return the latest month in range.
        // Return the response "from" field to check.
        $return->resfrom = sscanf($result->from, '%u-%u-%u');
    }

    $return->sessions = $hostsessions;
    return $return;
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

    $available = $zoom->recurring || $inprogress;

    $finished = !$zoom->recurring && $now > $lastavailable;

    return array($inprogress, $available, $finished);
}

/**
 * Get the Zoom id of the currently logged-in user.
 *
 * @param boolean $required If true, will error if the user doesn't have a Zoom account.
 * @return string
 */
function zoom_get_user_id($required = true) {
    global $USER;

    $cache = cache::make('mod_zoom', 'zoomid');
    if (!($zoomuserid = $cache->get($USER->id))) {
        $zoomuserid = false;
        $service = new mod_zoom_webservice();
        if ($service->user_getbyemail($USER->email)) {
            $zoomuserid = $service->lastresponse->id;
        } else if ($required) {
            zoom_print_error('user/getbyemail', $service->lasterror);
        }
        $cache->set($USER->id, $zoomuserid);
    }

    return $zoomuserid;
}

/**
 * Check if the error indicates that a meeting is gone.
 *
 * @param string $error
 * @return bool
 */
function zoom_is_meeting_gone_error($error) {
    // If the meeting's owner/user cannot be found, we consider the meeting to be gone.
    return strpos($error, 'not found') !== false || zoom_is_user_not_found_error($error);
}

/**
 * Check if the error indicates that a user is not found.
 *
 * @param string $error
 * @return bool
 */
function zoom_is_user_not_found_error($error) {
    return strpos($error, 'User not exist') !== false;
}

/**
 * Return the string parameter for zoomerr_meetingnotfound.
 *
 * @param string $cmid
 * @return stdClass
 */
function zoom_meetingnotfound_param($cmid) {
    // Provide links to recreate and delete.
    $recreate = new moodle_url('/mod/zoom/recreate.php', array('id' => $cmid, 'sesskey' => sesskey()));
    $delete = new moodle_url('/course/mod.php', array('delete' => $cmid, 'sesskey' => sesskey()));

    // Convert links to strings and pass as error parameter.
    $param = new stdClass();
    $param->recreate = $recreate->out();
    $param->delete = $delete->out();

    return $param;
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

    $service = new mod_zoom_webservice();
    $coursestoupdate = array();
    $calendar_fields = array('intro',
                             'introformat',
                             'start_time',
                             'duration',
                             'recurring');
    foreach ($zooms as $z) {
        if ($service->get_meeting_info($z)) {
            $response = &$service->lastresponse;

            // Check for changes.
            $changed = false;
            foreach ($z as $field => $value) {
                // The start_url has a parameter that always changes, so it doesn't really count as a change.
                if ($field != 'start_url' && $response->$field != $value) {
                    $changed = true;
                    break;
                }
            }
            if ($changed) {
                // Save in database.
                $response->timemodified = time();
                $DB->update_record('zoom', $response);
                // If the topic/title was changed, mark this course for cache clearing.
                if ($z->name != $response->name) {
                    $coursestoupdate[$z->course] = 1;
                }

                // Check if calendar needs updating.
                $calendar_changed = false;
                foreach ($calendar_fields as $field) {
                    if ($z->$field != $response->$field) {
                        $calendar_changed = true;
                    }
                }
                if ($calendar_changed) {
                    // Update calendar.
                    zoom_calendar_item_update($response);
                }
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
