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
 * This file keeps track of upgrades to the zoom module
 *
 * Sometimes, changes between versions involve alterations to database
 * structures and other major things that may break installations. The upgrade
 * function in this file will attempt to perform all the necessary actions to
 * upgrade your older installation to the current version. If there's something
 * it cannot do itself, it will tell you what you need to do.  The commands in
 * here will all be database-neutral, using the functions defined in DLL libraries.
 *
 * @package    mod_zoom
 * @copyright  2015 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute zoom upgrade from the given old version
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_zoom_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.
    $table = new xmldb_table('zoom');

    if ($oldversion < 2015071000) {
        // Add updated_at.
        $field = new xmldb_field('updated_at', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'created_at');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add ended_at.
        $field = new xmldb_field('ended_at', XMLDB_TYPE_CHAR, '20', null, null, null, null, 'updated_at');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2015071000, 'zoom');
    }

    if ($oldversion < 2015071500) {
        // Rename option_no_video_host to option_host_video; change default to 1; invert values.
        $field = new xmldb_field('option_no_video_host', XMLDB_TYPE_INTEGER, '1', null, null, null,
                '1', 'option_start_type');
        // Invert option_no_video_host.
        $DB->set_field('UPDATE {zoom} SET option_no_video_host = 1 - option_no_video_host');
        $dbman->change_field_default($table, $field);
        $dbman->rename_field($table, $field, 'option_host_video');

        // Rename option_no_video_participants to option_participants_video; change default to 1; invert values.
        $field = new xmldb_field('option_no_video_participants', XMLDB_TYPE_INTEGER, '1', null, null, null,
                '1', 'option_host_video');
        // Invert option_no_video_participants.
        $DB->set_field('UPDATE {zoom} SET option_no_video_participants = 1 - option_no_video_participants');
        $dbman->change_field_default($table, $field);
        $dbman->rename_field($table, $field, 'option_participants_video');

        // Change start_time to int (timestamp).
        $field = new xmldb_field('start_time', XMLDB_TYPE_INTEGER, '12', null, null, null, null, 'name');
        $starttimes = $DB->get_recordset('zoom');
        foreach ($starttimes as $time) {
            $time->start_time = strtotime($time->start_time);
            $DB->update_record('zoom', $time);
        }
        $starttimes->close();
        $dbman->change_field_type($table, $field);

        // Change precision/length of duration to 6 digits.
        $field = new xmldb_field('duration', XMLDB_TYPE_INTEGER, '6', null, null, null, null, 'type');
        $dbman->change_field_precision($table, $field);
        $DB->set_field('UPDATE {zoom} SET duration = duration*60');

        upgrade_mod_savepoint(true, 2015071500, 'zoom');
    }

    if ($oldversion < 2015071600) {
        // Add intro.
        $field = new xmldb_field('intro', XMLDB_TYPE_TEXT, null, null, null, null, null, 'course');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add introformat.
        $field = new xmldb_field('introformat', XMLDB_TYPE_INTEGER, '4', null, null, null, null, 'intro');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2015071600, 'zoom');
    }

    if ($oldversion < 2015072000) {
        // Drop updated_at.
        $field = new xmldb_field('updated_at');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Drop ended_at.
        $field = new xmldb_field('ended_at');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Add timemodified.
        $field = new xmldb_field('timemodified', XMLDB_TYPE_INTEGER, '12', null, null, null, null, 'start_time');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add grade.
        $field = new xmldb_field('grade', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'introformat');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2015072000, 'zoom');
    }

    if ($oldversion < 2016040100) {
        // Add webinar.
        $field = new xmldb_field('webinar', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'type');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Change type to recurring.
        $field = new xmldb_field('type', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'timemodified');
        $dbman->change_field_notnull($table, $field);
        $dbman->change_field_default($table, $field);
        // Meeting is recurring if type is 3.
        $DB->set_field_select('zoom', 'type', 0, 'type <> 3');
        $DB->set_field('zoom', 'type', 1, array('type' => 3));
        $dbman->rename_field($table, $field, 'recurring');

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2016040100, 'zoom');
    }

    if ($oldversion < 2018071900) {
        // Removed apiurl option from settings.
        set_config('apiurl', null, 'mod_zoom');
        upgrade_mod_savepoint(true, 2018071900, 'zoom');
    }

    if ($oldversion < 2018072000) {
        // Start zoom table modifications.
        $table = new xmldb_table('zoom');

        // Define field status to be dropped from zoom.
        $field = new xmldb_field('status');

        // Conditionally launch drop field status.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Define field exists_on_zoom to be added to zoom.
        $field = new xmldb_field('exists_on_zoom', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'option_audio');

        // Conditionally launch add field exists_on_zoom.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field uuid to be dropped from zoom.
        $field = new xmldb_field('uuid');

        // Conditionally launch drop field uuid.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2018072000, 'zoom');
    }

    return true;
}
