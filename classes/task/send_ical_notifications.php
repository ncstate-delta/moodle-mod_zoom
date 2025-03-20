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
 * @copyright  2025 OPENCOLLAB <info@opencollab.co.za>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoom\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/calendar/lib.php');
require_once($CFG->libdir . '/bennu/bennu.inc.php');
require_once($CFG->libdir . '/bennu/iCalendar_components.php');
require_once($CFG->dirroot . '/mod/zoom/locallib.php');

use context_module;
use context_user;
use core_availability\info_module;
use core_user;
use core\message\message;
use core\task\scheduled_task;
use moodle_url;
use stdClass;

/**
 * Scheduled task to send ical notifications for zoom meetings that were scheduled within the last 30 minutes.
 */
class send_ical_notifications extends scheduled_task {

    /**
     * Execute the send ical notifications cron function.
     *
     * @return void nothing.
     */
    public function execute() {
        if (get_config('zoom', 'sendicalnotifications')) {
            mtrace('Zoom ical Notifications - Starting cron job.');
            $zoomevents = $this->get_zoom_events_to_notify();
            if ($zoomevents) {
                foreach ($zoomevents as $zoomevent) {
                    $notificationtime = $this->get_notification_time((int) $zoomevent->id);
                    // Only run if it hasn't run before.
                    if ($notificationtime == 0) {
                        mtrace('A notification will be sent for Zoom event with ID ' . $zoomevent->id);
                        $this->send_zoom_ical_notifications($zoomevent);
                        // Set the notification time for this cron job.
                        $this->set_notification_time((int) $zoomevent->id);
                    }
                }
            } else {
                mtrace('Found no zoom event records to process and notify ' .
                       '(created or modified within the last hour that was not notified before).');
            }
            mtrace('Zoom ical Notifications - Cron job Completed.');
        } else {
            mtrace('The Admin Setting for the Send iCal Notification scheduled task ' .
                   'has not been enabled - will not run the cron job.');
        }
    }

    /**
     * Get zoom events created/modified in the last hour, but ignore the last 10 minutes. This allows
     * the user to still make adjustments to the event before the ical invite is sent out.
     * @return array
     */
    private function get_zoom_events_to_notify() {
        global $DB;

        $sql = 'SELECT *
        FROM {event}
        WHERE modulename = :zoommodulename
        AND eventtype = :zoomeventtype
        AND timemodified >= :onehourago
        AND timemodified <= :tenminutesago';

        return $DB->get_records_sql($sql, [
            'zoommodulename' => 'zoom',
            'zoomeventtype' => 'zoom',
            'onehourago' => time() - (60 * 60),
            'tenminutesago' => time() - (60 * 10),
        ]);
    }

    /**
     * Get the notification time (last successful ical notifications sent) for the related zoom event id.
     * @param int $zoomeventid The zoom event id.
     * @return int The timestamp of the last notification sent.
     */
    private function get_notification_time(int $zoomeventid) {
        global $DB;

        $notificationtime = $DB->get_field('zoom_ical_notifications', 'notificationtime', ['zoomeventid' => $zoomeventid]);
        if (!$notificationtime) {
            $notificationtime = 0;
        }
        return (int) $notificationtime;
    }

    /**
     * Set the notification time (the current time) for successful ical notifications sent for the related zoom event id.
     * @param int $zoomeventid The zoom event id.
     */
    private function set_notification_time(int $zoomeventid) {
        global $DB;

        $icalnotifojb = new stdClass();
        $icalnotifojb->zoomeventid = $zoomeventid;
        $icalnotifojb->notificationtime = time();

        $DB->insert_record('zoom_ical_notifications', $icalnotifojb);
    }

    /**
     * The zoom ical notification task.
     * @param stdClass $zoomevent The zoom event record.
     */
    private function send_zoom_ical_notifications(stdClass $zoomevent) {
        global $DB;

        $users = $this->get_users_to_notify((int) $zoomevent->instance, (int) $zoomevent->courseid);

        $zoom = $DB->get_record('zoom', ['id' => $zoomevent->instance], 'id,registration,join_url,meeting_id,webinar');

        $filestorage = get_file_storage();

        // Apply filters to event name and description.
        $cminfo = get_coursemodule_from_instance('zoom', (int) $zoomevent->instance, (int) $zoomevent->courseid);
        $formatoptions = [];
        if ($cminfo && !empty($cminfo->id)) {
            $formatoptions['context'] = context_module::instance($cminfo->id);
        }
        $zoomeventname = zoom_apply_filter_on_meeting_name($zoomevent->name, $formatoptions);
        $zoomeventhtmldesc = format_text($zoomevent->description, FORMAT_HTML, $formatoptions);
        $zoomeventplaindesc = strip_tags($zoomevent->description);

        // Setup zoom event url.
        $zoomurlwrapper = new moodle_url('/mod/zoom/view.php', ['id' => $cminfo->id]);
        $zoomurl = $zoomurlwrapper->out(false);

        foreach ($users as $user) {
            // Check if user has "Disable notifications" set.
            if ($user->emailstop) {
                continue;
            }

            $ical = $this->create_ical_object($zoomevent, $zoom, $zoomeventplaindesc, $zoomurl, $user->email);

            $filerecord = [
                'contextid' => context_user::instance($user->id)->id,
                'component' => 'user',
                'filearea' => 'draft',
                'itemid' => file_get_unused_draft_itemid(),
                'filepath' => '/',
                'filename' => clean_filename('icalexport.ics'),
            ];

            $serializedical = $ical->serialize();
            if (!$serializedical || empty($serializedical)) {
                mtrace('A problem occurred while trying to serialize the ical data for user ID ' .
                        $user->id . ' for zoom event ID ' . $zoomevent->id);
                continue;
            }

            $icalfileattachment = $filestorage->create_file_from_string($filerecord, $serializedical);

            $messagedata = new message();
            $messagedata->component = 'mod_zoom';
            $messagedata->name = 'ical_notifications';
            $messagedata->userfrom = core_user::get_noreply_user();
            $messagedata->userto = $user;
            $messagedata->subject = $zoomeventname;
            $messagedata->fullmessage = $zoomeventhtmldesc;
            $messagedata->fullmessageformat = FORMAT_HTML;
            $messagedata->fullmessagehtml = $zoomeventhtmldesc;
            $messagedata->smallmessage = $zoomeventname . ' - ' . $zoomeventplaindesc;
            $messagedata->notification = true;
            $messagedata->attachment = $icalfileattachment;
            $messagedata->attachname = $icalfileattachment->get_filename();

            $emailsuccess = message_send($messagedata);

            if ($emailsuccess) {
                mtrace('Successfully emailed user ID ' . $user->id .
                        ' for zoom event ID ' . $zoomevent->id);
            } else {
                mtrace('A problem occurred while emailing user ID ' . $user->id .
                        ' for zoom event ID ' . $zoomevent->id);
            }
        }
    }

    /**
     * Create the ical object.
     * @param stdClass $zoomevent The zoom event record.
     * @param stdClass $zoom The zoom record.
     * @param string $zoomeventdescription The zoom event's plain (non-html) description.
     * @param string $zoomurl The un-escaped zoom event url.
     * @param string $email The user's email.
     * @return \iCalendar
     */
    private function create_ical_object(stdClass $zoomevent, stdClass $zoom, string $zoomeventdescription,
                                        string $zoomurl, string $email) {
        global $CFG, $SITE;

        $ical = new \iCalendar();
        $ical->add_property('method', 'PUBLISH');
        $ical->add_property('prodid', '-//Moodle Pty Ltd//NONSGML Moodle Version ' . $CFG->version . '//EN');

        $icalevent = zoom_helper_icalendar_event($zoomevent, $zoomeventdescription);

        if ($zoom->registration == ZOOM_REGISTRATION_OFF) {
            $icalevent->add_property('location', $zoomurl);
        } else {
            $registrantjoinurl = zoom_get_registrant_join_url($email, $zoom->meeting_id, $zoom->webinar);
            if ($registrantjoinurl) {
                $icalevent->add_property('location', $registrantjoinurl);
            } else {
                $icalevent->add_property('location', $zoomurl);
            }
        }

        $noreplyuser = core_user::get_noreply_user();
        $icalevent->add_property('organizer', 'mailto:' . $noreplyuser->email, ['cn' => $SITE->fullname]);
        // Need to strip out the double quotations around the 'organizer' values - probably a bug in the core code.
        $organizervalue = $icalevent->properties['ORGANIZER'][0]->value;
        $icalevent->properties['ORGANIZER'][0]->value = substr($organizervalue, 1, -1);
        $organizercnparam = $icalevent->properties['ORGANIZER'][0]->parameters['CN'];
        $icalevent->properties['ORGANIZER'][0]->parameters['CN'] = substr($organizercnparam, 1, -1);

        // Add the event to the iCal file.
        $ical->add_component($icalevent);

        return $ical;
    }

    /**
     * Get an array of users in the format of userid=>user object.
     * @param int $zoomid The zoom instance id.
     * @param int $courseid The course id of the course in which the zoom event occurred.
     * @return array An array of users.
     */
    private function get_users_to_notify(int $zoomid, int $courseid) {
        $cminfo = get_fast_modinfo($courseid)->instances['zoom'][$zoomid];
        $users = get_users_by_capability($cminfo->context, 'mod/zoom:view');

        if (empty($users)) {
            return [];
        }

        $info = new info_module($cminfo);
        return $info->filter_user_list($users);
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
