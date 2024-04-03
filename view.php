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
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/locallib.php');
require_once($CFG->libdir . '/moodlelib.php');

require_login();
// Additional access checks in zoom_get_instance_setup().
[$course, $cm, $zoom] = zoom_get_instance_setup();

$config = get_config('zoom');

$context = context_module::instance($cm->id);
$iszoommanager = has_capability('mod/zoom:addinstance', $context);

$event = \mod_zoom\event\course_module_viewed::create([
    'objectid' => $PAGE->cm->instance,
    'context' => $PAGE->context,
]);
$event->add_record_snapshot('course', $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $zoom);
$event->trigger();

// Print the page header.

$PAGE->set_url('/mod/zoom/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($zoom->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->requires->js_call_amd("mod_zoom/toggle_text", 'init');

// Get Zoom user ID of current Moodle user.
$zoomuserid = zoom_get_user_id(false);

// Check if this user is the (real) host.
$userisrealhost = ($zoomuserid === $zoom->host_id);

// Get the alternative hosts of the meeting.
$alternativehosts = zoom_get_alternative_host_array_from_string($zoom->alternative_hosts);

// Check if this user is the host or an alternative host.
$userishost = ($userisrealhost || in_array(zoom_get_api_identifier($USER), $alternativehosts, true));

// Get host user from Zoom.
$showrecreate = false;
if ($zoom->exists_on_zoom == ZOOM_MEETING_EXPIRED) {
    $showrecreate = true;
} else {
    try {
        zoom_webservice()->get_meeting_webinar_info($zoom->meeting_id, $zoom->webinar);
    } catch (\mod_zoom\webservice_exception $error) {
        $showrecreate = zoom_is_meeting_gone_error($error);

        if ($showrecreate) {
            // Mark meeting as expired.
            $updatedata = new stdClass();
            $updatedata->id = $zoom->id;
            $updatedata->exists_on_zoom = ZOOM_MEETING_EXPIRED;
            $DB->update_record('zoom', $updatedata);

            $zoom->exists_on_zoom = ZOOM_MEETING_EXPIRED;
        }
    } catch (moodle_exception $error) {
        // Ignore other exceptions.
        debugging($error->getMessage());
    }
}

$isrecurringnotime = ($zoom->recurring && $zoom->recurrence_type == ZOOM_RECURRINGTYPE_NOTIME);

$stryes = get_string('yes');
$strno = get_string('no');
$strstart = get_string('start_meeting', 'mod_zoom');
$strjoin = get_string('join_meeting', 'mod_zoom');
$strregister = get_string('register', 'mod_zoom');
$strtime = get_string('meeting_time', 'mod_zoom');
$strduration = get_string('duration', 'mod_zoom');
$strpassprotect = get_string('passwordprotected', 'mod_zoom');
$strpassword = get_string('password', 'mod_zoom');
$strjoinlink = get_string('joinlink', 'mod_zoom');
$strencryption = get_string('option_encryption_type', 'mod_zoom');
$strencryptionenhanced = get_string('option_encryption_type_enhancedencryption', 'mod_zoom');
$strencryptionendtoend = get_string('option_encryption_type_endtoendencryption', 'mod_zoom');
$strjoinbeforehost = get_string('joinbeforehost', 'mod_zoom');
$strstartvideohost = get_string('starthostjoins', 'mod_zoom');
$strstartvideopart = get_string('startpartjoins', 'mod_zoom');
$straudioopt = get_string('option_audio', 'mod_zoom');
$strstatus = get_string('status', 'mod_zoom');
$strall = get_string('allmeetings', 'mod_zoom');
$strwwaitingroom = get_string('waitingroom', 'mod_zoom');
$strmuteuponentry = get_string('option_mute_upon_entry', 'mod_zoom');
$strauthenticatedusers = get_string('option_authenticated_users', 'mod_zoom');
$strhost = get_string('host', 'mod_zoom');
$strmeetinginvite = get_string('meeting_invite', 'mod_zoom');
$strmeetinginviteshow = get_string('meeting_invite_show', 'mod_zoom');

// Output starts here.
echo $OUTPUT->header();

if ($CFG->branch < '400') {
    echo $OUTPUT->heading(format_string($zoom->name), 2);
}

// Show notification if the meeting does not exist on Zoom.
if ($showrecreate) {
    // Only show recreate/delete links in the message for users that can edit.
    if ($iszoommanager) {
        $message = get_string('zoomerr_meetingnotfound', 'mod_zoom', zoom_meetingnotfound_param($cm->id));
        $style = \core\output\notification::NOTIFY_ERROR;
    } else {
        $message = get_string('zoomerr_meetingnotfound_info', 'mod_zoom');
        $style = \core\output\notification::NOTIFY_WARNING;
    }

    echo $OUTPUT->notification($message, $style);
}

// Show intro.
if ($zoom->intro && $CFG->branch < '400') {
    echo $OUTPUT->box(format_module_intro('zoom', $zoom, $cm->id), 'generalbox mod_introbox', 'intro');
}

// Supplementary feature: Meeting capacity warning.
// Only show if the admin did not disable this feature completely.
if (!$showrecreate && $config->showcapacitywarning == true) {
    // Only show if the user viewing this is the host.
    if ($userishost) {
        // Get meeting capacity.
        $meetingcapacity = zoom_get_meeting_capacity($zoom->host_id, $zoom->webinar);

        // Get number of course participants who are eligible to join the meeting.
        $eligiblemeetingparticipants = zoom_get_eligible_meeting_participants($context);

        // If the number of eligible course participants exceeds the meeting capacity, output a warning.
        if ($eligiblemeetingparticipants > $meetingcapacity) {
            // Compose warning string.
            $participantspageurl = new moodle_url('/user/index.php', ['id' => $course->id]);
            $meetingcapacityplaceholders = [
                'meetingcapacity' => $meetingcapacity,
                'eligiblemeetingparticipants' => $eligiblemeetingparticipants,
                'zoomprofileurl' => $config->zoomurl . '/profile',
                'courseparticipantsurl' => $participantspageurl->out(),
                'hostname' => zoom_get_user_display_name($zoom->host_id),
            ];
            $meetingcapacitywarning = get_string('meetingcapacitywarningheading', 'mod_zoom');
            $meetingcapacitywarning .= html_writer::empty_tag('br');
            if ($userisrealhost == true) {
                $meetingcapacitywarning .= get_string(
                    'meetingcapacitywarningbodyrealhost',
                    'mod_zoom',
                    $meetingcapacityplaceholders
                );
            } else {
                $meetingcapacitywarning .= get_string(
                    'meetingcapacitywarningbodyalthost',
                    'mod_zoom',
                    $meetingcapacityplaceholders
                );
            }

            $meetingcapacitywarning .= html_writer::empty_tag('br');
            if ($userisrealhost == true) {
                $meetingcapacitywarning .= get_string('meetingcapacitywarningcontactrealhost', 'mod_zoom');
            } else {
                $meetingcapacitywarning .= get_string('meetingcapacitywarningcontactalthost', 'mod_zoom');
            }

            // Ideally, this would use $OUTPUT->notification(), but this renderer adds a close icon to the notification which
            // does not make sense here. So we build the notification manually.
            echo html_writer::tag('div', $meetingcapacitywarning, ['class' => 'alert alert-warning']);
        }
    }
}

// Get meeting state from Zoom.
[$inprogress, $available, $finished] = zoom_get_state($zoom);

// Show join meeting button or unavailability note.
if (!$showrecreate) {
    // If registration is required, check the registration.
    if (!$userishost && $zoom->registration != ZOOM_REGISTRATION_OFF) {
        $userisregistered = zoom_is_user_registered_for_meeting($USER->email, $zoom->meeting_id, $zoom->webinar);

        // Unregistered users are allowed to register.
        if (!$userisregistered) {
            $available = true;
        }
    }

    if ($available) {
        // Show join meeting button.
        if ($userishost) {
            $buttonhtml = html_writer::tag('button', $strstart, ['type' => 'submit', 'class' => 'btn btn-success']);
        } else {
            $btntext = $strjoin;
            // If user is not already registered, use register text.
            if ($zoom->registration != ZOOM_REGISTRATION_OFF && !$userisregistered) {
                $btntext = $strregister;
            }

            $buttonhtml = html_writer::tag('button', $btntext, ['type' => 'submit', 'class' => 'btn btn-primary']);
        }

        $aurl = new moodle_url('/mod/zoom/loadmeeting.php', ['id' => $cm->id]);
        $buttonhtml .= html_writer::input_hidden_params($aurl);
        $link = html_writer::tag('form', $buttonhtml, ['action' => $aurl->out_omit_querystring(), 'target' => '_blank']);
    } else {
        // Get unavailability note.
        $unavailabilitynote = zoom_get_unavailability_note($zoom, $finished);

        // Show unavailability note.
        // Ideally, this would use $OUTPUT->notification(), but this renderer adds a close icon to the notification which does not
        // make sense here. So we build the notification manually.
        $link = html_writer::tag('div', $unavailabilitynote, ['class' => 'alert alert-primary']);
    }

    echo $OUTPUT->box_start('generalbox text-center');
    echo $link;
    echo $OUTPUT->box_end();
}

if ($zoom->show_schedule) {
    // Output "Schedule" heading.
    echo $OUTPUT->heading(get_string('schedule', 'mod_zoom'), 3);

    // Start "Schedule" table.
    $table = new html_table();
    $table->attributes['class'] = 'generaltable mod_view';
    $table->align = ['center', 'left'];
    $table->size = ['35%', '65%'];
    $numcolumns = 2;

    // Show start/end date or recurring meeting information.
    if ($isrecurringnotime) {
        $table->data[] = [get_string('recurringmeeting', 'mod_zoom'), get_string('recurringmeetingexplanation', 'mod_zoom')];
    } else if ($zoom->recurring && $zoom->recurrence_type != ZOOM_RECURRINGTYPE_NOTIME) {
        $table->data[] = [get_string('recurringmeeting', 'mod_zoom'), get_string('recurringmeetingthisis', 'mod_zoom')];
        $nextoccurrence = zoom_get_next_occurrence($zoom);
        if ($nextoccurrence > 0) {
            $table->data[] = [get_string('nextoccurrence', 'mod_zoom'), userdate($nextoccurrence)];
        } else {
            $table->data[] = [get_string('nextoccurrence', 'mod_zoom'), get_string('nooccurrenceleft', 'mod_zoom')];
        }

        $table->data[] = [$strduration, format_time($zoom->duration)];
    } else {
        $table->data[] = [$strtime, userdate($zoom->start_time)];
        $table->data[] = [$strduration, format_time($zoom->duration)];
    }

    // Show recordings section if option enabled to view recordings.
    if (!empty($config->viewrecordings)) {
        $recordinghtml = null;
        $recordingaddurl = new moodle_url('/mod/zoom/recordings.php', ['id' => $cm->id]);
        $recordingaddbutton = html_writer::div(get_string('recordingview', 'mod_zoom'), 'btn btn-primary');
        $recordingaddbuttonhtml = html_writer::link($recordingaddurl, $recordingaddbutton, ['target' => '_blank']);
        $recordingaddhtml = html_writer::div($recordingaddbuttonhtml);
        $recordinghtml .= $recordingaddhtml;

        $table->data[] = [get_string('recordings', 'mod_zoom'), $recordinghtml];
    }

    // Display add-to-calendar button if meeting was found and isn't recurring and if the admin did not disable the feature.
    if ($config->showdownloadical != ZOOM_DOWNLOADICAL_DISABLE && !$showrecreate && !$isrecurringnotime) {
        $icallink = new moodle_url('/mod/zoom/exportical.php', ['id' => $cm->id]);
        $calendaricon = $OUTPUT->pix_icon('i/calendar', get_string('calendariconalt', 'mod_zoom'));
        $calendarbutton = html_writer::div($calendaricon . ' ' . get_string('downloadical', 'mod_zoom'), 'btn btn-primary');
        $buttonhtml = html_writer::link((string) $icallink, $calendarbutton, ['target' => '_blank']);
        $table->data[] = [get_string('addtocalendar', 'mod_zoom'), $buttonhtml];
    }

    // Show meeting status.
    if ($zoom->exists_on_zoom == ZOOM_MEETING_EXPIRED) {
        $status = get_string('meeting_nonexistent_on_zoom', 'mod_zoom');
    } else if (!$isrecurringnotime) {
        if ($finished) {
            $status = get_string('meeting_finished', 'mod_zoom');
        } else if ($inprogress) {
            $status = get_string('meeting_started', 'mod_zoom');
        } else {
            $status = get_string('meeting_not_started', 'mod_zoom');
        }

        $table->data[] = [$strstatus, $status];
    }

    // Show host.
    $hostdisplayname = zoom_get_user_display_name($zoom->host_id);
    if (isset($hostdisplayname)) {
        $table->data[] = [$strhost, $hostdisplayname];
    }

    // Display alternate hosts if they exist and if the admin did not disable the feature.
    if ($iszoommanager) {
        if ($config->showalternativehosts != ZOOM_ALTERNATIVEHOSTS_DISABLE && !empty($zoom->alternative_hosts)) {
            // If the admin did show the alternative hosts user picker, we try to show the real names of the users here.
            if ($config->showalternativehosts == ZOOM_ALTERNATIVEHOSTS_PICKER) {
                // Unfortunately, the host is not only able to add alternative hosts in Moodle with the user picker.
                // He is also able to add any alternative host with an email address in Zoom directly.
                // Thus, we get a) the array of existing Moodle user objects and b) the array of non-Moodle user mail addresses
                // based on the given set of alternative host email addresses.
                $alternativehostusers = zoom_get_users_from_alternativehosts($alternativehosts);
                $alternativehostnonusers = zoom_get_nonusers_from_alternativehosts($alternativehosts);

                // Create a comma-separated string of the existing Moodle users' fullnames.
                $alternativehostusersstring = implode(', ', array_map('fullname', $alternativehostusers));

                // Create a comma-separated string of the non-Moodle users' mail addresses.
                foreach ($alternativehostnonusers as &$ah) {
                    $ah .= ' (' . get_string('externaluser', 'mod_zoom') . ')';
                }

                $alternativehostnonusersstring = implode(', ', $alternativehostnonusers);

                // Concatenate both strings.
                // If we have existing Moodle users and non-Moodle users.
                if ($alternativehostusersstring != '' && $alternativehostnonusersstring != '') {
                    $alternativehoststring = $alternativehostusersstring . ', ' . $alternativehostnonusersstring;

                    // If we just have existing Moodle users.
                } else if ($alternativehostusersstring != '') {
                    $alternativehoststring = $alternativehostusersstring;

                    // It seems as if we just have non-Moodle users.
                } else {
                    $alternativehoststring = $alternativehostnonusersstring;
                }

                // Output the concatenated string of alternative hosts.
                $table->data[] = [get_string('alternative_hosts', 'mod_zoom'), $alternativehoststring];

                // Otherwise we stick with the plain list of email addresses as we got it from Zoom directly.
            } else {
                $table->data[] = [get_string('alternative_hosts', 'mod_zoom'), $zoom->alternative_hosts];
            }
        }
    }

    // Show sessions link to users with edit capability.
    if ($iszoommanager) {
        $sessionsurl = new moodle_url('/mod/zoom/report.php', ['id' => $cm->id]);
        $sessionslink = html_writer::link($sessionsurl, get_string('sessionsreport', 'mod_zoom'));
        $table->data[] = [get_string('sessions', 'mod_zoom'), $sessionslink];
    }

    // Output table.
    echo html_writer::table($table);
}

if ($zoom->show_security) {
    // Output "Security" heading.
    echo $OUTPUT->heading(get_string('security', 'mod_zoom'), 3);

    // Start "Security" table.
    $table = new html_table();
    $table->attributes['class'] = 'generaltable mod_view';
    $table->align = ['center', 'left'];
    $table->size = ['35%', '65%'];
    $numcolumns = 2;

    // Get passcode information.
    $haspassword = (isset($zoom->password) && $zoom->password !== '');
    $strhaspass = ($haspassword) ? $stryes : $strno;
    $canviewjoinurl = ($userishost || has_capability('mod/zoom:viewjoinurl', $context));

    // Show passcode status.
    $table->data[] = [$strpassprotect, $strhaspass];

    // Show passcode.
    if ($haspassword && ($canviewjoinurl || get_config('zoom', 'displaypassword'))) {
        $table->data[] = [$strpassword, $zoom->password];
    }

    // Show join link.
    if ($canviewjoinurl) {
        $table->data[] = [$strjoinlink, html_writer::link($zoom->join_url, $zoom->join_url, ['target' => '_blank'])];
    }

    // Show encryption type.
    if (!$zoom->webinar) {
        if ($config->showencryptiontype != ZOOM_ENCRYPTION_DISABLE) {
            $strenc = ($zoom->option_encryption_type === ZOOM_ENCRYPTION_TYPE_E2EE)
                ? $strencryptionendtoend
                : $strencryptionenhanced;
            $table->data[] = [$strencryption, $strenc];
        }
    }

    // Show waiting room.
    if (!$zoom->webinar) {
        $strwr = ($zoom->option_waiting_room) ? $stryes : $strno;
        $table->data[] = [$strwwaitingroom, $strwr];
    }

    // Show join before host.
    if (!$zoom->webinar) {
        $strjbh = ($zoom->option_jbh) ? $stryes : $strno;
        $table->data[] = [$strjoinbeforehost, $strjbh];
    }

    // Show authentication.
    $table->data[] = [$strauthenticatedusers, ($zoom->option_authenticated_users) ? $stryes : $strno];

    // Output table.
    echo html_writer::table($table);
}

if ($zoom->show_media) {
    // Output "Media" heading.
    echo $OUTPUT->heading(get_string('media', 'mod_zoom'), 3);

    // Start "Media" table.
    $table = new html_table();
    $table->attributes['class'] = 'generaltable mod_view';
    $table->align = ['center', 'left'];
    $table->size = ['35%', '65%'];
    $numcolumns = 2;

    // Show host video.
    if (!$zoom->webinar) {
        $strvideohost = ($zoom->option_host_video) ? $stryes : $strno;
        $table->data[] = [$strstartvideohost, $strvideohost];
    }

    // Show participants video.
    if (!$zoom->webinar) {
        $strparticipantsvideo = ($zoom->option_participants_video) ? $stryes : $strno;
        $table->data[] = [$strstartvideopart, $strparticipantsvideo];
    }

    // Show audio options.
    $table->data[] = [$straudioopt, get_string('audio_' . $zoom->option_audio, 'mod_zoom')];

    // Show audio default configuration.
    $table->data[] = [$strmuteuponentry, ($zoom->option_mute_upon_entry) ? $stryes : $strno];

    // Show dial-in information.
    if (
        !$showrecreate
        && ($zoom->option_audio === ZOOM_AUDIO_BOTH || $zoom->option_audio === ZOOM_AUDIO_TELEPHONY)
        && ($userishost || has_capability('mod/zoom:viewdialin', $context))
    ) {
        // Get meeting invitation from Zoom.
        $meetinginvite = zoom_webservice()->get_meeting_invitation($zoom)->get_display_string($cm->id);
        // Show meeting invitation if there is any.
        if (!empty($meetinginvite)) {
            $meetinginvitetext = str_replace("\r\n", '<br/>', $meetinginvite);
            $showbutton = html_writer::tag(
                'button',
                $strmeetinginviteshow,
                ['id' => 'show-more-button', 'class' => 'btn btn-link pt-0 pl-0']
            );
            $meetinginvitebody = html_writer::div(
                $meetinginvitetext,
                '',
                ['id' => 'show-more-body', 'style' => 'display: none;']
            );
            $table->data[] = [$strmeetinginvite, html_writer::div($showbutton . $meetinginvitebody, '')];
        }
    }

    // Output table.
    echo html_writer::table($table);
}

// Supplementary feature: All meetings link.
// Only show if the admin did not disable this feature completely.
if ($config->showallmeetings != ZOOM_ALLMEETINGS_DISABLE) {
    $urlall = new moodle_url('/mod/zoom/index.php', ['id' => $course->id]);
    $linkall = html_writer::link($urlall, $strall);
    echo $OUTPUT->box_start('generalbox mt-4 pt-4 border-top text-center');
    echo $linkall;
    echo $OUTPUT->box_end();
}

// Finish the page.
echo $OUTPUT->footer();
