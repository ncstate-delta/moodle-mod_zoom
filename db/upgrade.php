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

    if ($oldversion < 2018091200) {
        // Removed apiurl option from settings.
        set_config('apiurl', null, 'mod_zoom');

        // Set the starting number of API calls.
        set_config('calls_left', 2000, 'mod_zoom');

        // Set the time at which to start looking for meeting reports.
        set_config('last_call_made_at', time() - (60 * 60 * 12), 'mod_zoom');

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

        // Define table zoom_meeting_details to be created.
        $table = new xmldb_table('zoom_meeting_details');

        // Adding fields to table zoom_meeting_details.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('uuid', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null);
        $table->add_field('meeting_id', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null);
        $table->add_field('end_time', XMLDB_TYPE_INTEGER, '12', null, XMLDB_NOTNULL, null, null);
        $table->add_field('duration', XMLDB_TYPE_INTEGER, '12', null, XMLDB_NOTNULL, null, null);
        $table->add_field('start_time', XMLDB_TYPE_INTEGER, '12', null, null, null, null);
        $table->add_field('topic', XMLDB_TYPE_CHAR, '300', null, XMLDB_NOTNULL, null, null);
        $table->add_field('total_minutes', XMLDB_TYPE_INTEGER, '12', null, null, null, '0');
        $table->add_field('participants_count', XMLDB_TYPE_INTEGER, '4', null, null, null, '0');
        $table->add_field('zoomid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table zoom_meeting_details.
        $table->add_key('uuid_unique', XMLDB_KEY_UNIQUE, array('uuid'));
        $table->add_key('id_primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('zoomid_foreign', XMLDB_KEY_FOREIGN, array('zoomid'), 'zoom', array('id'));
        $table->add_key('meeting_unique', XMLDB_KEY_UNIQUE, array('meeting_id', 'uuid'));

        // Conditionally launch create table for zoom_meeting_details.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table zoom_meeting_participants to be created.
        $table = new xmldb_table('zoom_meeting_participants');

        // Adding fields to table zoom_meeting_participants.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('zoomuserid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null);
        $table->add_field('uuid', XMLDB_TYPE_CHAR, '30', null, null, null, null);
        $table->add_field('user_email', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('join_time', XMLDB_TYPE_INTEGER, '12', null, XMLDB_NOTNULL, null, null);
        $table->add_field('leave_time', XMLDB_TYPE_INTEGER, '12', null, XMLDB_NOTNULL, null, null);
        $table->add_field('duration', XMLDB_TYPE_INTEGER, '12', null, XMLDB_NOTNULL, null, null);
        $table->add_field('attentiveness_score', XMLDB_TYPE_CHAR, '7', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('detailsid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'name');

        // Adding keys to table zoom_meeting_participants.
        $table->add_key('id_primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('user_by_meeting_key', XMLDB_KEY_UNIQUE, array('detailsid', 'zoomuserid'));
        $table->add_key('detailsid_foreign', XMLDB_KEY_FOREIGN, array('detailsid'), 'zoom_meeting_details', array('id'));

        // Adding indexes to table zoom_meeting_participants.
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, array('userid'));

        // Conditionally launch create table for zoom_meeting_participants.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2018091200, 'zoom');
    }

    if ($oldversion < 2018091400) {
        // Define field alternative_hosts to be added to zoom.
        $table = new xmldb_table('zoom');
        $field = new xmldb_field('alternative_hosts', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'exists_on_zoom');

        // Conditionally launch add field alternative_hosts.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2018091400, 'zoom');
    }

    if ($oldversion < 2018092201) {

        // Changing type of field userid on table zoom_meeting_participants to int.
        $table = new xmldb_table('zoom_meeting_participants');

        $index = new xmldb_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);

        // Conditionally launch drop index userid.
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }

        $field = new xmldb_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'id');

        // Launch change of type for field userid.
        $dbman->change_field_type($table, $field);

        $index = new xmldb_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);

        // Conditionally launch add index userid.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2018092201, 'zoom');
    }

    if ($oldversion < 2019061800) {
        // Make sure start_time is not null to match install.xml.
        $table = new xmldb_table('zoom_meeting_details');
        $field = new xmldb_field('start_time', XMLDB_TYPE_INTEGER, '12', null, XMLDB_NOTNULL, null, null);
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_notnull($table, $field);
        }
        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2019061800, 'zoom');
    }

    if ($oldversion < 2019091200) {
        // Change field alternative_hosts from type char(255) to text.
        $table = new xmldb_table('zoom');
        $field = new xmldb_field('alternative_hosts', XMLDB_TYPE_TEXT, null, null, null, null, null, 'exists_on_zoom');
        $dbman->change_field_type($table, $field);

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2019091200, 'zoom');
    }

    if ($oldversion < 2020042600) {
        // Change field zoom_meeting_participants from type int(11) to char(35),
        // because sometimes zoomuserid is concatenated with a timestamp.
        // See https://devforum.zoom.us/t/meeting-participant-user-id-value/7886/2.
        $table = new xmldb_table('zoom_meeting_participants');

        // First drop key, not needed anymore.
        $key = new xmldb_key('user_by_meeting_key', XMLDB_KEY_UNIQUE,
                ['detailsid', 'zoomuserid']);
        $dbman->drop_key($table, $key);

        // Change of type for field zoomuserid to char(35).
        $field = new xmldb_field('zoomuserid', XMLDB_TYPE_CHAR,
                '35', null, XMLDB_NOTNULL,
                null, null, 'userid');
        $dbman->change_field_type($table, $field);

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2020042600, 'zoom');
    }

    if ($oldversion < 2020042700) {
        // Define field attentiveness_score to be dropped from zoom_meeting_participants.
        $table = new xmldb_table('zoom_meeting_participants');
        $field = new xmldb_field('attentiveness_score');

        // Conditionally launch drop field attentiveness_score.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2020042700, 'zoom');
    }

    if ($oldversion < 2020051800) {
        // Define field option_mute_upon_entry to be added to zoom.
        $table = new xmldb_table('zoom');
        $field = new xmldb_field('option_mute_upon_entry', XMLDB_TYPE_INTEGER, '1', null, null, null, '1', 'option_audio');

        // Conditionally launch add field option_mute_upon_entry.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field option_waiting_room to be added to zoom.
        $table = new xmldb_table('zoom');
        $field = new xmldb_field('option_waiting_room', XMLDB_TYPE_INTEGER, '1', null, null, null, '1', 'option_mute_upon_entry');

        // Conditionally launch add field option_waiting_room.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field authenticated_users to be added to zoom.
        $table = new xmldb_table('zoom');
        $field = new xmldb_field('option_authenticated_users', XMLDB_TYPE_INTEGER,
                '1', null, null, null, '0', 'option_waiting_room');

        // Conditionally launch add field authenticated_users.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Changing the default of field option_host_video on table zoom to 0.
        $table = new xmldb_table('zoom');
        $field = new xmldb_field('option_host_video', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'option_start_type');

        // Launch change of default for field option_host_video.
        $dbman->change_field_default($table, $field);

        // Changing the default of field option_participants_video on table zoom to 0.
        $table = new xmldb_table('zoom');
        $field = new xmldb_field('option_participants_video', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'option_host_video');

        // Launch change of default for field option_participants_video.
        $dbman->change_field_default($table, $field);

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2020051800, 'zoom');
    }

    if ($oldversion < 2020052100) {
        // Increase meeting_id since Zoom increased the size from 10 to 11.

        // First need to drop index.
        $table = new xmldb_table('zoom');
        $index = new xmldb_index('meeting_id_idx', XMLDB_INDEX_NOTUNIQUE, ['meeting_id']);
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }

        // Increase size to 15 for future proofing.
        $field = new xmldb_field('meeting_id', XMLDB_TYPE_INTEGER, '15', null, XMLDB_NOTNULL, null, null, 'grade');
        $dbman->change_field_precision($table, $field);

        // Add index back.
        $dbman->add_index($table, $index);

        // First need to drop key.
        $table = new xmldb_table('zoom_meeting_details');
        $key = new xmldb_key('meeting_unique', XMLDB_KEY_UNIQUE, ['meeting_id', 'uuid']);
        $dbman->drop_key($table, $key);

        // Increase size to 15 for future proofing.
        $field = new xmldb_field('meeting_id', XMLDB_TYPE_INTEGER, '15', null, XMLDB_NOTNULL, null, null, 'uuid');
        $dbman->change_field_precision($table, $field);

        // Add key back.
        $dbman->add_key($table, $key);

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2020052100, 'zoom');
    }

    if ($oldversion < 2020100800) {
        // Changing the default of field option_host_video on table zoom to 0.
        $table = new xmldb_table('zoom');
        $field = new xmldb_field('option_host_video', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'option_start_type');

        // Launch change of default for field option_host_video.
        $dbman->change_field_default($table, $field);

        // Changing the default of field option_participants_video on table zoom to 0.
        $table = new xmldb_table('zoom');
        $field = new xmldb_field('option_participants_video', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'option_host_video');

        // Launch change of default for field option_participants_video.
        $dbman->change_field_default($table, $field);

        // Changing the default of field option_mute_upon_entry on table zoom to 1.
        $table = new xmldb_table('zoom');
        $field = new xmldb_field('option_mute_upon_entry', XMLDB_TYPE_INTEGER, '1', null, null, null, '1', 'option_audio');

        // Launch change of default for field option_participants_video.
        $dbman->change_field_default($table, $field);

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2020100800, 'zoom');
    }

    if ($oldversion < 2020120800) {
        // Delete config no longer used.
        set_config('calls_left', null, 'mod_zoom');
        upgrade_mod_savepoint(true, 2020120800, 'zoom');
    }

    if ($oldversion < 2021012902) {
        // Define field option_encryption_type to be added to zoom.
        $table = new xmldb_table('zoom');
        $field = new xmldb_field('option_encryption_type', XMLDB_TYPE_CHAR, '20', null, null, null, 'enhanced_encryption',
                'option_authenticated_users');

        // Conditionally launch add field option_encryption_type.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2021012902, 'zoom');
    }

    if ($oldversion < 2021012903) {
        // Quite all settings in settings.php had the 'mod_zoom' prefix while it should have had a 'zoom' prefix.
        // After the prefix has been modified in settings.php, the existing settings in DB have to be modified as well.

        // Get the existing settings with the old prefix from the DB,
        // but don't get the 'version' setting as this one has to have the 'mod_zoom' prefix.
        $oldsettingsql = 'SELECT name
                          FROM {config_plugins}
                          WHERE plugin = :plugin AND name != :name';
        $oldsettingparams = array('plugin' => 'mod_zoom', 'name' => 'version');
        $oldsettingkeys = $DB->get_fieldset_sql($oldsettingsql, $oldsettingparams);

        // Change the prefix of each setting.
        foreach ($oldsettingkeys as $oldsettingkey) {
            // Get the value of the existing setting with the old prefix.
            $oldsettingvalue = get_config('mod_zoom', $oldsettingkey);
            // Set the value of the setting with the new prefix.
            set_config($oldsettingkey, $oldsettingvalue, 'zoom');
            // Drop the setting with the old prefix.
            set_config($oldsettingkey, null, 'mod_zoom');
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2021012903, 'zoom');
    }

    if ($oldversion < 2021030300) {
        // Define index uuid (not unique) to be added to zoom_meeting_participants.
        $table = new xmldb_table('zoom_meeting_participants');
        $index = new xmldb_index('uuid', XMLDB_INDEX_NOTUNIQUE, ['uuid']);

        // Conditionally launch add index uuid.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2021030300, 'zoom');
    }

    if ($oldversion < 2021081900) {
        $table = new xmldb_table('zoom');

        // Define and conditionally add field recurrence_type.
        $field = new xmldb_field('recurrence_type', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'recurring');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define and conditionally add field repeat_interval.
        $field = new xmldb_field('repeat_interval', XMLDB_TYPE_INTEGER, '2', null, null, null, null, 'recurrence_type');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define and conditionally add field weekly_days.
        $field = new xmldb_field('weekly_days', XMLDB_TYPE_CHAR, '14', null, null, null, null, 'repeat_interval');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define and conditionally add field monthly_day.
        $field = new xmldb_field('monthly_day', XMLDB_TYPE_INTEGER, '2', null, null, null, null, 'weekly_days');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define and conditionally add field monthly_week.
        $field = new xmldb_field('monthly_week', XMLDB_TYPE_INTEGER, '2', null, null, null, null, 'monthly_day');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define and conditionally add field monthly_week_day.
        $field = new xmldb_field('monthly_week_day', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'monthly_week');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define and conditionally add field monthly_repeat_option.
        $field = new xmldb_field('monthly_repeat_option', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'monthly_week_day');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define and conditionally add field end_times.
        $field = new xmldb_field('end_times', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'monthly_week_day');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define and conditionally add field end_date_time.
        $field = new xmldb_field('end_date_time', XMLDB_TYPE_INTEGER, '12', null, null, null, null, 'end_times');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define and conditionally add field end_date_option.
        $field = new xmldb_field('end_date_option', XMLDB_TYPE_INTEGER, '1', null, null, null, null, 'end_date_time');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // For a time these defaults were not being updated but needed to be. This should catch them up.

        // Changing the default of field option_host_video on table zoom to 0.
        $table = new xmldb_table('zoom');
        $field = new xmldb_field('option_host_video', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'option_start_type');

        // Launch change of default for field option_host_video.
        $dbman->change_field_default($table, $field);

        // Changing the default of field option_participants_video on table zoom to 0.
        $table = new xmldb_table('zoom');
        $field = new xmldb_field('option_participants_video', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'option_host_video');

        // Launch change of default for field option_participants_video.
        $dbman->change_field_default($table, $field);

        // Changing the default of field option_mute_upon_entry on table zoom to 1.
        $table = new xmldb_table('zoom');
        $field = new xmldb_field('option_mute_upon_entry', XMLDB_TYPE_INTEGER, '1', null, null, null, '1', 'option_audio');

        // Launch change of default for field option_participants_video.
        $dbman->change_field_default($table, $field);

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2021081900, 'zoom');
    }

    if ($oldversion < 2021111100) {
        // Define table zoom_meeting_tracking_fields to be created.
        $table = new xmldb_table('zoom_meeting_tracking_fields');

        // Adding fields to table zoom_meeting_tracking_fields.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('meeting_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('tracking_field', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('value', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Adding keys to table zoom_meeting_tracking_fields.
        $table->add_key('id_primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table zoom_meeting_tracking_fields.
        $table->add_index('meeting_id', XMLDB_INDEX_NOTUNIQUE, array('meeting_id'));
        $table->add_index('tracking_field', XMLDB_INDEX_NOTUNIQUE, array('tracking_field'));

        // Conditionally launch create table for zoom_meeting_tracking_fields.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2021111100, 'zoom');
    }

    if ($oldversion < 2021111800) {
        // Define table zoom_meeting_recordings to be created.
        $table = new xmldb_table('zoom_meeting_recordings');

        // Adding fields to table zoom_meeting_recordings.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('zoomid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('meetinguuid', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null);
        $table->add_field('zoomrecordingid', XMLDB_TYPE_CHAR, '36', null, XMLDB_NOTNULL, null, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '300', null, XMLDB_NOTNULL, null, null);
        $table->add_field('externalurl', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('passcode', XMLDB_TYPE_CHAR, '30', null, null, null, null);
        $table->add_field('recordingtype', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null);
        $table->add_field('recordingstart', XMLDB_TYPE_INTEGER, '12', null, XMLDB_NOTNULL, null, null);
        $table->add_field('showrecording', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '12', null, null, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '12', null, null, null, null);

        // Adding keys to table zoom_meeting_recordings.
        $table->add_key('id_primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('zoomid_foreign', XMLDB_KEY_FOREIGN, array('zoomid'), 'zoom', array('id'));

        // Conditionally launch create table for zoom_meeting_recordings.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table zoom_meeting_recordings_view to be created.
        $table = new xmldb_table('zoom_meeting_recordings_view');

        // Adding fields to table zoom_meeting_recordings_view.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('recordingsid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('viewed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '12', null, null, null, null);

        // Adding keys to table zoom_meeting_recordings_view.
        $table->add_key('id_primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('recordingsid_foreign', XMLDB_KEY_FOREIGN, array('recordingsid'), 'zoom_meeting_recordings', array('id'));

        // Adding indexes to table zoom_meeting_recordings_view.
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, array('userid'));

        // Conditionally launch create table for zoom_meeting_recordings_view.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Add new field for recordings_visible_default.
        $table = new xmldb_table('zoom');
        // Define field recordings_visible_default to be added to zoom.
        $field = new xmldb_field('recordings_visible_default', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1',
                'alternative_hosts');

        // Conditionally launch add field recordings_visible_default.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2021111800, 'zoom');
    }

    if ($oldversion < 2021112900) {
        // Define table zoom_meeting_details to be created.
        $table = new xmldb_table('zoom_meeting_details');
        // Conditionally launch add key uuid_unique.
        if (!$table->getKey('uuid_unique')) {
            $key = new xmldb_key('uuid_unique', XMLDB_KEY_UNIQUE, ['uuid']);
            $dbman->add_key($table, $key);
        }

        // Launch drop key meeting_unique.
        $key = new xmldb_key('meeting_unique', XMLDB_KEY_UNIQUE, ['meeting_id', 'uuid']);
        $dbman->drop_key($table, $key);

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2021112900, 'zoom');
    }

    if ($oldversion < 2022022400) {

        // Change the recordings_visible_default field in the zoom table.
        $table = new xmldb_table('zoom');
        $field = new xmldb_field('recordings_visible_default', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1',
                'alternative_hosts');
        $dbman->change_field_default($table, $field);

        // Change the showrecording field in the zoom table.
        $table = new xmldb_table('zoom_meeting_recordings');
        $field = new xmldb_field('showrecording', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $dbman->change_field_default($table, $field);

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2022022400, 'zoom');
    }

    if ($oldversion < 2022031600) {

        $table = new xmldb_table('zoom');

        // Define and conditionally add field show_schedule.
        $field = new xmldb_field('show_schedule', XMLDB_TYPE_INTEGER, '1', null, null, null, '1', 'recordings_visible_default');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define and conditionally add field show_security.
        $field = new xmldb_field('show_security', XMLDB_TYPE_INTEGER, '1', null, null, null, '1', 'show_schedule');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define and conditionally add field show_media.
        $field = new xmldb_field('show_media', XMLDB_TYPE_INTEGER, '1', null, null, null, '1', 'show_security');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2022031600, 'zoom');
    }

    return true;
}
