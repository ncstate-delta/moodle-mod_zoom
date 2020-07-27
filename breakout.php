<?php
// This file is part of Moodle - http://moodle.org/
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
 * Export breakout room CSV for Zoom.
 *
 * @package    mod_zoom
 * @copyright  2020 Michael Hughes, University of Strathclyde, <michaelhughes@strath.ac.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once(__DIR__.'/locallib.php');

$config = get_config('mod_zoom');

list($course, $cm, $zoom) = zoom_get_instance_setup();

$context = context_course::instance($course->id);

$iszoommanager = has_capability('mod/zoom:addinstance', $context);

$PAGE->set_url('/mod/zoom/breakout.php', ['id' => $cm->id]);

// Check group mode is actually on.

// Check if there is a grouping set on the activity.
// Generally feel that it is bad if there is no grouping set, but not everyone.
$groupingid = 3; // TODO Fake data.
// We're probably going to need a UI form here to allow user to set some configuration(?)

// Do the export.
$export = new csv_export_writer();
$filesafename = preg_replace('/[^a-z0-9-]/', '_',core_text::strtolower(strip_tags($zoom->name)));;
// ^^ this is what moodle uses in completion/index.php
$export->set_filename('mod-zoom-break-'. $filesafename . '.csv');

$grouping = groups_get_grouping($groupingid);
$groups = groups_get_all_groups($course->id, 0, $grouping->id);
