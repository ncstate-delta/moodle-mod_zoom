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

namespace mod_zoomyt;

defined('MOODLE_INTERNAL') || die();

/**
 * Handles incoming Zoom webhook events.
 *
 * @package    mod_zoomyt
 * @copyright  2026 TUCC
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class webhook_handler {

    /** @var string Webhook secret token from Zoom app settings */
    private $secrettoken;

    /** @var bool Whether webhooks are enabled */
    private $enabled;

    /**
     * Constructor.
     */
    public function __construct() {
        $config = get_config('zoomyt');
        $this->secrettoken = $config->webhook_secret ?? '';
        $this->enabled = !empty($config->webhook_enabled);
    }

    /**
     * Process an incoming webhook request.
     *
     * @param string $rawbody The raw POST body.
     * @param array $headers The request headers.
     * @return array Response with 'status' (HTTP code) and 'body' (response data).
     */
    public function process(string $rawbody, array $headers): array {
        // Decode the JSON body first - needed for URL validation check.
        $payload = json_decode($rawbody);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log('Invalid JSON in webhook body');
            return ['status' => 400, 'body' => ['error' => 'Invalid JSON']];
        }

        // Handle URL validation challenge BEFORE checking if webhooks are enabled.
        // This is required because Zoom sends the validation challenge when you first
        // configure the webhook, before you can save the "enabled" setting in Moodle.
        if (isset($payload->event) && $payload->event === 'endpoint.url_validation') {
            // URL validation only requires the secret token, not full webhook enablement.
            if (empty($this->secrettoken)) {
                $this->log('URL validation received but no secret token configured');
                return ['status' => 401, 'body' => ['error' => 'Secret token not configured']];
            }
            return $this->handle_url_validation($payload);
        }

        // For all other events, check if webhooks are enabled.
        if (!$this->enabled) {
            $this->log('Webhook received but webhooks are disabled');
            return ['status' => 200, 'body' => ['message' => 'Webhooks disabled']];
        }

        // Check if secret token is configured.
        if (empty($this->secrettoken)) {
            $this->log('Webhook received but no secret token configured');
            return ['status' => 401, 'body' => ['error' => 'Webhook not configured']];
        }

        // Verify the webhook signature.
        if (!$this->verify_signature($rawbody, $headers)) {
            $this->log('Invalid webhook signature');
            return ['status' => 401, 'body' => ['error' => 'Invalid signature']];
        }

        // Process the event.
        return $this->handle_event($payload);
    }

    /**
     * Handle URL validation challenge from Zoom.
     *
     * When you first configure a webhook endpoint in Zoom, it sends a challenge
     * that must be hashed and returned to verify ownership.
     *
     * @param object $payload The webhook payload.
     * @return array Response.
     */
    private function handle_url_validation(object $payload): array {
        $plaintoken = $payload->payload->plainToken ?? '';
        
        if (empty($plaintoken)) {
            return ['status' => 400, 'body' => ['error' => 'Missing plainToken']];
        }

        // Create the encrypted token using HMAC-SHA256.
        $encryptedtoken = hash_hmac('sha256', $plaintoken, $this->secrettoken);

        $this->log('URL validation successful');

        return [
            'status' => 200,
            'body' => [
                'plainToken' => $plaintoken,
                'encryptedToken' => $encryptedtoken,
            ],
        ];
    }

    /**
     * Verify the webhook signature.
     *
     * Zoom signs all webhook payloads using HMAC-SHA256. We must verify this
     * signature to ensure the request is legitimate.
     *
     * @param string $rawbody The raw POST body.
     * @param array $headers The request headers.
     * @return bool True if signature is valid.
     */
    private function verify_signature(string $rawbody, array $headers): bool {
        // Normalize header keys to lowercase for consistent access.
        $headers = array_change_key_case($headers, CASE_LOWER);

        // Get the signature and timestamp from headers.
        $signature = $headers['x-zm-signature'] ?? '';
        $timestamp = $headers['x-zm-request-timestamp'] ?? '';

        if (empty($signature) || empty($timestamp)) {
            $this->log('Missing signature or timestamp headers');
            return false;
        }

        // Check timestamp is within 5 minutes (prevents replay attacks).
        $now = time();
        if (abs($now - (int)$timestamp) > 300) {
            $this->log('Webhook timestamp too old');
            return false;
        }

        // Construct the message to sign.
        $message = "v0:{$timestamp}:{$rawbody}";

        // Calculate expected signature.
        $expectedsig = 'v0=' . hash_hmac('sha256', $message, $this->secrettoken);

        // Compare signatures using timing-safe comparison.
        return hash_equals($expectedsig, $signature);
    }

    /**
     * Handle a verified webhook event.
     *
     * @param object $payload The webhook payload.
     * @return array Response.
     */
    private function handle_event(object $payload): array {
        $event = $payload->event ?? 'unknown';
        $eventts = $payload->event_ts ?? time() * 1000;

        $this->log("Processing event: {$event}");

        switch ($event) {
            case 'meeting.ended':
                return $this->handle_meeting_ended($payload);

            case 'recording.completed':
                return $this->handle_recording_completed($payload);

            case 'meeting.participant_joined':
                return $this->handle_participant_joined($payload);

            case 'meeting.participant_left':
                return $this->handle_participant_left($payload);

            default:
                $this->log("Unhandled event type: {$event}");
                return ['status' => 200, 'body' => ['message' => 'Event received']];
        }
    }

    /**
     * Handle meeting.ended event.
     *
     * Triggers immediate fetch of meeting participant report.
     *
     * @param object $payload The webhook payload.
     * @return array Response.
     */
    private function handle_meeting_ended(object $payload): array {
        global $CFG, $DB;

        $meetingid = $payload->payload->object->id ?? null;
        $uuid = $payload->payload->object->uuid ?? null;
        $topic = $payload->payload->object->topic ?? 'Unknown';

        if (!$meetingid) {
            $this->log('meeting.ended: Missing meeting ID');
            return ['status' => 400, 'body' => ['error' => 'Missing meeting ID']];
        }

        $this->log("Meeting ended: {$topic} (ID: {$meetingid}, UUID: {$uuid})");

        // Find the Moodle activity for this meeting.
        $zoom = $DB->get_record('zoomyt', ['meeting_id' => $meetingid]);
        if (!$zoom) {
            $this->log("Meeting {$meetingid} not found in Moodle");
            return ['status' => 200, 'body' => ['message' => 'Meeting not tracked']];
        }

        // Queue the report fetch task to run immediately.
        // We use an ad-hoc task to avoid blocking the webhook response.
        require_once($CFG->dirroot . '/mod/zoomyt/classes/task/get_meeting_reports.php');
        
        $task = new \mod_zoomyt\task\get_meeting_reports();
        $task->set_custom_data(['instance_id' => $zoom->id, 'triggered_by' => 'webhook']);
        \core\task\manager::queue_adhoc_task($task, true); // true = run if duplicate exists

        $this->log("Queued report fetch for activity {$zoom->id}");

        return ['status' => 200, 'body' => ['message' => 'Report fetch queued']];
    }

    /**
     * Handle recording.completed event.
     *
     * Triggers immediate fetch of recording information.
     *
     * @param object $payload The webhook payload.
     * @return array Response.
     */
    private function handle_recording_completed(object $payload): array {
        global $CFG, $DB;

        $meetingid = $payload->payload->object->id ?? null;
        $uuid = $payload->payload->object->uuid ?? null;
        $topic = $payload->payload->object->topic ?? 'Unknown';

        if (!$meetingid) {
            $this->log('recording.completed: Missing meeting ID');
            return ['status' => 400, 'body' => ['error' => 'Missing meeting ID']];
        }

        $this->log("Recording completed: {$topic} (ID: {$meetingid}, UUID: {$uuid})");

        // Find the Moodle activity for this meeting.
        $zoom = $DB->get_record('zoomyt', ['meeting_id' => $meetingid]);
        if (!$zoom) {
            $this->log("Meeting {$meetingid} not found in Moodle");
            return ['status' => 200, 'body' => ['message' => 'Meeting not tracked']];
        }

        // Queue the recording fetch task.
        require_once($CFG->dirroot . '/mod/zoomyt/classes/task/get_meeting_recordings.php');
        
        $task = new \mod_zoomyt\task\get_meeting_recordings();
        $task->set_custom_data(['instance_id' => $zoom->id, 'triggered_by' => 'webhook']);
        \core\task\manager::queue_adhoc_task($task, true);

        $this->log("Queued recording fetch for activity {$zoom->id}");

        return ['status' => 200, 'body' => ['message' => 'Recording fetch queued']];
    }

    /**
     * Handle meeting.participant_joined event.
     *
     * Logs participant joining for real-time tracking.
     *
     * @param object $payload The webhook payload.
     * @return array Response.
     */
    private function handle_participant_joined(object $payload): array {
        $meetingid = $payload->payload->object->id ?? null;
        $participant = $payload->payload->object->participant ?? null;

        if ($participant) {
            $name = $participant->user_name ?? 'Unknown';
            $email = $participant->email ?? 'N/A';
            $this->log("Participant joined meeting {$meetingid}: {$name} ({$email})");
        }

        // For now, just acknowledge. Real-time participant tracking could be added later.
        return ['status' => 200, 'body' => ['message' => 'Participant join logged']];
    }

    /**
     * Handle meeting.participant_left event.
     *
     * Logs participant leaving.
     *
     * @param object $payload The webhook payload.
     * @return array Response.
     */
    private function handle_participant_left(object $payload): array {
        $meetingid = $payload->payload->object->id ?? null;
        $participant = $payload->payload->object->participant ?? null;

        if ($participant) {
            $name = $participant->user_name ?? 'Unknown';
            $email = $participant->email ?? 'N/A';
            $this->log("Participant left meeting {$meetingid}: {$name} ({$email})");
        }

        return ['status' => 200, 'body' => ['message' => 'Participant leave logged']];
    }

    /**
     * Log a message for debugging.
     *
     * @param string $message The message to log.
     */
    private function log(string $message): void {
        // Use Moodle's debugging function if in developer mode.
        if (debugging()) {
            error_log("[ZoomYT Webhook] {$message}");
        }
    }
}
