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
 * @author     Nick Stefanski <nmstefanski@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->libdir . '/moodlelib.php');
require_once(dirname(__FILE__).'/locallib.php');

$id = required_param('id', PARAM_INT);
$recordingid = required_param('recordingid', PARAM_INT);

list($course, $cm, $zoom) = zoom_get_instance_setup();

if (!$rec = $DB->get_record('zoom_meeting_recordings', array('id' => $recordingid))){
    print_error('Recording could not be found');
}

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
$PAGE->set_context($context);

require_capability('mod/zoom:view', $context);

$params = array('recordingsid' => $rec->id, 'userid' => $USER->id);
$now = time();

if ($view = $DB->get_record('zoom_meeting_recordings_view', $params)){
    if (!$view->viewed){
        $view->viewed = 1;
        $view->timemodified = $now;
        $DB->update_record('zoom_meeting_recordings_view', $view);
    } else {
        // already record of view, no action needed
    }
} else {
    $view = new stdClass();
    $view->recordingsid = $rec->id;
    $view->userid = $USER->id;
    $view->viewed = 1;
    $view->timemodified = $now;
    $view->id = $DB->insert_record('zoom_meeting_recordings_view', $view);
}

$nexturl = new moodle_url($rec->externalurl);

redirect($nexturl);
