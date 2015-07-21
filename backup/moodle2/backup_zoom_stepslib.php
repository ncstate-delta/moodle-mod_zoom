<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Define the complete zoom structure for backup, with file and id annotations
 *
 * @package   mod_zoom
 * @category  backup
 * @copyright 2015 UC Regents
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_zoom_activity_structure_step extends backup_activity_structure_step {

    /**
     * Defines the backup structure of the module.
     *
     * @return backup_nested_element
     */
    protected function define_structure() {
        // Are we including userinfo?
        $userinfo = $this->get_setting_value('userinfo');

        // Define the root element describing the zoom instance.
        $zoom = new backup_nested_element('zoom', array('id'), array(
            'intro', 'introformat', 'grade',
            'uuid', 'meeting_id', 'start_url', 'join_url',
            'created_at', 'host_id', 'name', 'start_time', 'timemodified',
            'type', 'duration', 'timezone', 'password', 'option_jbh',
            'option_start_type', 'option_host_video', 'option_participants_video',
            'option_audio', 'status'));

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
