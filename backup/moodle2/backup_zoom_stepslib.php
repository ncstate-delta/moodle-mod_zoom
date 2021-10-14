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
 * Defines backup_zoom_activity_structure_step class.
 *
 * @package   mod_zoom
 * @category  backup
 * @copyright 2015 UC Regents
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Define the complete zoom structure for backup, with file and id annotations.
 */
class backup_zoom_activity_structure_step extends backup_activity_structure_step {

    /**
     * Defines the backup structure of the module.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        // Define the root element describing the zoom instance.
        $zoom = new backup_nested_element('zoom', array('id'), array(
            'intro', 'introformat', 'grade', 'meeting_id', 'start_url', 'join_url', 'created_at', 'host_id', 'name',
            'start_time', 'timemodified', 'recurring', 'recurrence_type', 'repeat_interval', 'weekly_days', 'monthly_day',
            'monthly_week', 'monthly_week_day', 'monthly_repeat_option', 'end_times', 'end_date_time', 'end_date_option',
            'webinar', 'duration', 'timezone', 'password', 'option_jbh', 'option_start_type', 'option_host_video',
            'option_participants_video', 'option_audio', 'option_mute_upon_entry', 'option_waiting_room',
            'option_authenticated_users', 'option_encryption_type', 'exists_on_zoom', 'alternative_hosts',
        ));

        // If we had more elements, we would build the tree here.

        // Define data sources.
        $zoom->set_source_table('zoom', array('id' => backup::VAR_ACTIVITYID));

        // If we were referring to other tables, we would annotate the relation
        // with the element's annotate_ids() method.

        // Define file annotations.
        // Intro does not need itemid.
        $zoom->annotate_files('mod_zoom', 'intro', null);

        // Return the root element (zoom), wrapped into standard activity structure.
        return $this->prepare_activity_structure($zoom);
    }
}
