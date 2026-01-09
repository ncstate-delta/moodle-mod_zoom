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
 * Video gallery renderable for Zoom YT.
 *
 * @package    mod_zoomyt
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoomyt\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use templatable;
use renderer_base;
use stdClass;

/**
 * Video gallery renderable class.
 */
class video_gallery implements renderable, templatable {

    /** @var int The zoom activity ID */
    protected $zoomid;

    /** @var int The course module ID */
    protected $cmid;

    /** @var bool Whether the user can manage videos */
    protected $canmanage;

    /** @var array The videos to display */
    protected $videos;

    /** @var string View mode: tile or list */
    protected $viewmode;

    /**
     * Constructor.
     *
     * @param int $zoomid The zoom activity ID.
     * @param int $cmid The course module ID.
     * @param bool $canmanage Whether user can manage videos.
     * @param string $viewmode View mode: tile or list.
     */
    public function __construct(int $zoomid, int $cmid, bool $canmanage = false, string $viewmode = 'tile') {
        $this->zoomid = $zoomid;
        $this->cmid = $cmid;
        $this->canmanage = $canmanage;
        $this->viewmode = $viewmode;
        $this->videos = $this->load_videos();
    }

    /**
     * Load videos for this activity.
     *
     * @return array Array of video objects.
     */
    protected function load_videos(): array {
        global $DB;

        $conditions = ['zoomid' => $this->zoomid, 'status' => 'uploaded'];
        if (!$this->canmanage) {
            $conditions['visible'] = 1;
        }

        $videos = $DB->get_records('zoomyt_videos', $conditions, 'zoom_session_time DESC');

        $result = [];
        foreach ($videos as $video) {
            $item = new stdClass();
            $item->id = $video->id;
            $item->title = $video->title;
            $item->description = $video->description;
            $item->youtube_video_id = $video->youtube_video_id;
            $item->youtube_url = $video->youtube_url;
            $item->thumbnail_url = $video->thumbnail_url ?: $this->get_default_thumbnail($video->youtube_video_id);
            $item->duration = $this->format_duration($video->duration);
            $item->session_date = userdate($video->zoom_session_time, get_string('strftimedatetime'));
            $item->visible = (bool)$video->visible;
            $item->status = $video->status;
            $item->embed_url = 'https://www.youtube.com/embed/' . $video->youtube_video_id;

            $result[] = $item;
        }

        return $result;
    }

    /**
     * Get default YouTube thumbnail URL.
     *
     * @param string $videoid YouTube video ID.
     * @return string Thumbnail URL.
     */
    protected function get_default_thumbnail(string $videoid): string {
        return 'https://img.youtube.com/vi/' . $videoid . '/mqdefault.jpg';
    }

    /**
     * Format duration in seconds to readable string.
     *
     * @param int $seconds Duration in seconds.
     * @return string Formatted duration.
     */
    protected function format_duration(int $seconds): string {
        if ($seconds < 60) {
            return $seconds . 's';
        }

        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%d:%02d', $minutes, $secs);
    }

    /**
     * Export for template.
     *
     * @param renderer_base $output The renderer.
     * @return stdClass Data for template.
     */
    public function export_for_template(renderer_base $output): stdClass {
        $data = new stdClass();
        $data->videos = $this->videos;
        $data->hasvideos = !empty($this->videos);
        $data->canmanage = $this->canmanage;
        $data->cmid = $this->cmid;
        $data->zoomid = $this->zoomid;
        $data->istileview = ($this->viewmode === 'tile');
        $data->islistview = ($this->viewmode === 'list');
        $data->viewmode = $this->viewmode;
        $data->videocount = count($this->videos);
        $data->showastile = (count($this->videos) > 1 && $this->viewmode === 'tile');

        return $data;
    }

    /**
     * Get videos for teacher management view.
     *
     * @param int $zoomid The zoom activity ID.
     * @return array All videos including pending/failed.
     */
    public static function get_all_videos_for_management(int $zoomid): array {
        global $DB;

        $sql = "SELECT zyv.*, zmr.recordingtype, zmr.externalurl as zoom_url
                FROM {zoomyt_videos} zyv
                LEFT JOIN {zoomyt_meeting_recordings} zmr ON zmr.id = zyv.recordingid
                WHERE zyv.zoomid = ?
                ORDER BY zyv.zoom_session_time DESC";

        $videos = $DB->get_records_sql($sql, [$zoomid]);

        $result = [];
        foreach ($videos as $video) {
            $item = new stdClass();
            $item->id = $video->id;
            $item->title = $video->title;
            $item->youtube_video_id = $video->youtube_video_id;
            $item->youtube_url = $video->youtube_url;
            $item->thumbnail_url = $video->thumbnail_url;
            $item->status = $video->status;
            $item->status_label = get_string('video_status_' . $video->status, 'zoomyt');
            $item->error_message = $video->error_message;
            $item->visible = (bool)$video->visible;
            $item->session_date = userdate($video->zoom_session_time, get_string('strftimedatetime'));
            $item->zoom_recording_deleted = (bool)$video->zoom_recording_deleted;
            $item->zoom_url = $video->zoom_url ?? null;
            $item->has_youtube = !empty($video->youtube_video_id);
            $item->has_zoom = !empty($video->zoom_url) && !$video->zoom_recording_deleted;

            $result[] = $item;
        }

        return $result;
    }

    /**
     * Toggle video visibility.
     *
     * @param int $videoid The video ID.
     * @param bool $visible New visibility state.
     * @return bool Success.
     */
    public static function toggle_visibility(int $videoid, bool $visible): bool {
        global $DB;

        return $DB->set_field('zoomyt_videos', 'visible', $visible ? 1 : 0, ['id' => $videoid]);
    }

    /**
     * Record a video view.
     *
     * @param int $videoid The video ID.
     * @param int $userid The user ID.
     * @param \context $context The context for event logging.
     * @return bool Success.
     */
    public static function record_view(int $videoid, int $userid, \context $context): bool {
        global $DB;

        $now = time();

        // Check for existing view record.
        $existing = $DB->get_record('zoomyt_video_views', [
            'videoid' => $videoid,
            'userid' => $userid,
        ]);

        if ($existing) {
            $existing->viewcount++;
            $existing->lastviewed = $now;
            $DB->update_record('zoomyt_video_views', $existing);
        } else {
            $view = new stdClass();
            $view->videoid = $videoid;
            $view->userid = $userid;
            $view->viewcount = 1;
            $view->firstviewed = $now;
            $view->lastviewed = $now;
            $DB->insert_record('zoomyt_video_views', $view);
        }

        // Get video for event data.
        $video = $DB->get_record('zoomyt_videos', ['id' => $videoid]);

        // Log the event.
        $event = \mod_zoomyt\event\video_viewed::create([
            'context' => $context,
            'objectid' => $videoid,
            'other' => [
                'youtube_video_id' => $video->youtube_video_id ?? '',
            ],
        ]);
        $event->trigger();

        return true;
    }
}
