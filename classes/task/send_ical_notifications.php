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
 * Task: send_ical_notification
 *
 * @package    mod_zoom
 * @copyright  2024 OPENCOLLAB <info@opencollab.co.za>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoom\task;

use calendar_event;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/calendar/lib.php');
require_once($CFG->libdir.'/bennu/bennu.inc.php');
require_once($CFG->libdir.'/bennu/iCalendar_components.php');
require_once($CFG->dirroot . '/mod/zoom/locallib.php');

/**
 * Scheduled task to send ical notifications for zoom meetings that were scheduled within the last 30 minutes.
 */
class send_ical_notifications extends \core\task\scheduled_task {

    /**
     * Execute the send ical notifications cron function.
     *
     * @return void nothing.
     */
    public function execute() {
        if (get_config('zoom', 'sendicalnotifications')) {
            mtrace('[Zoom ical Notifications] Starting cron job.');
            $zoomrecords = $this->get_potential_zoom_to_notify();
            if ($zoomrecords) {
                foreach ($zoomrecords as $zoomrecord) {
                    mtrace('[Zoom ical Notifications] Checking to see if zoom record with ID ' .
                            $zoomrecord->id . ' was notified before.');
                    $executiontime = $this->get_notification_execution_time($zoomrecord->id);
                    // Only run if it hasn't run before.
                    if ($executiontime == 0) {
                        mtrace('[Zoom ical Notifications] Zoom instance with ID ' .
                                $zoomrecord->id . ' can be notified - not notified before.');
                        $this->zoom_ical_notification($zoomrecord);
                        // Set execution time for this cron job.
                        mtrace('[Zoom ical Notifications] Zoom instance with ID ' . $zoomrecord->id .
                                ' was successfully notified - set execution time for log table.');
                        $this->set_notification_execution_time($zoomrecord->id);
                    }
                }
            } else {
                mtrace('[Zoom ical Notifications] Found no zoom records to process and notify ' .
                       '(created or modified within the last hour that was not notified before).');
            }
            mtrace('[Zoom ical Notifications] Cron job Completed.');
        } else {
            mtrace('[Zoom ical Notifications] The Admin Setting for the Send iCal Notification scheduled task ' .
                   'has not been enabled - will not run the cron job.');
        }
    }

    /**
     * Get zoom module instances created/modified in the last hour, but ignore the last 10 minutes.
     */
    private function get_potential_zoom_to_notify() {
        global $DB;

        $sql = 'SELECT *
        FROM {zoom}
        WHERE timemodified >= (unix_timestamp() - (60 * 60))
        AND timemodified <= (unix_timestamp() - (10 * 60))';

        return $DB->get_records_sql($sql);
    }

    /**
     * Get the execution time for the related zoom id.
     * @param string $zoomid The zoom instance id.
     * @return string The timestamp of the last execution.
     */
    private function get_notification_execution_time(string $zoomid) {
        global $DB;

        $sql = 'SELECT execution_time
        FROM {zoom_ical_notifications}
        WHERE zoomid = :zoom_id';

        $executiontime = $DB->get_field_sql($sql, ['zoom_id' => $zoomid]);
        if (!$executiontime) {
            $executiontime = 0;
        }
        return $executiontime;
    }

    /**
     * Set the execution time (the current time) for the related zoom id.
     * @param string $zoomid The zoom instance id.
     */
    private function set_notification_execution_time(string $zoomid) {
        global $DB;

        $icalnotifojb = new \stdClass();
        $icalnotifojb->zoomid = $zoomid;
        $icalnotifojb->execution_time = time();

        $DB->insert_record('zoom_ical_notifications', $icalnotifojb);
    }

    /**
     * The zoom ical notification task.
     * @param mixed $zoom The zoom entry.
     */
    private function zoom_ical_notification($zoom) {
        global $CFG, $DB, $SITE;

        mtrace('[Zoom ical Notifications] Notifying Zoom instance with ID ' . $zoom->id);

        $zoomevent = $DB->get_record('event', ['instance' => $zoom->id, 'courseid' => $zoom->course,
                                     'modulename' => 'zoom', 'eventtype' => 'zoom']);
        if ($zoomevent) {

            $calevent = new \calendar_event($zoomevent); // To use moodle calendar event services.

            $users = $this->zoom_get_users_to_notify($zoom->id);
            $users = $this->zoom_filter_users($zoom, $users);

            // HTML value to render in email body.
            $caleventdescription = $calevent->description;
            // Avoid filters - so we can manually add links as required.
            $activityurl = $CFG->wwwroot . '/mod/zoom/view.php?id=';
            $modinfo = get_fast_modinfo($zoom->course);
            $coursemodules = $modinfo->get_cms();
            if ($coursemodules) {
                foreach ($coursemodules as $coursemod) {
                    if ($coursemod->modname == 'zoom' && $coursemod->instance == $zoom->id) {
                        $activityurl .= $coursemod->id;
                        break;
                    }
                }
            }

            $filestorage = get_file_storage();

            foreach ($users as $user) {
                // Check if user has "Disable notifications" set.
                if ($user->emailstop) {
                    continue;
                }

                // Send email.
                $ical = $this->create_ical_object($zoom, $zoomevent, $calevent, $caleventdescription, $user);

                $filerecord = [
                    'contextid' => \context_user::instance($user->id)->id,
                    'component' => 'user',
                    'filearea' => 'draft',
                    'itemid' => file_get_unused_draft_itemid(),
                    'filepath' => '/',
                    'filename' => clean_filename('icalexport.ics'),
                ];

                $serializedical = $ical->serialize();
                if (!$serializedical || empty($serializedical)) {
                    mtrace('[Zoom ical Notifications] A problem occurred while trying to serialize the ical data for user ID ' .
                            $user->id . ' for zoom instance with ID ' . $zoom->id);
                    continue;
                }

                $icalfileattachment = $filestorage->create_file_from_string($filerecord, $serializedical);

                $messagedata = new \core\message\message();
                $messagedata->component = 'mod_zoom';
                $messagedata->name = 'ical_notifications';
                $messagedata->userfrom = \core_user::get_noreply_user();
                $messagedata->userto = $user;
                $messagedata->subject = $zoomevent->name;
                $messagedata->fullmessage = $caleventdescription;
                $messagedata->fullmessageformat = FORMAT_HTML;
                $messagedata->fullmessagehtml = $caleventdescription;
                $messagedata->smallmessage = $zoomevent->name . ' - ' . $caleventdescription;
                $messagedata->notification = true;
                $messagedata->attachment = $icalfileattachment;
                $messagedata->attachname = $icalfileattachment->get_filename();

                $emailsuccess = message_send($messagedata);

                if ($emailsuccess) {
                    mtrace('[Zoom ical Notifications] Successfully emailed user ID ' . $user->id .
                           ' for zoom instance with ID ' . $zoom->id);
                } else {
                    mtrace('[Zoom ical Notifications] A problem occurred while emailing user ID ' . $user->id .
                           ' for zoom instance with ID ' . $zoom->id);
                }
            }
        }
    }

    /**
     * Create the ical object.
     * @param stdClass $zoom The zoom entry.
     * @param object $zoomevent The event entry for the zoom instance.
     * @param \calendar_event $calevent The calendar event related to the zoom instance.
     * @param string $description The calendar event description.
     * @param \user $user The user object.
     * @return \iCalendar
     */
    private function create_ical_object($zoom, $zoomevent, $calevent, $description, $user) {
        global $CFG;
        $ical = new \iCalendar;
        $ical->add_property('method', 'PUBLISH');
        $ical->add_property('prodid', '-//Moodle Pty Ltd//NONSGML Moodle Version ' . $CFG->version . '//EN');

        $hostaddress = str_replace('http://', '', $CFG->wwwroot);
        $hostaddress = str_replace('https://', '', $hostaddress);

        $ev = new \iCalendar_event; // To export in ical format.
        $ev->add_property('uid', $zoomevent->id.'@'.$hostaddress);

        // Set iCal event summary from event name.
        $ev->add_property('summary', format_string($zoomevent->name, true, ['context' => $calevent->context]));

        $ev->add_property('description', html_to_text($description, 0));

        $ev->add_property('class', 'PUBLIC'); // PUBLIC / PRIVATE / CONFIDENTIAL.

        // Since we don't cater for modified invites, the created and last modified dates are the same.
        $ev->add_property('created', \Bennu::timestamp_to_datetime($zoomevent->timemodified));
        $ev->add_property('last-modified', \Bennu::timestamp_to_datetime($zoomevent->timemodified));

        $noreplyuser = \core_user::get_noreply_user();
        $ev->add_property('organizer', 'mailto:' . $noreplyuser->email, ['cn' => $this->get_lms_site_name()]);
        // Need to strip out the double quotations around the 'organizer' values - probably a bug in the core code.
        $ev->properties['ORGANIZER'][0]->value = substr($ev->properties['ORGANIZER'][0]->value, 1, -1);
        $ev->properties['ORGANIZER'][0]->parameters['CN'] = substr($ev->properties['ORGANIZER'][0]->parameters['CN'], 1, -1);

        $ev->add_property('dtstamp', \Bennu::timestamp_to_datetime());
        if ($zoomevent->timeduration > 0) {
            // Property dtend is better than duration, because it works in Microsoft Outlook and works better in Korganizer.
            $ev->add_property('dtstart', \Bennu::timestamp_to_datetime($zoomevent->timestart)); // When event starts.
            $ev->add_property('dtend', \Bennu::timestamp_to_datetime($zoomevent->timestart + $zoomevent->timeduration));
        } else if ($zoomevent->timeduration == 0) {
            // When no duration is present, the event is instantaneous event, ex - Due date of a module.
            // Moodle doesn't support all day events yet. See MDL-56227.
            $ev->add_property('dtstart', \Bennu::timestamp_to_datetime($zoomevent->timestart));
            $ev->add_property('dtend', \Bennu::timestamp_to_datetime($zoomevent->timestart));
        } else {
            // This can be used to represent all day events in future.
            throw new \coding_exception("Negative duration is not supported yet.");
        }

        if ($zoom->registration == ZOOM_REGISTRATION_OFF) {
            $ev->add_property('location', $zoom->join_url);
        } else {
            $registrantjoinurl = zoom_get_registrant_join_url($user->email, $zoom->meeting_id, $zoom->webinar);
            if ($registrantjoinurl) {
                $ev->add_property('location', $registrantjoinurl);
            } else {
                $ev->add_property('location', $zoom->join_url);
            }
        }

        $ical->add_component($ev);
        return $ical;
    }

    /**
     * Filter the zoom users based on availability restrictions.
     * @param object $zoom The zoom entry.
     * @param array $users An array of users that potentially has access to the Zoom activity.
     * @return array A filtered array of users.
     */
    private function zoom_filter_users($zoom, $users) {
        $modinfo = get_fast_modinfo($zoom->course);
        $coursemodules = $modinfo->get_cms();
        if ($coursemodules) {
            foreach ($coursemodules as $coursemod) {
                if ($coursemod->modname == 'zoom' && $coursemod->instance == $zoom->id) {
                    $availinfo = new \core_availability\info_module($coursemod);
                    $users = $availinfo->filter_user_list($users);
                    break;
                }
            }
        }
        return $users;
    }

    /**
     * Get an array of users in the format of userid=>user object.
     * @param string $zoomid The zoom instance id.
     * @return array An array of users.
     */
    private function zoom_get_users_to_notify($zoomid) {
        global $DB;
        $zoomusers = [];

        $sql = 'SELECT distinct ue.userid
        FROM {zoom} z
        JOIN {enrol} e
        ON e.courseid = z.course
        JOIN {user_enrolments} ue
        ON ue.enrolid = e.id
        WHERE z.id = :zoom_id';

        $zoomparticipantsids = $DB->get_records_sql($sql, ['zoom_id' => $zoomid]);
        if ($zoomparticipantsids) {
            foreach ($zoomparticipantsids as $zoomparticipantid) {
                $zoomusers += [$zoomparticipantid->userid => \core_user::get_user($zoomparticipantid->userid)];
            }
        }

        return $zoomusers;
    }

    /**
     * Get the LMS site name.
     * @return string The site name.
     */
    private function get_lms_site_name() {
        global $DB;

        $sql = 'SELECT fullname
        FROM {course}
        WHERE format = :format';

        return $DB->get_field_sql($sql, ['format' => 'site']);
    }

    /**
     * Returns the name of the task.
     *
     * @return string task name.
     */
    public function get_name() {
        return get_string('sendicalnotifications', 'mod_zoom');
    }

}
