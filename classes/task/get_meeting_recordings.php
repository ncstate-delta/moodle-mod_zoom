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

        $config = get_config('zoom');
        if (empty($config->apikey)) {
            mtrace('Skipping task - ', get_string('zoomerr_apikey_missing', 'zoom'));
            return;
        } else if (empty($config->apisecret)) {
            mtrace('Skipping task - ', get_string('zoomerr_apisecret_missing', 'zoom'));
            return;
        }

        // See if we cannot make anymore API calls.
        $retryafter = get_config('zoom', 'retry-after');
        if (!empty($retryafter) && time() < $retryafter) {
            mtrace('Out of API calls, retry after ' . userdate($retryafter,
                    get_string('strftimedaydatetime', 'core_langconfig')));
            return;
        }

        $service = new \mod_zoom_webservice();

        mtrace('Finding meeting recordings for this account...');

        $zoommeetings = zoom_get_all_meeting_records();
        foreach ($zoommeetings as $zoom) {
            // Only get recordings for this meeting if its recurring or already finished.
            $now = time();
            if ($zoom->recurring || $now > (intval($zoom->start_time) + intval($zoom->duration))) {
                // Get all existing recordings for this meeting.
                $recordings = zoom_get_meeting_recordings($zoom->id);
                // Fetch all recordings for this meeting.
                $zoomrecordingpairlist = $service->get_recording_url_list($zoom->meeting_id);
                if (!empty($zoomrecordingpairlist)) {
                    foreach ($zoomrecordingpairlist as $recordingstarttime => $zoomrecordingpair) {
                        // The video recording and audio only recordings are grouped together by their recording start timestamp.
                        foreach ($zoomrecordingpair as $zoomrecordinginfo) {
                            if (isset($recordings[trim($zoomrecordinginfo->recordingid)])) {
                                mtrace('Recording id: ' . $zoomrecordinginfo->recordingid . ' exist(s)...skipping');
                                continue;
                            }
                            $rec = new \stdClass();
                            $rec->zoomid = $zoom->id;
                            $rec->meetinguuid = trim($zoomrecordinginfo->meetinguuid);
                            $rec->zoomrecordingid = trim($zoomrecordinginfo->recordingid);
                            $rec->name = trim($zoom->name) . ' (' . trim($zoomrecordinginfo->recordingtype) . ')';
                            $rec->externalurl = $zoomrecordinginfo->url;
                            $rec->passcode = trim($zoomrecordinginfo->passcode);
                            $rec->recordingtype = trim($zoomrecordinginfo->recordingtype);
                            $rec->recordingstart = $recordingstarttime;
                            $rec->showrecording = $zoom->recordings_visible_default;
                            $rec->timecreated = $now;
                            $rec->timemodified = $now;
                            $rec->id = $DB->insert_record('zoom_meeting_recordings', $rec);
                            mtrace('Recording id: ' . $zoomrecordinginfo->recordingid . ' (' . $zoomrecordinginfo->recordingtype .
                                   ') added to the database');
                        }
                    }
                }
            }
        }
    }
}
