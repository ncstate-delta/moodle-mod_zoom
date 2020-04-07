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
 * @copyright  2018 UC Regents
 * @author     Kubilay Agi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoom\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task to get the meeting participants for each .
 *
 * @package   mod_zoom
 * @copyright 2018 UC Regents
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_meeting_reports extends \core\task\scheduled_task {

    /**
     * Percentage in which we want similar_text to reach before we consider
     * using its results.
     */
    const SIMILARNAME_THRESHOLD = 60;

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
        $config = get_config('mod_zoom');
        if (empty($config->apikey)) {
            mtrace('Skipping task - ', get_string('zoomerr_apikey_missing', 'zoom'));
            return;
        } else if (empty($config->apisecret)) {
            mtrace('Skipping task - ', get_string('zoomerr_apisecret_missing', 'zoom'));
            return;
        }
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
                if (!($zoomrecord = $DB->get_record('zoom', array('meeting_id' => $meeting->id), '*', IGNORE_MULTIPLE))) {
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
                list($names, $emails) = $this->get_enrollments($zoomrecord->course);

                foreach ($participants as $rawparticipant) {
                    $participant = $this->format_participant($rawparticipant, $detailsid, $names, $emails);
                    try {
                        $DB->insert_record('zoom_meeting_participants', $participant, false);
                    } catch (\dml_write_exception $ex) {
                        // Error might be because record already exists, so update.
                        $recordid = $DB->get_field('zoom_meeting_participants',
                                'id', ['detailsid' => $participant->detailsid,
                                    'zoomuserid' => $participant->zoomuserid]);
                        if (!empty($recordid)) {
                            $participant->id = $recordid;
                            $DB->update_record('zoom_meeting_participants', $participant);
                        } else {
                            throw $ex;  // Must be another error.
                        }
                    }
                }
            }
        }
        if ($recordedallmeetings) {
            set_config('last_call_made_at', time(), 'mod_zoom');
        }
    }

    /**
     * Formats participants array as a record for the database.
     *
     * @param stdClass $participant Unformatted array received from web service API call.
     * @param int $detailsid The id to link to the zoom_meeting_details table.
     * @param array $names Array that contains mappings of user's moodle ID to the user's name.
     * @param array $emails Array that contains mappings of user's moodle ID to the user's email.
     * @return array Formatted array that is ready to be inserted into the database table.
     */
    public function format_participant($participant, $detailsid, $names, $emails) {
        global $DB;
        $moodleuser = null;
        $moodleuserid = null;
        $name = null;

        // Cleanup the name. For some reason # gets into the name instead of a comma.
        $participant->name = str_replace('#', ',', $participant->name);

        // Try to see if we successfully queried for this user and found a Moodle id before.
        if (!empty($participant->id)) {
            // Sometimes uuid is blank from Zoom.
            $participantmatches = $DB->get_records('zoom_meeting_participants',
                    array('uuid' => $participant->id), null, 'id, userid, name');

            if (!empty($participantmatches)) {
                // Found some previous matches. Find first one with userid set.
                foreach ($participantmatches as $participantmatch) {
                    if (!empty($participantmatch->userid)) {
                        $moodleuserid = $participantmatch->userid;
                        $name = $participantmatch->name;
                        break;
                    }
                }
            }
        }

        // Did not find a previous match.
        if (empty($moodleuserid)) {
            if (!empty($participant->user_email) && ($moodleuserid =
                    array_search(strtoupper($participant->user_email), $emails))) {
                // Found email from list of enrolled users.
                $name = $names[$moodleuserid];
            } else if (!empty($participant->name) && ($moodleuserid =
                    array_search(strtoupper($participant->name), $names))) {
                // Found name from list of enrolled users.
                $name = $names[$moodleuserid];
            } else if (!empty($participant->user_email) &&
                    ($moodleuser = $DB->get_record('user',
                            array('email' => $participant->user_email,
                            'deleted' => 0, 'suspended' => 0), '*', IGNORE_MULTIPLE))) {
                // This is the case where someone attends the meeting, but is not enrolled in the class.
                $moodleuserid = $moodleuser->id;
                $name = strtoupper(fullname($moodleuser));
            } else if (!empty($participant->name) && ($moodleuserid =
                    $this->match_name($participant->name, $names))) {
                // Found name by using fuzzy text search.
                $name = $names[$moodleuserid];
            } else {
                // Did not find any matches, so use what is given by Zoom.
                $name = $participant->name;
                $moodleuserid = null;
            }
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
     * Get enrollment for given course.
     *
     * @param int $courseid
     * @return array    Returns an array of names and emails.
     */
    public function get_enrollments($courseid) {
        // Loop through each user to generate name->uids mapping.
        $coursecontext = \context_course::instance($courseid);
        $enrolled = get_enrolled_users($coursecontext);
        $names = array();
        $emails = array();
        foreach ($enrolled as $user) {
            $name = strtoupper(fullname($user));
            $names[$user->id] = $name;
            $emails[$user->id] = strtoupper($user->email);
        }
        return array($names, $emails);
    }

    /**
     * Returns name of task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('getmeetingreports', 'mod_zoom');
    }

    /**
     * Retrieves the number of API report calls that are still available.
     *
     * @return int The number of available calls that are left.
     */
    public function get_num_calls_left() {
        return get_config('mod_zoom', 'calls_left');
    }

    /**
     * Tries to match a given name to the roster using two different fuzzy text
     * matching algorithms and if they match, then returns the match.
     *
     * @param string $nametomatch
     * @param array $rosternames    Needs to be an array larger than 3 for any
     *                              meaningful results.
     *
     * @return int  Returns id for $rosternames. Returns false if no match found.
     */
    private function match_name($nametomatch, $rosternames) {
        if (count($rosternames) < 3) {
            return false;
        }

        $nametomatch = strtoupper($nametomatch);
        $similartextscores = [];
        $levenshteinscores = [];
        foreach ($rosternames as $name) {
            similar_text($nametomatch, $name, $percentage);
            if ($percentage > self::SIMILARNAME_THRESHOLD) {
                $similartextscores[$name] = $percentage;
                $levenshteinscores[$name] = levenshtein($nametomatch, $name);
            }
        }

        // If we did not find any quality matches, then return false.
        if (empty($similartextscores)) {
            return false;
        }

        // Simlar text has better matches with higher numbers.
        arsort($similartextscores);
        reset($similartextscores);  // Make sure key gets first element.
        $stmatch = key($similartextscores);

        // Levenshtein has better matches with lower numbers.
        asort($levenshteinscores);
        reset($levenshteinscores);  // Make sure key gets first element.
        $lmatch = key($levenshteinscores);

        // If both matches, then we can be rather sure that it is the same user.
        if ($stmatch == $lmatch) {
            $moodleuserid = array_search($stmatch, $rosternames);
            return $moodleuserid;
        } else {
            return false;
        }
    }
}
