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
 * @copyright  2015 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/* Moodle core API */

/**
 * Returns the information on whether the module supports a feature
 *
 * See {@link plugin_supports()} for more info.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function zoom_supports($feature) {

    switch($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the zoom into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass $zoom Submitted data from the form in mod_form.php
 * @param mod_zoom_mod_form $mform The form instance itself (if needed)
 * @return int The id of the newly inserted zoom record
 */
function zoom_add_instance(stdClass $zoom, mod_zoom_mod_form $mform = null) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/mod/zoom/classes/webservice.php');

    // Create meeting on Zoom.
    $service = new mod_zoom_webservice();
    if (!$service->meeting_create($zoom)) {
        zoom_print_error('meeting/create', $service->lasterror);
    }
    $zoom = $service->lastresponse;

    // Create meeting in database.
    $zoom->timemodified = time();
    $zoom->id = $DB->insert_record('zoom', $zoom);

    zoom_calendar_item_update($zoom);
    zoom_grade_item_update($zoom);

    return $zoom->id;
}

/**
 * Updates an instance of the zoom in the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param stdClass $zoom An object from the form in mod_form.php
 * @param mod_zoom_mod_form $mform The form instance itself (if needed)
 * @return boolean Success/Fail
 */
function zoom_update_instance(stdClass $zoom, mod_zoom_mod_form $mform = null) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/mod/zoom/classes/webservice.php');

    // Update meeting on Zoom.
    $service = new mod_zoom_webservice();

    try {
        $service->meeting_update($zoom);
    } catch (moodle_exception $e) {

        if (is_meeting_gone_error($service->lasterror)) {

            if (!$service->meeting_create($zoom)) {
                zoom_print_error('meeting/create', $service->lasterror);
            }

        } else {
            zoom_print_error('meeting/update', $service->lasterror);
        }
    }

    $zoom = $service->lastresponse;

    // Update meeting in database.
    $zoom->timemodified = time();
    $result = $DB->update_record('zoom', $zoom);

    zoom_calendar_item_update($zoom);
    zoom_grade_item_update($zoom);

    return $result;
}

/**
 * Removes an instance of the zoom from the database
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 * @throws moodle_exception if failed to delete and zoom
 *         did not issue a not found/expired error
 */
function zoom_delete_instance($id) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/mod/zoom/classes/webservice.php');

    if (!$zoom = $DB->get_record('zoom', array('id' => $id))) {
        return false;
    }

    // Include locallib.php for constants.
    require_once($CFG->dirroot.'/mod/zoom/locallib.php');

    // Delete any dependent records here.
    // Status -1 means expired and missing from zoom.
    // So don't bother with the webservice in this case.
    if ($zoom->status !== ZOOM_MEETING_EXPIRED) {
        $service = new mod_zoom_webservice();
        $service->delete_meeting($zoom->meeting_id, $zoom->host_id);
    }

    $DB->delete_records('zoom', array('id' => $zoom->id));

    zoom_calendar_item_delete($zoom);
    zoom_grade_item_delete($zoom);

    return true;
}

/**
 * Returns a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 *
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @param stdClass $course The course record
 * @param stdClass $user The user record
 * @param cm_info|stdClass $mod The course module info object or record
 * @param stdClass $zoom The zoom instance record
 * @return stdClass|null
 */
function zoom_user_outline($course, $user, $mod, $zoom) {

    $return = new stdClass();
    $return->time = 0;
    $return->info = '';
    return $return;
}

/**
 * Prints a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * It is supposed to echo directly without returning a value.
 *
 * @param stdClass $course the current course record
 * @param stdClass $user the record of the user we are generating report for
 * @param cm_info $mod course module info
 * @param stdClass $zoom the module instance record
 */
function zoom_user_complete($course, $user, $mod, $zoom) {
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in zoom activities and print it out.
 *
 * @param stdClass $course The course record
 * @param bool $viewfullnames Should we display full names
 * @param int $timestart Print activity since this timestamp
 * @return boolean True if anything was printed, otherwise false
 */
function zoom_print_recent_activity($course, $viewfullnames, $timestart) {
    return false;
}

/**
 * Prepares the recent activity data
 *
 * This callback function is supposed to populate the passed array with
 * custom activity records. These records are then rendered into HTML via
 * {@link zoom_print_recent_mod_activity()}.
 *
 * Returns void, it adds items into $activities and increases $index.
 *
 * @param array $activities sequentially indexed array of objects with added 'cmid' property
 * @param int $index the index in the $activities to use for the next record
 * @param int $timestart append activity since this time
 * @param int $courseid the id of the course we produce the report for
 * @param int $cmid course module id
 * @param int $userid check for a particular user's activity only, defaults to 0 (all users)
 * @param int $groupid check for a particular group's activity only, defaults to 0 (all groups)
 */
function zoom_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0) {
}

/**
 * Prints single activity item prepared by {@link zoom_get_recent_mod_activity()}
 *
 * @param stdClass $activity activity record with added 'cmid' property
 * @param int $courseid the id of the course we produce the report for
 * @param bool $detail print detailed report
 * @param array $modnames as returned by {@link get_module_types_names()}
 * @param bool $viewfullnames display users' full names
 */
function zoom_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
}

/**
 * Returns all other caps used in the module
 *
 * For example, this could be array('moodle/site:accessallgroups') if the
 * module uses that capability.
 *
 * @return array
 */
function zoom_get_extra_capabilities() {
    return array();
}

/**
 * Create or update Moodle calendar event of the Zoom instance.
 *
 * @param stdClass $zoom
 */
function zoom_calendar_item_update(stdClass $zoom) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/calendar/lib.php');

    $event = new stdClass();
    $event->name = $zoom->name;
    if ($zoom->intro) {
        $event->description = $zoom->intro;
        $event->format = $zoom->introformat;
    }
    $event->timestart = $zoom->start_time;
    $event->timeduration = $zoom->duration;
    $event->visible = $zoom->type != ZOOM_RECURRING_MEETING;

    $eventid = $DB->get_field('event', 'id', array(
        'modulename' => 'zoom',
        'instance' => $zoom->id
    ));

    // Load existing event object, or create a new one.
    if (!empty($eventid)) {
        calendar_event::load($eventid)->update($event);
    } else {
        $event->courseid = $zoom->course;
        $event->modulename = 'zoom';
        $event->instance = $zoom->id;
        $event->eventtype = 'zoom';
        calendar_event::create($event);
    }
}

/**
 * Delete Moodle calendar event of the Zoom instance.
 *
 * @param stdClass $zoom
 */
function zoom_calendar_item_delete(stdClass $zoom) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/calendar/lib.php');

    $eventid = $DB->get_field('event', 'id', array(
        'modulename' => 'zoom',
        'instance' => $zoom->id
    ));
    if (!empty($eventid)) {
        calendar_event::load($eventid)->delete();
    }
}

/* Gradebook API */

/**
 * Is a given scale used by the instance of zoom?
 *
 * This function returns if a scale is being used by one zoom
 * if it has support for grading and scales.
 *
 * @param int $zoomid ID of an instance of this module
 * @param int $scaleid ID of the scale
 * @return bool true if the scale is used by the given zoom instance
 */
function zoom_scale_used($zoomid, $scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('zoom', array('id' => $zoomid, 'grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Checks if scale is being used by any instance of zoom.
 *
 * This is used to find out if scale used anywhere.
 *
 * @param int $scaleid ID of the scale
 * @return boolean true if the scale is used by any zoom instance
 */
function zoom_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('zoom', array('grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Creates or updates grade item for the given zoom instance
 *
 * Needed by {@link grade_update_mod_grades()}.
 *
 * @param stdClass $zoom instance object with extra cmidnumber and modname property
 * @param array $grades optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return void
 */
function zoom_grade_item_update(stdClass $zoom, $grades=null) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    $item = array();
    $item['itemname'] = clean_param($zoom->name, PARAM_NOTAGS);
    $item['gradetype'] = GRADE_TYPE_VALUE;

    if ($zoom->grade > 0) {
        $item['gradetype'] = GRADE_TYPE_VALUE;
        $item['grademax']  = $zoom->grade;
        $item['grademin']  = 0;
    } else if ($zoom->grade < 0) {
        $item['gradetype'] = GRADE_TYPE_SCALE;
        $item['scaleid']   = -$zoom->grade;
    } else {
        $item['gradetype'] = GRADE_TYPE_NONE;
    }

    if ($grades === 'reset') {
        $item['reset'] = true;
        $grades = null;
    }

    grade_update('mod/zoom', $zoom->course, 'mod', 'zoom',
            $zoom->id, 0, $grades, $item);
}

/**
 * Delete grade item for given zoom instance
 *
 * @param stdClass $zoom instance object
 * @return grade_item
 */
function zoom_grade_item_delete($zoom) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    return grade_update('mod/zoom', $zoom->course, 'mod', 'zoom',
            $zoom->id, 0, null, array('deleted' => 1));
}

/**
 * Update zoom grades in the gradebook
 *
 * Needed by {@link grade_update_mod_grades()}.
 *
 * @param stdClass $zoom instance object with extra cmidnumber and modname property
 * @param int $userid update grade of specific user only, 0 means all participants
 */
function zoom_update_grades(stdClass $zoom, $userid = 0) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    // Populate array of grade objects indexed by userid.
    if ($zoom->grade == 0) {
        zoom_grade_item_update($zoom);
    } else if ($userid != 0) {
        $grade = grade_get_grades($zoom->course, 'mod', 'zoom', $zoom->id, $userid)->items[0]->grades[$userid];
        $grade->userid = $userid;
        if ($grade->grade == -1) {
            $grade->grade = null;
        }
        zoom_grade_item_update($zoom, $grade);
    } else if ($userid == 0) {
        $context = context_course::instance($zoom->course);
        $enrollusersid = array_keys(get_enrolled_users($context));
        $grades = grade_get_grades($zoom->course, 'mod', 'zoom', $zoom->id, $enrollusersid)->items[0]->grades;
        foreach ($grades as $k => $v) {
            $grades[$k]->userid = $k;
            if ($v->grade == -1) {
                $grades[$k]->grade = null;
            }
        }
        zoom_grade_item_update($zoom, $grades);
    } else {
        zoom_grade_item_update($zoom);
    }
}

/* File API */

/**
 * Returns the lists of all browsable file areas within the given module context
 *
 * The file area 'intro' for the activity introduction field is added automatically
 * by {@link file_browser::get_file_info_context_module()}
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return array of [(string)filearea] => (string)description
 */
function zoom_get_file_areas($course, $cm, $context) {
    return array();
}

/**
 * File browsing support for zoom file areas
 *
 * @package mod_zoom
 * @category files
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info instance or null if not found
 */
function zoom_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    return null;
}

/**
 * Serves the files from the zoom file areas
 *
 * @package mod_zoom
 * @category files
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the zoom's context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 */
function zoom_pluginfile($course, $cm, $context, $filearea, array $args, $forcedownload, array $options=array()) {
    global $DB, $CFG;

    if ($context->contextlevel != CONTEXT_MODULE) {
        send_file_not_found();
    }

    require_login($course, true, $cm);

    send_file_not_found();
}

/* Navigation API */

/**
 * Extends the global navigation tree by adding zoom nodes if there is a relevant content
 *
 * This can be called by an AJAX request so do not rely on $PAGE as it might not be set up properly.
 *
 * @param navigation_node $navref An object representing the navigation tree node of the zoom module instance
 * @param stdClass $course current course record
 * @param stdClass $module current zoom instance record
 * @param cm_info $cm course module information
 */
function zoom_extend_navigation(navigation_node $navref, stdClass $course, stdClass $module, cm_info $cm) {
    // TODO Delete this function and its docblock, or implement it.
}

/**
 * Extends the settings navigation with the zoom settings
 *
 * This function is called when the context for the page is a zoom module. This is not called by AJAX
 * so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav complete settings navigation tree
 * @param navigation_node $zoomnode zoom administration node
 */
function zoom_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $zoomnode=null) {
    // TODO Delete this function and its docblock, or implement it.
}

/* Miscellaneous */

/**
 * Print a user-friendly error message when a Zoom API call errors,
 * or fall back to a generic error message.
 *
 * @param string $apicall API endpoint (e.g. meeting/get)
 * @param string $error Error message (most likely from mod_zoom_webservice->lasterror)
 */
function zoom_print_error($apicall, $error) {
    $errstring = 'zoomerr';
    $param = null;

    // This handles special error messages that aren't the generic zoomerr.
    if (strpos($error, 'Api key and secret are required') !== false) {
        $errstring = 'zoomerr_apisettings_missing';
    } else if (strpos($error, 'Invalid api key or secret') !== false) {
        $errstring = 'zoomerr_apisettings_invalid';
    } else {
        switch ($apicall) {
            case 'user/getbyemail':
                if (strpos($error, 'not exist') !== false) {
                    global $USER;
                    $errstring = 'zoomerr_usernotfound';
                    $param = array(
                        'email' => $USER->email,
                        'zoomurl' => get_config('mod_zoom', 'zoomurl')
                    );
                }
                break;
            case 'meeting/get':
            case 'meeting/update':
            case 'meeting/delete':
                if (is_meeting_gone_error($error)) {
                    $errstring = 'zoomerr_meetingnotfound';
                }
                break;
        }
    }

    print_error($errstring, 'zoom', '', $param, "$apicall: $error");
}

/**
 * Check if the error indicates that a meeting is gone.
 *
 * @param string $error
 * @return bool
 */
function is_meeting_gone_error($error) {
    return strpos($error, 'not found or has expired') !== false;
}
