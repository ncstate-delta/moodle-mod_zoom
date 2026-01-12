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
 * @package    mod_zoomyt
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
$cm = get_coursemodule_from_id('zoomyt', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$zoom = $DB->get_record('zoomyt', ['id' => $cm->instance], '*', MUST_EXIST);

$context = context_module::instance($cm->id);

// Require login and capability.
require_login($course, true, $cm);
require_capability('mod/zoomyt:addinstance', $context);

// Set up the page.
$PAGE->set_url('/mod/zoomyt/manage_recordings.php', ['id' => $id]);
$PAGE->set_title(format_string($zoom->name) . ' - ' . get_string('manage_recordings', 'zoomyt'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Handle actions.
if ($action === 'togglevisibility' && $videoid) {
    require_sesskey();
    $video = $DB->get_record('zoomyt_videos', ['id' => $videoid, 'zoomid' => $zoom->id], '*', MUST_EXIST);
    $DB->set_field('zoomyt_videos', 'visible', $video->visible ? 0 : 1, ['id' => $videoid]);
    redirect(new moodle_url('/mod/zoomyt/manage_recordings.php', ['id' => $id]));
}

// Handle sync recordings action - run both get_meeting_reports and get_meeting_recordings tasks.
if ($action === 'syncrecordings') {
    require_sesskey();
    require_once($CFG->dirroot . '/mod/zoomyt/classes/task/get_meeting_reports.php');
    require_once($CFG->dirroot . '/mod/zoomyt/classes/task/get_meeting_recordings.php');

    $messages = [];
    $errors = [];

    // First, sync meeting reports (session data).
    try {
        $task = new \mod_zoomyt\task\get_meeting_reports();
        $task->execute_for_instance($zoom->id);
        $messages[] = get_string('sync_reports_success', 'zoomyt');
    } catch (Exception $e) {
        $errors[] = get_string('sync_reports_error', 'zoomyt', $e->getMessage());
    }

    // Then, sync recordings.
    try {
        $task = new \mod_zoomyt\task\get_meeting_recordings();
        $task->execute_for_instance($zoom->id);
        $messages[] = get_string('sync_recordings_success', 'zoomyt');
    } catch (Exception $e) {
        $errors[] = get_string('sync_recordings_error', 'zoomyt', $e->getMessage());
    }

    foreach ($messages as $msg) {
        \core\notification::success($msg);
    }
    foreach ($errors as $err) {
        \core\notification::error($err);
    }

    redirect(new moodle_url('/mod/zoomyt/manage_recordings.php', ['id' => $id]));
}

// Handle sync to YouTube action.
if ($action === 'syncyoutube') {
    require_sesskey();
    require_once($CFG->dirroot . '/mod/zoomyt/classes/task/sync_recordings_to_youtube.php');

    try {
        $task = new \mod_zoomyt\task\sync_recordings_to_youtube();
        // Run just for this specific zoom instance.
        $task->execute_for_instance($zoom->id);
        \core\notification::success(get_string('sync_youtube_success', 'zoomyt'));
    } catch (Exception $e) {
        \core\notification::error(get_string('sync_youtube_error', 'zoomyt', $e->getMessage()));
    }
    redirect(new moodle_url('/mod/zoomyt/manage_recordings.php', ['id' => $id]));
}

// Get all videos for this activity.
require_once($CFG->dirroot . '/mod/zoomyt/classes/output/video_gallery.php');
$videos = \mod_zoomyt\output\video_gallery::get_all_videos_for_management($zoom->id);

// Get Zoom meeting recordings that haven't been synced yet.
// Use CONCAT to create a unique key for each row (uuid + recordingid).
$sql = "SELECT CONCAT(zmd.uuid, '-', COALESCE(zmr.id, 0)) as uniquekey,
               zmd.uuid, zmd.meeting_id, zmd.start_time, zmd.end_time, 
               zmd.duration, zmd.topic, zmd.total_minutes, zmd.participants_count, zmd.zoomid,
               zmr.id as recordingid, zmr.recordingtype, zmr.recordingstart
        FROM {zoomyt_meeting_details} zmd
        JOIN {zoomyt} z ON z.id = zmd.zoomid
        LEFT JOIN {zoomyt_meeting_recordings} zmr ON zmr.meetinguuid = zmd.uuid
        WHERE z.id = ?
        ORDER BY zmd.start_time DESC";
$meetings = $DB->get_records_sql($sql, [$zoom->id]);

// Group meetings by UUID.
$meetingdata = [];
foreach ($meetings as $meeting) {
    $uuid = $meeting->uuid;
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
echo $OUTPUT->heading(get_string('manage_recordings', 'zoomyt'));

// Back link.
$backurl = new moodle_url('/mod/zoomyt/view.php', ['id' => $cm->id]);
echo html_writer::tag('p', html_writer::link($backurl, '&laquo; ' . get_string('back')));

// Sync action buttons.
$syncrecordingsurl = new moodle_url('/mod/zoomyt/manage_recordings.php', [
    'id' => $id,
    'action' => 'syncrecordings',
    'sesskey' => sesskey(),
]);
$syncyoutubeurl = new moodle_url('/mod/zoomyt/manage_recordings.php', [
    'id' => $id,
    'action' => 'syncyoutube',
    'sesskey' => sesskey(),
]);

echo html_writer::start_div('mb-3');
echo html_writer::link($syncrecordingsurl,
    '<i class="fa fa-refresh"></i> ' . get_string('sync_recordings_button', 'zoomyt'),
    ['class' => 'btn btn-outline-primary mr-2']
);
echo html_writer::link($syncyoutubeurl,
    '<i class="fa fa-youtube-play"></i> ' . get_string('sync_youtube_button', 'zoomyt'),
    ['class' => 'btn btn-outline-danger']
);
echo html_writer::end_div();

// YouTube Videos Section.
echo $OUTPUT->heading(get_string('video_gallery', 'zoomyt'), 3);

if (empty($videos)) {
    echo html_writer::tag('p', get_string('no_videos', 'zoomyt'), ['class' => 'alert alert-info']);
} else {
    $table = new html_table();
    $table->head = [
        get_string('name'),
        get_string('session_date', 'zoomyt'),
        get_string('youtube_status', 'zoomyt'),
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
        $visibletext = $video->visible ? get_string('video_visible', 'zoomyt') : get_string('video_hidden', 'zoomyt');
        $visibleclass = $video->visible ? 'text-success' : 'text-muted';
        $visiblecell = html_writer::span($visibletext, $visibleclass);

        // Actions.
        $actions = [];
        $toggleurl = new moodle_url('/mod/zoomyt/manage_recordings.php', [
            'id' => $id,
            'action' => 'togglevisibility',
            'videoid' => $video->id,
            'sesskey' => sesskey(),
        ]);
        $toggleicon = $video->visible ? 'fa-eye-slash' : 'fa-eye';
        $toggletitle = get_string('toggle_video_visibility', 'zoomyt');
        $actions[] = html_writer::link($toggleurl, '<i class="fa ' . $toggleicon . '"></i>', [
            'title' => $toggletitle,
            'class' => 'btn btn-sm btn-outline-secondary',
        ]);

        if ($video->has_youtube) {
            $actions[] = html_writer::link($video->youtube_url, '<i class="fa fa-external-link"></i>', [
                'target' => '_blank',
                'title' => get_string('view_on_youtube', 'zoomyt'),
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
echo $OUTPUT->heading(get_string('sessions', 'zoomyt'), 3);

if (empty($meetingdata)) {
    echo html_writer::tag('p', get_string('nosessions', 'zoomyt'), ['class' => 'alert alert-info']);
} else {
    $table = new html_table();
    $table->head = [
        get_string('name'),
        get_string('date'),
        get_string('duration', 'zoomyt'),
        get_string('zoom_recording_status', 'zoomyt'),
    ];
    $table->attributes['class'] = 'table table-striped';

    foreach ($meetingdata as $meeting) {
        $row = new html_table_row();

        // Recording status.
        if ($meeting['has_recording']) {
            $recstatus = html_writer::span(
                get_string('recording_available', 'zoomyt') . ' (' . implode(', ', array_unique($meeting['recording_types'])) . ')',
                'badge badge-success'
            );
        } else {
            $recstatus = html_writer::span(get_string('recording_not_available', 'zoomyt'), 'badge badge-secondary');
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
