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
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_GROUPINGS:
        case FEATURE_GROUPMEMBERSONLY:
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the zoom object into the database.
 *
 * Given an object containing all the necessary data (defined by the form in mod_form.php), this function
 * will create a new instance and return the id number of the new instance.
 *
 * @param stdClass $zoom Submitted data from the form in mod_form.php
 * @param mod_zoom_mod_form $mform The form instance (included because the function is used as a callback)
 * @return int The id of the newly inserted zoom record
 */
function zoom_add_instance(stdClass $zoom, mod_zoom_mod_form $mform = null) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/mod/zoom/classes/webservice.php');

    $instance = mod_zoom_instance::factory($zoom->webinar);
    $instance->populate_from_mod_form($zoom);

    $service = new mod_zoom_webservice();
    $response = $service->create_instance($instance->export_to_api_format(), $instance->is_webinar(), $instance->hostid);
    // Updating our data with the data returned by Zoom ensures that our data match.
    $instance->populate_from_api_response($response);

    $newid = $DB->insert_record('zoom', $instance->export_to_database_format());
    $instance->set_databaseid($newid);

    // TODO: figure out how to handle these
    zoom_calendar_item_update($instance);
    zoom_grade_item_update($zoom);

    return $newid;
}

/**
 * Updates an instance of the zoom in the database and on Zoom servers.
 *
 * Given an object containing all the necessary data (defined by the form in mod_form.php), this function
 * will update an existing instance with new data.
 *
 * @param stdClass $zoom An object from the form in mod_form.php
 * @param mod_zoom_mod_form $mform The form instance (included because the function is used as a callback)
 * @return int The id of the newly inserted zoom record
 */
function zoom_update_instance(stdClass $zoom, mod_zoom_mod_form $mform = null) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/mod/zoom/classes/webservice.php');

    if ($zoom->webinar) {
        $instance = new mod_zoom_webinar();
    } else {
        $instance = new mod_zoom_meeting();
    }
    $instance->populate_from_mod_form($zoom);
    $instance->make_modified_now();
    $DB->update_record('zoom', $instance->export_to_database_format());
    $updatedinstancerecord = $DB->get_record('zoom', array('id' => $instance->databaseid));
    $instance->populate_from_database_record($updatedinstancerecord);

    // Update meeting on Zoom.
    $service = new mod_zoom_webservice();
    $service->update_instance($instance->export_to_api_format(), $instance->is_webinar(), $instance->id);


    // TODO: need to fix these here too. kind of avoiding this haha.
    zoom_calendar_item_update($instance);
    zoom_grade_item_update($zoom);

    return $zoom->id;
}

/**
 * Removes an instance of the zoom from the database
 *
 * Given an ID of an instance of this module, this function will permanently delete the instance and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 * @throws moodle_exception if failed to delete and zoom did not issue a not found error
 */
function zoom_delete_instance($id) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/mod/zoom/classes/webservice.php');

    if (!$zoom = $DB->get_record('zoom', array('id' => $id))) {
        return false;
    }

    // Include locallib.php for constants.
    require_once($CFG->dirroot.'/mod/zoom/locallib.php');

    $instance = mod_zoom_instance::factory($record->webinar);
    $instance->populate_from_api_response($record);

    // If the meeting is missing from zoom, don't bother with the webservice.
    if ($instance->exists_on_zoom()) {
        $service = new mod_zoom_webservice();
        try {
            $service->delete_meeting($instance->get_instance_id(), $instance->is_webinar());
        } catch (moodle_exception $error) {
            if (!error_indicates_meeting_gone($error)) {
                throw $error;
            }
        }
    }

    $DB->delete_records('zoom', array('id' => $instance->get_database_id()));

    // If we delete a meeting instance, do we want to delete the participants?
    $meetinginstances = $DB->get_records('zoom_meeting_details', array('meeting_id' => $instance->get_instance_id()));
    foreach ($meetinginstances as $meetinginstance) {
        $DB->delete_records('zoom_meeting_participants', array('uuid' => $meetinginstance->uuid));
    }
    $DB->delete_records('zoom_meeting_details', array('meeting_id' => $instance->get_instance_id()));

    // Delete any dependent records here.
    zoom_calendar_item_delete($zoom); // TODO: lol this thing again
    zoom_grade_item_delete($zoom);

    return true;
}

/**
 * Given a course and a time, this module should find recent activity that has occurred in zoom activities and print it out.
 *
 * @param stdClass $course The course record
 * @param bool $viewfullnames Should we display full names
 * @param int $timestart Print activity since this timestamp
 * @return boolean True if anything was printed, otherwise false
 * @todo implement this function
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
 * @todo implement this function
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
 * @todo implement this function
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
 * @todo implement this function
 */
function zoom_get_extra_capabilities() {
    return array();
}

/**
 * Create or update Moodle calendar event of the Zoom instance.
 *
 * @param mod_zoom\instance $instance
 */
function zoom_calendar_item_update(mod_zoom_instance $instance) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/calendar/lib.php');

    $databaseid = $instance->databaseid;
    $eventid = $DB->get_field('event', 'id', array(
        'modulename' => 'zoom',
        'instance' => $databaseid
    ));

    // Load existing event object, or create a new one.
    if (empty($eventid)) {
        $event = $instance->export_to_calendar_format(True);
        calendar_event::create($event);
    } else {
        $event = $instance->export_to_calendar_format(False);
        calendar_event::load($eventid)->update($event);
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
 * Needed by {@link grade_update_mod_grades()}. This is a callback?
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

    grade_update('mod/zoom', $zoom->course, 'mod', 'zoom', $zoom->id, 0, $grades, $item);
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
 * @todo implement this function
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
 * @todo implement this function
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
 * @todo implement this function
 */
function zoom_extend_navigation(navigation_node $navref, stdClass $course, stdClass $module, cm_info $cm) {
}

/**
 * Extends the settings navigation with the zoom settings
 *
 * This function is called when the context for the page is a zoom module. This is not called by AJAX
 * so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav complete settings navigation tree
 * @param navigation_node $zoomnode zoom administration node
 * @todo implement this function
 */
function zoom_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $zoomnode=null) {
}

/**
 * Get icon mapping for font-awesome.
 *
 * @see https://docs.moodle.org/dev/Moodle_icons
 */
function mod_zoom_get_fontawesome_icon_map() {
    return [
        'mod_zoom:i/calendar' => 'fa-calendar'
    ];
}
