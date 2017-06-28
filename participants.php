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
 * List all zoom meetings.
 *
 * @package    mod_zoom
 * @copyright  2015 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/../../lib/accesslib.php');
require_once(dirname(__FILE__).'/../../lib/moodlelib.php');

list($course, $cm, $zoom) = zoom_get_instance_setup();

// Check capability.
$context = context_module::instance($cm->id);
require_capability('mod/zoom:addinstance', $context);

$session = required_param('session', PARAM_INT); // Session.
$export = optional_param('export', null, PARAM_ALPHA);

$PAGE->set_url('/mod/zoom/participants.php', array('id' => $cm->id, 'session' => $session, 'export' => $export));

$strname = $zoom->name;
$strtitle = get_string('participants', 'mod_zoom');
$PAGE->navbar->add($strtitle);
$PAGE->set_title("$course->shortname: $strname");
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

/* Cached structure: class->sessions[hostid][meetingid][starttime]
 *                        ->reqfrom
 *                        ->reqto
 *                        ->resfrom
 */
$cache = cache::make('mod_zoom', 'sessions');
if (!($todisplay = $cache->get($zoom->host_id)) || empty($todisplay->sessions[$zoom->meeting_id][$session])) {
    $reqdate = getdate($session);
    $reqdate['month'] = $reqdate['mon'];
    $reqdate['day'] = $reqdate['mday'];

    $fdate = sprintf('%u-%u-%u', $reqdate['year'], $reqdate['month'], $reqdate['day']);
    $todisplay = zoom_get_sessions_for_display($zoom, $fdate, $fdate);

    $cache->set(strval($zoom->host_id), $todisplay);
}

$participants = $todisplay->sessions[$zoom->meeting_id][$session]->participants;

// Display the headers/etc if we're not exporting, or if there is no data.
if (empty($export) || empty($participants)) {
    echo $OUTPUT->header();
    echo $OUTPUT->heading($strname);
    echo $OUTPUT->heading($strtitle, 4);

    // Stop if there is no data.
    if (empty($participants)) {
        notice(get_string('noparticipants', 'mod_zoom'),
                new moodle_url('/mod/zoom/report.php', array('id' => $cm->id)));
        echo $OUTPUT->footer();
        exit();
    }
}

// Loop through each user to generate name->uids mapping.
$coursecontext = context_course::instance($course->id);
$enrolled = get_enrolled_users($coursecontext);
$nametouids = array();
foreach ($enrolled as $user) {
    $name = strtoupper(fullname($user));
    $uids = empty($nametouids[$name]) ? array() : $nametouids[$name];
    $uids[] = $user->idnumber;
    $nametouids[$name] = $uids;
}

$table = new html_table();
$table->head = array(get_string('idnumber'),
                     get_string('name'),
                     get_string('jointime', 'mod_zoom'),
                     get_string('leavetime', 'mod_zoom'),
                     get_string('duration', 'mod_zoom'));

foreach ($participants as $p) {
    $row = array();

    // For some reason, the Zoom report may add a space to the end of the name.
    $name = trim($p->name);

    // ID number.
    $row[] = empty($nametouids[strtoupper($name)]) ? '' : implode(', ', $nametouids[strtoupper($name)]);

    // Name.
    $row[] = $name;

    // Join/leave times.
    $join = strtotime($p->join_time);
    $row[] = userdate($join);
    $leave = strtotime($p->leave_time);
    $row[] = userdate($leave);

    // Duration.
    $row[] = format_time($leave - $join);

    $table->data[] = $row;
}

if ($export != 'xls') {
    echo html_writer::table($table);

    $exporturl = new moodle_url('/mod/zoom/participants.php', array(
            'id' => $cm->id,
            'session' => $session,
            'export' => 'xls'
        ));
    $xlsstring = get_string('application/vnd.ms-excel', 'mimetypes');
    $xlsicon = html_writer::img($OUTPUT->pix_url('f/spreadsheet'), $xlsstring, array('title' => $xlsstring));
    echo get_string('export', 'mod_zoom') . ': ' . html_writer::link($exporturl, $xlsicon);

    echo $OUTPUT->footer();
} else {
    require_once(dirname(__FILE__).'/../../lib/excellib.class.php');

    $workbook = new MoodleExcelWorkbook("zoom_participants_{$zoom->meeting_id}");
    $worksheet = $workbook->add_worksheet($strtitle);
    $boldformat = $workbook->add_format();
    $boldformat->set_bold(true);
    $row = $col = 0;

    foreach($table->head as $colname) {
        $worksheet->write_string($row, $col++, $colname, $boldformat);
    }
    $row++; $col = 0;

    foreach ($table->data as $entry) {
        foreach ($entry as $value) {
            $worksheet->write_string($row, $col++, $value);
        }
        $row++; $col = 0;
    }

    $workbook->close();
    exit();
}
