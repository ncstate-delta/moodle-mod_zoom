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

$zoomuserid = zoom_get_user_id(false);
$alternativehosts = array();
if (!is_null($zoom->alternative_hosts)) {
    $alternativehosts = explode(",", $zoom->alternative_hosts);
}

$userishost = ($zoomuserid === $zoom->host_id || in_array($USER->email, $alternativehosts));

$service = new mod_zoom_webservice();
try {
    $service->get_meeting_webinar_info($zoom->meeting_id, $zoom->webinar);
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
$strsessiondates = get_string('session_dates', 'mod_zoom');
$strpassprotect = get_string('passwordprotected', 'mod_zoom');
$strpassword = get_string('password', 'mod_zoom');
$strjoinlink = get_string('join_link', 'mod_zoom');
$strjoinbeforehost = get_string('joinbeforehost', 'mod_zoom');
$strstartvideohost = get_string('starthostjoins', 'mod_zoom');
$strstartvideopart = get_string('startpartjoins', 'mod_zoom');
$straudioopt = get_string('option_audio', 'mod_zoom');
$strstatus = get_string('status', 'mod_zoom');
$strall = get_string('allmeetings', 'mod_zoom');
$strautorec = get_string('auto_recording', 'mod_zoom');
$strmuteopt = get_string('option_mute_upon_entry', 'mod_zoom');

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

list($inprogress, $available, $finished, $start_time) = zoom_get_state($zoom);

if ($available) {
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

$title = new html_table_cell($link);
$title->header = true;
$title->colspan = $numcolumns;
$table->data[] = array($title);

if (in_array($zoom->type, [ZOOM_RECURRING_MEETING_WITH_FIXED_TIME, ZOOM_RECURRING_WEBINAR])) {
    $sessiondates = '';
    $events = $DB->get_records_sql("SELECT *
                             FROM {event} e
                            WHERE modulename = 'zoom'
                                AND instance = $zoom->id
                         ORDER BY timestart ASC");

    foreach ($events as $value) {
        $sessiondates .= zoom_convert_date_time($value->timestart, 'jS F Y, g:i A') . ' ' . usertimezone().'<br/>';
    }
}

//Display session dates
$table->data[] = !empty($sessiondates)
    ? array($strsessiondates, $sessiondates)
    : zoom_convert_date_time($zoom->start_time, 'jS F Y, g:i A');

// Generate add-to-calendar button if meeting was found and isn't recurring.
if (!($showrecreate || $zoom->recurring)) {
    $icallink = new moodle_url('/mod/zoom/exportical.php', array('id' => $cm->id));
    $calendaricon = $OUTPUT->pix_icon('i/calendar', get_string('calendariconalt', 'mod_zoom'), 'mod_zoom');
    $calendarbutton = html_writer::div($calendaricon . ' ' . get_string('downloadical', 'mod_zoom'), 'btn btn-primary');
    $buttonhtml = html_writer::link((string) $icallink, $calendarbutton, array('target' => '_blank'));
    $table->data[] = array(get_string('addtocalendar', 'mod_zoom'), $buttonhtml);
}

if (in_array($zoom->type, [ZOOM_SCHEDULED_MEETING, ZOOM_RECURRING_MEETING_WITH_FIXED_TIME])) {
    $icallink = new moodle_url('/mod/zoom/exportical.php', array('id' => $cm->id));
    $calendaricon = $OUTPUT->pix_icon('i/calendar', get_string('calendariconalt', 'mod_zoom'), 'mod_zoom');
    $calendarbutton = html_writer::div($calendaricon . ' ' . get_string('downloadical', 'mod_zoom'), 'btn btn-primary');
    $buttonhtml = html_writer::link((string)$icallink, $calendarbutton, array('target' => '_blank'));
    $table->data[] = array(get_string('addtocalendar', 'mod_zoom'), $buttonhtml);
    $table->data[] = array($strtime, zoom_convert_date_time($start_time, 'jS F Y, g:i A'). ' '. usertimezone());
    $table->data[] = array($strduration, (int)$zoom->duration);
} else {
    $recurringmessage = new html_table_cell(get_string('recurringmeetinglong', 'mod_zoom'));
    $recurringmessage->colspan = $numcolumns;
    $table->data[] = array($recurringmessage);
}

if (!$zoom->webinar) {
    $strjbh = ($zoom->option_jbh) ? $stryes : $strno;
    $table->data[] = array($strjoinbeforehost, $strjbh);

    $strvideohost = ($zoom->option_host_video) ? $stryes : $strno;
    $table->data[] = array($strstartvideohost, $strvideohost);

    $strparticipantsvideo = ($zoom->option_participants_video) ? $stryes : $strno;
    $table->data[] = array($strstartvideopart, $strparticipantsvideo);
}
$strmuteonentry = ($zoom->option_mute_upon_entry) ? $stryes : $strno;
$table->data[] = array($strmuteopt, $strmuteonentry);

$table->data[] = array($straudioopt, get_string('audio_' . $zoom->option_audio, 'mod_zoom'));
$table->data[] = array($strautorec, get_string('auto_rec_' . $zoom->auto_recording, 'mod_zoom'));
$sql = "SELECT rec.play_url,rec.download_url,
        rec.status,rec.start_time
        from mdl_zoom_recordings as rec
        join mdl_zoom as zoom ON zoom.meeting_id=rec.meeting_id 
        where rec.meeting_id = {$zoom->meeting_id}";
$records = $DB->get_records_sql($sql);

if (!empty($records)) {
    
    if($zoom->enable_stream_url == 1 && $zoom->enable_download_url== 1){
        //$table->data[] = array(get_string('view_recording','zoom'));
        $display = '';
        foreach ($records as $key => $value) {
            $play_urls = $value->play_url;
                $download_urls = $value->download_url;
                $dates = $value->start_time;
                $rec_date = date_create($dates);
                $date = date_format($rec_date, 'd-m-Y');
                $display .= '<br>&nbsp;'.$date.'<br>'.'&nbsp;<a target="_blank" href="'.$play_urls.'">View |</a>&nbsp;<a target="_blank" href="'.$download_urls.'">Download</a>';   
            }
            $display .= '</br>';
            $table->data[] = [get_string('view_recording','zoom'),$display];
    } else if($zoom->enable_stream_url == 1){
        $display = '';
        foreach ($records as $key => $value) {
                $play_urls = $value->play_url;
                $dates = $value->start_time;
                $rec_date = date_create($dates);
                $date = date_format($rec_date, 'd-m-Y');
                $display .= '<br>&nbsp;'.$date.'<br>'.'&nbsp;<a target="_blank" href="'.$play_urls.'">View';
            }
            $display .= '</br>';
            $table->data[] = [get_string('view_recording','zoom'),$display];
    }else if($zoom->enable_download_url== 1){
        $display = '';
        foreach ($records as $key => $value) {
                $download_urls = $value->download_url;
                $dates = $value->start_time;
                $rec_date = date_create($dates);
                $date = date_format($rec_date, 'd-m-Y');
                $display .= '<br>&nbsp;'.$date.'<br>'.'&nbsp;<a target="_blank" href="'.$download_urls.'">Download';
            }
            $display .= '</br>';
            $table->data[] = [get_string('view_recording','zoom'),$display];
    } else{
            $table->data[] = array(get_string('view_recording', 'zoom'), get_string('err_recording_not_found', 'zoom'));
        }
}
if ($iszoommanager) {
    // Only show sessions link to users with edit capability.
    $sessionsurl = new moodle_url('/mod/zoom/report.php', array('id' => $cm->id));
    $sessionslink = html_writer::link($sessionsurl, get_string('sessions', 'mod_zoom'));
    $sessions = new html_table_cell($sessionslink);
    $sessions->colspan = $numcolumns;
    $table->data[] = array($sessions);

    // Display alternate hosts if they exist.
    if (!empty($zoom->alternative_hosts)) {
        $table->data[] = array(get_string('alternative_hosts', 'mod_zoom'), $zoom->alternative_hosts);
    }
}

if (!$zoom->recurring) {
    if (!$zoom->exists_on_zoom) {
        $status = get_string('meeting_nonexistent_on_zoom', 'mod_zoom');
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
