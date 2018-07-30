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

class get_meeting_reports extends \core\task\scheduled_task {
    /**
     * Adds pariticipants to the participants database table
     * 
     * @param string $meetingwebinarinstanceid The meeting or webinar ID that you want to use to get the participants list.
     * @param bool $webinar Whether the meeting or webinar whose participants to add is a webinar.
     * @return int The number of records that were inserted into the database.
     */
    private function add_participants_to_db(string $meetingwebinarinstanceid, $webinar) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/mod/zoom/classes/webservice.php');
        $numrecords = 0;
        $service = new \mod_zoom_webservice();
        try {
            $rawparticipants = $service->get_meeting_participants($meetingwebinarinstanceid, $webinar);
        } catch (moodle_exception $error) {
            $rawparticipants = array();
        }
        foreach($rawparticipants as $rawparticipant) {
            $participant = $this->format_object_to_record($rawparticipant, $meetingwebinarinstanceid);
            $DB->insert_record('zoom_meetings_participants', $participant);
            $numrecords += 1;
        }
    }

    /**
     * Formats participants array as a record for the database.
     * 
     * @param object Unformatted array received from web service API call.
     * @param string The unique meeting instance ID.
     * @return array Formatted array that is ready to be inserted into the database table.
     * 
     */
    private function format_object_to_record($participant, $meetingwebinarinstanceid) {
        $formatted = array(
            'participant_universal_id' => $participant->id,
            'participant_instance_id' => $participant->user_id,
            'meeting_webinar_instance_id' => $meetingwebinarinstanceid,
            'participant_email' => $participant->user_email,
            'join_time' => $participant->join_time,
            'leave_time' => $participant->leave_time,
            'duration' => $participant->duration,
            'attentiveness_score' => $participant->attentiveness_score
        );
        return $formatted;
    }

    /**
     * Retrieves the number of API report calls that are still available.
     * @return int The number of available calls that are left.
     */
    private function get_num_calls_left() {
        return get_config('zoom', 'calls_left');
    }

    /**
     * Updates the number of API report calls that are still available.
     */
    private function decrement_num_calls() {
        $curr_count = $this->get_num_calls_left();
        if ($curr_count == 0) {
            return -1;
        }
        set_config('calls_left', $curr_count - 1, 'zoom');
    }

    /**
     * Gets the meeting IDs from the queue, retrieve the information for each meeting, then remove the meeting from the queue.
     */
    public function execute() {
        global $DB;
        $sql = 'SELECT zmq.meeting_webinar_instance_id,
                       zmq.end_time,
                       zmq.meeting_webinar_universal_id
                  FROM {zoom_meetings_queue} zmq
                 WHERE zmq.end_time < :check_time
              ORDER BY zmq.end_time ASC
                 LIMIT 1000
        ';
        // We can change the value of LIMIT based on how many times per day we want to run this query.
        // We have a total of 2000 available.
        $params = [
            'check_time' => time() + ZOOM_REPORT_DELAY_MINUTES * 60
        ];
        $meetings = $DB->get_records_sql($sql, $params);
        foreach ($meetings as $meeting) {
            if ($this->get_num_calls_left() < 1) {
                break;
            }
            // Get user data and put it into the database for participants.
            $webinar = $DB->get_record('zoom', array('meeting_id' => $meeting->meeting_webinar_universal_id))->webinar;
            $this->add_participants_to_db($meeting->meeting_webinar_instance_id, $webinar);
            // Delete the meeting from the queue so we don't get repeated/wasted API calls for future cron jobs.
            $this->delete_meeting_from_queue($meeting->meeting_webinar_instance_id);
            // Decrement the number of API calls we have left.
            $this->decrement_num_calls();
        }
    }

    /**
     * Remove participant entries from the database table once the meeting has been deleted in the zoom database table.
     * @param string The meeting UUID associated with the user data set to be removed.
     * @return int The number of records that were deleted.
     */
    private function delete_user_data(string $meetingwebinarinstanceid) {
        global $DB;
        $num_records = 0;
        $sql = 'SELECT zmp.participant_instance_id
                    FROM {zoom_meetings_participants} zmp
                    WHERE zmp.meeting_webinar_instance_id = :meetingwebinarinstanceid
        ';
        $params = [
            'meetingwebinarinstanceid' => $meetingwebinarinstanceid
        ];
        $users = $DB->get_records_sql($sql, $params);
        $userids = array();
        foreach ($users as $user) {
            $userids[] = $user->participant_universal_id;
            $num_records += 1;
        }
        if (!$DB->delete_records_list('zoom_meetings_participants', 'participant_universal_id', $userids)){
            return 0;
        }
        return $num_records;
    }

    /**
     * Remove the meeting from the database table if the participants have been retrieved or if the meeting was deleted.
     * @param string Meeting UUID that you want to delete from the queue database table.
     */
    private function delete_meeting_from_queue(string $meetingwebinarinstanceid) {
        global $DB;
        $condition = array('meeting_webinar_instance_id' => $meetingwebinarinstanceid);
        $DB->delete_records('zoom_meetings_queue', $condition);
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
