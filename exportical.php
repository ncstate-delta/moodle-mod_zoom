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
 * Export ical file for a zoom meeting.
 *
 * @package    mod_zoom
 * @copyright  2015 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/moodlelib.php');
require_once(__DIR__ . '/locallib.php');
require_once($CFG->libdir . '/bennu/bennu.inc.php');

// Course_module ID.
$id = required_param('id', PARAM_INT);
if ($id) {
    $cm = get_coursemodule_from_id('zoom', $id, 0, false, MUST_EXIST);
    $course = get_course($cm->course);
    $zoom = $DB->get_record('zoom', ['id' => $cm->instance], '*', MUST_EXIST);
} else {
    throw new moodle_exception('zoomerr_id_missing', 'mod_zoom');
}

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
$PAGE->set_context($context);

require_capability('mod/zoom:view', $context);

// Get config.
$config = get_config('zoom');

// Check if the admin did not disable the feature.
if ($config->showdownloadical == ZOOM_DOWNLOADICAL_DISABLE) {
    $disabledredirecturl = new moodle_url('/mod/zoom/view.php', ['id' => $id]);
    throw new moodle_exception('err_downloadicaldisabled', 'mod_zoom', $disabledredirecturl);
}

// Check if we are dealing with a recurring meeting with no fixed time.
if ($zoom->recurring && $zoom->recurrence_type == ZOOM_RECURRINGTYPE_NOTIME) {
    $errorredirecturl = new moodle_url('/mod/zoom/view.php', ['id' => $id]);
    throw new moodle_exception('err_downloadicalrecurringnofixed', 'mod_zoom', $errorredirecturl);
}

// Start ical file.
$ical = new iCalendar();
$ical->add_property('method', 'PUBLISH');
$ical->add_property('prodid', '-//Moodle Pty Ltd//NONSGML Moodle Version ' . $CFG->version . '//EN');

// Get the meeting invite note to add to the description property.
$meetinginvite = zoom_webservice()->get_meeting_invitation($zoom)->get_display_string($cm->id);

// Compute and add description property to event.
$convertedtext = html_to_text($zoom->intro);
$descriptiontext = get_string('calendardescriptionURL', 'mod_zoom', $CFG->wwwroot . '/mod/zoom/view.php?id=' . $cm->id);
if (!empty($convertedtext)) {
    $descriptiontext .= get_string('calendardescriptionintro', 'mod_zoom', $convertedtext);
}

if (!empty($meetinginvite)) {
    $descriptiontext .= "\n\n" . $meetinginvite;
}

// Get all occurrences of the meeting from the DB.
$params = ['modulename' => 'zoom', 'instance' => $zoom->id];
$events = $DB->get_records('event', $params, 'timestart ASC');

// If we haven't got at least a single occurrence.
if (empty($events)) {
    // We could handle this case in a nicer way ans return an empty iCal file without events,
    // but as this case should not happen in real life anyway, return a fatal error to make clear that something is wrong.
    $errorredirecturl = new moodle_url('/mod/zoom/view.php', ['id' => $id]);
    throw new moodle_exception('err_downloadicalrecurringempty', 'mod_zoom', $errorredirecturl);
}

// Iterate over all events.
// We will add each event as an individual iCal event.
foreach ($events as $event) {
    $icalevent = zoom_helper_icalendar_event($event, $descriptiontext);
    // Add the event to the iCal file.
    $ical->add_component($icalevent);
}

// Start output of iCal file.
$serialized = $ical->serialize();
$filename = 'icalexport.ics';

// Create headers.
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', time()) . ' GMT');
header('Cache-Control: private, must-revalidate, pre-check=0, post-check=0, max-age=0');
header('Expires: ' . gmdate('D, d M Y H:i:s', 0) . 'GMT');
header('Pragma: no-cache');
header('Accept-Ranges: none'); // Comment out if PDFs do not work...
header('Content-disposition: attachment; filename=' . $filename);
header('Content-length: ' . strlen($serialized));
header('Content-type: text/calendar; charset=utf-8');

echo $serialized;
