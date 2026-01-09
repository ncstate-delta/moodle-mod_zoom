<?php
// This file is part of the Zoom YT plugin for Moodle - http://moodle.org/
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
 * Manage recordings page for teachers.
 *
 * Shows all Zoom sessions and their recording/YouTube status.
 *
 * @package    mod_zoom_yt
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);
$videoid = optional_param('videoid', 0, PARAM_INT);

// Get course module.
$cm = get_coursemodule_from_id('zoom_yt', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$zoom = $DB->get_record('zoom_yt', ['id' => $cm->instance], '*', MUST_EXIST);

$context = context_module::instance($cm->id);

// Require login and capability.
require_login($course, true, $cm);
require_capability('mod/zoom_yt:addinstance', $context);

// Set up the page.
$PAGE->set_url('/mod/zoom_yt/manage_recordings.php', ['id' => $id]);
$PAGE->set_title(format_string($zoom->name) . ' - ' . get_string('manage_recordings', 'zoom_yt'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Handle actions.
if ($action === 'togglevisibility' && $videoid) {
    require_sesskey();
    $video = $DB->get_record('zoom_yt_videos', ['id' => $videoid, 'zoomid' => $zoom->id], '*', MUST_EXIST);
    $DB->set_field('zoom_yt_videos', 'visible', $video->visible ? 0 : 1, ['id' => $videoid]);
    redirect(new moodle_url('/mod/zoom_yt/manage_recordings.php', ['id' => $id]));
}

// Get all videos for this activity.
require_once($CFG->dirroot . '/mod/zoom_yt/classes/output/video_gallery.php');
$videos = \mod_zoom_yt\output\video_gallery::get_all_videos_for_management($zoom->id);

// Get Zoom meeting recordings that haven't been synced yet.
$sql = "SELECT zmd.*, zmr.id as recordingid, zmr.recordingtype, zmr.recordingstart
        FROM {zoom_yt_meeting_details} zmd
        JOIN {zoom_yt} z ON z.id = zmd.zoomid
        LEFT JOIN {zoom_yt_meeting_recordings} zmr ON zmr.meetinguuid = zmd.meetinguuid
        WHERE z.id = ?
        ORDER BY zmd.start_time DESC";
$meetings = $DB->get_records_sql($sql, [$zoom->id]);

// Group meetings by UUID.
$meetingdata = [];
foreach ($meetings as $meeting) {
    $uuid = $meeting->meetinguuid;
    if (!isset($meetingdata[$uuid])) {
        $meetingdata[$uuid] = [
            'uuid' => $uuid,
            'topic' => $meeting->topic ?? $zoom->name,
            'start_time' => $meeting->start_time,
            'duration' => $meeting->duration,
            'has_recording' => false,
            'recording_types' => [],
        ];
    }
    if (!empty($meeting->recordingid)) {
        $meetingdata[$uuid]['has_recording'] = true;
        $meetingdata[$uuid]['recording_types'][] = $meeting->recordingtype;
    }
}

// Output starts here.
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manage_recordings', 'zoom_yt'));

// Back link.
$backurl = new moodle_url('/mod/zoom_yt/view.php', ['id' => $cm->id]);
echo html_writer::tag('p', html_writer::link($backurl, '&laquo; ' . get_string('back')));

// YouTube Videos Section.
echo $OUTPUT->heading(get_string('video_gallery', 'zoom_yt'), 3);

if (empty($videos)) {
    echo html_writer::tag('p', get_string('no_videos', 'zoom_yt'), ['class' => 'alert alert-info']);
} else {
    $table = new html_table();
    $table->head = [
        get_string('name'),
        get_string('session_date', 'zoom_yt'),
        get_string('youtube_status', 'zoom_yt'),
        get_string('visibility'),
        get_string('actions'),
    ];
    $table->attributes['class'] = 'table table-striped';

    foreach ($videos as $video) {
        $row = new html_table_row();

        // Title.
        $titlecell = $video->title;
        if ($video->has_youtube) {
            $titlecell = html_writer::link($video->youtube_url, $video->title, ['target' => '_blank']);
        }

        // Status with badge.
        $statusclass = 'badge-secondary';
        if ($video->status === 'uploaded') {
            $statusclass = 'badge-success';
        } else if ($video->status === 'failed') {
            $statusclass = 'badge-danger';
        } else if (in_array($video->status, ['downloading', 'uploading'])) {
            $statusclass = 'badge-warning';
        }
        $statuscell = html_writer::span($video->status_label, 'badge ' . $statusclass);

        if ($video->status === 'failed' && $video->error_message) {
            $statuscell .= html_writer::tag('small', ' ' . $video->error_message, ['class' => 'text-danger d-block']);
        }

        // Visibility.
        $visibletext = $video->visible ? get_string('video_visible', 'zoom_yt') : get_string('video_hidden', 'zoom_yt');
        $visibleclass = $video->visible ? 'text-success' : 'text-muted';
        $visiblecell = html_writer::span($visibletext, $visibleclass);

        // Actions.
        $actions = [];
        $toggleurl = new moodle_url('/mod/zoom_yt/manage_recordings.php', [
            'id' => $id,
            'action' => 'togglevisibility',
            'videoid' => $video->id,
            'sesskey' => sesskey(),
        ]);
        $toggleicon = $video->visible ? 'fa-eye-slash' : 'fa-eye';
        $toggletitle = get_string('toggle_video_visibility', 'zoom_yt');
        $actions[] = html_writer::link($toggleurl, '<i class="fa ' . $toggleicon . '"></i>', [
            'title' => $toggletitle,
            'class' => 'btn btn-sm btn-outline-secondary',
        ]);

        if ($video->has_youtube) {
            $actions[] = html_writer::link($video->youtube_url, '<i class="fa fa-external-link"></i>', [
                'target' => '_blank',
                'title' => get_string('view_on_youtube', 'zoom_yt'),
                'class' => 'btn btn-sm btn-outline-primary',
            ]);
        }

        $row->cells = [
            $titlecell,
            $video->session_date,
            $statuscell,
            $visiblecell,
            implode(' ', $actions),
        ];

        $table->data[] = $row;
    }

    echo html_writer::table($table);
}

// Past Zoom Sessions Section.
echo $OUTPUT->heading(get_string('sessions', 'zoom_yt'), 3);

if (empty($meetingdata)) {
    echo html_writer::tag('p', get_string('nosessions', 'zoom_yt'), ['class' => 'alert alert-info']);
} else {
    $table = new html_table();
    $table->head = [
        get_string('name'),
        get_string('date'),
        get_string('duration'),
        get_string('zoom_recording_status', 'zoom_yt'),
    ];
    $table->attributes['class'] = 'table table-striped';

    foreach ($meetingdata as $meeting) {
        $row = new html_table_row();

        // Recording status.
        if ($meeting['has_recording']) {
            $recstatus = html_writer::span(
                get_string('recording_available', 'zoom_yt') . ' (' . implode(', ', array_unique($meeting['recording_types'])) . ')',
                'badge badge-success'
            );
        } else {
            $recstatus = html_writer::span(get_string('recording_not_available', 'zoom_yt'), 'badge badge-secondary');
        }

        $row->cells = [
            $meeting['topic'],
            userdate($meeting['start_time'], get_string('strftimedatetime')),
            format_time($meeting['duration'] * 60),
            $recstatus,
        ];

        $table->data[] = $row;
    }

    echo html_writer::table($table);
}

echo $OUTPUT->footer();
