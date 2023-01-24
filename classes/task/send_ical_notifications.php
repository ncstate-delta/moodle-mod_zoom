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
 * @copyright  2022 Paul Marais <paul@opencollab.co.za>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoom\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/calendar/lib.php');
require_once($CFG->libdir . '/bennu/bennu.inc.php');
require_once($CFG->libdir . '/bennu/iCalendar_components.php');
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
            $zooms = $this->get_potential_zoom_to_notify();
            if ($zooms) {
                foreach ($zooms as $zoom) {
                    mtrace('[Zoom ical Notifications] Checking to see if zoom ID ' . $zoom->id . ' was notified before.');
                    $executiontime = $this->get_notification_execution_time($zoom->id);
                    // Only run if it hasn't run before.
                    if ($executiontime === 0) {
                        mtrace('[Zoom ical Notifications] Zoom ID ' . $zoom->id . ' can be notified - not notified before.');
                        $this->zoom_ical_notification($zoom);
                        mtrace('[Zoom ical Notifications] Zoom ID ' . $zoom->id . ' was successfully notified.');
                    }
                }
            } else {
                mtrace('[Zoom ical Notifications] No events to process.');
            }
            mtrace('[Zoom ical Notifications] Cron job Completed.');
        } else {
            mtrace('[Zoom ical Notifications] This scheduled task is not enabled in the plugin admin settings.');
        }
    }

    private function get_potential_zoom_to_notify() {
        global $DB;

        // Get zoom module instances created/modified in the last hour, but ignore the last 10 minutes.
        $sql = 'SELECT *
        FROM {zoom}
        WHERE timemodified >= ' . (time() - 3600) . '
        AND timemodified <= ' . (time() - 600);

        return $DB->get_records_sql($sql);
    }

    private function get_notification_execution_time($zoomid) {
        global $DB;

        $sql = 'SELECT executiontime
        FROM {zoom_ical_notifications}
        WHERE zoomid = :zoomid';

        $executiontime = $DB->get_field_sql($sql, ['zoomid' => $zoomid]);
        if (!$executiontime) {
            $executiontime = 0;
        }
        return $executiontime;
    }

    private function set_notification_execution_time($zoomid) {
        global $DB;

        $record = new \stdClass();
        $record->zoomid = $zoomid;
        $record->executiontime = time();

        $DB->insert_record('zoom_ical_notifications', $record);
    }

    private function zoom_ical_notification($zoom) {
        global $CFG, $DB, $SITE;

        mtrace('[Zoom ical Notifications] Notifying Zoom instance with ID ' . $zoom->id);

        $zoomevent = $DB->get_record('event', ['instance' => $zoom->id, 'courseid' => $zoom->course, 'modulename' => 'zoom', 'eventtype' => 'zoom']);
        if ($zoomevent) {
            $calevent = new \calendar_event($zoomevent); // To use moodle calendar event services.

            $users = $this->zoom_get_users_to_notify($zoom->id);
            $users = $this->zoom_filter_users($zoom, $users);

            $from = format_string($SITE->shortname) . ' Event No Reply (via ' .  format_string($SITE->shortname) . ')';

            $ical = null;
            $filename = null;

            // HTML value to render in email body.
            $description = $calevent->description;
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
            $description .= '<p>Links:<br>';
            $description .= '-------<br>';
            $description .= $activityurl;
            $description .= '</p>';

            // If no registration required - then the same join link can be used - reuse one ical object.
            if ($zoom->registration == ZOOM_REGISTRATION_OFF) {
                $ical = $this->create_ical_object($zoom, $zoom_event, $cal_event, $description);
                $filename = $this->serialize_attachment($ical);
            }

            foreach ($users as $user) {
                // If registration is required - then a unique registration link per user is required.
                if ($zoom->registration != ZOOM_REGISTRATION_OFF) {
                    $ical = $this->create_ical_object($zoom, $zoomevent, $calevent, $description, $user);
                    $filename = $this->serialize_attachment($ical);
                }
                $emailsuccess = email_to_user($user, $from, $zoomevent->name, $description, $description, $filename, $filename);
                if ($emailsuccess) {
                    mtrace('[Zoom ical Notifications] Successfully emailed user ID ' . $user->id . ' for zoom ID ' . $zoom->id);
                } else {
                    mtrace('[Zoom ical Notifications] Error while emailing user ID ' . $user->id . ' for zoom ID ' . $zoom->id);
                }
            }

            // Set execution time for this cron job
            $this->set_notification_execution_time($zoom->id);
        } 
    }

    private function create_ical_object($zoom, $zoomevent, $calevent, $description, $user=null) {
        global $CFG;
        $ical = new \iCalendar;
        $ical->add_property('method', 'PUBLISH');
        $ical->add_property('prodid', '-//Moodle Pty Ltd//NONSGML Moodle Version ' . $CFG->version . '//EN');

        $hostaddress = str_replace('http://', '', $CFG->wwwroot);
        $hostaddress = str_replace('https://', '', $hostaddress);

        $ev = new \iCalendar_event; // To export in ical format.
        $ev->add_property('uid', $zoomevent->id . '@' . $hostaddress);

        // Set iCal event summary from event name.
        $ev->add_property('summary', format_string($zoomevent->name, true, ['context' => $calevent->context]));
        $ev->add_property('description', html_to_text($description, 0));
        $ev->add_property('class', 'PUBLIC'); // PUBLIC / PRIVATE / CONFIDENTIAL.

        // Since we don't cater for modified invites, the created and last modified dates are the same.
        $ev->add_property('created', \Bennu::timestamp_to_datetime($zoomevent->timemodified));
        $ev->add_property('last-modified', \Bennu::timestamp_to_datetime($zoomevent->timemodified));

        $noreplyuser = \core_user::get_noreply_user();
        $ev->add_property('organizer', 'mailto:' . $noreplyuser->email, ['cn' => $this->get_lms_site_name()]);
        // Need to strip out the double quotations around the 'organizer' values - probably a bug in the core code
        $ev->properties['ORGANIZER'][0]->value = substr($ev->properties['ORGANIZER'][0]->value, 1, -1);
        $ev->properties['ORGANIZER'][0]->parameters['CN'] = substr($ev->properties['ORGANIZER'][0]->parameters['CN'], 1, -1);

        $ev->add_property('dtstamp', \Bennu::timestamp_to_datetime()); // Now.
        if ($zoomevent->timeduration > 0) {
            //Use dtend instead of duration, because it works in Microsoft Outlook and works better in Korganizer.
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

    private function serialize_attachment($ical) {
        global $CFG;

        $filename = 'icalexport.ics';             
        
        $serialized = $ical->serialize();
        if (empty($serialized)) {
            die('bad serialization');
        }

        $tempfilepathname = $CFG->dataroot . '/' . $filename;
        file_put_contents($tempfilepathname, $serialized);

        return $filename;
    }

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

    private function zoom_get_users_to_notify($zoomid) {
        global $DB;
        $zoomusers = [];

        $sql = 'SELECT distinct ue.userid
        FROM {zoom} z
        JOIN {enrol} e
        ON e.courseid = z.course
        JOIN {user_enrolments} ue
        ON ue.enrolid = e.id
        WHERE z.id = :zoomid';

        $zoomparticipantids = $DB->get_records_sql($sql, ['zoomid' => $zoomid]);
        if ($zoomparticipantids) {
            foreach ($zoomparticipantids as $zoomparticipantid) {
               $zoomusers[$zoomparticipantid->userid] = \core_user::get_user($zoomparticipantid->userid);
            }
        }

        return $zoomusers;
    }

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