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
 * The task for getting recordings from Zoom to Moodle.
 *
 * @package    mod_zoom
 * @author     Jwalit Shah <jwalitshah@catalyst-au.net>
 * @copyright  2021 Jwalit Shah <jwalitshah@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoom\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/zoom/locallib.php');

/**
 * Scheduled task to get the meeting recordings.
 */
class get_meeting_recordings extends \core\task\scheduled_task {
    /**
     * Returns name of task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('getmeetingrecordings', 'mod_zoom');
    }

    /**
     * Get any new recordings that have been added on zoom.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        try {
            $service = zoom_webservice();
        } catch (\moodle_exception $exception) {
            mtrace('Skipping task - ', $exception->getMessage());
            return;
        }

        $config = get_config('zoom');
        if (empty($config->viewrecordings)) {
            mtrace('Skipping task - ', get_string('zoomerr_viewrecordings_off', 'zoom'));
            return;
        }

        // See if we cannot make anymore API calls.
        $retryafter = get_config('zoom', 'retry-after');
        if (!empty($retryafter) && time() < $retryafter) {
            mtrace('Out of API calls, retry after ' . userdate($retryafter, get_string('strftimedaydatetime', 'core_langconfig')));
            return;
        }

        mtrace('Finding meeting recordings for this account...');

        $recordingtypestrings = [
            'active_speaker' => get_string('recordingtype_active_speaker', 'mod_zoom'),
            'audio_interpretation' => get_string('recordingtype_audio_interpretation', 'mod_zoom'),
            'audio_only' => get_string('recordingtype_audio_only', 'mod_zoom'),
            'audio_transcript' => get_string('recordingtype_audio_transcript', 'mod_zoom'),
            'chat_file' => get_string('recordingtype_chat', 'mod_zoom'),
            'closed_caption' => get_string('recordingtype_closed_caption', 'mod_zoom'),
            'gallery_view' => get_string('recordingtype_gallery', 'mod_zoom'),
            'poll' => get_string('recordingtype_poll', 'mod_zoom'),
            'production_studio' => get_string('recordingtype_production_studio', 'mod_zoom'),
            'shared_screen' => get_string('recordingtype_shared', 'mod_zoom'),
            'shared_screen_with_gallery_view' => get_string('recordingtype_shared_gallery', 'mod_zoom'),
            'shared_screen_with_speaker_view' => get_string('recordingtype_shared_speaker', 'mod_zoom'),
            'shared_screen_with_speaker_view(CC)' => get_string('recordingtype_shared_speaker_cc', 'mod_zoom'),
            'sign_interpretation' => get_string('recordingtype_sign', 'mod_zoom'),
            'speaker_view' => get_string('recordingtype_speaker', 'mod_zoom'),
            'summary' => get_string('recordingtype_summary', 'mod_zoom'),
            'summary_next_steps' => get_string('recordingtype_summary_next_steps', 'mod_zoom'),
            'summary_smart_chapters' => get_string('recordingtype_summary_smart_chapters', 'mod_zoom'),
            'timeline' => get_string('recordingtype_timeline', 'mod_zoom'),
        ];

        $localmeetings = zoom_get_all_meeting_records();

        $now = time();
        $from = gmdate('Y-m-d', strtotime('-1 day', $now));
        $to = gmdate('Y-m-d', strtotime('+1 day', $now));

        $hostmeetings = [];

        foreach ($localmeetings as $zoom) {
            // Only get recordings for this meeting if its recurring or already finished.
            if ($zoom->recurring || $now > (intval($zoom->start_time) + intval($zoom->duration))) {
                $hostmeetings[$zoom->host_id][$zoom->meeting_id] = $zoom;
            }
        }

        if (empty($hostmeetings)) {
            mtrace('No meetings need to be processed.');
            return;
        }

        $meetingpasscodes = [];
        $localrecordings = zoom_get_meeting_recordings_grouped();

        foreach ($hostmeetings as $hostid => $meetings) {
            // Fetch all recordings for this user.
            $zoomrecordings = $service->get_user_recordings($hostid, $from, $to);

            foreach ($zoomrecordings as $recordingid => $recording) {
                if (isset($localrecordings[$recording->meetinguuid][$recordingid])) {
                    mtrace('Recording id: ' . $recordingid . ' exists...skipping');
                    continue;
                }

                if (empty($meetings[$recording->meetingid])) {
                    // Skip meetings that are not in Moodle.

                    var_dump($recording);
                    mtrace('Meeting id: ' . $recording->meetingid . ' does not exist...skipping');
                    continue;
                }

                // As of 2023-09-24, 'password' is not present in the user recordings API response.
                if (empty($meetingpasscodes[$recording->meetinguuid])) {
                    try {
                        $settings = $service->get_recording_settings($recording->meetinguuid);
                        $meetingpasscodes[$recording->meetinguuid] = $settings->password;
                    } catch (moodle_exception $error) {
                        continue;
                    }
                }

                $zoom = $meetings[$recording->meetingid];
                $recordingtype = $recording->recordingtype;
                $recordingtypestring = $recordingtypestrings[$recordingtype];

                $record = new \stdClass();
                $record->zoomid = $zoom->id;
                $record->meetinguuid = $recording->meetinguuid;
                $record->zoomrecordingid = $recordingid;
                $record->name = trim($zoom->name) . ' (' . $recordingtypestring . ')';
                $record->externalurl = $recording->url;
                $record->passcode = $meetingpasscodes[$recording->meetinguuid];
                $record->recordingtype = $recordingtype;
                $record->recordingstart = $recording->recordingstart;
                $record->showrecording = $zoom->recordings_visible_default;
                $record->timecreated = $now;
                $record->timemodified = $now;

                $record->id = $DB->insert_record('zoom_meeting_recordings', $record);
                mtrace('Recording id: ' . $recordingid . ' (' . $recordingtype . ') added to the database');
            }
        }
    }
}
