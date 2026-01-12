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

declare(strict_types=1);

namespace mod_zoomyt\completion;

use core_completion\activity_custom_completion;

// Include lib.php for zoomyt_get_user_total_attendance function.
global $CFG;
require_once($CFG->dirroot . '/mod/zoomyt/lib.php');

/**
 * Activity custom completion subclass for the Zoom YT activity.
 *
 * Contains the class for defining mod_zoomyt's custom completion rules
 * and fetching the completion statuses of the custom completion rules.
 *
 * @package    mod_zoomyt
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends activity_custom_completion {

    /**
     * Fetches the completion state for a given completion rule.
     *
     * @param string $rule The completion rule.
     * @return int The completion state.
     */
    public function get_state(string $rule): int {
        global $DB;

        $this->validate_rule($rule);

        $zoom = $DB->get_record('zoomyt', ['id' => $this->cm->instance], '*', MUST_EXIST);

        if ($rule === 'completionattendance') {
            $totalduration = zoomyt_get_user_total_attendance($zoom->id, $this->userid);
            $requiredseconds = $zoom->completionattendance * 60;

            return ($totalduration >= $requiredseconds) ? COMPLETION_COMPLETE : COMPLETION_INCOMPLETE;
        }

        return COMPLETION_INCOMPLETE;
    }

    /**
     * Fetch the list of custom completion rules that this module defines.
     *
     * @return array
     */
    public static function get_defined_custom_rules(): array {
        return ['completionattendance'];
    }

    /**
     * Returns an associative array of the descriptions of custom completion rules.
     *
     * @return array
     */
    public function get_custom_rule_descriptions(): array {
        global $DB;

        $zoom = $DB->get_record('zoomyt', ['id' => $this->cm->instance]);

        $descriptions = [];

        if (!empty($zoom->completionattendance)) {
            $descriptions['completionattendance'] = get_string(
                'completionattendance_desc',
                'zoomyt',
                $zoom->completionattendance
            );
        }

        return $descriptions;
    }

    /**
     * Returns an array of all completion rules, in the order they should be displayed to users.
     *
     * @return array
     */
    public function get_sort_order(): array {
        return [
            'completionview',
            'completionattendance',
        ];
    }
}
