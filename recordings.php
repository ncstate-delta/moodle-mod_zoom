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
 * Adding, updating, and deleting zoom meeting recordings.
 *
 * @package    mod_zoom
 * @author     Nick Stefanski <nmstefanski@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/recording_form.php');

const ACTION_ADD    = 1;
const ACTION_UPDATE = 2;
const ACTION_DELETE = 3;

$id                 = required_param('id', PARAM_INT);
$action             = required_param('action', PARAM_INT);

list($course, $cm, $zoom) = zoom_get_instance_setup();

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/zoom:addinstance', $context);

$url = new moodle_url('/mod/zoom/recordings.php', $params = array('id' => $cm->id, 'action' => $action));

$PAGE->set_url($url);

$strname = $zoom->name;
$strtitle = get_string('recordings', 'mod_zoom');
$PAGE->navbar->add($strtitle);
$PAGE->set_title("$course->shortname: $strname");
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading($strname);
echo $OUTPUT->heading($strtitle, 4);

//$currenttab = attendance_tabs::TAB_ADD;
$formparams = array('course' => $course, 'cm' => $cm, 'modcontext' => $context);
switch ($action) {
    case ACTION_ADD:
        $mform = new mod_zoom_recording_form($url, $formparams);

        if ($mform->is_cancelled()) {
            redirect(new moodle_url('/mod/zoom/view.php', $params = array('id' => $cm->id)));
        }

        if ($formdata = $mform->get_data()) {
            $now = time();

            $rec = new stdClass();
            $rec->zoomid = $zoom->id;
            $rec->name = $formdata->name;
            $rec->externalurl = $formdata->externalurl;
            $rec->timecreated = $now;
            $rec->timemodified = $now;
            $rec->id = $DB->insert_record('zoom_meeting_recordings', $rec);

            // Redirect back to meeting view.
            redirect(new moodle_url('/mod/zoom/view.php', $params = array('id' => $cm->id)));
        }
        break;
}

$mform->display();

echo $OUTPUT->footer();
