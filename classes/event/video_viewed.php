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
 * Event for when a user views a YouTube video.
 *
 * @package    mod_zoom_yt
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoom_yt\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Video viewed event.
 */
class video_viewed extends \core\event\base {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['objecttable'] = 'zoom_yt_videos';
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Get event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event_video_viewed', 'zoom_yt');
    }

    /**
     * Get event description.
     *
     * @return string
     */
    public function get_description(): string {
        $videoid = $this->other['youtube_video_id'] ?? 'unknown';
        return "User {$this->userid} viewed YouTube video {$videoid}.";
    }

    /**
     * Get URL related to the event.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/mod/zoom_yt/view.php', ['id' => $this->contextinstanceid]);
    }
}
