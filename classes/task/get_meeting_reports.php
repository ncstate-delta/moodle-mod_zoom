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

class get_meeting_reports extends \core\task\scheduled_task {
    /**
     * Formats participants array as a record for the database.
     *
     * @param stdClass $participant Unformatted array received from web service API call.
     * @param int $detailsid The id to link to the zoom_meeting_details table.
     * @param array $names Array that contains mappings of user's moodle ID to the user's name.
     * @param array $emails Array that contains mappings of user's moodle ID to the user's email.
     * @return array Formatted array that is ready to be inserted into the database table.
     */
    private function format_participant($participant, $detailsid, $names, $emails) {
        global $DB;
        $moodleuser = null;
        $user = null;
        $moodleuserid = null;
        $name = null;

        // Reset gets the value of first element in the array, or returns false if empty.
        // The returned array is indexed by the id field, and we won't know the values of id otherwise.
        $participantmatches = $DB->get_records('zoom_meeting_participants',
                                                array('uuid' => $participant->id), null, 'id, userid, name');
        if ($user = reset($participantmatches)) {
            $moodleuserid = $user->userid;
            $name = $user->name;
        } else if ($moodleuserid = array_search($participant->user_email, $emails)) {
            $name = $names[$moodleuserid];
        } else if ($moodleuserid = array_search($participant->name, $names)) {
            $name = $names[$moodleuserid];
        } else if ($moodleuser = $DB->get_record('user', array('email' => $participant->user_email))) {
            // This is the case where someone attends the meeting, but is not enrolled in the class.
            $moodleuserid = $moodleuser->id;
            $name = strtoupper(fullname($moodleuser));
        } else {
            $name = $participant->name;
            $moodleuserid = null;
        }

        if ($participant->user_email == '') {
            $participant->user_email = null;
        }
        if ($participant->id == '') {
            $participant->id = null;
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
        return get_config('mod_zoom', 'calls_left');
    }

    /**
     * Compare function for usort.
     * @param array $a One meeting/webinar object array to compare.
     * @param array $b Another meeting/webinar object array to compare.
     */
    private function cmp($a, $b) {
        if (strtotime($a->start_time) == strtotime($b->start_time)) {
            return 0;
        }
        return (strtotime($a->start_time) < strtotime($b->start_time)) ? -1 : 1;
    }

    /**
     * Gets the meeting IDs from the queue, retrieve the information for each meeting, then remove the meeting from the queue.
     * @link https://zoom.github.io/api/#report-metric-apis
     */
    public function execute() {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/mod/zoom/classes/webservice.php');
        $service = new \mod_zoom_webservice();

        $starttime = get_config('mod_zoom', 'last_call_made_at');

        // Zoom requires this format when passing the to and from arguments.
        // Zoom just returns all the meetings from the day range instead of actual time range specified.
        $end = gmdate('Y-m-d') . 'T' . gmdate('H:i:s') . 'Z';
        $start = gmdate('Y-m-d', $starttime) . 'T' . gmdate('H:i:s', $starttime) . 'Z';

        $activehostsuuids = $service->get_active_hosts_uuids($start, $end);
        $allmeetings = array();
        $recordedallmeetings = true;
        $cclehostrecords = $DB->get_records('zoom', null, '', 'id, host_id');
        $cclehosts = array();
        foreach ($cclehostrecords as $cclehostrecord) {
            $cclehosts[] = $cclehostrecord->host_id;
        }
        // We are only allowed to use 1 report API call per second, sleep() calls are put into webservice.php.
        foreach ($activehostsuuids as $activehostsuuid) {
            // This API call returns information about meetings and webinars, don't need extra functionality for webinars.
            $usersmeetings = array();
            if (in_array($activehostsuuid, $cclehosts)) {
                $usersmeetings = $service->get_user_report($activehostsuuid, $start, $end);
            } else {
                continue;
            }
            foreach ($usersmeetings as $usermeeting) {
                $allmeetings[] = $usermeeting;
            }
            if ($this->get_num_calls_left() < 1) {
                // If we run out of API calls here, there's no point in doing the next step, which requires API calls.
                mtrace('Error: Zoom Report API calls have been exhausted.');
                return;
            }
        }

        // Sort all meetings based on start_time so that we know where to pick up again if we run out of API calls.
        usort($allmeetings, array(get_class(), 'cmp'));

        foreach ($allmeetings as $meeting) {
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

                if ($this->get_num_calls_left() < 1) {
                    mtrace('Error: Zoom Report API calls have been exhausted.');
                    $recordedallmeetings = false;
                    set_config('last_call_made_at', $meeting->start_time - 1, 'mod_zoom');
                    // Need to pick up from where you left off the last time the cron task ran.
                    // This assumes that the meetings are returned in order of least recent to most recent.
                    // According to Zoom support, this is the case.
                    break;
                }
                $detailsid = $DB->insert_record('zoom_meeting_details', $meeting);
                $iswebinar = $zoomrecord->webinar;
                $participants = $service->get_meeting_participants($meeting->uuid, $iswebinar);

                // Loop through each user to generate name->uids mapping.
                $coursecontext = \context_course::instance($zoomrecord->course);
                $enrolled = get_enrolled_users($coursecontext);
                $names = array();
                $emails = array();
                foreach ($enrolled as $user) {
                    $name = strtoupper(fullname($user));
                    $names[$user->id] = $name;
                    $emails[$user->id] = strtoupper($user->email);
                }

                foreach ($participants as $rawparticipant) {
                    $participant = $this->format_participant($rawparticipant, $detailsid, $names, $emails);
                    $DB->insert_record('zoom_meeting_participants', $participant, false);
                }
            }
        }
        if ($recordedallmeetings) {
            set_config('last_call_made_at', time(), 'mod_zoom');
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
