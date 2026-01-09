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
 * Scheduled task to sync Zoom cloud recordings to YouTube.
 *
 * @package    mod_zoomyt
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoomyt\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/zoomyt/locallib.php');

/**
 * Sync Zoom recordings to YouTube task.
 */
class sync_recordings_to_youtube extends \core\task\scheduled_task {

    /** @var int Maximum videos to process per run */
    const MAX_VIDEOS_PER_RUN = 5;

    /**
     * Get task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_sync_recordings_youtube', 'zoomyt');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/zoomyt/classes/youtube_service.php');
        require_once($CFG->dirroot . '/mod/zoomyt/classes/category_settings.php');

        mtrace('Starting Zoom to YouTube sync task...');

        // Get temp directory and check space.
        $tempdir = $this->get_temp_directory();
        if (!$tempdir) {
            mtrace('ERROR: Temporary directory not available or not writable.');
            return;
        }

        $maxspace = get_config('zoomyt', 'temp_storage_limit') ?: 5368709120; // 5GB default.
        $availablespace = disk_free_space($tempdir);

        if ($availablespace < 1073741824) { // Less than 1GB.
            mtrace('WARNING: Less than 1GB available in temp directory. Skipping sync.');
            return;
        }

        // Find recordings that need to be synced.
        $recordings = $this->get_pending_recordings();
        mtrace('Found ' . count($recordings) . ' recordings to process.');

        $processed = 0;
        foreach ($recordings as $recording) {
            if ($processed >= self::MAX_VIDEOS_PER_RUN) {
                mtrace('Reached maximum videos per run limit.');
                break;
            }

            try {
                $this->process_recording($recording, $tempdir);
                $processed++;
            } catch (\Exception $e) {
                mtrace('ERROR processing recording ' . $recording->id . ': ' . $e->getMessage());
                $this->mark_recording_failed($recording, $e->getMessage());
            }
        }

        mtrace('Processed ' . $processed . ' recordings.');

        // Clean up old Zoom recordings.
        $this->cleanup_old_zoom_recordings();

        mtrace('Zoom to YouTube sync task completed.');
    }

    /**
     * Get the temporary directory for downloads.
     *
     * @return string|null Path to temp directory or null if not available.
     */
    protected function get_temp_directory(): ?string {
        global $CFG;

        $tempdir = get_config('zoomyt', 'temp_directory');
        if (empty($tempdir)) {
            $tempdir = $CFG->tempdir . '/zoomyt_videos';
        }

        if (!is_dir($tempdir)) {
            if (!mkdir($tempdir, 0755, true)) {
                return null;
            }
        }

        if (!is_writable($tempdir)) {
            return null;
        }

        return $tempdir;
    }

    /**
     * Get recordings that need to be synced to YouTube.
     *
     * @return array Array of recording objects.
     */
    protected function get_pending_recordings(): array {
        global $DB;

        // Find Zoom recordings that haven't been uploaded to YouTube yet.
        $sql = "SELECT zmr.*, z.id as zoomid, z.course, z.name as session_name,
                       zmd.start_time as session_time
                FROM {zoomyt_meeting_recordings} zmr
                JOIN {zoomyt} z ON z.id = zmr.zoomid
                JOIN {zoomyt_meeting_details} zmd ON zmd.meetinguuid = zmr.meetinguuid
                LEFT JOIN {zoomyt_videos} zyv ON zyv.recordingid = zmr.id
                WHERE zyv.id IS NULL
                  AND zmr.recordingtype IN ('active_speaker', 'shared_screen_with_speaker_view', 
                                             'shared_screen_with_gallery_view', 'gallery_view')
                  AND zmr.showrecording = 1
                ORDER BY zmr.recordingstart ASC";

        $allrecordings = $DB->get_records_sql($sql, [], 0, 50);

        // Group by meeting UUID and prioritize recording types.
        $bymeeting = [];
        foreach ($allrecordings as $rec) {
            $key = $rec->zoomid . '_' . $rec->meetinguuid;
            if (!isset($bymeeting[$key])) {
                $bymeeting[$key] = [];
            }
            $bymeeting[$key][] = $rec;
        }

        // Select best recording for each meeting.
        $selected = [];
        $priority = ['active_speaker', 'shared_screen_with_speaker_view', 'shared_screen_with_gallery_view', 'gallery_view'];

        foreach ($bymeeting as $recordings) {
            $best = null;
            $bestpriority = 999;

            foreach ($recordings as $rec) {
                $idx = array_search($rec->recordingtype, $priority);
                if ($idx !== false && $idx < $bestpriority) {
                    $best = $rec;
                    $bestpriority = $idx;
                }
            }

            if ($best) {
                $selected[] = $best;
            }
        }

        return $selected;
    }

    /**
     * Process a single recording.
     *
     * @param object $recording The recording to process.
     * @param string $tempdir Temporary directory for downloads.
     */
    protected function process_recording(object $recording, string $tempdir): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/zoomyt/classes/youtube_service.php');
        require_once($CFG->dirroot . '/mod/zoomyt/classes/category_settings.php');

        mtrace('Processing recording: ' . $recording->name . ' (ID: ' . $recording->id . ')');

        // Get course category.
        $course = $DB->get_record('course', ['id' => $recording->course], 'id, category', MUST_EXIST);

        // Get YouTube service for this course.
        $ytservice = \mod_zoomyt\youtube_service::get_instance_for_course($recording->course);
        if (!$ytservice || !$ytservice->is_configured()) {
            mtrace('  YouTube not configured for course ' . $recording->course . ', skipping.');
            return;
        }

        // Create pending video record.
        $video = new \stdClass();
        $video->zoomid = $recording->zoomid;
        $video->recordingid = $recording->id;
        $video->meetinguuid = $recording->meetinguuid;
        $video->zoom_recording_id = $recording->zoomrecordingid;
        $video->title = $recording->session_name;
        $video->description = 'Recorded session from ' . userdate($recording->session_time);
        $video->zoom_session_time = $recording->session_time;
        $video->status = 'downloading';
        $video->timecreated = time();
        $video->timemodified = time();

        // Get visibility setting.
        $catsettings = new \mod_zoomyt\category_settings($course->category);
        $settings = $catsettings->get_effective_settings();
        $video->visibility = $settings->yt_default_visibility ?? get_config('zoomyt', 'youtube_default_visibility') ?? 'unlisted';

        $videoid = $DB->insert_record('zoomyt_videos', $video);
        $video->id = $videoid;

        // Download the recording.
        mtrace('  Downloading from Zoom...');
        $localpath = $tempdir . '/zoom_recording_' . $recording->id . '.mp4';

        try {
            $this->download_zoom_recording($recording, $localpath);
        } catch (\Exception $e) {
            $video->status = 'failed';
            $video->error_message = 'Download failed: ' . $e->getMessage();
            $video->timemodified = time();
            $DB->update_record('zoomyt_videos', $video);
            throw $e;
        }

        // Update status to uploading.
        $video->status = 'uploading';
        $video->timemodified = time();
        $DB->update_record('zoomyt_videos', $video);

        // Upload to YouTube.
        mtrace('  Uploading to YouTube...');
        try {
            $result = $ytservice->upload_video(
                $localpath,
                $video->title,
                $video->description,
                $video->visibility
            );

            $video->youtube_video_id = $result->id;
            $video->youtube_url = $result->url;
            $video->thumbnail_url = $result->thumbnail_url;
            $video->duration = $result->duration;
            $video->status = 'uploaded';
            $video->timemodified = time();
            $DB->update_record('zoomyt_videos', $video);

            mtrace('  Uploaded successfully: ' . $result->url);

            // Log the event.
            $event = \mod_zoomyt\event\video_uploaded_to_youtube::create([
                'context' => \context_course::instance($recording->course),
                'objectid' => $video->id,
                'other' => [
                    'youtube_video_id' => $result->id,
                    'zoom_recording_id' => $recording->id,
                ],
            ]);
            $event->trigger();

        } catch (\Exception $e) {
            $video->status = 'failed';
            $video->error_message = 'Upload failed: ' . $e->getMessage();
            $video->timemodified = time();
            $DB->update_record('zoomyt_videos', $video);
            throw $e;
        } finally {
            // Delete local file.
            if (file_exists($localpath)) {
                unlink($localpath);
                mtrace('  Deleted local file.');
            }
        }
    }

    /**
     * Download a Zoom recording to local file.
     *
     * @param object $recording The recording info.
     * @param string $localpath Local path to save to.
     */
    protected function download_zoom_recording(object $recording, string $localpath): void {
        global $CFG;

        require_once($CFG->dirroot . '/mod/zoomyt/classes/webservice.php');

        // Check available space.
        $tempdir = dirname($localpath);
        $maxspace = get_config('zoomyt', 'temp_storage_limit') ?: 5368709120;
        $availablespace = disk_free_space($tempdir);

        if ($availablespace < $maxspace * 0.1) {
            throw new \moodle_exception('insufficient_disk_space', 'zoomyt');
        }

        // Get download URL from Zoom.
        $downloadurl = $recording->externalurl;

        // Add access token if needed.
        if (strpos($downloadurl, 'access_token') === false) {
            // TODO: May need to authenticate with Zoom to get download access.
        }

        // Download the file.
        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_FOLLOWLOCATION' => true,
            'CURLOPT_MAXREDIRS' => 5,
        ]);

        $fp = fopen($localpath, 'w');
        if (!$fp) {
            throw new \moodle_exception('cannot_create_file', 'zoomyt', '', $localpath);
        }

        $curl->setopt(['CURLOPT_FILE' => $fp]);
        $curl->get($downloadurl);
        fclose($fp);

        if ($curl->get_errno()) {
            unlink($localpath);
            throw new \moodle_exception('download_failed', 'zoomyt', '', $curl->error);
        }

        $info = $curl->get_info();
        if ($info['http_code'] !== 200) {
            unlink($localpath);
            throw new \moodle_exception('download_failed', 'zoomyt', '', 'HTTP ' . $info['http_code']);
        }

        mtrace('  Downloaded ' . round(filesize($localpath) / 1048576, 2) . ' MB');
    }

    /**
     * Mark a recording as failed.
     *
     * @param object $recording The recording.
     * @param string $message Error message.
     */
    protected function mark_recording_failed(object $recording, string $message): void {
        global $DB;

        // Check if there's already a video record.
        $video = $DB->get_record('zoomyt_videos', ['recordingid' => $recording->id]);

        if ($video) {
            $video->status = 'failed';
            $video->error_message = $message;
            $video->timemodified = time();
            $DB->update_record('zoomyt_videos', $video);
        }
    }

    /**
     * Clean up old Zoom cloud recordings.
     */
    protected function cleanup_old_zoom_recordings(): void {
        global $DB;

        mtrace('Checking for old Zoom recordings to delete...');

        // Get videos that have been uploaded and are past the retention period.
        $sql = "SELECT zyv.*, zcs.zoom_recording_delete_days, z.course
                FROM {zoomyt_videos} zyv
                JOIN {zoomyt} z ON z.id = zyv.zoomid
                JOIN {course} c ON c.id = z.course
                LEFT JOIN {zoomyt_category_settings} zcs ON zcs.categoryid = c.category AND zcs.inherit = 0
                WHERE zyv.status = 'uploaded'
                  AND zyv.zoom_recording_deleted = 0
                  AND zyv.youtube_video_id IS NOT NULL";

        $videos = $DB->get_records_sql($sql);
        $deleted = 0;

        foreach ($videos as $video) {
            // Get effective delete days setting.
            $deletedays = $video->zoom_recording_delete_days;
            if ($deletedays === null) {
                $deletedays = get_config('zoomyt', 'zoom_recording_delete_days');
            }

            if (empty($deletedays)) {
                continue; // Don't delete.
            }

            $deletethreshold = time() - ($deletedays * 86400);

            if ($video->timecreated < $deletethreshold) {
                try {
                    // TODO: Call Zoom API to delete the recording.
                    // For now, just mark it as deleted.
                    $video->zoom_recording_deleted = 1;
                    $video->timemodified = time();
                    $DB->update_record('zoomyt_videos', $video);
                    $deleted++;
                    mtrace('  Marked recording as deleted for video ID: ' . $video->id);
                } catch (\Exception $e) {
                    mtrace('  ERROR deleting recording for video ID ' . $video->id . ': ' . $e->getMessage());
                }
            }
        }

        mtrace('Marked ' . $deleted . ' Zoom recordings for deletion.');
    }
}
