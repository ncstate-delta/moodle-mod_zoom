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
            $zoom_records = $this->get_potential_zoom_to_notify();
            if ($zoom_records) {
                foreach($zoom_records as $zoom_record) {
                    mtrace('[Zoom ical Notifications] Checking to see if zoom record with ID ' . $zoom_record->id . ' was notified before.');
                    $execution_time = $this->get_notification_execution_time($zoom_record->id);
                    // Only run if it hasn't run before
                    if ($execution_time == 0) {
                        mtrace('[Zoom ical Notifications] Zoom instance with ID ' . $zoom_record->id . ' can be notified - not notified before.');
                        $this->zoom_ical_notification($zoom_record);
                        // Set execution time for this cron job
                        mtrace('[Zoom ical Notifications] Zoom instance with ID ' . $zoom_record->id . ' was successfully notified - set execution time for log table.');
                        $this->set_notification_execution_time($zoom_record->id);
                    }
                }
            } else {
                mtrace('[Zoom ical Notifications] Found no zoom records to process and notify (created or modified within the last hour that was not notified before).');
            }
            mtrace('[Zoom ical Notifications] Cron job Completed.');
        } else {
            mtrace('[Zoom ical Notifications] The Admin Setting for the Send iCal Notification scheduled task has not been enabled - will not run the cron job.');
        }
    }

    function get_potential_zoom_to_notify() {
        global $DB;

        // Get zoom module instances created/modified in the last hour, but ignore the last 10 minutes
        $sql = 'SELECT * 
        FROM {zoom}
        WHERE timemodified >= (unix_timestamp() - (60 * 60))
        AND timemodified <= (unix_timestamp() - (10 * 60))';

        return $DB->get_records_sql($sql);
    }

    function get_notification_execution_time($zoom_id) {
        global $DB;

        $sql = 'SELECT execution_time
        FROM {zoom_ical_notifications}
        WHERE zoomid = :zoom_id';

        $execution_time = $DB->get_field_sql($sql, array('zoom_id' => $zoom_id));
        if (!$execution_time) {
            $execution_time = 0;
        }
        return $execution_time;
    }

    function set_notification_execution_time($zoom_id) {
        global $DB;

        $ical_notif_ojb = new \stdClass();
        $ical_notif_ojb->zoomid = $zoom_id;
        $ical_notif_ojb->execution_time = time();

        $DB->insert_record('zoom_ical_notifications', $ical_notif_ojb);

    }

    function zoom_ical_notification($zoom) {
        global $CFG, $DB, $SITE;

        mtrace('[Zoom ical Notifications] Notifying Zoom instance with ID ' . $zoom->id);

        $zoom_event = $DB->get_record('event', array('instance' => $zoom->id, 'courseid' => $zoom->course, 'modulename' => 'zoom', 'eventtype' => 'zoom'));
        if ($zoom_event) {

            $cal_event = new \calendar_event($zoom_event); // To use moodle calendar event services.

            $users = $this->zoom_get_users_to_notify($zoom->id);
            $users = $this->zoom_filter_users($zoom, $users);

            // html value to render in email body
            $cal_event_description = $cal_event->description; 
            // Avoid filters - so we can manually add links as required       
            $activity_url = $CFG->wwwroot . '/mod/zoom/view.php?id=';
            $modinfo = get_fast_modinfo($zoom->course);
            $course_modules = $modinfo->get_cms();
            if ($course_modules) {
                foreach ($course_modules as $course_mod) {
                    if ($course_mod->modname == 'zoom' && $course_mod->instance == $zoom->id) {
                        $activity_url .= $course_mod->id;
                        break;
                    }
                }
            }
            //Removed for now due to user request
            //$cal_event_description .= '<p>Links:<br>';
            //$cal_event_description .= '-------<br>';
            //$cal_event_description .= $activity_url;
            //$cal_event_description .= '</p>';

            $file_storage = get_file_storage();

            foreach($users as $user) {
                // Check if user has "Disable notifications" set
                if ($user->emailstop) {
                    continue;
                }

                // Send email
                $ical = $this->create_ical_object($zoom, $zoom_event, $cal_event, $cal_event_description, $user);

                $file_record = [
                    'contextid' => \context_user::instance($user->id)->id,
                    'component' => 'user',
                    'filearea' => 'draft',
                    'itemid' => file_get_unused_draft_itemid(),
                    'filepath' => '/',
                    'filename' => clean_filename('icalexport.ics'),
                ];

                $serialized_ical = $ical->serialize();
                if(!$serialized_ical || empty($serialized_ical)) {
                    mtrace('[Zoom ical Notifications] A problem occurred while trying to serialize the ical data for user ID ' . $user->id . ' for zoom instance with ID ' . $zoom->id);
                    continue;
                }
                
                $ical_file_attachment = $file_storage->create_file_from_string($file_record, $serialized_ical);
                
                $message_data = new \core\message\message();
                $message_data->component = 'mod_zoom';
                $message_data->name = 'ical_notifications';
                $message_data->userfrom = \core_user::get_noreply_user();
                $message_data->userto = $user;
                $message_data->subject = $zoom_event->name;
                $message_data->fullmessage = $cal_event_description;
                $message_data->fullmessageformat = FORMAT_HTML;
                $message_data->fullmessagehtml = $cal_event_description;
                $message_data->smallmessage = $zoom_event->name . ' - ' . $cal_event_description;
                $message_data->notification = true;
                $message_data->attachment = $ical_file_attachment;
                $message_data->attachname =  $ical_file_attachment->get_filename();

                $email_success = message_send($message_data);

                if ($email_success) {
                    mtrace('[Zoom ical Notifications] Successfully emailed user ID ' . $user->id . ' for zoom instance with ID ' . $zoom->id);
                } else {
                    mtrace('[Zoom ical Notifications] A problem occurred while emailing user ID ' . $user->id . ' for zoom instance with ID ' . $zoom->id);
                }
            }
        } 
    }

    function create_ical_object($zoom, $zoom_event, $cal_event, $description, $user) {
        global $CFG;
        $ical = new \iCalendar;
        $ical->add_property('method', 'PUBLISH');
        $ical->add_property('prodid', '-//Moodle Pty Ltd//NONSGML Moodle Version ' . $CFG->version . '//EN');

        $hostaddress = str_replace('http://', '', $CFG->wwwroot);
        $hostaddress = str_replace('https://', '', $hostaddress);

        $ev = new \iCalendar_event; // To export in ical format.
        $ev->add_property('uid', $zoom_event->id.'@'.$hostaddress);

        // Set iCal event summary from event name.
        $ev->add_property('summary', format_string($zoom_event->name, true, ['context' => $cal_event->context]));    

        $ev->add_property('description', html_to_text($description, 0));

        $ev->add_property('class', 'PUBLIC'); // PUBLIC / PRIVATE / CONFIDENTIAL

        // Since we don't cater for modified invites, the created and last modified dates are the same
        $ev->add_property('created', \Bennu::timestamp_to_datetime($zoom_event->timemodified));
        $ev->add_property('last-modified', \Bennu::timestamp_to_datetime($zoom_event->timemodified));

        $no_reply_user = \core_user::get_noreply_user();
        $ev->add_property('organizer', 'mailto:' . $no_reply_user->email, array('cn' => $this->get_lms_site_name()));
        // Need to strip out the double quotations around the 'organizer' values - probably a bug in the core code
        $ev->properties['ORGANIZER'][0]->value = substr($ev->properties['ORGANIZER'][0]->value, 1, -1);
        $ev->properties['ORGANIZER'][0]->parameters['CN'] = substr($ev->properties['ORGANIZER'][0]->parameters['CN'], 1, -1);

        $ev->add_property('dtstamp', \Bennu::timestamp_to_datetime()); // now
        if ($zoom_event->timeduration > 0) {
            //dtend is better than duration, because it works in Microsoft Outlook and works better in Korganizer
            $ev->add_property('dtstart', \Bennu::timestamp_to_datetime($zoom_event->timestart)); // when event starts.
            $ev->add_property('dtend', \Bennu::timestamp_to_datetime($zoom_event->timestart + $zoom_event->timeduration));
        } else if ($zoom_event->timeduration == 0) {
            // When no duration is present, the event is instantaneous event, ex - Due date of a module.
            // Moodle doesn't support all day events yet. See MDL-56227.
            $ev->add_property('dtstart', \Bennu::timestamp_to_datetime($zoom_event->timestart));
            $ev->add_property('dtend', \Bennu::timestamp_to_datetime($zoom_event->timestart));
        } else {
            // This can be used to represent all day events in future.
            throw new \coding_exception("Negative duration is not supported yet.");
        }

        if ($zoom->registration == ZOOM_REGISTRATION_OFF) {
            $ev->add_property('location', $zoom->join_url);
        } else {
            $registrant_join_url = zoom_get_registrant_join_url($user->email, $zoom->meeting_id, $zoom->webinar);
            if ($registrant_join_url) {
                $ev->add_property('location', $registrant_join_url);
            } else {
                $ev->add_property('location', $zoom->join_url);
            }
        }

        $ical->add_component($ev);
        return $ical;       
    }

    function zoom_filter_users($zoom, $users) {
        $modinfo = get_fast_modinfo($zoom->course);
        $course_modules = $modinfo->get_cms();
        if ($course_modules) {
            foreach ($course_modules as $course_mod) {
                if ($course_mod->modname == 'zoom' && $course_mod->instance == $zoom->id) {
                    $avail_info = new \core_availability\info_module($course_mod);
                    $users = $avail_info->filter_user_list($users);
                    break;
                }
            }
        } 
        return $users;
    }

    function zoom_get_users_to_notify($zoom_id) {
        global $DB;
        $zoom_users = [];

        $sql = 'SELECT distinct ue.userid
        FROM {zoom} z
        JOIN {enrol} e
        ON e.courseid = z.course
        JOIN {user_enrolments} ue
        ON ue.enrolid = e.id
        WHERE z.id = :zoom_id';

        $zoom_participants_ids = $DB->get_records_sql($sql, array('zoom_id' => $zoom_id));
        if ($zoom_participants_ids) {
            foreach($zoom_participants_ids as $zoom_participant_id) {
               $zoom_users += [$zoom_participant_id->userid => \core_user::get_user($zoom_participant_id->userid)];
            }        
        }

        return $zoom_users;
    }

    function get_lms_site_name() {
        global $DB;

        $sql = 'SELECT fullname
        FROM {course} 
        WHERE format = :format';

        return $DB->get_field_sql($sql, array('format' => 'site'));        
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