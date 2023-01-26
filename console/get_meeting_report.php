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
 * Console page to output the results of the CLI to get the Zoom meeting reports.
 *
 * @package    mod_zoom
 * @copyright  2020 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__.'/../../../config.php');
require_once(dirname(__FILE__).'/../../../lib/moodlelib.php');

// Force debugging errors.
error_reporting(E_ALL);
ini_set('display_errors', '1');

$courseid = required_param('courseid', PARAM_INT);
$startdate = optional_param('start', date('Y-m-d', strtotime('-3 days')), PARAM_ALPHANUMEXT);
$enddate = optional_param('end', date('Y-m-d'), PARAM_ALPHANUMEXT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

require_course_login($course);

$context = context_course::instance($course->id);
require_capability('mod/zoom:view', $context);
require_capability('mod/zoom:refreshsessions', $context);

// Set up the moodle page.
$PAGE->set_url('/mod/zoom/console/');

echo html_writer::tag('h1', get_string('getmeetingreports', 'mod_zoom'));
$output = null;
exec("php $CFG->dirroot/mod/zoom/cli/get_meeting_report.php --start=$startdate --end=$enddate --courseid=$courseid", $output);
echo '<pre>';
echo implode("\n", $output);
echo '</pre>';
