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
// Login check require_login() is called in zoom_get_instance_setup();.
// @codingStandardsIgnoreLine
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/mod_form.php');
require_once(dirname(__FILE__).'/../../lib/moodlelib.php');

list($course, $cm, $zoom) = zoom_get_instance_setup();

// Check capability.
$context = context_module::instance($cm->id);
require_capability('mod/zoom:addinstance', $context);

$PAGE->set_url('/mod/zoom/report.php', array('id' => $cm->id));

$strname = $zoom->name;
$strtitle = get_string('sessions', 'mod_zoom');
$PAGE->navbar->add($strtitle);
$PAGE->set_title("$course->shortname: $strname");
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

$created = date_parse($zoom->created_at);
$now = getdate();
$now['month'] = $now['mon'];
$now['day'] = $now['mday'];
$from = optional_param_array('from', $created, PARAM_INT);
$to = optional_param_array('to', $now, PARAM_INT);
$fto = null;
$ffrom = null;
if (array_key_exists('date_selector', $from)) {
    $fromepoch = $from['date_selector'];
    $fromdt = DateTime::createFromFormat('U', (string)$fromepoch);
    $ffrom = $fromdt->format('Y-m-d');
}
else {
    $ffrom = sprintf('%u-%u-%u', $from['year'], $from['month'], $from['day']);
}
if (array_key_exists('date_selector', $to)) {
    $toepoch = $to['date_selector'];
    $todt = DateTime::createFromFormat('U', (string)$toepoch);
    $fto = $todt->format('Y-m-d');
}
else {
    $fto = sprintf('%u-%u-%u', $to['year'], $to['month'], $to['day']);
}
// $ffrom = sprintf('%u-%u-%u', $from['year'], $from['month'], $from['day']);
// $fto = sprintf('%u-%u-%u', $to['year'], $to['month'], $to['day']);

/* Cached structure: class->sessions[hostid][meetingid][starttime]
 *                        ->reqfrom
 *                        ->reqto
 *                        ->resfrom
 */
$cache = cache::make('mod_zoom', 'sessions');
if (!($todisplay = $cache->get(strval($zoom->host_id))) || $ffrom != $todisplay->reqfrom ||
        $fto != $todisplay->reqto || empty($todisplay->resfrom)) {
    // Send a new request if the from and to fields change from what we cached, or if the response is empty.
    $todisplay = zoom_get_sessions_for_display($zoom, $ffrom, $fto);
    $cache->set(strval($zoom->host_id), $todisplay);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($strname);
echo $OUTPUT->heading($strtitle, 4);

if (!empty($todisplay)) {
    // If the time period is longer than a month, Zoom will only return the latest month in range.
    $resfrom = $todisplay->resfrom; // From field in zoom's response.
    $parsedfrom = date_parse($ffrom);
    if ($resfrom[0] != $parsedfrom['year'] || $resfrom[1] != $parsedfrom['month'] || $resfrom[2] != $parsedfrom['day']) {
        echo $OUTPUT->notification(get_string('err_long_timeframe', 'mod_zoom'), 'notifymessage');
    }

    if (isset($todisplay->sessions[$zoom->meeting_id])) {
        $meetsessions = $todisplay->sessions[$zoom->meeting_id];

        $table = new html_table();
        $table->head = array(get_string('title', 'mod_zoom'),
                             get_string('starttime', 'mod_zoom'),
                             get_string('endtime', 'mod_zoom'),
                             get_string('duration', 'mod_zoom'),
                             get_string('participants', 'mod_zoom'));
        $table->align = array('left', 'left', 'left', 'left', 'left');

        foreach ($meetsessions as $starttime => $meet) {
            $row = array();

            $row[] = $meet->topic;

            $format = get_string('strftimedatetimeshort', 'langconfig');

            $start = strtotime($meet->start_time);

            $row[] = userdate($start, $format);
            if (!empty($meet->end_time)) {
                $end = strtotime($meet->end_time);
                $row[] = userdate($end, $format);
                $row[] = format_time($end - $start);
            } else {
                $row[] = '';
                $row[] = '';
            }

            $numparticipants = count($meet->participants);

            if ($numparticipants > 0) {
                $url = new moodle_url('/mod/zoom/participants.php',
                        array('id' => $cm->id, 'session' => $starttime));
                $row[] = html_writer::link($url, $numparticipants);
            } else {
                $row[] = 0;
            }

            $table->data[] = $row;
        }
    }
}


$dateform = new mod_zoom_report_form('report.php?id='.$cm->id);
$dateform->set_data(array('from' => $from, 'to' => $to));
echo $dateform->render();

if (!empty($table->data)) {
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('nosessions', 'mod_zoom'), 'notifymessage');
}

echo $OUTPUT->footer();
