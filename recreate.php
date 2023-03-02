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
 * Recreate a meeting that exists on Moodle but cannot be found on Zoom.
 *
 * @package    mod_zoom
 * @copyright  2017 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/locallib.php');

require_login();
// Additional access checks in zoom_get_instance_setup().
list($course, $cm, $zoom) = zoom_get_instance_setup();

require_sesskey();
$context = context_module::instance($cm->id);
// This capability is for managing Zoom instances in general.
require_capability('mod/zoom:addinstance', $context);

$PAGE->set_url('/mod/zoom/recreate.php', ['id' => $cm->id]);

// Create a new meeting with Zoom API to replace the missing one.
// We will use the logged-in user's Zoom account to recreate,
// in case the meeting's former owner no longer exists on Zoom.
$zoom->host_id = zoom_get_user_id();

$trackingfields = $DB->get_records('zoom_meeting_tracking_fields', ['meeting_id' => $zoom->id]);
foreach ($trackingfields as $trackingfield) {
    $field = $trackingfield->tracking_field;
    $zoom->$field = $trackingfield->value;
}

// Set the current zoom table entry to use the new meeting (meeting_id/etc).
$response = zoom_webservice()->create_meeting($zoom);
$zoom = populate_zoom_from_response($zoom, $response);
$zoom->exists_on_zoom = ZOOM_MEETING_EXISTS;
$zoom->timemodified = time();
$DB->update_record('zoom', $zoom);

// Return to Zoom page.
redirect(new moodle_url('/mod/zoom/view.php', ['id' => $cm->id]),
        get_string('recreatesuccessful', 'mod_zoom'));
