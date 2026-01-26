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
 * Zoom webhook endpoint.
 *
 * Receives real-time events from Zoom (meeting ended, recording completed, etc.)
 * and processes them immediately instead of waiting for scheduled tasks.
 *
 * @package    mod_zoomyt
 * @copyright  2026 TUCC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// No Moodle session needed - this is called by Zoom's servers.
define('NO_MOODLE_COOKIES', true);
define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/zoomyt/classes/webhook_handler.php');

// Get the raw POST body.
$rawbody = file_get_contents('php://input');

// Get headers.
$headers = getallheaders();

// Create webhook handler.
$handler = new \mod_zoomyt\webhook_handler();

// Process the webhook.
try {
    $result = $handler->process($rawbody, $headers);
    
    // Send appropriate response.
    http_response_code($result['status']);
    header('Content-Type: application/json');
    echo json_encode($result['body']);
    
} catch (Exception $e) {
    // Log the error.
    debugging('Zoom webhook error: ' . $e->getMessage(), DEBUG_DEVELOPER);
    
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Internal server error']);
}
