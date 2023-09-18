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
 * Task: get_meeting_reports
 *
 * @package    mod_zoom
 * @copyright  2018 UC Regents
 * @author     Kubilay Agi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoom\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/zoom/locallib.php');

/**
 * Scheduled task to get the meeting participants for each .
 */
class get_meeting_reports extends \core\task\scheduled_task {
    /**
     * Percentage in which we want similar_text to reach before we consider
     * using its results.
     */
    private const SIMILARNAME_THRESHOLD = 60;

    /**
     * Used to determine if debugging is turned on or off for outputting messages.
     * @var bool
     */
    public $debuggingenabled = false;

    /**
     * The mod_zoom\webservice instance used to query for data. Can be stubbed
     * for unit testing.
     * @var mod_zoom\webservice
     */
    public $service = null;

    /**
     * Sort meetings by end time.
     * @param array $a One meeting/webinar object array to compare.
     * @param array $b Another meeting/webinar object array to compare.
     */
    private function cmp($a, $b) {
        if ($a->end_time == $b->end_time) {
            return 0;
        }

        return ($a->end_time < $b->end_time) ? -1 : 1;
    }

    /**
     * Gets the meeting IDs from the queue, retrieve the information for each
     * meeting, then remove the meeting from the queue.
     * @link https://zoom.github.io/api/#report-metric-apis
     *
     * @param string $paramstart    If passed, will find meetings starting on given date. Format is YYYY-MM-DD.
     * @param string $paramend      If passed, will find meetings ending on given date. Format is YYYY-MM-DD.
     * @param array $hostuuids      If passed, will find only meetings for given array of host uuids.
     */
    public function execute($paramstart = null, $paramend = null, $hostuuids = null) {
        try {
            $this->service = zoom_webservice();
        } catch (\moodle_exception $exception) {
            mtrace('Skipping task - ', $exception->getMessage());
            return;
        }

        // See if we cannot make anymore API calls.
        $retryafter = get_config('zoom', 'retry-after');
        if (!empty($retryafter) && time() < $retryafter) {
            mtrace('Out of API calls, retry after ' . userdate($retryafter, get_string('strftimedaydatetime', 'core_langconfig')));
            return;
        }

        $this->debuggingenabled = debugging();

        // If running as a task, then record when we last left off if
        // interrupted or finish.
        $runningastask = true;

        if (!empty($hostuuids)) {
            $runningastask = false;
        }

        if (!empty($paramstart)) {
            $starttime = strtotime($paramstart);
            $runningastask = false;
        } else {
            $starttime = get_config('zoom', 'last_call_made_at');
        }

        if (empty($starttime)) {
            // Zoom only provides data from 30 days ago.
            $starttime = strtotime('-30 days');
        }

        if (!empty($paramend)) {
            $endtime = strtotime($paramend);
            $runningastask = false;
        }

        if (empty($endtime)) {
            $endtime = time();
        }

        // Zoom requires this format when passing the to and from arguments.
        // Zoom just returns all the meetings from the day range instead of
        // actual time range specified.
        $start = gmdate('Y-m-d', $starttime);
        $end = gmdate('Y-m-d', $endtime);

        mtrace(sprintf('Finding meetings between %s to %s', $start, $end));

        $recordedallmeetings = true;
        try {
            if (!empty($hostuuids)) {
                // Can only query on $hostuuids using Report API. So throw
                // exception to skip Dashboard API.
                throw new \Exception('Querying $hostuuids; need to use Report API');
            }

            $allmeetings = $this->get_meetings_via_dashboard($start, $end);
        } catch (\Exception $e) {
            mtrace($e->getMessage());
            // If ran into exception, then Dashboard API must have failed. Try
            // using Report API.
            $allmeetings = $this->get_meetings_via_reports($start, $end, $hostuuids);
        }

        // Sort all meetings based on end_time so that we know where to pick
        // up again if we run out of API calls.
        $allmeetings = array_map([$this, 'normalize_meeting'], $allmeetings);
        usort($allmeetings, [$this, 'cmp']);

        mtrace("Processing " . count($allmeetings) . " meetings");

        foreach ($allmeetings as $meeting) {
            // Only process meetings if they happened after the time we left off.
            $meetingtime = ($meeting->end_time == intval($meeting->end_time)) ? $meeting->end_time : strtotime($meeting->end_time);
            if ($runningastask && $meetingtime <= $starttime) {
                continue;
            }

            try {
                if (!$this->process_meeting_reports($meeting)) {
                    // If returned false, then ran out of API calls or got
                    // unrecoverable error. Try to pick up where we left off.
                    if ($runningastask) {
                        // Only want to resume if we were processing all reports.
                        $recordedallmeetings = false;
                        set_config('last_call_made_at', $meetingtime - 1, 'zoom');
                    }

                    break;
                }
            } catch (\Exception $e) {
                mtrace($e->getMessage());
                mtrace($e->getTraceAsString());
                // Some unknown error, need to handle it so we can record
                // where we left off.
                if ($runningastask) {
                    $recordedallmeetings = false;
                    set_config('last_call_made_at', $meetingtime - 1, 'zoom');
                    break;
                }
            }
        }

        if ($recordedallmeetings && $runningastask) {
            // All finished, so save the time that we set end time for the initial query.
            set_config('last_call_made_at', $endtime, 'zoom');
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
            $participantmatches = $DB->get_records(
                'zoom_meeting_participants',
                ['uuid' => $participant->id],
                null,
                'id, userid, name'
            );

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
            if (!empty($participant->user_email) && ($moodleuserid = array_search(strtoupper($participant->user_email), $emails))) {
                // Found email from list of enrolled users.
                $name = $names[$moodleuserid];
            } else if (!empty($participant->name) && ($moodleuserid = array_search(strtoupper($participant->name), $names))) {
                // Found name from list of enrolled users.
                $name = $names[$moodleuserid];
            } else if (
                !empty($participant->user_email)
                && ($moodleuser = $DB->get_record('user', [
                    'email' => $participant->user_email,
                    'deleted' => 0,
                    'suspended' => 0,
                ], '*', IGNORE_MULTIPLE))
            ) {
                // This is the case where someone attends the meeting, but is not enrolled in the class.
                $moodleuserid = $moodleuser->id;
                $name = strtoupper(fullname($moodleuser));
            } else if (!empty($participant->name) && ($moodleuserid = $this->match_name($participant->name, $names))) {
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

        return [
            'name' => $name,
            'userid' => $moodleuserid,
            'detailsid' => $detailsid,
            'zoomuserid' => $participant->user_id,
            'uuid' => $participant->id,
            'user_email' => $participant->user_email,
            'join_time' => strtotime($participant->join_time),
            'leave_time' => strtotime($participant->leave_time),
            'duration' => $participant->duration,
        ];
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
        $names = [];
        $emails = [];
        foreach ($enrolled as $user) {
            $name = strtoupper(fullname($user));
            $names[$user->id] = $name;
            $emails[$user->id] = strtoupper(zoom_get_api_identifier($user));
        }

        return [$names, $emails];
    }

    /**
     * Get meetings first by querying for active hostuuids for given time
     * period. Then find meetings that host have given in given time period.
     *
     * This is the older method of querying for meetings. It has been superseded
     * by the Dashboard API. However, that API is only available for Business
     * accounts and higher. The Reports API is available for Pro user and up.
     *
     * This method is kept for those users that have Pro accounts and using
     * this plugin.
     *
     * @param string $start    If passed, will find meetings starting on given date. Format is YYYY-MM-DD.
     * @param string $end      If passed, will find meetings ending on given date. Format is YYYY-MM-DD.
     * @param array $hostuuids If passed, will find only meetings for given array of host uuids.
     *
     * @return array
     */
    public function get_meetings_via_reports($start, $end, $hostuuids) {
        global $DB;
        mtrace('Using Reports API');
        if (empty($hostuuids)) {
            $this->debugmsg('Empty hostuuids, querying all hosts');
            // Get all hosts.
            $activehostsuuids = $this->service->get_active_hosts_uuids($start, $end);
        } else {
            $this->debugmsg('Hostuuids passed');
            // Else we just want a specific hosts.
            $activehostsuuids = $hostuuids;
        }

        $allmeetings = [];
        $localhosts = $DB->get_records_menu('zoom', null, '', 'id, host_id');

        mtrace("Processing " . count($activehostsuuids) . " active host uuids");

        foreach ($activehostsuuids as $activehostsuuid) {
            // This API call returns information about meetings and webinars,
            // don't need extra functionality for webinars.
            $usersmeetings = [];
            if (in_array($activehostsuuid, $localhosts)) {
                $this->debugmsg('Getting meetings for host uuid ' . $activehostsuuid);
                try {
                    $usersmeetings = $this->service->get_user_report($activehostsuuid, $start, $end);
                } catch (\mod_zoom\not_found_exception $e) {
                    // Zoom API returned user not found for a user it said had,
                    // meetings. Have to skip user.
                    $this->debugmsg("Skipping $activehostsuuid because user does not exist on Zoom");
                    continue;
                } catch (\mod_zoom\retry_failed_exception $e) {
                    // Hit API limit, so cannot continue.
                    mtrace($e->response . ': ' . $e->zoomerrorcode);
                    return;
                }
            } else {
                // Ignore hosts who hosted meetings outside of integration.
                continue;
            }

            $this->debugmsg(sprintf('Found %d meetings for user', count($usersmeetings)));
            foreach ($usersmeetings as $usermeeting) {
                $allmeetings[] = $usermeeting;
            }
        }

        return $allmeetings;
    }

    /**
     * Get meetings and webinars using Dashboard API.
     *
     * @param string $start    If passed, will find meetings starting on given date. Format is YYYY-MM-DD.
     * @param string $end      If passed, will find meetings ending on given date. Format is YYYY-MM-DD.
     *
     * @return array
     */
    public function get_meetings_via_dashboard($start, $end) {
        mtrace('Using Dashboard API');

        $meetings = $this->service->get_meetings($start, $end);
        $webinars = $this->service->get_webinars($start, $end);
        $allmeetings = array_merge($meetings, $webinars);

        return $allmeetings;
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

    /**
     * Outputs finer grained debugging messaging if debug mode is on.
     *
     * @param string $msg
     */
    public function debugmsg($msg) {
        if ($this->debuggingenabled) {
            mtrace($msg);
        }
    }

    /**
     * Saves meeting details and participants for reporting.
     *
     * @param array $meeting    Normalized meeting object
     * @return boolean
     */
    public function process_meeting_reports($meeting) {
        global $DB;

        $this->debugmsg(sprintf(
            'Processing meeting %s|%s that occurred at %s',
            $meeting->meeting_id,
            $meeting->uuid,
            $meeting->start_time
        ));

        // If meeting doesn't exist in the zoom database, the instance is
        // deleted, and we don't need reports for these.
        if (!($zoomrecord = $DB->get_record('zoom', ['meeting_id' => $meeting->meeting_id], '*', IGNORE_MULTIPLE))) {
            mtrace('Meeting does not exist locally; skipping');
            return true;
        }

        $meeting->zoomid = $zoomrecord->id;

        // Insert or update meeting details.
        if (!($DB->record_exists('zoom_meeting_details', ['uuid' => $meeting->uuid]))) {
            $this->debugmsg('Inserting zoom_meeting_details');
            $detailsid = $DB->insert_record('zoom_meeting_details', $meeting);
        } else {
            // Details entry already exists, so update it.
            $this->debugmsg('Updating zoom_meeting_details');
            $detailsid = $DB->get_field('zoom_meeting_details', 'id', ['uuid' => $meeting->uuid]);
            $meeting->id = $detailsid;
            $DB->update_record('zoom_meeting_details', $meeting);
        }

        try {
            $participants = $this->service->get_meeting_participants($meeting->uuid, $zoomrecord->webinar);
        } catch (\mod_zoom\not_found_exception $e) {
            mtrace(sprintf('Warning: Cannot find meeting %s|%s; skipping', $meeting->meeting_id, $meeting->uuid));
            return true;    // Not really a show stopping error.
        } catch (\mod_zoom\webservice_exception $e) {
            mtrace($e->response . ': ' . $e->zoomerrorcode);
            return false;
        }

        // Loop through each user to generate name->uids mapping.
        [$names, $emails] = $this->get_enrollments($zoomrecord->course);

        $this->debugmsg(sprintf('Processing %d participants', count($participants)));

        // Now try to insert participants, first drop any records for given
        // meeting and then add. There is no unique key that we can use for
        // knowing what users existed before.
        try {
            $transaction = $DB->start_delegated_transaction();

            $count = $DB->count_records('zoom_meeting_participants', ['detailsid' => $detailsid]);
            if (!empty($count)) {
                $this->debugmsg(sprintf('Dropping previous records of %d participants', $count));
                $DB->delete_records('zoom_meeting_participants', ['detailsid' => $detailsid]);
            }

            foreach ($participants as $rawparticipant) {
                $this->debugmsg(sprintf(
                    'Working on %s (user_id: %d, uuid: %s)',
                    $rawparticipant->name,
                    $rawparticipant->user_id,
                    $rawparticipant->id
                ));
                $participant = $this->format_participant($rawparticipant, $detailsid, $names, $emails);
                $recordid = $DB->insert_record('zoom_meeting_participants', $participant, true);
                $this->debugmsg('Inserted record ' . $recordid);
            }

            $transaction->allow_commit();
        } catch (\dml_exception $exception) {
            $transaction->rollback($exception);
            mtrace('ERROR: Cannot insert zoom_meeting_participants: ' . $exception->getMessage());
            return false;
        }

        $this->debugmsg('Finished updating meeting report');
        return true;
    }

    /**
     * The meeting object from the Dashboard API differs from the Report API, so
     * normalize the meeting object to conform to what is expected it the
     * database.
     *
     * @param object $meeting
     * @return object   Normalized meeting object
     */
    public function normalize_meeting($meeting) {
        $normalizedmeeting = new \stdClass();

        // Returned meeting object will not be using Zoom's id, because it is a
        // primary key in our own tables.
        $normalizedmeeting->meeting_id = $meeting->id;

        // Convert times to Unixtimestamps.
        $normalizedmeeting->start_time = strtotime($meeting->start_time);
        $normalizedmeeting->end_time = strtotime($meeting->end_time);

        // Copy values that are named the same.
        $normalizedmeeting->uuid = $meeting->uuid;
        $normalizedmeeting->topic = $meeting->topic;

        // Dashboard API has duration as H:M:S while report has it in minutes.
        $timeparts = explode(':', $meeting->duration);

        // Convert duration into minutes.
        if (count($timeparts) === 1) {
            // Time is already in minutes.
            $normalizedmeeting->duration = intval($meeting->duration);
        } else if (count($timeparts) === 2) {
            // Time is in MM:SS format.
            $normalizedmeeting->duration = $timeparts[0];
        } else {
            // Time is in HH:MM:SS format.
            $normalizedmeeting->duration = 60 * $timeparts[0] + $timeparts[1];
        }

        // Copy values that are named differently.
        $normalizedmeeting->participants_count = $meeting->participants ?? $meeting->participants_count;

        // Dashboard API does not have total_minutes.
        $normalizedmeeting->total_minutes = $meeting->total_minutes ?? null;

        return $normalizedmeeting;
    }
}
