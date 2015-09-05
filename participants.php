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
require_once(dirname(__FILE__).'/../../lib/moodlelib.php');

list($course, $cm, $zoom) = zoom_get_instance_setup();
$session = required_param('session', PARAM_INT); // Session.

$PAGE->set_url('/mod/zoom/participants.php', array('id' => $cm->id, 'session' => $session));

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

echo $OUTPUT->header();
echo $OUTPUT->heading($strname);
echo $OUTPUT->heading($strtitle, 4);

$participants = $todisplay->sessions[$zoom->meeting_id][$session]->participants;
if (empty($participants)) {
    notice(get_string('noparticipants', 'mod_zoom'),
            new moodle_url('/mod/zoom/report.php', array('id' => $cm->id)));
}

$table = new html_table();
$table->head = array(get_string('name'),
                     get_string('jointime', 'mod_zoom'),
                     get_string('leavetime', 'mod_zoom'),
                     get_string('duration', 'mod_zoom'));
$numcolumns = 4;

foreach ($participants as $p) {
    $row = array();
    $row[] = $p->name;

    $join = strtotime($p->join_time);
    $row[] = userdate($join);
    $leave = strtotime($p->leave_time);
    $row[] = userdate($leave);

    $row[] = format_time($leave - $join);

    $table->data[] = $row;
}


echo html_writer::table($table);
echo $OUTPUT->footer();
