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
 * Event for when YouTube is connected to a category.
 *
 * @package    mod_zoom_yt
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoom_yt\event;

defined('MOODLE_INTERNAL') || die();

/**
 * YouTube connected event.
 */
class youtube_connected extends \core\event\base {

    /**
     * Init method.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Get event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event_youtube_connected', 'zoom_yt');
    }

    /**
     * Get event description.
     *
     * @return string
     */
    public function get_description(): string {
        $channelname = $this->other['channel_name'] ?? 'unknown';
        return "YouTube channel '{$channelname}' was connected to category {$this->contextinstanceid}.";
    }
}
