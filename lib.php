<?php
use Symfony\Component\Config\Resource\ReflectionClassResource;

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
 * Returns the information on whether the module supports a feature.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function zoom_supports($feature) {
    switch($feature) {
        case FEATURE_BACKUP_MOODLE2:
        case FEATURE_COMPLETION_TRACKS_VIEWS:
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
    $service = new mod_zoom_webservice();

    if (defined('PHPUNIT_TEST') && PHPUNIT_TEST) {
        $zoom->id = $DB->insert_record('zoom', $zoom);
        zoom_grade_item_update($zoom);
        zoom_calendar_item_update($zoom);
        return $zoom->id;
    }

    // Deals with password manager issues.
    $zoom->password = $zoom->meetingcode;
    unset($zoom->meetingcode);

    if (empty($zoom->requirepasscode)) {
        $zoom->password = '';
    }

    // Handle weekdays if weekly recurring meeting selected.
    if ($zoom->recurring && $zoom->recurrence_type == ZOOM_RECURRINGTYPE_WEEKLY) {
        $zoom->weekly_days = zoom_handle_weekly_days($zoom);
    }

    $zoom->course = (int) $zoom->course;

    $response = $service->create_meeting($zoom);
    $zoom = populate_zoom_from_response($zoom, $response);
    $zoom->timemodified = time();
    if (!empty($zoom->schedule_for)) {
        // Wait until after receiving a successful response from zoom to update the host
        // based on the schedule_for field. Zoom handles the schedule for on their
        // end, but returns the host as the person who created the meeting, not the person
        // that it was scheduled for.
        $correcthostzoomuser = $service->get_user($zoom->schedule_for);
        $zoom->host_id = $correcthostzoomuser->id;
    }

    if (isset($zoom->recurring) && isset($response->occurrences) && empty($response->occurrences)) {
        // Recurring meetings did not create any occurrencces.
        // This means invalid options selected.
        // Need to rollback created meeting.
        $service->delete_meeting($zoom->meeting_id, $zoom->webinar);

        $redirecturl = new moodle_url('/course/view.php', ['id' => $zoom->course]);
        throw new moodle_exception('erroraddinstance', 'zoom', $redirecturl->out());
    }

    $zoom->id = $DB->insert_record('zoom', $zoom);
    
    // Store tracking field data for meeting.
    $config = get_config('zoom');
    $defaulttrackingfields = explode(",", $config->defaulttrackingfields);
    
    foreach ($defaulttrackingfields as $trackingfield) {
        $trackingfield = trim($trackingfield);
        foreach ($response->tracking_fields as $rtf) {
            if ($rtf->field == $trackingfield) {
                $tfobject = new stdClass();
                $tfobject->meeting_id = $zoom->id;
                $fieldname = strtolower($trackingfield);
                $tfobject->tracking_field = $fieldname;
                $tfobject->value = $zoom->$fieldname;
                $DB->insert_record('zoom_meeting_tracking_fields', $tfobject);
            }
        }
    }

    zoom_calendar_item_update($zoom);
    zoom_grade_item_update($zoom);

    return $zoom->id;
}

/**
 * Updates an instance of the zoom in the database and on Zoom servers.
 *
 * Given an object containing all the necessary data (defined by the form in mod_form.php), this function
 * will update an existing instance with new data.
 *
 * @param stdClass $zoom An object from the form in mod_form.php
 * @param mod_zoom_mod_form $mform The form instance (included because the function is used as a callback)
 * @return boolean Success/Failure
 */
function zoom_update_instance(stdClass $zoom, mod_zoom_mod_form $mform = null) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/mod/zoom/classes/webservice.php');
    $service = new mod_zoom_webservice();

    // The object received from mod_form.php returns instance instead of id for some reason.
    if (isset($zoom->instance)) {
        $zoom->id = $zoom->instance;
    }
    $zoom->timemodified = time();

    // Deals with password manager issues.
    if (isset($zoom->meetingcode)) {
        $zoom->password = $zoom->meetingcode;
        unset($zoom->meetingcode);
    }

    if (property_exists($zoom, 'requirepasscode') && empty($zoom->requirepasscode)) {
        $zoom->password = '';
    }

    // Handle weekdays if weekly recurring meeting selected.
    if ($zoom->recurring && $zoom->recurrence_type == ZOOM_RECURRINGTYPE_WEEKLY) {
        $zoom->weekly_days = zoom_handle_weekly_days($zoom);
    }

    $DB->update_record('zoom', $zoom);

    $updatedzoomrecord = $DB->get_record('zoom', array('id' => $zoom->id));
    $zoom->meeting_id = $updatedzoomrecord->meeting_id;
    $zoom->webinar = $updatedzoomrecord->webinar;

    // Update meeting on Zoom.
    try {
        $service->update_meeting($zoom);
        if (!empty($zoom->schedule_for)) {
            // Only update this if we actually get a valid user.
            if ($correcthostzoomuser = $service->get_user($zoom->schedule_for)) {
                $zoom->host_id = $correcthostzoomuser->id;
                $DB->update_record('zoom', $zoom);
            }
        }
    } catch (moodle_exception $error) {
        return false;
    }
    
    // Update tracking field data for meeting.
    $config = get_config('zoom');
    $defaulttrackingfields = explode(",", $config->defaulttrackingfields);
    
    foreach ($defaulttrackingfields as $trackingfield) {
        $trackingfield = trim($trackingfield);
        $fieldname = strtolower($trackingfield);
        $tfobject = $DB->get_record('zoom_meeting_tracking_fields', array('meeting_id' => $updatedzoomrecord->id, 'tracking_field' => $fieldname));
        $tfobject->value = $zoom->$fieldname;
        $DB->update_record('zoom_meeting_tracking_fields', $tfobject);
    }

    // Get the updated meeting info from zoom, before updating calendar events.
    $response = $service->get_meeting_webinar_info($zoom->meeting_id, $zoom->webinar);
    $zoom = populate_zoom_from_response($zoom, $response);

    zoom_calendar_item_update($zoom);
    zoom_grade_item_update($zoom);

    return true;
}

/**
 * Function to handle selected weekdays, for recurring weekly meeting.
 *
 * @param stdClass $zoom The zoom instance
 * @return string The comma separated string for selected weekdays
 */
function zoom_handle_weekly_days($zoom) {
    $weekdaynumbers = [];
    for ($i = 1; $i <= 7; $i++) {
        $key = 'weekly_days_' . $i;
        if (!empty($zoom->$key)) {
            $weekdaynumbers[] = $i;
        }
    }
    return implode(',', $weekdaynumbers);
}

/**
 * Function to unset the weekly options in postprocessing.
 *
 * @param stdClass $data The form data object
 * @return stdClass $data The form data object minus weekly options.
 */
function zoom_remove_weekly_options($data) {
    // Unset the weekly_days options.
    for ($i = 1; $i <= 7; $i++) {
        $key = 'weekly_days_' . $i;
        unset($data->$key);
    }
    return $data;
}

/**
 * Function to unset the monthly options in postprocessing.
 *
 * @param stdClass $data The form data object
 * @return stdClass $data The form data object minus monthly options.
 */
function zoom_remove_monthly_options($data) {
    // Unset the monthly options.
    unset($data->monthly_repeat_option);
    unset($data->monthly_day);
    unset($data->monthly_week);
    unset($data->monthly_week_day);
    return $data;
}

/**
 * Populates a zoom meeting or webinar from a response object.
 *
 * Given a zoom meeting object from mod_form.php, this function uses the response to repopulate some of the object properties.
 *
 * @param stdClass $zoom An object from the form in mod_form.php
 * @param stdClass $response A response from an API call like 'create meeting' or 'update meeting'
 * @return stdClass A $zoom object ready to be added to the database.
 */
function populate_zoom_from_response(stdClass $zoom, stdClass $response) {
    global $CFG;
    // Inlcuded for constants.
    require_once($CFG->dirroot.'/mod/zoom/locallib.php');

    $newzoom = clone $zoom;

    $samefields = array('start_url', 'join_url', 'created_at', 'timezone');
    foreach ($samefields as $field) {
        if (isset($response->$field)) {
            $newzoom->$field = $response->$field;
        }
    }
    if (isset($response->duration)) {
        $newzoom->duration = $response->duration * 60;
    }
    $newzoom->meeting_id = $response->id;
    $newzoom->name = $response->topic;
    if (isset($response->agenda)) {
        $newzoom->intro = $response->agenda;
    }
    if (isset($response->start_time)) {
        $newzoom->start_time = strtotime($response->start_time);
    }
    $recurringtypes = [
        ZOOM_RECURRING_MEETING,
        ZOOM_RECURRING_FIXED_MEETING,
        ZOOM_RECURRING_WEBINAR,
        ZOOM_RECURRING_FIXED_WEBINAR,
    ];
    $newzoom->recurring = in_array($response->type, $recurringtypes);
    if (!empty($response->occurrences)) {
        $newzoom->occurrences = [];
        // Normalise the occurrence times.
        foreach ($response->occurrences as $occurrence) {
            $occurrence->start_time = strtotime($occurrence->start_time);
            $occurrence->duration = $occurrence->duration * 60;
            $newzoom->occurrences[] = $occurrence;
        }
    }
    if (isset($response->password)) {
        $newzoom->password = $response->password;
    }
    if (isset($response->settings->encryption_type)) {
        $newzoom->option_encryption_type = $response->settings->encryption_type;
    }
    if (isset($response->settings->join_before_host)) {
        $newzoom->option_jbh = $response->settings->join_before_host;
    }
    if (isset($response->settings->participant_video)) {
        $newzoom->option_participants_video = $response->settings->participant_video;
    }
    if (isset($response->settings->alternative_hosts)) {
        $newzoom->alternative_hosts = $response->settings->alternative_hosts;
    }
    if (isset($response->settings->mute_upon_entry)) {
        $newzoom->option_mute_upon_entry = $response->settings->mute_upon_entry;
    }
    if (isset($response->settings->meeting_authentication)) {
        $newzoom->option_authenticated_users = $response->settings->meeting_authentication;
    }
    if (isset($response->settings->waiting_room)) {
        $newzoom->option_waiting_room = $response->settings->waiting_room;
    }

    return $newzoom;
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
        // For some reason already deleted, so let Moodle take care of the rest.
        return true;
    }

    // Include locallib.php for constants.
    require_once($CFG->dirroot.'/mod/zoom/locallib.php');

    // If the meeting is missing from zoom, don't bother with the webservice.
    if ($zoom->exists_on_zoom == ZOOM_MEETING_EXISTS) {
        $service = new mod_zoom_webservice();
        try {
            $service->delete_meeting($zoom->meeting_id, $zoom->webinar);
        } catch (zoom_not_found_exception $error) {
            // Meeting not on Zoom, so continue.
            mtrace('Meeting not on Zoom; continuing');
        } catch (moodle_exception $error) {
            // Some other error, so throw error.
            throw $error;
        }
    }

    // If we delete a meeting instance, do we want to delete the participants?
    $meetinginstances = $DB->get_records('zoom_meeting_details', array('meeting_id' => $zoom->meeting_id));
    foreach ($meetinginstances as $meetinginstance) {
        $DB->delete_records('zoom_meeting_participants', array('uuid' => $meetinginstance->uuid));
    }
    $DB->delete_records('zoom_meeting_details', array('meeting_id' => $zoom->meeting_id));
    
    // Delete tracking field data for deleted meetings.
    $DB->delete_records('zoom_meeting_tracking_fields', array('meeting_id' => $zoom->id));

    // Delete any dependent records here.
    zoom_calendar_item_delete($zoom);
    zoom_grade_item_delete($zoom);

    $DB->delete_records('zoom', array('id' => $zoom->id));

    return true;
}

/**
 * Callback function to update the Zoom event in the database and on Zoom servers.
 *
 * The function is triggered when the course module name is set via quick edit.
 *
 * @param int $courseid
 * @param stdClass $zoom Zoom Module instance object.
 * @param stdClass $cm Course Module object.
 * @return bool
 */
function zoom_refresh_events($courseid, $zoom, $cm) {
    return zoom_update_instance($zoom);
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
 * custom activity records. These records are then rendered into HTML
 * zoom_print_recent_mod_activity().
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
 * Prints single activity item prepared by zoom_get_recent_mod_activity()
 *
 * @param stdClass $activity activity record with added 'cmid' property
 * @param int $courseid the id of the course we produce the report for
 * @param bool $detail print detailed report
 * @param array $modnames as returned by get_module_types_names()
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
 * @param stdClass $zoom
 */
function zoom_calendar_item_update(stdClass $zoom) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/calendar/lib.php');

    // Based on data passed back from zoom, create/update/delete events based on data.
    $newevents = array();
    if (!$zoom->recurring) {
        $newevents[''] = zoom_populate_calender_item($zoom);
    } else if (!empty($zoom->occurrences)) {
        foreach ($zoom->occurrences as $occurrence) {
            $uuid = $occurrence->occurrence_id;
            $newevents[$uuid] = zoom_populate_calender_item($zoom, $occurrence);
        }
    }

    // Fetch all the events related to this zoom instance.
    $conditions = array(
        'modulename' => 'zoom',
        'instance' => $zoom->id,
    );
    $events = $DB->get_records('event', $conditions);
    $eventfields = array('name', 'timestart', 'timeduration');
    foreach ($events as $event) {
        $uuid = $event->uuid;
        if (isset($newevents[$uuid])) {
            // This event already exists in Moodle.
            $changed = false;
            $newevent = $newevents[$uuid];
            // Check if the important fields have actually changed.
            foreach ($eventfields as $field) {
                if ($newevent->$field !== $event->$field) {
                    $changed = true;
                }
            }
            if ($changed) {
                calendar_event::load($event)->update($newevent);
            }

            // Event has been updated, remove from the list.
            unset($newevents[$uuid]);
        } else {
            // Event does not exist in Zoom, so delete from Moodle.
            calendar_event::load($event)->delete();
        }
    }

    // Any remaining events in the array don't exist on Moodle, so create a new event.
    foreach ($newevents as $uuid => $newevent) {
        calendar_event::create($newevent);
    }
}

/**
 * Return an array with the days of the week.
 *
 * @return array
 */
function zoom_get_weekday_options() {
    return [
        1 => get_string('sunday', 'calendar'),
        2 => get_string('monday', 'calendar'),
        3 => get_string('tuesday', 'calendar'),
        4 => get_string('wednesday', 'calendar'),
        5 => get_string('thursday', 'calendar'),
        6 => get_string('friday', 'calendar'),
        7 => get_string('saturday', 'calendar'),
    ];
}

/**
 * Return an array with the weeks of the month.
 *
 * @return array
 */
function zoom_get_monthweek_options() {
    return [
        1 => get_string('weekoption_first', 'zoom'),
        2 => get_string('weekoption_second', 'zoom'),
        3 => get_string('weekoption_third', 'zoom'),
        4 => get_string('weekoption_fourth', 'zoom'),
        -1 => get_string('weekoption_last', 'zoom'),
    ];
}

/**
 * Populate the calendar event object, based on the zoom instance
 *
 * @param stdClass $zoom The zoom instance.
 * @param stdClass $occurrence The occurrence object passed from the zoom api.
 * @return stdClass The calendar event object.
 */
function zoom_populate_calender_item(stdClass $zoom, stdClass $occurrence = null) {
    $event = new stdClass();
    $event->type = CALENDAR_EVENT_TYPE_ACTION;
    $event->modulename = 'zoom';
    $event->eventtype = 'zoom';
    $event->courseid = $zoom->course;
    $event->instance = $zoom->id;
    $event->visible = true;
    $event->name = $zoom->name;
    if ($zoom->intro) {
        $event->description = $zoom->intro;
        $event->format = $zoom->introformat;
    }
    if (!$occurrence) {
        $event->timesort = $zoom->start_time;
        $event->timestart = $zoom->start_time;
        $event->timeduration = $zoom->duration;
    } else {
        $event->timesort = $occurrence->start_time;
        $event->timestart = $occurrence->start_time;
        $event->timeduration = $occurrence->duration;
        $event->uuid = $occurrence->occurrence_id;
    }

    // Recurring meetings/webinars with no fixed time are created as invisible events.
    // For recurring meetings/webinars with a fixed time, we want to see the events on the calendar.
    if ($zoom->recurring && $zoom->recurrence_type == ZOOM_RECURRINGTYPE_NOTIME) {
        $event->visible = false;
    }

    return $event;
}

/**
 * Delete Moodle calendar events of the Zoom instance.
 *
 * @param stdClass $zoom
 */
function zoom_calendar_item_delete(stdClass $zoom) {
    global $CFG, $DB;
    require_once($CFG->dirroot.'/calendar/lib.php');

    $events = $DB->get_records('event', array(
        'modulename' => 'zoom',
        'instance' => $zoom->id
    ));
    foreach ($events as $event) {
        calendar_event::load($event)->delete();
    }
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @param int $userid User id override
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_zoom_core_calendar_provide_event_action(calendar_event $event,
                                                      \core_calendar\action_factory $factory, $userid = null) {
    global $CFG, $DB, $USER;

    require_once($CFG->dirroot . '/mod/zoom/locallib.php');

    if (empty($userid)) {
        $userid = $USER->id;
    }

    $cm = get_fast_modinfo($event->courseid, $userid)->instances['zoom'][$event->instance];
    $zoom  = $DB->get_record('zoom', array('id' => $cm->instance), '*');
    list($inprogress, $available, $finished) = zoom_get_state($zoom);

    return $factory->create_instance(
        get_string('join_meeting', 'zoom'),
        new \moodle_url('/mod/zoom/view.php', array('id' => $cm->id)),
        1,
        $available
    );
}

function mod_zoom_update_tracking_fields() {
    global $DB;
    
    $config = get_config('zoom');
    $defaulttrackingfields = explode(",", $config->defaulttrackingfields);
    $zoomtrackingfields = zoom_list_tracking_fields();
    $configtypes = array('id', 'field', 'required', 'visible', 'recommended_values');
    $configvalue = '';
    $confignames = array();
    
    foreach ($defaulttrackingfields as $defaulttrackingfield) {
        $defaulttrackingfield = trim($defaulttrackingfield);
        foreach ($zoomtrackingfields as $zoomtrackingfield) {
            $key = array_search($defaulttrackingfield, $zoomtrackingfield);
            if ($key) {
                foreach ($configtypes as $configtype) {
                    $configname = 'tf_' . strtolower($defaulttrackingfield) . '_' . $configtype;
                    $confignames[] = $configname;
                    if ($configtype == 'recommended_values') {
                        foreach ($zoomtrackingfield[$configtype] as $rv) {
                            $configvalue .= $rv . ', ';
                        }
                        $configvalue = rtrim($configvalue, ', ');
                    } else {
                        $configvalue = $zoomtrackingfield[$configtype];
                    }
                    set_config($configname, $configvalue, 'zoom');
                }
                break;
            }
        }
    }
    
    $proparray = get_object_vars($config);
    $properties = array_keys($proparray);
    $oldconfigs = array_diff($properties, $confignames);
    $pattern = '/^tf_([^_]*)_.*/';
    foreach ($oldconfigs as $oldconfig) {
        if (preg_match($pattern, $oldconfig, $oldfield)) {
            set_config($oldconfig, null, 'zoom');
            $DB->delete_records('zoom_meeting_tracking_fields', array('tracking_field' => $oldfield[1]));
        }
    }
}

/* Gradebook API */

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
 * Needed by grade_update_mod_grades().
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
        $gradebook = grade_get_grades($zoom->course, 'mod', 'zoom', $zoom->id);
        // Prevent the gradetype from switching to None if grades exist.
        if (empty($gradebook->items[0]->grades)) {
            $item['gradetype'] = GRADE_TYPE_NONE;
        } else {
            return;
        }
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
 * Needed by grade_update_mod_grades().
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
 * by file_browser::get_file_info_context_module()
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
