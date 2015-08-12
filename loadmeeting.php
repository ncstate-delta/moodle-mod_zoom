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
 * Load zoom meeting and assign grade to the user join the meeting.
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_zoom
 * @copyright  2015 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/moodlelib.php');

$id = required_param('id', PARAM_INT); // Course_module ID.
if ($id) {
    $cm         = get_coursemodule_from_id('zoom', $id, 0, false, MUST_EXIST);
    $course     = get_course($cm->course);
    $zoom  = $DB->get_record('zoom', array('id' => $cm->instance), '*', MUST_EXIST);
} else {
    error('You must specify a course_module ID');
}

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
$PAGE->set_context($context);

require_capability('mod/zoom:view', $context);

// Check whether user had a grade. If no, then assign full credits to him or her.
$gradelist = grade_get_grades($course->id, 'mod', 'zoom', $cm->instance, $USER->id);

// Assign full credits for user who has no grade yet, if this meeting is gradable
// (i.e. the grade type is not "None").
if (!empty($gradelist->items) && empty($gradelist->items[0]->grades[$USER->id]->grade)) {
    $grademax = $gradelist->items[0]->grademax;
    $grades = array('rawgrade' => $grademax,
                    'userid' => $USER->id,
                    'usermodified' => $USER->id,
                    'dategraded' => '',
                    'feedbackformat' => '',
                    'feedback' => '');

    zoom_grade_item_update($zoom, $grades);
}

// Redirect user to join zoom meeting.
$joinurl = new moodle_url($zoom->join_url, array('uname' => fullname($USER)));
redirect($joinurl);