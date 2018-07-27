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

namespace mod_zoom\task;

defined('MOODLE_INTERNAL') || die();

// It takes Zoom 30 minutes to generate a report.
define('ZOOM_REPORT_DELAY_MINUTES', 30);
define('REPORT_INTERVAL_HOURS', 12);

class get_meeting_reports extends \core\task\scheduled_task {
    /**
     * Formats participants array as a record for the database.
     *
     * @param stdClass $participant Unformatted array received from web service API call.
     * @param int $detailsid The id to link to the zoom_meeting_details table.
     * @return array Formatted array that is ready to be inserted into the database table.
     */
    private function format_participant($participant, $detailsid) {
        global $DB;
        $moodleuser = null;
        $moodleuserid = null;
        $name = null;

        if ($user = $DB->get_record('zoom_meeting_participants', array('uuid' => $participant->id))) {
            $moodleuserid = $user->userid;
            $name = $user->name;
            // Get name and UID from user table.
        } else if ($moodleuser = $DB->get_record('user', array('email' => $participant->user_email))) {
            $moodleuserid = $moodleuser->idnumber;
            $name = strtoupper(fullname($moodleuser));
        }

        return array(
            'name' => $name,
            'userid' => $moodleuserid,
            'detailsid' => $detailsid,
            'zoomuserid' => $participant->user_id,
            'uuid' => $participant->id,
            'user_email' => $participant->user_email,
            'join_time' => strtotime($participant->join_time),
            'leave_time' => strtotime($participant->leave_time),
            'duration' => $participant->duration,
            'attentiveness_score' => $participant->attentiveness_score
        );
    }

    /**
     * Retrieves the number of API report calls that are still available.
     *
     * @return int The number of available calls that are left.
     */
    private function get_num_calls_left() {
        return get_config('zoom', 'calls_left');
    }

    /**
     * Gets the meeting IDs from the queue, retrieve the information for each meeting, then remove the meeting from the queue.
     * @link https://zoom.github.io/api/#report-metric-apis
     */
    public function execute() {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/mod/zoom/classes/webservice.php');
        $service = new \mod_zoom_webservice();

        $starttime = time() - REPORT_INTERVAL_HOURS * 60 * 60;

        // Zoom requires this format when passing the to and from arguments.
        $end = gmdate('Y-m-d') . 'T' . gmdate('H:i:s') . 'Z';
        $start = gmdate('Y-m-d', $starttime) . 'T' . gmdate('H:i:s', $starttime) . 'Z';

        // Need to figure out a way to get the active hosts from 30 days ago if the task has never run before.
        // Might be able to do this with a config variable to act as a flag.
        $activehostsuuids = $service->get_active_hosts_uuids($start, $end);
        $allmeetings = array();
        // Need to use sleep here because we are only allowed to use 1 report API call per second.
        foreach ($activehostsuuids as $activehostsuuid) {
            if ($this->get_num_calls_left() < 1) {
                break;
            }
            $allmeetings[] = $service->get_user_report($activehostsuuid, $start, $end);
            sleep(1);
        }

        $existingwebinars = $DB->get_records_select('zoom',
                                                    'webinar = true AND start_time + duration < :check_time',
                                                    array('check_time' => time() + ZOOM_REPORT_DELAY_MINUTES * 60),
                                                    null,
                                                    'meeting_id, recurring');
        foreach ($existingwebinars as $existingwebinar) {
            if ($existingwebinar->recurring || !($DB->record_exists('zoom_meeting_details',
                                                                    array('meeting_id' => $existingwebinar->meeting_id)))) {
                $webinarinfo = $service->get_webinar_details_report($existingwebinar->meeting_id);
                sleep(1);
            }
        }

        foreach ($allmeetings as $meetinggroup) {
            foreach ($meetinggroup as $meeting) {
                if (!($DB->record_exists('zoom_meeting_details', array('uuid' => $meeting->uuid)))) {
                    // If meeting doesn't exist in the zoom database, the instance is deleted, and we don't need reports for these.
                    if (!($zoomrecord = $DB->get_record('zoom', array('meeting_id' => $meeting->id)))) {
                        continue;
                    }
                    $meeting->zoomid = $zoomrecord->id;
                    $meeting->start_time = strtotime($meeting->start_time);
                    $meeting->end_time = strtotime($meeting->end_time);
                    $meeting->meeting_id = $meeting->id;
                    // Need to unset because id field in database means something different, we want it to autoincrement.
                    unset($meeting->id);
                    $detailsid = $DB->insert_record('zoom_meeting_details', $meeting);
                    $iswebinar = $zoomrecord->webinar;
                    $participants = $service->get_meeting_participants($meeting->uuid, $iswebinar);
                    sleep(1);
                    foreach ($participants as $rawparticipant) {
                        // We only want to insert them into the database if they are actually a Zoom user, otherwise we get errors.
                        if (!empty($rawparticipant->id)) {
                            $participant = $this->format_participant($rawparticipant, $detailsid);
                            $DB->insert_record('zoom_meeting_participants', $participant, false);
                        }
                    }
                }
            }
        }
    }

    /**
     * Returns name of task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('getmeetingreports', 'mod_zoom');
    }
}
