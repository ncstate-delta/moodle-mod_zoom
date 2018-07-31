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
// Meeting types.
define('ZOOM_INSTANT_MEETING', 1);
define('ZOOM_SCHEDULED_MEETING', 2);
define('ZOOM_RECURRING_MEETING', 3);
define('ZOOM_SCHEDULED_WEBINAR', 5);
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
// User types. Numerical values from Zoom API.
define('ZOOM_USER_TYPE_BASIC', 1);
define('ZOOM_USER_TYPE_PRO', 2);
define('ZOOM_USER_TYPE_CORP', 3);

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
        print_error(get_string('zoomerr_id_missing', 'zoom'));
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
    $hostsessions = array();

    // If the from or to fields change, report.php will issue a new request.
    $return->reqfrom = $from;
    $return->reqto = $to;

    if ($zoom->webinar) {
        $uuidlist = $service->list_uuids($zoom->host_id, true);

        foreach ($uuidlist as $uuid) {
            // Get participants for this uuid/session.
            $participants = $service->list_webinar_attendees($uuid);

            // Rename user_name to name to match report API.
            foreach ($participants as $participant) {
                // For some reason, the Dashboard API replaces ',' with '#' in names.
                $participant->name = str_replace('#', ',', $participant->user_name);
            }

            // Create a 'meeting' like the one from the report API.
            $meeting = new stdClass();
            $meeting->topic = $zoom->name;
            $meeting->participants = $participants;
            $hostsessions[$meeting->id][strtotime($meeting->start_time)] = $meeting;
        }

        // The webinar/uuid/list call doesn't actually use the from/to dates.
        $return->resfrom = sscanf($from, '%u-%u-%u');
    } else {
        $meetings = $service->get_user_report($zoom->host_id, $from, $to);
        $uuidlist = $service->list_uuids($zoom->host_id, false);

        foreach ($meetings as $meet) {
            $starttime = strtotime($meet->start_time);
            $hostsessions[$meet->id][$starttime] = $meet;
        }

        // Rename user_name to name to match report API.
        foreach ($hostsessions as $session) {
            foreach ($session as $sess) {
                foreach ($sess->participants as $participant) {
                    // For some reason, the Dashboard API replaces ',' with '#' in names.
                    $participant->name = str_replace('#', ',', $participant->name);
                }
            }
        }

        // If the time period is longer than a month, Zoom will only return the latest month in range.
        // Return the response "from" field to check.
        if(!empty($meetings)) {
            var_dump($meetings);
            $return->resfrom = sscanf($meetings->from, '%u-%u-%u');
        }
        else {
            $return->resfrom = sscanf($from, '%u-%u-%u');
        }
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
        try {
            $zoomuser = $service->get_user($USER->email);
            $zoomuserid = $zoomuser->id;
        } catch (moodle_exception $error) {
            if ($required) {
                throw $error;
            } else {
                $zoomuserid = $zoomuser->id;
            }
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
    return strpos($error, 'not exist') !== false;
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
    $calendarfields = array('intro',
                             'introformat',
                             'start_time',
                             'duration',
                             'recurring');
    foreach ($zooms as $z) {
        $gotinfo = false;
        try {
            $response = $service->get_meeting_webinar_info($z->meeting_id, $z->webinar);
            $gotinfo = true;
        } catch (moodle_exception $error) {
            if (strpos($error, 'is not found or has expired') === false) {
                throw $error;
            } else {
                $z->exists_on_zoom = false;
                $DB->update_record('zoom', $z);
            }
        }
        if ($gotinfo) {
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
                $calendarchanged = false;
                foreach ($calendarfields as $field) {
                    if ($z->$field != $response->$field) {
                        $calendarchanged = true;
                    }
                }
                if ($calendarchanged) {
                    // Update calendar.
                    zoom_calendar_item_update($response);
                }
            }
        }
    }

    // Clear caches for meetings whose topic/title changed (and rebuild as needed).
    foreach (array_flip($coursestoupdate) as $course) {
        rebuild_course_cache($course, true);
    }
}

/**
 * Get the data of each user for the participants report.
 * @param int The meeting ID that you want to get the participants report for.
 * @return array The user data as an array of records (array of arrays).
 */
function zoom_get_participants_report(int $req_meeting_instance_id = -1) {  // Should probably move this function to locallib.php
    global $DB;
    require_once($CFG->dirroot.'/mod/zoom/classes/webservice.php');
    $service = new mod_zoom_webservice();
    $sql = 'SELECT zmp.participant_universal_id,
                    zmp.meeting_webinar_instance_id,
                    SUM(zmp.duration) as duration
                FROM {zoom_meetings_participants} zmp
                WHERE zmp.meeting_webinar_instance_id = :req_meeting_instance_id
            GROUP BY zmp.participant_universal_id, zmp.meeting_webinar_instance_id
    ';
    $params = [
        'req_meeting_instance_id' => $req_meeting_instance_id
    ];
    $participants = $DB->get_records_sql($sql, $params);
    $userreport = array();
    foreach ($participants as $participant) {
        $userinfo = $service->get_user($participant->participant_email);
        $userreport[] = array(
            'first_name' => $userinfo->first_name,
            'last_name' => $userinfo->last_name,
            'email' => $userinfo->email,
            'duration' => $participant->duration,
            'attentiveness_score' => $participant->attentiveness_score
        );
    }
    return $userreport;
}
