<?php
// This file is part of Moodle - http://moodle.org/
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

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');
use Behat\Behat\Context\Context;

/**
 * Behat steps for mod_zoom.
 *
 * @package mod_zoom
 * @copyright 2020 UC Regents
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_zoom extends behat_base implements Context {
    /**
     * Convert page names to URLs for steps like 'When I am on the "[page name]" page'.
     *
     * Recognised page names are:
     * | None so far!      |                                                              |
     *
     * @param string $page name of the page, with the component name removed e.g. 'Admin notification'.
     * @return moodle_url the corresponding URL.
     * @throws Exception with a meaningful error message if the specified page cannot be found.
     */
    protected function resolve_page_url(string $page): moodle_url {
        switch (strtolower($page)) {
            default:
                throw new Exception("Unrecognized page type '{$page}'.");
        }
    }

    /**
     * Convert page names to URLs for steps like 'When I am on the "[identifier]" "[page type]" page'.
     *
     * Recognised page names are:
     * | pagetype   | name meaning       | description                    |
     * | View       | Zoom meeting name  | The Zoom meeting activity page |
     *
     * @param string $page identifies which type of page this is, e.g. 'mod_zoom > View'.
     * @param string $identifier identifies the particular page, e.g. 'Test Meeting'.
     * @return moodle_url the corresponding URL.
     * @throws Exception with a meaningful error message if the specified page cannot be found.
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        global $DB;

        switch (strtolower($type)) {
            case 'view':
                return new moodle_url('/mod/zoom/view.php', [
                    'id' => $this->get_cm_by_meeting_name($identifier)->id,
                ]);
            case 'edit':
                return new moodle_url('/course/modedit.php', [
                    'update' => $this->get_cm_by_meeting_name($identifier)->id,
                    'return' => 0,
                ]);
            default:
                throw new Exception('Unrecognized zoom page type: ' . $type);
        }
    }

    /**
     * Get a Zoom meeting by name.
     *
     * @param string $name Zoom meeting name.
     * @return stdClass the corresponding DB row.
     */
    protected function get_meeting_by_name(string $name): stdClass {
        global $DB;
        return $DB->get_record('zoom', ['name' => $name]);
    }

    /**
     * Get a Zoom meeting cmid from the meeting name.
     *
     * @param string $name Zoom meeting name.
     * @return stdClass cm from get_coursemodule_from_instance.
     */
    protected function get_cm_by_meeting_name(string $name): stdClass {
        $meeting = $this->get_meeting_by_name($name);
        return get_coursemodule_from_instance('zoom', $meeting->id, $meeting->course);
    }
}
