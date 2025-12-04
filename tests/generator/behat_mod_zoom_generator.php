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

/**
 * Behat data generator for mod_zoom
 *
 * @package mod_zoom
 * @copyright 2025 Alan McCoy
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_zoom_generator extends behat_generator_base {
    /**
     * Get a list of entities that can be created.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'meetings' => [
                'singular' => 'meeting',
                'datagenerator' => 'meeting',
                'required' => ['name'],
            ],
        ];
    }


    /**
     * Look up the id of a Zoom meeting from its name.
     *
     * @param string $zoomname The Zoom activity name, for example 'Test meeting'.
     * @return int corresponding id.
     * @throws Exception
     */
    protected function get_zoom_id(string $zoomname): int {
        global $DB;

        if (!$id = $DB->get_field('zoom', 'id', ['name' => $zoomname])) {
            throw new Exception('There is no Zoom activity with name "' . $zoomname . '" does not exist');
        }
        return $id;
    }

    /**
     * Get the activity id from its name
     *
     * @param string $activityname
     * @return int
     * @throws Exception
     */
    protected function get_activity_id(string $activityname): int {
        global $DB;

        $sql = <<<EOF
            SELECT cm.instance
              FROM {course_modules} cm
        INNER JOIN {modules} m ON m.id = cm.module
        INNER JOIN {zoom} z ON z.id = cm.instance
             WHERE cm.idnumber = :idnumber OR z.name = :name
EOF;
        $id = $DB->get_field_sql($sql, ['idnumber' => $activityname, 'name' => $activityname]);
        if (empty($id)) {
            throw new Exception("There is no Zoom meeting with name '{$activityname}' does not exist");
        }

        return $id;
    }
}
