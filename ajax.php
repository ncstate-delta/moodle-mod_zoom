<?php
// This file is part of the Zoom YT plugin for Moodle - http://moodle.org/
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
 * AJAX handler for Zoom YT plugin.
 *
 * @package    mod_zoom_yt
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/zoom_yt/locallib.php');

$action = required_param('action', PARAM_ALPHA);
$cmid = required_param('cmid', PARAM_INT);

// Verify session.
require_sesskey();

// Get course module and context.
$cm = get_coursemodule_from_id('zoom_yt', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);

$result = ['success' => false, 'message' => ''];

try {
    switch ($action) {
        case 'record_view':
            $videoid = required_param('videoid', PARAM_INT);
            
            require_once($CFG->dirroot . '/mod/zoom_yt/classes/output/video_gallery.php');
            \mod_zoom_yt\output\video_gallery::record_view($videoid, $USER->id, $context);
            
            $result['success'] = true;
            break;

        case 'toggle_visibility':
            // Require editing capability.
            require_capability('mod/zoom_yt:addinstance', $context);
            
            $videoid = required_param('videoid', PARAM_INT);
            $visible = required_param('visible', PARAM_BOOL);
            
            require_once($CFG->dirroot . '/mod/zoom_yt/classes/output/video_gallery.php');
            \mod_zoom_yt\output\video_gallery::toggle_visibility($videoid, $visible);
            
            $result['success'] = true;
            $result['visible'] = $visible;
            break;

        default:
            throw new moodle_exception('invalid_action', 'zoom_yt');
    }
} catch (Exception $e) {
    $result['success'] = false;
    $result['message'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($result);
