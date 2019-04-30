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
 * CLI script to manually update the meeting report.
 *
 * @package    mod_zoom
 * @copyright  2019 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__.'/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

// Now get cli options.
list($options, $unrecognized) = cli_get_params(
        array(
            'help' => false,
            'courseid' => false,
            'meetingid' => false,
            'meetinguuid' => false
        ),
        array(
            'h' => 'help'
        )
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help = "CLI script to manually resychronize the meeting report for a given course, meetingid, or meetinguuid.

A meeting report needs to already exist. This will requery the participant data and relink them to Moodle users.

Options:
-h, --help          Print out this help
-c, --courseid      Course ID
-m, --meetingid     Zoom meeting ID
-u, --meetinguuid   Zoom meeting UUID

Example:
\$sudo -u www-data /usr/bin/php mod/zoom/cli/update_meeting_report.php --courseid=1234
";
    cli_error($help);
}

// Get the meeting uuids to process.
$uuids = array();
if (!empty($options['courseid'])) {
    // Get all Zoom meeting instances for course.
    $sql = "SELECT meeting.*
              FROM {zoom_meeting_details} meeting
         LEFT JOIN {zoom} zoom ON (zoom.id=meeting.zoomid)
             WHERE zoom.course=?";
    $meetings = $DB->get_records_sql($sql, array($options['courseid']));
} else if (!empty($options['meetingid'])) {
    // Get all Zoom meeting instances for meetingid.
    $sql = "SELECT meeting.*
              FROM {zoom_meeting_details} meeting
             WHERE meeting.meeting_id=?";
    $meetings = $DB->get_records('zoom_meeting_details',
            array('meeting_id' => $options['meetingid']));
} else if (!empty($options['meetinguuid'])) {
    $sql = "SELECT meeting.*
              FROM {zoom_meeting_details} meeting
             WHERE meeting.uuid=?";
    $meetings = $DB->get_records('zoom_meeting_details',
            array('uuid' => $options['meetinguuid']));
}

if (empty($meetings)) {
    cli_error('No meeting details found.');
}

$meetingtask = new mod_zoom\task\get_meeting_reports();
$service = new \mod_zoom_webservice();
$trace = new text_progress_trace();

// Go through each meeting and update participants.
$meetingcache = $enrollmentcache = array();
foreach ($meetings as $meeting) {
    $detailsid  = $meeting->id;
    $meetingid  = $meeting->meeting_id;
    $uuid       = $meeting->uuid;

    $trace->output(sprintf('Processiing detailsid: %d | meetindid: %d | uuid: %s',
            $detailsid, $meetingid, $uuid));

    // Query for zoom record.
    if (!isset($meetingcache[$meetingid])) {
        if (!($zoomrecord = $DB->get_record('zoom', array('meeting_id' => $meetingid), '*', IGNORE_MULTIPLE))) {
            // If meeting doesn't exist in the zoom database, the instance is deleted, and we don't need reports for these.
            $trace->output(sprintf('MeetingID %d does not exist; skipping', $meetingid), 1);
            continue;
        }
        $meetingcache[$meetingid] = $zoomrecord;
    }
    $zoomrecord = $meetingcache[$meetingid];

    // Query for course enrollment.
    if (!isset($enrollmentcache[$zoomrecord->course])) {
        $enrollmentcache[$zoomrecord->course] = $meetingtask->get_enrollments($zoomrecord->course);
    }
    list($names, $emails) = $enrollmentcache[$zoomrecord->course];

    if ($meetingtask->get_num_calls_left() < 1) {
        cli_error('Error: Zoom Report API calls have been exhausted.');
    }

    try {
        $participants = $service->get_meeting_participants($uuid, $zoomrecord->webinar);
    } catch (Exception $e) {
        $trace->output('Exception: ' . $e->getMessage(), 1);
        continue;
    }
    $trace->output(sprintf('Processing %d participants', count($participants)), 1);
    foreach ($participants as $rawparticipant) {
        $trace->output(sprintf('Working on %s (user_id: %d, uuid: %s)',
                $rawparticipant->name, $rawparticipant->user_id, $rawparticipant->id), 2);

        $participant = $meetingtask->format_participant($rawparticipant, $detailsid, $names, $emails);

        // Unique keys are detailsid and zoomuserid.
        if ($record = $DB->get_record('zoom_meeting_participants',
                array('detailsid' => $participant['detailsid'],
                    'zoomuserid' => $participant['zoomuserid']))) {
            // User exists, so need to update record.

            // To update, need to set ID.
            $participant['id'] = $record->id;

            $olddiff = array_diff_assoc((array) $record, $participant);
            $newdiff = array_diff_assoc($participant, (array) $record);

            if (empty($olddiff) && empty($newdiff)) {
                $trace->output('No changes found.', 3);
            } else {
                // Using http_build_query since it is an easy way to output array
                // key/value in one line.
                $trace->output('Old values: ' . print_diffs($olddiff), 3);
                $trace->output('New values: ' . print_diffs($newdiff), 3);

                $DB->update_record('zoom_meeting_participants', $participant);
                $trace->output('Updated record ' . $record->id, 3);
            }
        } else {
            // Participant does not already exist.
            $recordid = $DB->insert_record('zoom_meeting_participants', $participant, false);

            $trace->output('Inserted record ' . $recordid, 3);
        }
    }
}

/**
 * Builds a string with key/value of given array.
 *
 * @param array $diff
 * @return string
 */
function print_diffs($diff) {
    $retval = '';
    foreach ($diff as $key => $value) {
        if (!empty($retval)) {
            $retval .= ', ';
        }
        $retval .= "$key => $value";
    }
    return $retval;
}