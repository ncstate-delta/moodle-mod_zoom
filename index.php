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
 * List all zoom meetings.
 *
 * @package    mod_zoom
 * @copyright  2015 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/locallib.php');

$id = required_param('id', PARAM_INT); // Course.

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

require_course_login($course);

$context = context_course::instance($course->id);
require_capability('mod/zoom:view', $context);

$params = array(
    'context' => $context
);
$event = \mod_zoom\event\course_module_instance_list_viewed::create($params);
$event->add_record_snapshot('course', $course);
$event->trigger();

$strname = get_string('modulenameplural', 'mod_zoom');
$strnew = get_string('newmeetings', 'mod_zoom');
$strold = get_string('oldmeetings', 'mod_zoom');

$strtopic = get_string('topic', 'mod_zoom');
$strtype = get_string('type', 'mod_zoom');
$strtime = get_string('meeting_time', 'mod_zoom');
$strduration = get_string('duration', 'mod_zoom');
$stractions = get_string('actions', 'mod_zoom');
$strmeetingstarted = get_string('meeting_started', 'mod_zoom');
$strstart = get_string('start', 'mod_zoom');
$strjoin = get_string('join', 'mod_zoom');

$PAGE->set_url('/mod/zoom/index.php', array('id' => $id));
$PAGE->navbar->add($strname);
$PAGE->set_title("$course->shortname: $strname");
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading($strname);

if (! $zooms = get_all_instances_in_course('zoom', $course)) {
    notice(get_string('nozooms', 'mod_zoom'), new moodle_url('/course/view.php', array('id' => $course->id)));
}

$usesections = course_format_uses_sections($course->format);

$cache = cache::make('mod_zoom', 'zoomid');
if (!($zoomuserid = $cache->get($USER->id))) {
    $zoomuserid = false;
    $service = new mod_zoom_webservice();
    if ($service->user_getbyemail($USER->email)) {
        $zoomuserid = $service->lastresponse->id;
    }
    $cache->set($USER->id, $zoomuserid);
}

$newtable = new html_table();
$newtable->attributes['class'] = 'generaltable mod_index';

$newhead = array($strtopic, $strtime, $strduration, $stractions);
$newalign = array('left', 'left', 'left', 'left');

$oldtable = new html_table();
$oldhead = array($strtopic, $strtime);
$oldalign = array('left', 'left');
// TODO: Add recordings here when they are ready.

if ($usesections) {
    $strsectionname = get_string('sectionname', 'format_'.$course->format);
    $newtable->head = array_merge(array($strsectionname), $newhead);
    $newtable->align = array_merge(array('center'), $newalign);
    $oldtable->head = array_merge(array($strsectionname), $oldhead);
    $oldtable->align = array_merge(array('center'), $oldalign);
} else {
    $newtable->head = $newhead;
    $newtable->align = $newalign;
    $oldtable->head = $oldhead;
    $oldtable->align = $oldalign;
}

$now = time();
$modinfo = get_fast_modinfo($course);
$cms = $modinfo->instances['zoom'];
foreach ($zooms as $z) {
    $row = array();
    list($inprogress, $available, $finished) = zoom_get_state($z);

    $cm = $cms[$z->id];
    if ($usesections && isset($cm->sectionnum)) {
        $row[0] = get_section_name($course, $cm->sectionnum);
    }

    $url = new moodle_url('view.php', array('id' => $cm->id));
    $row[1] = html_writer::link($url, $cm->get_formatted_name());
    // Recurring meetings have no start time or duration.
    $displaytime = ($z->type == ZOOM_RECURRING_MEETING) ?
            get_string('recurringmeetinglong', 'mod_zoom') : userdate($z->start_time);

    if ($finished) {
        $row[2] = $displaytime;
        $oldtable->data[] = $row;
    } else {
        if ($inprogress) {
            $label = html_writer::tag('span', $strmeetingstarted,
                    array('class' => 'label label-info zoom-info'));
            $row[2] = html_writer::tag('div', $label);
        } else {
            $row[2] = $displaytime;
        }

        $row[3] = ($z->type == ZOOM_RECURRING_MEETING) ?
                '--' : get_string('minutes', 'mod_zoom', $z->duration / 60);

        if ($available) {
            if ($zoomuserid === false || $zoomuserid != $z->host_id) {
                $buttonhtml = html_writer::tag('button', $strjoin,
                        array('type' => 'submit', 'class' => 'btn btn-primary'));
                $aurl = new moodle_url('/mod/zoom/loadmeeting.php', array('id' => $cm->id));
            } else {
                $buttonhtml = html_writer::tag('button', $strstart,
                        array('type' => 'submit', 'class' => 'btn btn-success'));
                $aurl = new moodle_url($z->start_url);
            }
            $buttonhtml .= html_writer::input_hidden_params($aurl);
            $row[4] = html_writer::tag('form', $buttonhtml, array('action' => $aurl->out_omit_querystring()));
        } else {
            $row[4] = '--';
        }

        $newtable->data[] = $row;
    }
}

echo $OUTPUT->heading($strnew, 4);
echo html_writer::table($newtable);
echo $OUTPUT->heading($strold, 4);
echo html_writer::table($oldtable);

echo $OUTPUT->footer();
