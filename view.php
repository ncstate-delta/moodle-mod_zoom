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
 * Prints a particular instance of zoom
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_zoom
 * @copyright  2015 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// Login check require_login() is called in zoom_get_instance_setup();.
// @codingStandardsIgnoreLine
require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/../../lib/moodlelib.php');

$config = get_config('mod_zoom');

list($course, $cm, $zoom) = zoom_get_instance_setup();
$instance = mod_zoom_instance::factory($zoom->webinar);
$instance->populate_from_database_record($zoom);
var_dump($zoom);
// var_dump($instance);

$context = context_module::instance($cm->id);
$iszoommanager = has_capability('mod/zoom:addinstance', $context);

$event = \mod_zoom\event\course_module_viewed::create(array(
    'objectid' => $PAGE->cm->instance,
    'context' => $PAGE->context,
));
$event->add_record_snapshot('course', $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $zoom);
$event->trigger();

// Print the page header.

$PAGE->set_url('/mod/zoom/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($instance->name));
$PAGE->set_heading(format_string($course->fullname));

$zoomuserid = zoom_get_user_id(false);
$alternativehosts = $instance->alternative_hosts;;
$userishost = $instance->is_any_host($zoomuserid, $USER->email);

$service = new mod_zoom_webservice();
$showrecreate = false;
try {
    $service->get_instance_info($instance->id, $instance->is_webinar());
} catch (moodle_exception $error) {
    $showrecreate = error_indicates_meeting_gone($error);
}

$stryes = get_string('yes');
$strno = get_string('no');
$strstart = get_string('start_meeting', 'mod_zoom');
$strjoin = get_string('join_meeting', 'mod_zoom');
$strunavailable = get_string('unavailable', 'mod_zoom');
$strtime = get_string('meeting_time', 'mod_zoom');
$strduration = get_string('duration', 'mod_zoom');
$strpassprotect = get_string('passwordprotected', 'mod_zoom');
$strpassword = get_string('password', 'mod_zoom');
$strjoinlink = get_string('join_link', 'mod_zoom');
$strjoinbeforehost = get_string('joinbeforehost', 'mod_zoom');
$strstartvideohost = get_string('starthostjoins', 'mod_zoom');
$strstartvideopart = get_string('startpartjoins', 'mod_zoom');
$straudioopt = get_string('option_audio', 'mod_zoom');
$strstatus = get_string('status', 'mod_zoom');
$strall = get_string('allmeetings', 'mod_zoom');

// Output starts here.
echo $OUTPUT->header();

// Show a recreate dialog for meetings that were deleted on Zoom servers.
if ($showrecreate) {
    // Only show recreate/delete links in the message for users that can edit.
    if ($iszoommanager) {
        $message = get_string('zoomerr_meetingnotfound', 'mod_zoom', zoom_meetingnotfound_param($cm->id));
        $style = 'notifywarning';
    } else {
        $message = get_string('zoomerr_meetingnotfound_info', 'mod_zoom');
        $style = 'notifymessage';
    }
    echo $OUTPUT->notification($message, $style);
}

// Show name and description.
echo $OUTPUT->heading(format_string($instance->name), 2);
if (!empty($instance->description)) {
    echo $OUTPUT->box(format_module_intro('zoom', $instance->export_to_database_format(), $cm->id), 'generalbox mod_introbox', 'intro');
}

// Create table for meeting attributes.
$table = new html_table();
$table->attributes['class'] = 'generaltable mod_view';
$table->align = array('center', 'left');
$numcolumns = 2;

// Conditionally display button to join/start meeting.
if ($instance->can_join()) {
    if ($userishost) {
        $buttonhtml = html_writer::tag('button', $strstart, array('type' => 'submit', 'class' => 'btn btn-success'));
    } else {
        $buttonhtml = html_writer::tag('button', $strjoin, array('type' => 'submit', 'class' => 'btn btn-primary'));
    }
    $aurl = new moodle_url('/mod/zoom/loadmeeting.php', array('id' => $cm->id));
    $buttonhtml .= html_writer::input_hidden_params($aurl);
    $link = html_writer::tag('form', $buttonhtml, array('action' => $aurl->out_omit_querystring(), 'target' => '_blank'));
} else {
    $link = html_writer::tag('span', $strunavailable, array('style' => 'font-size:20px'));
}

// Do more table manipulation.
$title = new html_table_cell($link);
$title->header = true;
$title->colspan = $numcolumns;
$table->data[] = array($title);

// Show sessions link and alternative hosts to any Zoom manager.
if ($iszoommanager) {
    // Only show sessions link to users with edit capability.
    $sessionsurl = new moodle_url('/mod/zoom/report.php', array('id' => $cm->id));
    $sessionslink = html_writer::link($sessionsurl, get_string('sessions', 'mod_zoom'));
    $sessions = new html_table_cell($sessionslink);
    $sessions->colspan = $numcolumns;
    $table->data[] = array($sessions);

    // Display alternate hosts if they exist.
    if (!empty($instance->alternativehosts)) {
        $table->data[] = array(get_string('alternative_hosts', 'mod_zoom'), $instance->alternativehosts);
    }
}

// Generate add-to-calendar button if instance was found and isn't recurring.
if (!$showrecreate && !$instance->is_recurring()) {
    $icallink = new moodle_url('/mod/zoom/exportical.php', array('id' => $cm->id));
    $calendaricon = $OUTPUT->pix_icon('i/calendar', get_string('calendariconalt', 'mod_zoom'), 'mod_zoom');
    $calendarbutton = html_writer::div($calendaricon . ' ' . get_string('downloadical', 'mod_zoom'), 'btn btn-primary');
    $buttonhtml = html_writer::link((string) $icallink, $calendarbutton, array('target' => '_blank'));
    $table->data[] = array(get_string('addtocalendar', 'mod_zoom'), $buttonhtml);
}

// Display either time information or that the instance is recurring.
if ($instance->is_recurring()) {
    $recurringmessage = new html_table_cell(get_string('recurringmeetinglong', 'mod_zoom'));
    $recurringmessage->colspan = $numcolumns;
    $table->data[] = array($recurringmessage);
} else {
    $table->data[] = array($strtime, userdate($instance->starttime));
    $table->data[] = array($strduration, format_time($instance->duration));
}

// Display password information.
if (!$instance->is_webinar()) {
    $haspassword = (isset($instance->password) && $instance->password !== '');
    $strhaspass = ($haspassword) ? $stryes : $strno;
    $table->data[] = array($strpassprotect, $strhaspass);

    // Display the actual password only to hosts.
    if ($userishost && $haspassword) {
        $table->data[] = array($strpassword, $instance->password);
    }
}

// Display the join url to hosts.
if ($userishost) {
    $table->data[] = array($strjoinlink, html_writer::link($instance->join_url, $instance->join_url, array('target' => '_blank')));
}

// Display some settings.
if (!$instance->is_webinar()) {
    $strjbh = ($instance->joinbeforehost) ? $stryes : $strno;
    $table->data[] = array($strjoinbeforehost, $strjbh);

    $strparticipantsvideo = ($instance->participantsvideo) ? $stryes : $strno;
    $table->data[] = array($strstartvideopart, $strparticipantsvideo);
}

// Display host video settings.
$strvideohost = ($instance->hostvideo) ? $stryes : $strno;
$table->data[] = array($strstartvideohost, $strvideohost);

// Display audio settings.
$table->data[] = array($straudioopt, get_string('audio_' . $instance->audio, 'mod_zoom'));

// Display status message.
if (!$instance->existsonzoom) {
    $status = get_string('meeting_nonexistent_on_zoom', 'mod_zoom');
} else if ($instance->can_join()) {
    $status = get_string('meeting_avaiable', 'mod_zoom');
} else {
    $status = get_string('meeting_unavailable', 'mod_zoom');
}
$table->data[] = array($strstatus, $status);

$urlall = new moodle_url('/mod/zoom/index.php', array('id' => $course->id));
$linkall = html_writer::link($urlall, $strall);
$linktoall = new html_table_cell($linkall);
$linktoall->colspan = $numcolumns;
$table->data[] = array($linktoall);

echo html_writer::table($table);

// Finish the page.
echo $OUTPUT->footer();
