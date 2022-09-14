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
 * The task for deleting recordings in Moodle if removed from Zoom.
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
 * Scheduled task to delete meeting recordings from Moodle.
 */
class delete_meeting_recordings extends \core\task\scheduled_task {
    /**
     * Returns name of task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('deletemeetingrecordings', 'mod_zoom');
    }

    /**
     * Delete any recordings that have been removed from zoom.
     *
     * @return void
     */
    public function execute() {
        global $DB;

        $config = get_config('zoom');
        $useoauth = true;

        if (empty($config->clientid) || empty($config->clientsecret) || empty($config->accountid)) {
            $useoauth = false;
        }

        if ($useoauth) {
            if (empty($config->clientid)) {
                mtrace('Skipping task - ', get_string('zoomerr_clientid_missing', 'zoom'));
                return;
            } else if (empty($config->clientsecret)) {
                mtrace('Skipping task - ', get_string('zoomerr_clientsecret_missing', 'zoom'));
                return;
            } else if (empty($config->accountid)) {
                mtrace('Skipping task - ', get_string('zoomerr_accountid_missing', 'zoom'));
                return;
            }
        } else {
            if (empty($config->apikey)) {
                mtrace('Skipping task - ', get_string('zoomerr_apikey_missing', 'zoom'));
                return;
            } else if (empty($config->apisecret)) {
                mtrace('Skipping task - ', get_string('zoomerr_apisecret_missing', 'zoom'));
                return;
            }
        }

        // See if we cannot make anymore API calls.
        $retryafter = get_config('zoom', 'retry-after');
        if (!empty($retryafter) && time() < $retryafter) {
            mtrace('Out of API calls, retry after ' . userdate($retryafter,
                    get_string('strftimedaydatetime', 'core_langconfig')));
            return;
        }

        $service = new \mod_zoom_webservice();

        mtrace('Checking if any meeting recordings in Moodle have been removed from Zoom...');

        // Get all recordings stored in Moodle.
        $zoomrecordings = zoom_get_meeting_recordings();
        // Fetch all recordings for the unique meetinguuid.
        $meetinguuidsfetched = [];
        if (!empty($zoomrecordings)) {
            foreach ($zoomrecordings as $zoomrecordingid => $recording) {
                $meetinguuid = trim($recording->meetinguuid);
                if (!isset($meetinguuidsfetched[$meetinguuid])) {
                    $meetinguuidsfetched[$meetinguuid] = true;
                    $recordinglist = $service->get_recording_url_list($meetinguuid);
                    if (!empty($recordinglist)) {
                        foreach ($recordinglist as $recordingstarttime => $recordingpair) {
                            // The video recording and audio only recordings are grouped together by the recording start timestamp.
                            foreach ($recordingpair as $recordinginfo) {
                                if (isset($zoomrecordings[trim($recordinginfo->recordingid)])) {
                                    mtrace('Recording id: ' . $recordinginfo->recordingid . ' exist(s)...skipping');
                                    unset($zoomrecordings[trim($recordinginfo->recordingid)]);
                                    continue;
                                }
                            }
                        }
                    }
                }
            }
        }

        // Now check if any recordings have been removed on zoom.
        // We need to remove them from Moodle as well.
        if (!empty($zoomrecordings)) {
            foreach ($zoomrecordings as $zoomrecordingid => $recording) {
                mtrace('Deleting recording with id: ' . $zoomrecordingid .
                       ' as corresponding record on zoom has been removed.');
                $DB->delete_records('zoom_meeting_recordings', ['zoomrecordingid' => $zoomrecordingid]);
            }
        }
    }
}
