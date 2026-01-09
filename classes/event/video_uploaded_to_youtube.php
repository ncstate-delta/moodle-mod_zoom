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
 * Event for when a video is uploaded to YouTube.
 *
 * @package    mod_zoomyt
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoomyt\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Video uploaded to YouTube event.
 */
class video_uploaded_to_youtube extends \core\event\base {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = 'zoomyt_videos';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Get event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event_video_uploaded', 'zoomyt');
    }

    /**
     * Get event description.
     *
     * @return string
     */
    public function get_description(): string {
        $ytid = $this->other['youtube_video_id'] ?? 'unknown';
        $zoomid = $this->other['zoom_recording_id'] ?? 'unknown';
        return "Video from Zoom recording {$zoomid} was uploaded to YouTube as {$ytid}.";
    }

    /**
     * Get URL related to the event.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/mod/zoomyt/view.php', ['id' => $this->contextinstanceid]);
    }
}
