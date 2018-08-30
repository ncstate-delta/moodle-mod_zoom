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

    if ($oldversion < 2018081700) {
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
        upgrade_mod_savepoint(true, 2018081700, 'zoom');
    }

    // Database changes from CCLE-7741.
    if ($oldversion < 2018082100) {
        // Define table zoom_meetings_queue to be created.
        $table = new xmldb_table('zoom_meetings_queue');

        // Adding fields to table zoom_meetings_queue.
        $table->add_field('meeting_webinar_instance_id', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null);
        $table->add_field('meeting_webinar_universal_id', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null);
        $table->add_field('end_time', XMLDB_TYPE_INTEGER, '12', null, XMLDB_NOTNULL, null, null);
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('duration', XMLDB_TYPE_INTEGER, '12', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table zoom_meetings_queue.
        $table->add_key('meeting_webinar_instance_id_unique', XMLDB_KEY_UNIQUE, array('meeting_webinar_instance_id'));
        $table->add_key('id_primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for zoom_meetings_queue.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table zoom_meetings_participants to be created.
        $table = new xmldb_table('zoom_meetings_participants');

        // Adding fields to table zoom_meetings_participants.
        $table->add_field('participant_instance_id', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null);
        $table->add_field('participant_universal_id', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null);
        $table->add_field('participant_email', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('meeting_webinar_instance_id', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null);
        $table->add_field('join_time', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('leave_time', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('duration', XMLDB_TYPE_INTEGER, '12', null, XMLDB_NOTNULL, null, null);
        $table->add_field('attentiveness_score', XMLDB_TYPE_CHAR, '7', null, XMLDB_NOTNULL, null, null);
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);

        // Adding keys to table zoom_meetings_participants.
        $table->add_key('participant_universal_id_unique', XMLDB_KEY_UNIQUE, array('participant_universal_id'));
        $table->add_key('id_primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for zoom_meetings_participants.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2018082100, 'zoom');
    }

    if ($oldversion < 2018082101) {

        // Define field start_time to be added to zoom_meetings_queue.
        $table = new xmldb_table('zoom_meetings_queue');
        $field = new xmldb_field('start_time', XMLDB_TYPE_INTEGER, '12', null, null, null, null, 'id');

        // Conditionally launch add field start_time.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $field = new xmldb_field('topic', XMLDB_TYPE_CHAR, '300', null, XMLDB_NOTNULL, null, null, 'duration');

        // Conditionally launch add field topic.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2018082101, 'zoom');
    }

    if ($oldversion < 2018082102) {

        // Define field retrieved to be added to zoom_meetings_queue.
        $table = new xmldb_table('zoom_meetings_queue');
        $field = new xmldb_field('retrieved', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'topic');

        // Conditionally launch add field retrieved.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2018082102, 'zoom');
    }

    if ($oldversion < 2018082103) {
        // Changes for zoom_meetings_queue.
        // Define table zoom_meetings_queue to be renamed to zoom_meeting_details.
        $table = new xmldb_table('zoom_meetings_queue');

        // Rename field meeting_webinar_instance_id on table zoom_meeting_details to uuid.
        $field = new xmldb_field('meeting_webinar_instance_id', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null, null);
        // Launch rename field meeting_webinar_instance_id.
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'uuid');
        } else {
            $field = new xmldb_field('uuid', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null, null);
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Rename field meeting_webinar_universal_id on table zoom_meeting_details to meeting_id.
        $field = new xmldb_field('meeting_webinar_universal_id', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null,
                                 'uuid');
        // Launch rename field meeting_webinar_universal_id.
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'meeting_id');
        } else {
            $field = new xmldb_field('meeting_id', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null,
                                 'uuid');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Define field retrieved to be dropped from zoom_meeting_details.
        $field = new xmldb_field('retrieved');
        // Conditionally launch drop field retrieved.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Define field total_minutes to be added to zoom_meeting_details.
        $field = new xmldb_field('total_minutes', XMLDB_TYPE_INTEGER, '12', null, null, null, '0', 'topic');
        // Conditionally launch add field total_minutes.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field participant_count to be added to zoom_meeting_details.
        $field = new xmldb_field('participants_count', XMLDB_TYPE_INTEGER, '4', null, null, null, '0', 'total_minutes');
        // Conditionally launch add field participant_count.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field zoomid to be added to zoom_meeting_details.
        $field = new xmldb_field('zoomid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'participants_count');
        // Conditionally launch add field zoomid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define key zoomid_foreign (foreign) to be added to zoom_meeting_details.
        $key = new xmldb_key('zoomid_foreign', XMLDB_KEY_FOREIGN, array('zoomid'), 'zoom', array('id'));
        // Launch add key zoomid_foreign.
        $dbman->add_key($table, $key);

        // Define key meeting_unique (unique) to be added to zoom_meeting_details.
        $key = new xmldb_key('meeting_unique', XMLDB_KEY_UNIQUE, array('meeting_id', 'uuid'));
        // Launch add key meeting_unique.
        $dbman->add_key($table, $key);

        // Launch rename table for zoom_meetings_queue.
        if ($dbman->table_exists($table)) {
            $dbman->rename_table($table, 'zoom_meeting_details');
        }

        // Changes for zoom_meetings_participants.
        // Define table zoom_meetings_participants to be renamed to zoom_meeting_participants.
        $table = new xmldb_table('zoom_meetings_participants');

        // Rename field participant_instance_id on table zoom_meeting_participants to zoomuserid.
        $field = new xmldb_field('participant_instance_id', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null, null);
        // Launch rename field participant_instance_id.
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'zoomuserid');
        } else {
            $field = new xmldb_field('zoomuserid', XMLDB_TYPE_INTEGER, '11', null, XMLDB_NOTNULL, null, null, null);
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Rename field participant_universal_id on table zoom_meeting_participants to uuid.
        $field = new xmldb_field('participant_universal_id', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null,
                                 'zoomuserid');
        // Launch rename field participant_universal_id.
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'uuid');
        } else {
            $field = new xmldb_field('uuid', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null,
                                 'zoomuserid');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Rename field participant_email on table zoom_meeting_participants to user_email.
        $field = new xmldb_field('participant_email', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null,
                                 'uuid');
        // Launch rename field participant_email.
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'user_email');
        } else {
            $field = new xmldb_field('user_email', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null,
                                 'uuid');
            if (!$dbman->field_exists($table, $field)) {
                $dbman->add_field($table, $field);
            }
        }

        // Define field userid to be added to zoom_meeting_participants.
        $field = new xmldb_field('userid', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'id');
        // Conditionally launch add field userid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field name to be added to zoom_meeting_participants.
        $field = new xmldb_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'userid');
        // Conditionally launch add field name.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define field detailsid to be added to zoom_meeting_participants.
        $field = new xmldb_field('detailsid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'name');
        // Conditionally launch add field detailsid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define key user_by_meeting_key (unique) to be added to zoom_meeting_participants.
        $key = new xmldb_key('user_by_meeting_key', XMLDB_KEY_UNIQUE, array('detailsid', 'zoomuserid'));
        // Launch add key user_by_meeting_key.
        $dbman->add_key($table, $key);

        // Define key detailsid_foreign (foreign) to be added to zoom_meeting_participants.
        $key = new xmldb_key('detailsid_foreign', XMLDB_KEY_FOREIGN, array('detailsid'), 'zoom_meeting_details', array('id'));
        // Launch add key detailsid_foreign.
        $dbman->add_key($table, $key);

        // Define index userid (not unique) to be added to zoom_meeting_participants.
        $index = new xmldb_index('userid', XMLDB_INDEX_NOTUNIQUE, array('userid'));
        // Conditionally launch add index userid.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Define field meeting_webinar_instance_id to be dropped from zoom_meeting_participants.
        $field = new xmldb_field('meeting_webinar_instance_id');
        // Conditionally launch drop field meeting_webinar_instance_id.
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Changing type of field join_time on table zoom_meeting_participants to int.
        $field = new xmldb_field('join_time', XMLDB_TYPE_INTEGER, '12', null, XMLDB_NOTNULL, null, null, 'user_email');
        // Launch change of type for field join_time.
        $dbman->change_field_type($table, $field);

        // Changing type of field leave_time on table zoom_meeting_participants to int.
        $field = new xmldb_field('leave_time', XMLDB_TYPE_INTEGER, '12', null, XMLDB_NOTNULL, null, null, 'join_time');
        // Launch change of type for field leave_time.
        $dbman->change_field_type($table, $field);

        // Launch rename table for zoom_meetings_participants.
        if ($dbman->table_exists($table)) {
            $dbman->rename_table($table, 'zoom_meeting_participants');
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2018082103, 'zoom');
    }

    if ($oldversion < 2018082300) {
        // Define key user_by_meeting_key (unique) to be dropped form zoom_meeting_participants.
        $table = new xmldb_table('zoom_meeting_participants');
        $key = new xmldb_key('participant_universal_id_unique', XMLDB_KEY_UNIQUE, array('uuid'));

        // Launch drop key user_by_meeting_key.
        $dbman->drop_key($table, $key);

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2018082300, 'zoom');
    }

    if ($oldversion < 2018082400) {
        // Set the starting number of API calls.
        set_config('calls_left', 2000, 'mod_zoom');

        // Set the time at which to start looking for meeting reports.
        set_config('last_call_made_at', time() - (60 * 60 * 12), 'mod_zoom');

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2018082400, 'zoom');
    }

    if ($oldversion < 2018083105) {

        // Changing nullability of field user_email on table zoom_meeting_participants to not null.
        $table = new xmldb_table('zoom_meeting_participants');
        $field = new xmldb_field('user_email', XMLDB_TYPE_TEXT, null, null, null, null, null, 'uuid');

        // Launch change of nullability for field user_email.
        $dbman->change_field_notnull($table, $field);

        // Changing nullability of field uuid on table zoom_meeting_participants to not null.
        $field = new xmldb_field('uuid', XMLDB_TYPE_CHAR, '30', null, null, null, null, 'zoomuserid');

        // Launch change of nullability for field uuid.
        $dbman->change_field_notnull($table, $field);

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2018083105, 'zoom');
    }

    if ($oldversion < 2018083106) {
        // Define field alternative_hosts to be added to zoom.
        $table = new xmldb_table('zoom');
        $field = new xmldb_field('alternative_hosts', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'exists_on_zoom');

        // Conditionally launch add field alternative_hosts.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Zoom savepoint reached.
        upgrade_mod_savepoint(true, 2018083106, 'zoom');
    }

    return true;
}
