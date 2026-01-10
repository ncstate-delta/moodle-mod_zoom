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
 * @package    mod_zoomyt
 * @copyright  2015 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute zoomyt upgrade from the given old version
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_zoomyt_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.
    $table = new xmldb_table('zoomyt');

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

        upgrade_mod_savepoint(true, 2015071000, 'zoomyt');
    }

    if ($oldversion < 2015071500) {
        // Rename option_no_video_host to option_host_video; change default to 1; invert values.
        $field = new xmldb_field('option_no_video_host', XMLDB_TYPE_INTEGER, '1', null, null, null, '1', 'option_start_type');
        // Invert option_no_video_host.
        $DB->set_field('UPDATE {zoomyt} SET option_no_video_host = 1 - option_no_video_host');
        $dbman->change_field_default($table, $field);
        $dbman->rename_field($table, $field, 'option_host_video');

        // Rename option_no_video_participants to option_participants_video; change default to 1; invert values.
        $field = new xmldb_field(
            'option_no_video_participants',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            null,
            null,
            '1',
            'option_host_video'
        );
        // Invert option_no_video_participants.
        $DB->set_field('UPDATE {zoomyt} SET option_no_video_participants = 1 - option_no_video_participants');
        $dbman->change_field_default($table, $field);
        $dbman->rename_field($table, $field, 'option_participants_video');

        // Change start_time to int (timestamp).
        $field = new xmldb_field('start_time', XMLDB_TYPE_INTEGER, '12', null, null, null, null, 'name');
        $starttimes = $DB->get_recordset('zoomyt');
        foreach ($starttimes as $time) {
            $time->start_time = strtotime($time->start_time);
            $DB->update_record('zoomyt', $time);
        }

        $starttimes->close();
        $dbman->change_field_type($table, $field);

        // Change precision/length of duration to 6 digits.
        $field = new xmldb_field('duration', XMLDB_TYPE_INTEGER, '6', null, null, null, null, 'type');
        $dbman->change_field_precision($table, $field);
        $DB->set_field('UPDATE {zoomyt} SET duration = duration*60');

        upgrade_mod_savepoint(true, 2015071500, 'zoomyt');
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

        upgrade_mod_savepoint(true, 2015071600, 'zoomyt');
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
        upgrade_mod_savepoint(true, 2015072000, 'zoomyt');
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
        $DB->set_field_select('zoomyt', 'type', 0, 'type <> 3');
        $DB->set_field('zoomyt', 'type', 1, ['type' => 3]);
        $dbman->rename_field($table, $field, 'recurring');

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2016040100, 'zoomyt');
    }

    if ($oldversion < 2018091200) {
        // Removed apiurl option from settings.
        set_config('apiurl', null, 'mod_zoomyt');

        // Set the starting number of API calls.
        set_config('calls_left', 2000, 'mod_zoomyt');

        // Set the time at which to start looking for meeting reports.
        set_config('last_call_made_at', time() - (60 * 60 * 12), 'mod_zoomyt');

        // Start zoom table modifications.
        $table = new xmldb_table('zoomyt');

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
        $table = new xmldb_table('zoomyt_meeting_details');

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
        $table->add_key('uuid_unique', XMLDB_KEY_UNIQUE, ['uuid']);
        $table->add_key('id_primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('zoomid_foreign', XMLDB_KEY_FOREIGN, ['zoomid'], 'zoomyt', ['id']);
        $table->add_key('meeting_unique', XMLDB_KEY_UNIQUE, ['meeting_id', 'uuid']);

        // Conditionally launch create table for zoom_meeting_details.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table zoom_meeting_participants to be created.
        $table = new xmldb_table('zoomyt_meeting_participants');

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
        $table->add_key('id_primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('user_by_meeting_key', XMLDB_KEY_UNIQUE, ['detailsid', 'zoomuserid']);
        $table->add_key('detailsid_foreign', XMLDB_KEY_FOREIGN, ['detailsid'], 'zoomyt_meeting_details', ['id']);

        // Adding indexes to table zoom_meeting_participants.
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);

        // Conditionally launch create table for zoom_meeting_participants.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2018091200, 'zoomyt');
    }

    if ($oldversion < 2018091400) {
        // Define field alternative_hosts to be added to zoom.
        $table = new xmldb_table('zoomyt');
        $field = new xmldb_field('alternative_hosts', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'exists_on_zoom');

        // Conditionally launch add field alternative_hosts.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2018091400, 'zoomyt');
    }

    if ($oldversion < 2018092201) {
        // Changing type of field userid on table zoom_meeting_participants to int.
        $table = new xmldb_table('zoomyt_meeting_participants');

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
        upgrade_mod_savepoint(true, 2018092201, 'zoomyt');
    }

    if ($oldversion < 2019061800) {
        // Make sure start_time is not null to match install.xml.
        $table = new xmldb_table('zoomyt_meeting_details');
        $field = new xmldb_field('start_time', XMLDB_TYPE_INTEGER, '12', null, XMLDB_NOTNULL, null, null);
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_notnull($table, $field);
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2019061800, 'zoomyt');
    }

    if ($oldversion < 2019091200) {
        // Change field alternative_hosts from type char(255) to text.
        $table = new xmldb_table('zoomyt');
        $field = new xmldb_field('alternative_hosts', XMLDB_TYPE_TEXT, null, null, null, null, null, 'exists_on_zoom');
        $dbman->change_field_type($table, $field);

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2019091200, 'zoomyt');
    }

    if ($oldversion < 2020042600) {
        // Change field zoom_meeting_participants from type int(11) to char(35),
        // because sometimes zoomuserid is concatenated with a timestamp.
        // See https://devforum.zoom.us/t/meeting-participant-user-id-value/7886/2.
        $table = new xmldb_table('zoomyt_meeting_participants');

        // First drop key, not needed anymore.
        $key = new xmldb_key('user_by_meeting_key', XMLDB_KEY_UNIQUE, ['detailsid', 'zoomuserid']);
        $dbman->drop_key($table, $key);

        // Change of type for field zoomuserid to char(35).
        $field = new xmldb_field('zoomuserid', XMLDB_TYPE_CHAR, '35', null, XMLDB_NOTNULL, null, null, 'userid');
        $dbman->change_field_type($table, $field);

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2020042600, 'zoomyt');
    }

    if ($oldversion < 2020042700) {
        // Define field attentiveness_score to be dropped from zoom_meeting_participants.
        $table = new xmldb_table('zoomyt_meeting_participants');
        $field = new xmldb_field('attentiveness_score');

        // Conditionally launch drop field attentiveness_score.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2020042700, 'zoomyt');
    }

    if ($oldversion < 2020051800) {
        // Define field option_mute_upon_entry to be added to zoom.
        $table = new xmldb_table('zoomyt');
        $field = new xmldb_field('option_mute_upon_entry', XMLDB_TYPE_INTEGER, '1', null, null, null, '1', 'option_audio');

        // Conditionally launch add field option_mute_upon_entry.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field option_waiting_room to be added to zoom.
        $table = new xmldb_table('zoomyt');
        $field = new xmldb_field('option_waiting_room', XMLDB_TYPE_INTEGER, '1', null, null, null, '1', 'option_mute_upon_entry');

        // Conditionally launch add field option_waiting_room.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field authenticated_users to be added to zoom.
        $table = new xmldb_table('zoomyt');
        $field = new xmldb_field(
            'option_authenticated_users',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            null,
            null,
            '0',
            'option_waiting_room'
        );

        // Conditionally launch add field authenticated_users.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Changing the default of field option_host_video on table zoom to 0.
        $table = new xmldb_table('zoomyt');
        $field = new xmldb_field('option_host_video', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'option_start_type');

        // Launch change of default for field option_host_video.
        $dbman->change_field_default($table, $field);

        // Changing the default of field option_participants_video on table zoom to 0.
        $table = new xmldb_table('zoomyt');
        $field = new xmldb_field('option_participants_video', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'option_host_video');

        // Launch change of default for field option_participants_video.
        $dbman->change_field_default($table, $field);

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2020051800, 'zoomyt');
    }

    if ($oldversion < 2020052100) {
        // Increase meeting_id since Zoom increased the size from 10 to 11.

        // First need to drop index.
        $table = new xmldb_table('zoomyt');
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
        $table = new xmldb_table('zoomyt_meeting_details');
        $key = new xmldb_key('meeting_unique', XMLDB_KEY_UNIQUE, ['meeting_id', 'uuid']);
        $dbman->drop_key($table, $key);

        // Increase size to 15 for future proofing.
        $field = new xmldb_field('meeting_id', XMLDB_TYPE_INTEGER, '15', null, XMLDB_NOTNULL, null, null, 'uuid');
        $dbman->change_field_precision($table, $field);

        // Add key back.
        $dbman->add_key($table, $key);

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2020052100, 'zoomyt');
    }

    if ($oldversion < 2020100800) {
        // Changing the default of field option_host_video on table zoom to 0.
        $table = new xmldb_table('zoomyt');
        $field = new xmldb_field('option_host_video', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'option_start_type');

        // Launch change of default for field option_host_video.
        $dbman->change_field_default($table, $field);

        // Changing the default of field option_participants_video on table zoom to 0.
        $table = new xmldb_table('zoomyt');
        $field = new xmldb_field('option_participants_video', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'option_host_video');

        // Launch change of default for field option_participants_video.
        $dbman->change_field_default($table, $field);

        // Changing the default of field option_mute_upon_entry on table zoom to 1.
        $table = new xmldb_table('zoomyt');
        $field = new xmldb_field('option_mute_upon_entry', XMLDB_TYPE_INTEGER, '1', null, null, null, '1', 'option_audio');

        // Launch change of default for field option_participants_video.
        $dbman->change_field_default($table, $field);

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2020100800, 'zoomyt');
    }

    if ($oldversion < 2020120800) {
        // Delete config no longer used.
        set_config('calls_left', null, 'mod_zoomyt');
        upgrade_mod_savepoint(true, 2020120800, 'zoomyt');
    }

    if ($oldversion < 2021012902) {
        // Define field option_encryption_type to be added to zoom.
        $table = new xmldb_table('zoomyt');
        $field = new xmldb_field(
            'option_encryption_type',
            XMLDB_TYPE_CHAR,
            '20',
            null,
            null,
            null,
            'enhanced_encryption',
            'option_authenticated_users'
        );

        // Conditionally launch add field option_encryption_type.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2021012902, 'zoomyt');
    }

    if ($oldversion < 2021012903) {
        // Quite all settings in settings.php had the 'mod_zoomyt' prefix while it should have had a 'zoomyt' prefix.
        // After the prefix has been modified in settings.php, the existing settings in DB have to be modified as well.

        // Get the existing settings with the old prefix from the DB,
        // but don't get the 'version' setting as this one has to have the 'mod_zoomyt' prefix.
        $oldsettingsql = 'SELECT name
                          FROM {config_plugins}
                          WHERE plugin = :plugin AND name != :name';
        $oldsettingparams = ['plugin' => 'mod_zoomyt', 'name' => 'version'];
        $oldsettingkeys = $DB->get_fieldset_sql($oldsettingsql, $oldsettingparams);

        // Change the prefix of each setting.
        foreach ($oldsettingkeys as $oldsettingkey) {
            // Get the value of the existing setting with the old prefix.
            $oldsettingvalue = get_config('mod_zoomyt', $oldsettingkey);
            // Set the value of the setting with the new prefix.
            set_config($oldsettingkey, $oldsettingvalue, 'zoomyt');
            // Drop the setting with the old prefix.
            set_config($oldsettingkey, null, 'mod_zoomyt');
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2021012903, 'zoomyt');
    }

    if ($oldversion < 2021030300) {
        // Define index uuid (not unique) to be added to zoom_meeting_participants.
        $table = new xmldb_table('zoomyt_meeting_participants');
        $index = new xmldb_index('uuid', XMLDB_INDEX_NOTUNIQUE, ['uuid']);

        // Conditionally launch add index uuid.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2021030300, 'zoomyt');
    }

    if ($oldversion < 2021081900) {
        $table = new xmldb_table('zoomyt');

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
        $table = new xmldb_table('zoomyt');
        $field = new xmldb_field('option_host_video', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'option_start_type');

        // Launch change of default for field option_host_video.
        $dbman->change_field_default($table, $field);

        // Changing the default of field option_participants_video on table zoom to 0.
        $table = new xmldb_table('zoomyt');
        $field = new xmldb_field('option_participants_video', XMLDB_TYPE_INTEGER, '1', null, null, null, '0', 'option_host_video');

        // Launch change of default for field option_participants_video.
        $dbman->change_field_default($table, $field);

        // Changing the default of field option_mute_upon_entry on table zoom to 1.
        $table = new xmldb_table('zoomyt');
        $field = new xmldb_field('option_mute_upon_entry', XMLDB_TYPE_INTEGER, '1', null, null, null, '1', 'option_audio');

        // Launch change of default for field option_participants_video.
        $dbman->change_field_default($table, $field);

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2021081900, 'zoomyt');
    }

    if ($oldversion < 2021111100) {
        // Define table zoom_meeting_tracking_fields to be created.
        $table = new xmldb_table('zoomyt_tracking_fields');

        // Adding fields to table zoom_meeting_tracking_fields.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('meeting_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('tracking_field', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('value', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Adding keys to table zoom_meeting_tracking_fields.
        $table->add_key('id_primary', XMLDB_KEY_PRIMARY, ['id']);

        // Adding indexes to table zoom_meeting_tracking_fields.
        $table->add_index('meeting_id', XMLDB_INDEX_NOTUNIQUE, ['meeting_id']);
        $table->add_index('tracking_field', XMLDB_INDEX_NOTUNIQUE, ['tracking_field']);

        // Conditionally launch create table for zoom_meeting_tracking_fields.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2021111100, 'zoomyt');
    }

    if ($oldversion < 2021111800) {
        // Define table zoom_meeting_recordings to be created.
        $table = new xmldb_table('zoomyt_meeting_recordings');

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
        $table->add_key('id_primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('zoomid_foreign', XMLDB_KEY_FOREIGN, ['zoomid'], 'zoomyt', ['id']);

        // Conditionally launch create table for zoom_meeting_recordings.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table zoom_meeting_recordings_view to be created.
        $table = new xmldb_table('zoomyt_rec_views');

        // Adding fields to table zoom_meeting_recordings_view.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('recordingsid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('viewed', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '12', null, null, null, null);

        // Adding keys to table zoom_meeting_recordings_view.
        $table->add_key('id_primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('recordingsid_foreign', XMLDB_KEY_FOREIGN, ['recordingsid'], 'zoomyt_meeting_recordings', ['id']);

        // Adding indexes to table zoom_meeting_recordings_view.
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);

        // Conditionally launch create table for zoom_meeting_recordings_view.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Add new field for recordings_visible_default.
        $table = new xmldb_table('zoomyt');
        // Define field recordings_visible_default to be added to zoom.
        $field = new xmldb_field(
            'recordings_visible_default',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '1',
            'alternative_hosts'
        );

        // Conditionally launch add field recordings_visible_default.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2021111800, 'zoomyt');
    }

    if ($oldversion < 2021112900) {
        // Define table zoom_meeting_details to be created.
        $table = new xmldb_table('zoomyt_meeting_details');
        // Conditionally launch add key uuid_unique.
        if (!$table->getKey('uuid_unique')) {
            $key = new xmldb_key('uuid_unique', XMLDB_KEY_UNIQUE, ['uuid']);
            $dbman->add_key($table, $key);
        }

        // Launch drop key meeting_unique.
        $key = new xmldb_key('meeting_unique', XMLDB_KEY_UNIQUE, ['meeting_id', 'uuid']);
        $dbman->drop_key($table, $key);

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2021112900, 'zoomyt');
    }

    if ($oldversion < 2022022400) {
        // Change the recordings_visible_default field in the zoom table.
        $table = new xmldb_table('zoomyt');
        $field = new xmldb_field(
            'recordings_visible_default',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '1',
            'alternative_hosts'
        );
        $dbman->change_field_default($table, $field);

        // Change the showrecording field in the zoom table.
        $table = new xmldb_table('zoomyt_meeting_recordings');
        $field = new xmldb_field('showrecording', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $dbman->change_field_default($table, $field);

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2022022400, 'zoomyt');
    }

    if ($oldversion < 2022031600) {
        $table = new xmldb_table('zoomyt');

        // Define and conditionally add field show_schedule.
        $field = new xmldb_field(
            'show_schedule',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '1',
            'recordings_visible_default'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define and conditionally add field show_security.
        $field = new xmldb_field('show_security', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'show_schedule');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define and conditionally add field show_media.
        $field = new xmldb_field('show_media', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'show_security');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2022031600, 'zoomyt');
    }

    if ($oldversion < 2022071500) {
        // Define table zoom_meeting_breakout_rooms to be created.
        $table = new xmldb_table('zoomyt_breakout_rooms');

        // Adding fields to table zoom_meeting_breakout_rooms.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '32', null, null, null, null);
        $table->add_field('zoomid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table zoom_meeting_breakout_rooms.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_zoomid', XMLDB_KEY_FOREIGN, ['zoomid'], 'zoomyt', ['id']);

        // Conditionally launch create table for customfield_category.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table zoom_rooms_participants to be created.
        $table = new xmldb_table('zoomyt_breakout_parts');

        // Adding fields to table zoom_rooms_participants.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('breakoutroomid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table zoom_rooms_participants.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_breakoutroomid', XMLDB_KEY_FOREIGN, ['breakoutroomid'], 'zoomyt_breakout_rooms', ['id']);

        // Conditionally launch create table for customfield_category.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table zoom_rooms_groups to be created.
        $table = new xmldb_table('zoomyt_breakout_groups');

        // Adding fields to table zoom_rooms_groups.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('breakoutroomid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table zoom_rooms_groups.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_breakoutroomid', XMLDB_KEY_FOREIGN, ['breakoutroomid'], 'zoomyt_breakout_rooms', ['id']);

        // Conditionally launch create table for customfield_category.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2022071500, 'zoomyt');
    }

    if ($oldversion < 2022082500) {
        $table = new xmldb_table('zoomyt');

        // Define and conditionally add field option_auto_recording.
        $field = new xmldb_field('option_auto_recording', XMLDB_TYPE_CHAR, '5', null, null, null, null, 'show_media');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2022082500, 'zoomyt');
    }

    if ($oldversion < 2022102700) {
        $table = new xmldb_table('zoomyt');

        // Define and conditionally add field registration.
        $field = new xmldb_field('registration', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '2', 'option_auto_recording');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2022102700, 'zoomyt');
    }

    if ($oldversion < 2023080202) {
        // Issue #432: Inconsistency between the DB and schema, this is to verify everything matches.
        // Verify show_schedule, show_security, and show_media are all set to NOTNULL.
        // Verify option_auto_record is set to NOTNULL and defaults to "none".
        $table = new xmldb_table('zoomyt');

        // Launch change of nullability for show schedule.
        $field = new xmldb_field(
            'show_schedule',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '1',
            'recordings_visible_default'
        );
        $dbman->change_field_notnull($table, $field);

        $field = new xmldb_field('show_security', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'show_schedule');
        $dbman->change_field_notnull($table, $field);

        $field = new xmldb_field('show_media', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'show_security');
        $dbman->change_field_notnull($table, $field);

        $field = new xmldb_field('option_auto_recording', XMLDB_TYPE_CHAR, '5', null, null, null, null, 'show_media');
        $dbman->change_field_type($table, $field);

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2023080202, 'zoomyt');
    }

    if ($oldversion < 2023111600) {
        // Issue #326: Drop start_url from database.

        // Start zoom table modifications.
        $table = new xmldb_table('zoomyt');

        // Define field status to be dropped from zoom.
        $field = new xmldb_field('start_url');

        // Conditionally launch drop field status.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2023111600, 'zoomyt');
    }

    if ($oldversion < 2024012500) {
        // Version 5.1.0 incorrectly upgraded the zoom table's registration field. It should not be null and should default to 2.
        $table = new xmldb_table('zoomyt');
        $field = new xmldb_field('registration', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '2', 'option_auto_recording');

        // Set any null values to the new default: 2.
        $DB->set_field_select('zoomyt', 'registration', '2', 'registration IS NULL');

        // Launch change of nullability for field registration.
        $dbman->change_field_notnull($table, $field);

        // Launch change of default for field registration.
        $dbman->change_field_default($table, $field);

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2024012500, 'zoomyt');
    }

    if ($oldversion < 2024030100) {
        // Define field grading_method to be added to zoom.
        $table = new xmldb_table('zoomyt');
        $field = new xmldb_field('grading_method', XMLDB_TYPE_CHAR, '10', null, null, null, null, 'grade');

        // Conditionally launch add field grading_method.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2024030100, 'zoomyt');
    }

    if ($oldversion < 2024041900) {
        // Update existing recording names to default for translatable recordingtype strings.
        $meetings = $DB->get_records('zoomyt');

        foreach ($meetings as $meeting) {
            $DB->set_field_select('zoomyt_meeting_recordings', 'name', $meeting->name, 'zoomid = ?', [$meeting->id]);
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2024041900, 'zoomyt');
    }

    if ($oldversion < 2024070300) {
        // Update existing meeting occurrence duration to seconds.
        $occurrences = $DB->get_records('zoomyt_meeting_details');

        foreach ($occurrences as $occurrence) {
            $duration = $occurrence->end_time - $occurrence->start_time;
            $DB->set_field_select('zoomyt_meeting_details', 'duration', $duration, 'id = ?', [$occurrence->id]);
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2024070300, 'zoomyt');
    }

    if ($oldversion < 2024072500) {
        // Changing precision of field recordingtype on table zoom_meeting_recordings to (50).
        $table = new xmldb_table('zoomyt_meeting_recordings');
        $field = new xmldb_field('recordingtype', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null, 'passcode');

        // Launch change of precision for field recordingtype.
        $dbman->change_field_precision($table, $field);

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2024072500, 'zoomyt');
    }

    if ($oldversion < 2025050900) {
        // Define table zoom_ical_notifications to be created.
        $table = new xmldb_table('zoomyt_ical_notifications');

        // Adding fields to table zoom_ical_notifications.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('zoomeventid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('notificationtime', XMLDB_TYPE_INTEGER, '12', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table zoom_ical_notifications.
        $table->add_key('id_primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_zoomeventid', XMLDB_KEY_FOREIGN_UNIQUE, ['zoomeventid'], 'event', ['id']);

        // Conditionally launch create table for zoom_ical_notifications.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2025050900, 'zoomyt');
    }

    if ($oldversion < 2025010900) {
        // Define table zoomyt_category_settings to be created.
        $table = new xmldb_table('zoomyt_category_settings');

        // Adding fields to table zoomyt_category_settings.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('categoryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('accountid', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('clientid', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('clientsecret', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('apiendpoint', XMLDB_TYPE_CHAR, '10', null, null, null, null);
        $table->add_field('zoomurl', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('inherit', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('defaultrecurring', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
        $table->add_field('defaultwaitingroom', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
        $table->add_field('defaultjoinbeforehost', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
        $table->add_field('defaultaudiooption', XMLDB_TYPE_CHAR, '15', null, null, null, null);
        $table->add_field('defaulthostvideo', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
        $table->add_field('defaultparticipantsvideo', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '12', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '12', null, XMLDB_NOTNULL, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table zoomyt_category_settings.
        $table->add_key('id_primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('categoryid_unique', XMLDB_KEY_UNIQUE, ['categoryid']);
        $table->add_key('fk_categoryid', XMLDB_KEY_FOREIGN, ['categoryid'], 'course_categories', ['id']);
        $table->add_key('fk_usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

        // Conditionally launch create table for zoomyt_category_settings.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2025010900, 'zoomyt');
    }

    if ($oldversion < 2025010901) {
        // Add YouTube fields to category settings table.
        $table = new xmldb_table('zoomyt_category_settings');

        // YouTube client ID.
        $field = new xmldb_field('yt_client_id', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'defaultparticipantsvideo');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // YouTube client secret.
        $field = new xmldb_field('yt_client_secret', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'yt_client_id');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // YouTube refresh token.
        $field = new xmldb_field('yt_refresh_token', XMLDB_TYPE_TEXT, null, null, null, null, null, 'yt_client_secret');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // YouTube channel ID.
        $field = new xmldb_field('yt_channel_id', XMLDB_TYPE_CHAR, '50', null, null, null, null, 'yt_refresh_token');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // YouTube channel name.
        $field = new xmldb_field('yt_channel_name', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'yt_channel_id');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // YouTube default visibility.
        $field = new xmldb_field('yt_default_visibility', XMLDB_TYPE_CHAR, '20', null, null, null, 'unlisted', 'yt_channel_name');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Zoom recording delete days.
        $field = new xmldb_field('zoom_recording_delete_days', XMLDB_TYPE_INTEGER, '4', null, null, null, null, 'yt_default_visibility');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define table zoomyt_videos to be created.
        $table = new xmldb_table('zoomyt_videos');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('zoomid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('recordingid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('meetinguuid', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null);
        $table->add_field('zoom_recording_id', XMLDB_TYPE_CHAR, '50', null, null, null, null);
        $table->add_field('youtube_video_id', XMLDB_TYPE_CHAR, '20', null, null, null, null);
        $table->add_field('youtube_url', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('title', XMLDB_TYPE_CHAR, '300', null, XMLDB_NOTNULL, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('thumbnail_url', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('duration', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('visibility', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'unlisted');
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
        $table->add_field('error_message', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('zoom_recording_deleted', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('zoom_session_time', XMLDB_TYPE_INTEGER, '12', null, null, null, null);
        $table->add_field('visible', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '12', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '12', null, XMLDB_NOTNULL, null, null);

        $table->add_key('id_primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_zoomid', XMLDB_KEY_FOREIGN, ['zoomid'], 'zoomyt', ['id']);
        $table->add_key('fk_recordingid', XMLDB_KEY_FOREIGN, ['recordingid'], 'zoomyt_meeting_recordings', ['id']);

        $table->add_index('youtube_video_id_idx', XMLDB_INDEX_UNIQUE, ['youtube_video_id']);
        $table->add_index('meetinguuid_idx', XMLDB_INDEX_NOTUNIQUE, ['meetinguuid']);
        $table->add_index('status_idx', XMLDB_INDEX_NOTUNIQUE, ['status']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table zoomyt_video_views to be created.
        $table = new xmldb_table('zoomyt_video_views');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('videoid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('viewcount', XMLDB_TYPE_INTEGER, '6', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('firstviewed', XMLDB_TYPE_INTEGER, '12', null, XMLDB_NOTNULL, null, null);
        $table->add_field('lastviewed', XMLDB_TYPE_INTEGER, '12', null, XMLDB_NOTNULL, null, null);

        $table->add_key('id_primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_videoid', XMLDB_KEY_FOREIGN, ['videoid'], 'zoomyt_videos', ['id']);
        $table->add_key('fk_userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('videoid_userid_unique', XMLDB_KEY_UNIQUE, ['videoid', 'userid']);

        $table->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2025010901, 'zoomyt');
    }

    if ($oldversion < 2025050904) {
        // Re-run table creation for sites that missed earlier upgrades due to version ordering issues.

        // Define table zoomyt_category_settings to be created.
        $table = new xmldb_table('zoomyt_category_settings');

        if (!$dbman->table_exists($table)) {
            // Adding fields to table zoomyt_category_settings.
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('categoryid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('accountid', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('clientid', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('clientsecret', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('apiendpoint', XMLDB_TYPE_CHAR, '10', null, null, null, null);
            $table->add_field('zoomurl', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('inherit', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('defaultrecurring', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
            $table->add_field('defaultwaitingroom', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
            $table->add_field('defaultjoinbeforehost', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
            $table->add_field('defaultaudiooption', XMLDB_TYPE_CHAR, '15', null, null, null, null);
            $table->add_field('defaulthostvideo', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
            $table->add_field('defaultparticipantsvideo', XMLDB_TYPE_INTEGER, '1', null, null, null, null);
            $table->add_field('yt_client_id', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('yt_client_secret', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('yt_refresh_token', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('yt_channel_id', XMLDB_TYPE_CHAR, '50', null, null, null, null);
            $table->add_field('yt_channel_name', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('yt_default_visibility', XMLDB_TYPE_CHAR, '20', null, null, null, 'unlisted');
            $table->add_field('zoom_recording_delete_days', XMLDB_TYPE_INTEGER, '4', null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '12', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '12', null, XMLDB_NOTNULL, null, null);
            $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

            $table->add_key('id_primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('categoryid_unique', XMLDB_KEY_UNIQUE, ['categoryid']);
            $table->add_key('fk_categoryid', XMLDB_KEY_FOREIGN, ['categoryid'], 'course_categories', ['id']);
            $table->add_key('fk_usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);

            $dbman->create_table($table);
        } else {
            // Table exists, add YouTube fields if missing.
            $field = new xmldb_field('yt_client_id', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'defaultparticipantsvideo');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            $field = new xmldb_field('yt_client_secret', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'yt_client_id');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            $field = new xmldb_field('yt_refresh_token', XMLDB_TYPE_TEXT, null, null, null, null, null, 'yt_client_secret');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            $field = new xmldb_field('yt_channel_id', XMLDB_TYPE_CHAR, '50', null, null, null, null, 'yt_refresh_token');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            $field = new xmldb_field('yt_channel_name', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'yt_channel_id');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            $field = new xmldb_field('yt_default_visibility', XMLDB_TYPE_CHAR, '20', null, null, null, 'unlisted', 'yt_channel_name');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }

            $field = new xmldb_field('zoom_recording_delete_days', XMLDB_TYPE_INTEGER, '4', null, null, null, null, 'yt_default_visibility');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Define table zoomyt_videos to be created.
        $table = new xmldb_table('zoomyt_videos');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('zoomid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('recordingid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('meetinguuid', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null);
            $table->add_field('zoom_recording_id', XMLDB_TYPE_CHAR, '50', null, null, null, null);
            $table->add_field('youtube_video_id', XMLDB_TYPE_CHAR, '20', null, null, null, null);
            $table->add_field('youtube_url', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('title', XMLDB_TYPE_CHAR, '300', null, XMLDB_NOTNULL, null, null);
            $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('thumbnail_url', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('duration', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
            $table->add_field('visibility', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'unlisted');
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'pending');
            $table->add_field('error_message', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('zoom_recording_deleted', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('zoom_session_time', XMLDB_TYPE_INTEGER, '12', null, null, null, null);
            $table->add_field('visible', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '12', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '12', null, XMLDB_NOTNULL, null, null);

            $table->add_key('id_primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_zoomid', XMLDB_KEY_FOREIGN, ['zoomid'], 'zoomyt', ['id']);
            $table->add_key('fk_recordingid', XMLDB_KEY_FOREIGN, ['recordingid'], 'zoomyt_meeting_recordings', ['id']);

            $table->add_index('youtube_video_id_idx', XMLDB_INDEX_UNIQUE, ['youtube_video_id']);
            $table->add_index('meetinguuid_idx', XMLDB_INDEX_NOTUNIQUE, ['meetinguuid']);
            $table->add_index('status_idx', XMLDB_INDEX_NOTUNIQUE, ['status']);

            $dbman->create_table($table);
        }

        // Define table zoomyt_video_views to be created.
        $table = new xmldb_table('zoomyt_video_views');

        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('videoid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('viewcount', XMLDB_TYPE_INTEGER, '6', null, XMLDB_NOTNULL, null, '1');
            $table->add_field('firstviewed', XMLDB_TYPE_INTEGER, '12', null, XMLDB_NOTNULL, null, null);
            $table->add_field('lastviewed', XMLDB_TYPE_INTEGER, '12', null, XMLDB_NOTNULL, null, null);

            $table->add_key('id_primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('fk_videoid', XMLDB_KEY_FOREIGN, ['videoid'], 'zoomyt_videos', ['id']);
            $table->add_key('fk_userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_key('videoid_userid_unique', XMLDB_KEY_UNIQUE, ['videoid', 'userid']);

            $table->add_index('userid_idx', XMLDB_INDEX_NOTUNIQUE, ['userid']);

            $dbman->create_table($table);
        }

        // Add show_join_button field to zoomyt table.
        $table = new xmldb_table('zoomyt');
        $field = new xmldb_field('show_join_button', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'show_media');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2025050904, 'zoomyt');
    }

    return true;
}
