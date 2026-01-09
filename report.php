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
 * @package    mod_zoomyt
 * @copyright  2015 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/locallib.php');
require_once(__DIR__ . '/mod_form.php');
require_once($CFG->libdir . '/moodlelib.php');

require_login();
// Additional access checks in zoomyt_get_instance_setup().
[$course, $cm, $zoom] = zoomyt_get_instance_setup();

// Check capability.
$context = context_module::instance($cm->id);
require_capability('mod/zoomyt:addinstance', $context);

$PAGE->set_url('/mod/zoomyt/report.php', ['id' => $cm->id]);

$strname = $zoom->name;
$strtitle = get_string('sessions', 'mod_zoomyt');
$PAGE->navbar->add($strtitle);
$PAGE->set_title("$course->shortname: $strname");
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading($strname);
echo $OUTPUT->heading($strtitle, 4);

$sessions = zoomyt_get_sessions_for_display($zoom->id);
if (!empty($sessions)) {
    $maskparticipantdata = get_config('zoomyt', 'maskparticipantdata');
    $table = new html_table();
    $table->head = [
        get_string('title', 'mod_zoomyt'),
        get_string('starttime', 'mod_zoomyt'),
        get_string('endtime', 'mod_zoomyt'),
        get_string('duration', 'mod_zoomyt'),
        get_string('participants', 'mod_zoomyt'),
    ];
    $table->align = ['left', 'left', 'left', 'left', 'left'];
    $format = get_string('strftimedatetimeshort', 'langconfig');

    foreach ($sessions as $uuid => $meet) {
        $row = [];
        $row[] = $meet['topic'];
        $row[] = $meet['starttime'];
        $row[] = $meet['endtime'];
        $row[] = format_time($meet['duration']);

        if ($meet['count'] > 0) {
            if ($maskparticipantdata) {
                $row[] = $meet['count']
                         . ' ['
                         . get_string('participantdatanotavailable', 'mod_zoomyt')
                         . '] '
                         . $OUTPUT->help_icon('participantdatanotavailable', 'mod_zoomyt');
            } else {
                $url = new moodle_url('/mod/zoomyt/participants.php', ['id' => $cm->id, 'uuid' => $uuid]);
                $row[] = html_writer::link($url, $meet['count']);
            }
        } else {
            $row[] = 0;
        }

        $table->data[] = $row;
    }
}

if (!empty($table->data)) {
    echo html_writer::table($table);
} else {
    echo $OUTPUT->notification(get_string('nomeetinginstances', 'mod_zoomyt'), 'notifymessage');
}

echo $OUTPUT->footer();
