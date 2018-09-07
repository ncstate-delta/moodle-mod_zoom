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
$PAGE->set_title(format_string($zoom->name));
$PAGE->set_heading(format_string($course->fullname));

/*
 * Other things you may want to set - remove if not needed.
 * $PAGE->set_cacheable(false);
 * $PAGE->set_focuscontrol('some-html-id');
 * $PAGE->add_body_class('zoom-'.$somevar);
 */

$zoomuserid = zoom_get_user_id(false);
$userishost = ($zoomuserid == $zoom->host_id);

$service = new mod_zoom_webservice();
try {
    $service->get_meeting_info($zoom);
    $showrecreate = false;
} catch (moodle_exception $error) {
    $showrecreate = zoom_is_meeting_gone_error($error);
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

echo $OUTPUT->heading(format_string($zoom->name), 2);
if ($zoom->intro) {
    echo $OUTPUT->box(format_module_intro('zoom', $zoom, $cm->id), 'generalbox mod_introbox', 'intro');
}

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_view';

$table->align = array('center', 'left');
$numcolumns = 2;

list($inprogress, $available, $finished) = zoom_get_state($zoom);

if ($available) {
    if ($userishost) {
        $buttonhtml = html_writer::tag('button', $strstart, array('type' => 'submit', 'class' => 'btn btn-success'));
    } else {
        $buttonhtml = html_writer::tag('button', $strjoin, array('type' => 'submit', 'class' => 'btn btn-primary'));
    }
    $aurl = new moodle_url('/mod/zoom/loadmeeting.php', array('id' => $cm->id, 'userishost' => $userishost));
    $buttonhtml .= html_writer::input_hidden_params($aurl);
    $link = html_writer::tag('form', $buttonhtml, array('action' => $aurl->out_omit_querystring()));
} else {
    $link = html_writer::tag('span', $strunavailable, array('style' => 'font-size:20px'));
}

$title = new html_table_cell($link);
$title->header = true;
$title->colspan = $numcolumns;
$table->data[] = array($title);

// Only show sessions link to users with edit capability.
if ($iszoommanager) {
    $sessionsurl = new moodle_url('/mod/zoom/report.php', array('id' => $cm->id));
    $sessionslink = html_writer::link($sessionsurl, get_string('sessions', 'mod_zoom'));
    $sessions = new html_table_cell($sessionslink);
    $sessions->colspan = $numcolumns;
    $table->data[] = array($sessions);
}

// Generate add-to-calendar buttons if meeting was found.
if (!$showrecreate) {
    $googlelink = 'https://ucla.zoom.us/meeting/' . $zoom->meeting_id . '/calendar/google/add';
    $outlooklink = 'https://ucla.zoom.us/meeting/' . $zoom->meeting_id . '/ics';
    $googleicon = $OUTPUT->pix_icon('i/google', get_string('googleiconalt', 'mod_zoom'), 'mod_zoom');
    $windowsicon = $OUTPUT->pix_icon('i/windows', get_string('windowsiconalt', 'mod_zoom'), 'mod_zoom');
    $googlebutton = html_writer::div($googleicon . ' ' . get_string('googlecalendar', 'mod_zoom'), 'btn btn-primary');
    $outlookbutton = html_writer::div($windowsicon . ' ' . get_string('outlook', 'mod_zoom'), 'btn btn-primary');
    $googlehtml = html_writer::link($googlelink, $googlebutton, array('target' => '_blank'));
    $outlookhtml = html_writer::link($outlooklink, $outlookbutton, array('target' => '_blank'));
    $table->data[] = array(get_string('addtocalendar', 'mod_zoom'), $googlehtml . $outlookhtml);
}

if ($zoom->recurring) {
    $recurringmessage = new html_table_cell(get_string('recurringmeetinglong', 'mod_zoom'));
    $recurringmessage->colspan = $numcolumns;
    $table->data[] = array($recurringmessage);
} else {
    $table->data[] = array($strtime, userdate($zoom->start_time));
    $table->data[] = array($strduration, format_time($zoom->duration));
}

if (!$zoom->webinar) {
    $haspassword = (isset($zoom->password) && $zoom->password !== '');
    $strhaspass = ($haspassword) ? $stryes : $strno;
    $table->data[] = array($strpassprotect, $strhaspass);

    if ($zoomuserid === $zoom->host_id && $haspassword) {
        $table->data[] = array($strpassword, $zoom->password);
    }
}

if ($userishost) {
    $table->data[] = array($strjoinlink, html_writer::link($zoom->join_url, $zoom->join_url));
}

if (!$zoom->webinar) {
    $strjbh = ($zoom->option_jbh) ? $stryes : $strno;
    $table->data[] = array($strjoinbeforehost, $strjbh);

    $strvideohost = ($zoom->option_host_video) ? $stryes : $strno;
    $table->data[] = array($strstartvideohost, $strvideohost);

    $strparticipantsvideo = ($zoom->option_participants_video) ? $stryes : $strno;
    $table->data[] = array($strstartvideopart, $strparticipantsvideo);
}

$table->data[] = array($straudioopt, $zoom->option_audio);

if (!$zoom->recurring) {
    if ($zoom->status == ZOOM_MEETING_EXPIRED) {
        $status = get_string('meeting_expired', 'mod_zoom');
    } else if ($finished) {
        $status = get_string('meeting_finished', 'mod_zoom');
    } else if ($inprogress) {
        $status = get_string('meeting_started', 'mod_zoom');
    } else {
        $status = get_string('meeting_not_started', 'mod_zoom');
    }

    $table->data[] = array($strstatus, $status);
}

$urlall = new moodle_url('/mod/zoom/index.php', array('id' => $course->id));
$linkall = html_writer::link($urlall, $strall);
$linktoall = new html_table_cell($linkall);
$linktoall->colspan = $numcolumns;
$table->data[] = array($linktoall);

echo html_writer::table($table);

// Finish the page.
echo $OUTPUT->footer();
