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
 * English strings for zoom.
 *
 * @package    mod_zoom
 * @copyright  2015 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['actions'] = 'Actions';
$string['addtocalendar'] = 'Add to calendar';
$string['alternative_hosts'] = 'Alternative Hosts';
$string['alternative_hosts_help'] = 'The alternative host option allows you to schedule meetings and designate another Pro user on the same account to start the meeting or webinar if you are unable to. This user will receive an email notifying them that they\'ve been added as an alternative host, with a link to start the meeting. Separate multiple emails by comma (without spaces).';
$string['allmeetings'] = 'All meetings';
$string['apikey'] = 'Zoom API key';
$string['apikey_desc'] = '';
$string['apisecret'] = 'Zoom API secret';
$string['apisecret_desc'] = '';
$string['apiurl'] = 'Zoom API url';
$string['apiurl_desc'] = '';
$string['attentiveness_score'] = 'Attentiveness score*';
$string['attentiveness_score_help'] = '*Attentiveness score is lowered when a participant does not have Zoom in focus for more than 30 seconds when someone is sharing a screen.';
$string['audio_both'] = 'VoIP and Telephony';
$string['audio_telephony'] = 'Telephony only';
$string['audio_voip'] = 'VoIP only';
$string['auto_rec_cloud'] = 'Record on Cloud';
$string['auto_rec_local'] = 'Record on Local';
$string['auto_rec_none'] = 'Disabled';
$string['auto_recording'] = 'Auto Recording';
$string['auto_recording_help'] = 'Record on cloud option is only available to pre-authorized Zoom accounts.';
$string['cachedef_zoomid'] = 'The zoom user id of the user';
$string['cachedef_sessions'] = 'Information from the zoom get user report request';
$string['calendardescriptionURL'] = 'Meeting join URL: {$a}.';
$string['calendardescriptionintro'] = "\nDescription:\n{\$a}";
$string['calendariconalt'] = 'Calendar icon';
$string['clickjoin'] = 'Clicked join meeting button';
$string['connectionok'] = 'Connection working.';
$string['connectionfailed'] = 'Connection failed: ';
$string['connectionstatus'] = 'Connection status';
$string['defaultsettings'] = 'Default Zoom settings';
$string['defaultsettings_help'] = 'These settings define the defaults for all new Zoom meetings and webinars.';
$string['downloadical'] = 'Download iCal';
$string['duration'] = 'Duration (minutes)';
$string['recurringmeeting'] = 'Recurring';
$string['enable_recurring'] = 'Recurring';
$string['end_after'] = 'End after X occurrences';
$string['end_by_date'] = 'End by date';
$string['endtime'] = 'End time';
$string['ending_type'] = 'End type';
$string['err_duration_nonpositive'] = 'The duration must be positive.';
$string['err_duration_too_long'] = 'The duration cannot exceed 150 hours.';
$string['err_long_timeframe'] = 'Requested time frame too long, showing results of latest month in range.';
$string['err_password'] = 'Password may only contain the following characters: [a-z A-Z 0-9 @ - _ *]. Max of 10 characters.';
$string['err_start_time_past'] = 'The start date cannot be in the past.';
$string['errorwebservice'] = 'Zoom webservice error: {$a}.';
$string['export'] = 'Export';
$string['firstjoin'] = 'First able to join';
$string['firstjoin_desc'] = 'The earliest a user can join a scheduled meeting (minutes before start).';
$string['getmeetingreports'] = 'Get meeting report from Zoom';
$string['invalid_status'] = 'Status invalid, check the database.';
$string['join'] = 'Join';
$string['joinbeforehost'] = 'Join meeting before host';
$string['join_link'] = 'Join link';
$string['join_meeting'] = 'Join Meeting';
$string['jointime'] = 'Join time';
$string['leavetime'] = 'Leave time';
$string['licensesnumber'] = 'Number of licenses';
$string['redefinelicenses'] = 'Redefine licenses';
$string['lowlicenses'] = 'If the number of your licenses exceeds those required, then when you create each new activity by the user, it will be assigned a PRO license by lowering the status of another user. The option is effective when the number of active PRO-licenses is more than 5.';
$string['meeting_nonexistent_on_zoom'] = 'Nonexistent on Zoom';
$string['meeting_finished'] = 'Finished';
$string['meeting_not_started'] = 'Not started';
$string['meetingoptions'] = 'Meeting option';
$string['meetingoptions_help'] = '*Join before host* allows attendees to join the meeting before the host joins or when the host cannot attend the meeting.';
$string['meeting_started'] = 'In progress';
$string['meeting_time'] = 'Start Time';
$string['modulename'] = 'Zoom meeting';
$string['modulenameplural'] = 'Zoom Meetings';
$string['modulename_help'] = 'Zoom is a video and web conferencing platform that gives authorized users the ability to host online meetings.';
$string['newmeetings'] = 'New Meetings';
$string['nomeetinginstances'] = 'No sessions found for this meeting.';
$string['noparticipants'] = 'No participants found for this session at this time.';
$string['nosessions'] = 'No sessions found for specified range.';
$string['nozooms'] = 'No meetings';
$string['off'] = 'Off';
$string['oldmeetings'] = 'Concluded Meetings';
$string['on'] = 'On';
$string['option_audio'] = 'Audio options';
$string['option_host_video'] = 'Host video';
$string['option_jbh'] = 'Enable join before host';
$string['option_mute_upon_entry'] = 'Mute attendees upon entry';
$string['option_participants_video'] = 'Participants video';
$string['participants'] = 'Participants';
$string['password'] = 'Password';
$string['session_dates'] = 'Session dates';
$string['passwordprotected'] = 'Password Protected';
$string['pluginadministration'] = 'Manage Zoom meeting';
$string['pluginname'] = 'Zoom meeting';
$string['privacy:metadata:zoom_meeting_details'] = 'The database table that stores information about each meeting instance.';
$string['privacy:metadata:zoom_meeting_details:topic'] = 'The name of the meeting that the user attended.';
$string['privacy:metadata:zoom_meeting_participants'] = 'The database table that stores information about meeting participants.';
$string['privacy:metadata:zoom_meeting_participants:attentiveness_score'] = 'The participant\'s attentiveness score';
$string['privacy:metadata:zoom_meeting_participants:duration'] = 'How long the participant was in the meeting';
$string['privacy:metadata:zoom_meeting_participants:join_time'] = 'The time that the participant joined the meeting';
$string['privacy:metadata:zoom_meeting_participants:leave_time'] = 'The time that the participant left the meeting';
$string['privacy:metadata:zoom_meeting_participants:name'] = 'The name of the participant';
$string['privacy:metadata:zoom_meeting_participants:user_email'] = 'The email of the participant';
$string['recurringmeeting'] = 'Recurring';
$string['recurrence_type'] = 'Recurrence';
$string['recurring_days'] = 'Repeat after X days';
$string['recurring_months'] = 'Repeat after X months';
$string['recurring_weeks'] = 'Repeat after X weeks';
$string['recurringmeeting_help'] = 'Has no end date';
$string['recurringmeetinglong'] = 'Recurring meeting (meeting with no end date or time)';
$string['report'] = 'Reports';
$string['reportapicalls'] = 'Report API calls exhausted';
$string['requirepassword'] = 'Require meeting password';
$string['enablenotifymail'] = 'Mail Notification';
$string['enableremindermail'] = 'Reminder Mail';
$string['resetapicalls'] = 'Reset the number of available API calls';
$string['search:activity'] = 'Zoom - activity information';
$string['sessions'] = 'Sessions';
$string['start'] = 'Start';
$string['starthostjoins'] = 'Start video when host joins';
$string['start_meeting'] = 'Start Meeting';
$string['startpartjoins'] = 'Start video when participant joins';
$string['start_time'] = 'When';
$string['starttime'] = 'Start time';
$string['status'] = 'Status';
$string['time_zone'] = 'Time zone';
$string['title'] = 'Title';
$string['topic'] = 'Topic';
$string['type'] = 'Type';
$string['unavailable'] = 'Unable to join at this time';
$string['updatemeetings'] = 'Update meeting settings from Zoom';
$string['usepersonalmeeting'] = 'Use personal meeting ID {$a}';
$string['webinar'] = 'Webinar';
$string['webinar_help'] = 'This option is only available to pre-authorized Zoom accounts.';
$string['enablenotifymail_desc'] = '';
$string['enableremindermail_desc'] = '';
$string['remindertime'] = 'Reminder Time';
$string['remindertime_desc'] = '';

$string['form_recurring'] = 'Recurring';
$string['form_recurring_help'] = '<p>This setting determines whether your session recurs on a daily, weekly, or monthly basis.</p>
<p><b>Daily</b>: This schedules a daily meeting. This works with the Every x days setting to determine if it is daily, every other day, etc.</p>
<p><b>Weekly</b>: This enables the Weekly setting, allowing you to check the days of the week the session occurs.</p>
<p><b>Monthly</b>: This allows you to schedule a session that occurs on a numbered day of the month (ie, every 3rd day of the month) or specified to a particular day (ie. every 1st Sunday).</p>';

$string['form_repeat_type'] = 'Repeat type';
$string['form_repeat_type_help'] = 'The session repeat type determines if the session is scheduled to occur once <b>(Recurring with no fixed time)</b> or multiple times <b>(Recurring with fixed time)</b>.  
If you select a Recurring session, the settings below will be turned on for you to define how often the session takes place <b>(Daily, Weekly or Monthly)</b> and when the session is scheduled to end.';
$string['form_recurringdays'] = 'Every X day(s)';
$string['form_recurringdays_help'] = 'This setting works with the Daily recurring type to control how often the session is scheduled.  
If you want to the session to take place every other day, then select 2.  To schedule a session every 3 days of the week, you would select 3, and so forth.';
$string['form_weeklygrp'] = 'Weekly';
$string['form_weeklygrp_help'] = 'Select the day(s) of the week your Weekly recurring sessions will occur on.  For example, a session could be scheduled for every Tuesday and Thursday.';
$string['form_monthlygrp'] = 'Monthly';
$string['form_monthlygrp_help'] = 'Here you can schedule your Monthly recurring session to occur on a numbered day of the month (ie, every 3rd day of the month) 
or specified to a particular day of the week (ie. every 1st Sunday of the month).';
$string['form_everymonths'] = 'of every X month(s)';
$string['form_everymonths_help'] = 'This will schedule your monthly session to fall on every numbered day of the month that you define.  
For example, if you select 5, your session will happen on the 5th day of each month - no matter what the actual day is (the 5th day could land on a Thursday one month and be a Saturday another).';
$string['form_endingtype'] = 'Ending type';
$string['form_endingtype_help'] = '<p>Set an ending type for your recurring session. You have two choices:</p>
<ol>
	<li>
		<p>Ending: This allows you to set a specific date, month and year for the sessions to end using the Ending on setting.</p>
	</li>
	<li>
		<p>Ending after X sessions: this option will allow you to choose the total number of sessions you plan to schedule using the ending After X sessions setting. 
		For example, you can define a session to end after 6 meetings. This would set a daily session to end after 6 days, a weekly session to end after 6 weeks, and a monthly session to end after 6 months.</p>
	</li>
</ol>';
$string['form_enddate'] = 'Ending on';
$string['form_enddate_help'] = 'This setting works with the Ending type "Ending."  The date you set here will be the last date your recurring session occurs on.';
$string['form_endafter'] = 'Every after X sessions(s)';
$string['form_endafter_help'] = 'This setting defines the total number of recurring sessions scheduled. For example, you can define a session\'s schedule to end after 6 meetings.  
This will work with whatever session type you defined previously (ie, if you defined a Weekly session on Thursday, the session would end after 6 Thursdays).';
$string['form_enable_notify_mail'] = 'Enable mail notification';
$string['form_enable_notify_mail_help'] = '';
$string['form_enable_reminder_mail'] = 'Enable mail reminder';
$string['form_enable_reminder_mail_help'] = '';
$string['webinar_already_true'] = '<p><b>This module was already set as a webinar, not meeting. You cannot toggle this setting after creating the webinar.</b></p>';
$string['webinar_already_false'] = '<p><b>This module was already set as a meeting, not webinar. You cannot toggle this setting after creating the meeting.</b></p>';
$string['zoom:addinstance'] = 'Add a new Zoom meeting';
$string['zoomerr'] = 'An error occured with Zoom.'; // Generic error.
$string['zoomerr_apikey_missing'] = 'Zoom API key not found';
$string['zoomerr_apisecret_missing'] = 'Zoom API secret not found';
$string['zoomerr_id_missing'] = 'You must specify a course_module ID or an instance ID';
$string['zoomerr_licensescount_missing'] = 'Zoom utmost setting found but, licensescount setting not found';
$string['zoomerr_meetingnotfound'] = 'This meeting cannot be found on Zoom. You can <a href="{$a->recreate}">recreate it here</a> or <a href="{$a->delete}">delete it completely</a>.';
$string['zoomerr_meetingnotfound_info'] = 'This meeting cannot be found on Zoom. Please contact the meeting host if you have questions.';
$string['zoomerr_usernotfound'] = 'Unable to find your account on Zoom. If you are using Zoom for the first time, you must Zoom account by logging into Zoom <a href="{$a}" target="_blank">{$a}</a>. Once you\'ve activated your Zoom account, reload this page and continue setting up your meeting. Else make sure your email on Zoom matches your email on this system.';
$string['zoomurl'] = 'Zoom home page URL';
$string['zoomurl_desc'] = '';
$string['zoom:view'] = 'View Zoom meetings';
$string['send_zoom_notifications'] = 'Send zoom meeting notifications and reminders';

// Email
$string['msg_header'] = 'Dear {$a},';
$string['msg_attendee_desc'] = 'You have been requested to attend the following session.';
$string['msg_host_desc'] = 'You are scheduled to host the following Session.';
$string['msg_session_name'] = 'Session name: {$a}';
$string['msg_session_date'] = 'Date: {$a}';
$string['msg_session_time'] = 'Time: {$a}';
$string['msg_session_duration'] = 'Duration: {$a} minutes';
$string['msg_session_key'] = 'Session key: {$a}';
$string['msg_session_agenda'] = 'Agenda: {$a}';
$string['msg_footer'] = '
-------------------------------------------------------
To join the session
-------------------------------------------------------
1. Go to {$a}
2. Log in with your account.
3. Click the active link in the Join Session box.
4. Follow the instructions that appear on your screen.';
$string['msg_subject_invite'] = 'Session Invitation: {$a}';
$string['msg_subject_update'] = 'Session Update: {$a}';
$string['msg_subject_cancel'] = 'Session Canceled: {$a}';
$string['msg_subject_reminder'] = 'Session Reminder: {$a}';