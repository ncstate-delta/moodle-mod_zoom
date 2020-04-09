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
            'h' => 'help',
            'c' => 'courseid',
            'm' => 'meetingid',
            'u' => 'meetinguuid'
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
$meetingtask->debuggingenabled = true;  // We want to see detailed progress.
$service = new \mod_zoom_webservice();
$trace = new text_progress_trace();

// Go through each meeting and update participants.
$meetingcache = $enrollmentcache = array();
foreach ($meetings as $meeting) {
    // Task is expecting meeting_id to be in id.
    $meeting->id = $meeting->meeting_id;
    $meetingtask->process_meeting_reports($meeting, $service);
}