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
 * Load zoom meeting recording and add a record of the view.
 *
 * @package    mod_zoom
 * @copyright  2020 Nick Stefanski <nmstefanski@gmail.com>
 * @author     2021 Jwalit Shah <jwalitshah@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/moodlelib.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);
$meetinguuid = required_param('meetinguuid', PARAM_TEXT);
$recordingstart = required_param('recordingstart', PARAM_INT);
$showrecording = required_param('showrecording', PARAM_INT);

list($course, $cm, $zoom) = zoom_get_instance_setup();
require_login($course, true, $cm);

$context = context_module::instance($id);
$PAGE->set_context($context);
require_capability('mod/zoom:addinstance', $context);

$urlparams = array('id' => $id);
$url = new moodle_url('/mod/zoom/recordings.php', $urlparams);
if (!confirm_sesskey()) {
    redirect($url, get_string('sesskeyinvalid', 'mod_zoom'));
}

// Find the video recording and audio only recording pair that matches the criteria.
$recordings = $DB->get_records('zoom_meeting_recordings', ['meetinguuid' => $meetinguuid, 'recordingstart' => $recordingstart]);
if (empty($recordings)) {
    throw new moodle_exception('recordingnotfound', 'mod_zoom', '', get_string('recordingnotfound', 'zoom'));
}

$now = time();

// Toggle the showrecording value.
if ($showrecording === 1 || $showrecording === 0) {
    foreach ($recordings as $rec) {
        $rec->showrecording = $showrecording;
        $rec->timemodified = $now;
        $DB->update_record('zoom_meeting_recordings', $rec);
    }
}

redirect($url);
