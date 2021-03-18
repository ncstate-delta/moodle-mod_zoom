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
$string['alternative_hosts_help'] = "The alternative host option allows you to schedule meetings and designate other Zoom user(s) to start the meeting as well. These users will receive an email from Zoom notifying them that they've been added as an alternative host, with a link to start the meeting.\n\nAs input format, please provide the email address(es) of the alternative host(s). You can separate multiple emails by a comma (without spaces).";
$string['alternative_hosts_desc'] = 'With this setting, you can control if the option to choose alternative hosts is shown to users in the activity instance settings or not. Two types of widgets are available: A plain input field which accepts comma-separated email addresses. And a user picker with autocompletion which provides easy selection of users who are enrolled into the course, have a Zoom account and have a role out of {$a->roles}. Alternative hosts which might be set by the teacher in Zoom directly but might not be selectable from within the Moodle user picker are still shown on the activity overview page and are also preserved when a meeting is updated from within Moodle.';
$string['alternative_hosts_disable'] = 'Disable alternative hosts option';
$string['alternative_hosts_inputfield'] = 'Show alternative hosts option as plain input field';
$string['alternative_hosts_picker'] = 'Show alternative hosts option as user picker with autocompletion';
$string['alternative_hosts_picker_help'] = "The alternative host option allows you to schedule meetings and designate other Zoom user(s) enrolled in this course to start the meeting as well. These users will receive an email from Zoom notifying them that they've been added as an alternative host, with a link to start the meeting.\n\nYou can pick one or multiple alternative hosts based on your meeting needs.\n\nIf you can't find a particular user in this user picker, this user either is not enrolled into this course with an appropriate role or does not have an eligible account on Zoom.";
$string['alternative_hosts_picker_noneselected'] = 'No alternative host selected';
$string['alternative_hosts_picker_placeholder'] = 'Select user(s)';
$string['allmeetings'] = 'All meetings';
$string['allmeetings_desc'] = 'With this setting, you can control if a link to the Zoom activity index page will be shown at the bottom of every activity instance overview page or not. This setting only affects the presentation of the link on the Moodle activity overview pages. Even if you decide not to show the link there, the user might still be able to access the Zoom activity index page through other links within the course.';
$string['allmeetings_disable'] = 'Disable all meetings link';
$string['allmeetings_enable'] = 'Enable all meetings link';
$string['apikey'] = 'Zoom API key';
$string['apikey_desc'] = '';
$string['apisecret'] = 'Zoom API secret';
$string['apisecret_desc'] = '';
$string['apiurl'] = 'Zoom API url';
$string['apiurl_desc'] = '';
$string['audio_both'] = 'Computer audio and Telephone';
$string['audio_telephony'] = 'Telephone only';
$string['audio_voip'] = 'Computer audio only';
$string['audiodefault'] = 'Audio default';
$string['authentication'] = 'Authentication';
$string['cachedef_zoomid'] = 'The zoom user id of the user';
$string['cachedef_sessions'] = 'Information from the zoom get user report request';
$string['cachedef_zoommeetingsecurity'] = 'Zoom meeting security settings, including meeting password requirements of the account';
$string['calendardescriptionURL'] = 'Meeting join URL: {$a}.';
$string['calendardescriptionintro'] = "\nDescription:\n{\$a}";
$string['calendariconalt'] = 'Calendar icon';
$string['changehost'] = 'Change host';
$string['clickjoin'] = 'Clicked join meeting button';
$string['connectionok'] = 'Connection working.';
$string['connectionfailed'] = 'Connection failed: ';
$string['connectionsettings'] = 'Connection settings';
$string['connectionsettings_desc'] = 'These settings define how Moodle connects to Zoom.';
$string['connectionstatus'] = 'Connection status';
$string['defaultsettings'] = 'Default Zoom settings';
$string['defaultsettings_help'] = 'These settings define the defaults for all new Zoom meetings and webinars.';
$string['displayleadtime'] = 'Display lead time';
$string['displayleadtime_desc'] = 'If enabled, the leadtime will be displayed to the users. This way, users are informed that / when they can join the meeting before the scheduled start time. This might keep users from constantly reloading the page until they can join.';
$string['displaypassword'] = 'Display passcode';
$string['displaypassword_help'] = 'If enabled the meeting passcode will always be displayed to non-hosts.';
$string['downloadical'] = 'Download iCal';
$string['downloadical_desc'] = 'With this setting, you can control if a link to download an iCal file for the meeting will be shown on the activity instance overview page or not. This setting only affects the possibility to download an iCal file for third-party calendar tools. Regardless of this setting, the Zoom meeting activity will add a calendar entry into the Moodle calendar as soon as a meeting start date is set.';
$string['downloadical_disable'] = 'Disable download iCal link';
$string['downloadical_enable'] = 'Enable download iCal link';
$string['duration'] = 'Duration (minutes)';
$string['encryptiontype'] = 'Encryption type';
$string['encryptiontype_desc'] = 'With this setting, you can control if the option to choose end-to-end encryption over enhanced encryption is shown to users in the activity instance settings or not. This setting only affects the Moodle activity instance settings. Even if you decide to always show the option, the user will still need end-to-end encryption in Zoom to finally enable end-to-end encryption.';
$string['encryptiontype_disable'] = 'Disable encryption type chooser';
$string['encryptiontype_showonlyife2epossible'] = 'Show encryption type chooser only if the user can use end-to-end encryption';
$string['encryptiontype_alwaysshow'] = 'Always show encryption type chooser regardless if the user can use end-to-end encryption or not';
$string['endtime'] = 'End time';
$string['err_downloadicaldisabled'] = 'Downloading Zoom meeting iCal files was disabled.';
$string['err_duration_nonpositive'] = 'The duration must be positive.';
$string['err_duration_too_long'] = 'The duration cannot exceed 150 hours.';
$string['err_long_timeframe'] = 'Requested time frame too long, showing results of latest month in range.';
$string['err_invalid_password'] = 'Passcode contains invalid characters.';
$string['err_password'] = 'Passcode may only contain the following characters: [a-z A-Z 0-9 @ - _ *]. Max of 10 characters.';
$string['err_password_required'] = 'Passcode is required.';
$string['err_start_time_past'] = 'The start date cannot be in the past.';
$string['errorwebservice_badrequest'] = 'Zoom received a bad request: {$a}';
$string['errorwebservice_notfound'] = 'The resource does not exists';
$string['errorwebservice'] = 'Zoom webservice error: {$a}.';
$string['export'] = 'Export';
$string['externaluser'] = 'External user';
$string['firstjoin'] = 'First able to join';
$string['firstjoin_desc'] = 'The earliest a user can join a scheduled meeting (minutes before start).';
$string['getmeetingreports'] = 'Get meeting report from Zoom';
$string['globalsettings'] = 'Global settings';
$string['globalsettings_desc'] = 'These settings apply to the Zoom plugin as a whole.';
$string['host'] = 'Host';
$string['hostintro'] = '<a target="_blank" href="https://support.zoom.us/hc/en-us/articles/208220166">Alternative Hosts</a> can start Zoom meetings and manage the Waiting Room.';
$string['indicator:cognitivedepth'] = 'Zoom cognitive';
$string['indicator:cognitivedepth_help'] = 'This indicator is based on the cognitive depth reached by the student in a Zoom activity.';
$string['indicator:socialbreadth'] = 'Zoom social';
$string['indicator:socialbreadth_help'] = 'This indicator is based on the social breadth reached by the student in a Zoom activity.';
$string['invalidscheduleuser'] = 'You cannot schedule for the specified user.';
$string['invalid_status'] = 'Status invalid, check the database.';
$string['invitationtext'] = 'Display invitation text';
$string['invitationtext_desc'] = 'This setting allows you to choose whether to display the meeting invitation text from Zoom. By default, this text contains call in phone numbers and direct links to meetings in Zoom. If enabled, this text will be displayed on the activity page and in the iCal description.';
$string['invitationtext_disable'] = 'Disable invitation text';
$string['invitationtext_enable'] = 'Enable invitation text';
$string['join'] = 'Join';
$string['joinbeforehost'] = 'Join meeting before host';
$string['joinbeforehostenable'] = 'Allow participants to join anytime';
$string['join_link'] = 'Join link';
$string['join_meeting'] = 'Join Meeting';
$string['jointime'] = 'Join time';
$string['leavetime'] = 'Leave time';
$string['licensesnumber'] = 'Number of licenses';
$string['licensesettings'] = 'License settings';
$string['licensesettings_desc'] = 'These settings define the way how Moodle handles your Zoom license.';
$string['redefinelicenses'] = 'Redefine licenses';
$string['lowlicenses'] = 'If the number of your licenses exceeds those required, then when you create each new activity by the user, it will be assigned a PRO license by lowering the status of another user. The option is effective when the number of active PRO-licenses is more than 5.';
$string['maskparticipantdata'] = 'Mask participant data';
$string['maskparticipantdata_help'] = 'Prevents participant data from appearing in reports (useful for sites that mask participant data, e.g., for HIPAA).';
$string['media'] = 'Media';
$string['meeting_nonexistent_on_zoom'] = 'Nonexistent on Zoom';
$string['meeting_invite'] = 'Phone/Dial-In info';
$string['meeting_invite_show'] = 'Show meeting invitation';
$string['meeting_invite_hide'] = 'Hide meeting invitation';
$string['meeting_finished'] = 'Finished';
$string['meeting_not_started'] = 'Not started';
$string['meeting_started'] = 'In progress';
$string['meeting_time'] = 'Start Time';
$string['meetingcapacitywarning'] = 'Meeting capacity warning';
$string['meetingcapacitywarning_desc'] = 'With this setting, you can show a warning notification if there are more active and enrolled participants in the course than the host\'s Zoom license meeting capacity is. The notification will be shown to the host (and alternative hosts) on the Zoom activity overview page. It will recommend the host to turn to the Zoom account owner to obtain a larger Zoom license if necessary. You can change this message through Moodle language customization.';
$string['meetingcapacitywarning_disable'] = 'Disable meeting capacity warning';
$string['meetingcapacitywarning_enable'] = 'Enable meeting capacity warning';
$string['meetingcapacitywarningheading'] = 'Meeting capacity warning:';
$string['meetingcapacitywarningbodyrealhost'] = 'Your Zoom license has a capacity of <strong><a href="{$a->zoomprofileurl}" target="_blank">{$a->meetingcapacity} meeting participants</a></strong>, but this course has <strong><a href="{$a->courseparticipantsurl}">{$a->eligiblemeetingparticipants} enrolled and active participants</a></strong>.';
$string['meetingcapacitywarningbodyalthost'] = 'The Zoom license of this meeting\'s host, {$a->hostname}, has a capacity of <strong>{$a->meetingcapacity} meeting participants</strong>, but this course has <strong><a href="{$a->courseparticipantsurl}">{$a->eligiblemeetingparticipants} enrolled and active participants</a></strong>.';
$string['meetingcapacitywarningcontactrealhost'] = 'Please turn to the Zoom account owner to obtain a larger Zoom license if all of these course participants need to join the meeting.';
$string['meetingcapacitywarningcontactalthost'] = 'Please ask the host to turn to the Zoom account owner to obtain a larger Zoom license if all of these course participants need to join the meeting.';
$string['modulename'] = 'Zoom meeting';
$string['modulenameplural'] = 'Zoom Meetings';
$string['modulename_help'] = 'Zoom is a video and web conferencing platform that gives authorized users the ability to host online meetings.';
$string['newmeetings'] = 'New Meetings';
$string['nomeetinginstances'] = 'No sessions found for this meeting.';
$string['noparticipants'] = 'No participants found for this session at this time.';
$string['nosessions'] = 'No sessions found for specified range.';
$string['nozooms'] = 'No meetings';
$string['nozoomsfound'] = 'No meetings found for the given course.';
$string['off'] = 'Off';
$string['oldmeetings'] = 'Concluded Meetings';
$string['on'] = 'On';
$string['option_audio'] = 'Audio options';
$string['option_audio_help'] = 'With this option, you can allow users to call in using Telephone only, Computer audio only or both';
$string['option_authenticated_users'] = 'Require authentication to join';
$string['option_authenticated_users_help'] = "Enabling this option requires all attendees to sign in with their authorized zoom account to be able to join the meeting. It does <em>not</em> relate to logging into Moodle in any way.";
$string['option_encryption_type'] = 'Encryption';
$string['option_encryption_type_help'] = "With this option, you control the level of encryption and privacy of this meeting.\n\n*Enhanced encryption* means that the encryption key is stored in the Zoom cloud.\n\n*End-to-end encryption* means that the encryption key is stored on your local device and no one else can obtain your encryption key, not even Zoom.\n\nPlease note that when you enable end-to-end encryption, several features will not be available from within the meeting - [See details in the Zoom documentation](https://support.zoom.us/hc/en-us/articles/360048660871).";
$string['option_encryption_type_enhancedencryption'] = 'Enhanced encryption';
$string['option_encryption_type_endtoendencryption'] = 'End-to-end encryption';
$string['option_host_video'] = 'Host video';
$string['option_host_video_help'] = 'Enabling this option will enable the host\'s video when joining the meeting. Even if you choose off, the host will have the option to start his/her video.';
$string['option_jbh'] = 'Join before host';
$string['option_jbh_help'] = "Enabling this option allows attendees to join the meeting before the host joins or when the host cannot attend the meeting.\n\nThis option is mutually exclusive with the 'Waiting room' option, so selecting one will disable the other.";
$string['option_mute_upon_entry'] = 'Mute participants upon entry';
$string['option_mute_upon_entry_help'] = 'Enabling this option wil automatically mute all participants when they join the meeting. Participants can unmute themselves after joining the meeting.';
$string['option_participants_video'] = 'Participants video';
$string['option_participants_video_help'] = 'Enabling this option will enable the participants\' video when joining the meeting. Even if you choose off, the participants will have the option to start their video.';
$string['option_proxyhost'] = 'Use proxy';
$string['option_proxyhost_desc'] = 'The proxy set here as \'<code>&lt;hostname&gt;:&lt;port&gt;</code>\' is used only for communicating with Zoom. Leave empty to use the Moodle default proxy settings. You only need to set this if you do not want to set a global proxy in Moodle.';
$string['option_waiting_room'] = 'Enable waiting room';
$string['option_waiting_room_help'] = "Enabling this option allows the host to control when a participant joins the meeting.\n\nThis option is mutually exclusive with the 'Join before host' option, so selecting one will disable the other.";
$string['participantdatanotavailable'] = 'Details not available';
$string['participantdatanotavailable_help'] = 'Participant data is not available for this Zoom session (e.g., due to HIPAA-compliance).';
$string['participants'] = 'Participants';
$string['password'] = 'Passcode';
$string['password_allowed_char'] = 'Passcode may only contain the following characters: [a-z A-Z 0-9 @ - _ *].';
$string['password_consecutive'] = 'Maximum of {$a} consecutive characters (abcd, 1111, 1234, etc.).';
$string['password_length'] = 'Minimum of {$a} character(s).';
$string['password_letter'] = 'Passcode must contain at least 1 letter.';
$string['password_lower_upper'] = 'Passcode must include both lower and uppercase characters.';
$string['password_max_length'] = 'Maximum of 10 characters.';
$string['password_number'] = 'Passcode must contain at least 1 number.';
$string['password_only_numeric'] = 'Passcode may only contain numbers and no other characters.';
$string['password_special'] = 'Passcode must have at least 1 special character (@-_*).';
$string['passwordprotected'] = 'Passcode Protected';
$string['pluginadministration'] = 'Manage Zoom meeting';
$string['pluginname'] = 'Zoom meeting';
$string['privacy:metadata:zoom_meeting_details'] = 'The database table that stores information about each meeting instance.';
$string['privacy:metadata:zoom_meeting_details:topic'] = 'The name of the meeting that the user attended.';
$string['privacy:metadata:zoom_meeting_participants'] = 'The database table that stores information about meeting participants.';
$string['privacy:metadata:zoom_meeting_participants:duration'] = 'How long the participant was in the meeting';
$string['privacy:metadata:zoom_meeting_participants:join_time'] = 'The time that the participant joined the meeting';
$string['privacy:metadata:zoom_meeting_participants:leave_time'] = 'The time that the participant left the meeting';
$string['privacy:metadata:zoom_meeting_participants:name'] = 'The name of the participant';
$string['privacy:metadata:zoom_meeting_participants:user_email'] = 'The email of the participant';
$string['recurringmeeting'] = 'Recurring meeting';
$string['recurringmeeting_help'] = 'Enabling this option will make the meeting a recurring meeting without an end date or time. It can then be accessed anytime.';
$string['recurringmeetingthisis'] = 'This is a recurring meeting';
$string['recurringmeetinglong'] = 'Recurring meeting (meeting with no end date or time)';
$string['recurringmeetingexplanation'] = 'The meeting does not have an end date or time';
$string['recycleonjoin'] = 'Recycle license upon join';
$string['refreshreports'] = 'Refresh session reports';
$string['licenseonjoin'] = 'Select this option if you would like the host to receive a license upon starting the meeting, <i>as well as</i> upon creation.';
$string['recreatesuccessful'] = 'Sucessfully recreated meeting';
$string['report'] = 'Reports';
$string['reportapicalls'] = 'Report API calls exhausted';
$string['requirepasscode'] = 'Require meeting passcode';
$string['requirepasscode_help'] = 'Enabling this option will require that the host sets a passcode for the meeting. Joining participants will be required to input this before joining the meeting. Participants who enter the meeting from within the Moodle activity do not need to input this passcode.';
$string['resetapicalls'] = 'Reset the number of available API calls';
$string['schedule'] = 'Schedule';
$string['schedulefor'] = 'Schedule meeting for';
$string['scheduleforself'] = 'Yourself';
$string['schedulefor_help'] = 'You can schedule meetings on behalf of another user. As a prerequisite, this user must have assigned you scheduling privilege in Zoom. The selected user will be the host of the meeting and will be the one whose Zoom license will be used for the meeting.';
$string['schedulingprivilege'] = 'Scheduling privilege';
$string['schedulingprivilege_desc'] = 'With this setting, you can control if the scheduling privilege option is shown to users in the activity instance settings or not. This setting only affects the Moodle activity instance settings. Even if you decide to show the option, the user will still need to get the scheduling privilege granted by another user in Zoom to finally schedule a meeting for the other user.';
$string['schedulingprivilege_disable'] = 'Disable scheduling privilege option';
$string['schedulingprivilege_enable'] = 'Enable scheduling privilege option';
$string['search:activity'] = 'Zoom - activity information';
$string['security'] = 'Security';
$string['sessions'] = 'Sessions';
$string['sessionsreport'] = 'Sessions report';
$string['setpasscode'] = 'Set passcode';
$string['start'] = 'Start';
$string['starthostjoins'] = 'Start video when host joins';
$string['start_meeting'] = 'Start Meeting';
$string['startpartjoins'] = 'Start video when participant joins';
$string['start_time'] = 'When';
$string['starttime'] = 'Start time';
$string['status'] = 'Status';
$string['supplementaryfeaturessettings'] = 'Supplementary features settings';
$string['supplementaryfeaturessettings_desc'] = 'These settings control if and how supplementary Zoom features are provided to the users.';
$string['title'] = 'Title';
$string['topic'] = 'Topic';
$string['unavailable'] = 'You are unable to join at this time.';
$string['unavailablefirstjoin'] = 'You can join {$a->mins} minutes before the scheduled start time at the earliest.';
$string['unavailablefinished'] = 'The meeting has finished already.';
$string['unavailablenotstartedyet'] = 'The meeting has not started yet.';
$string['updatemeetings'] = 'Update meeting settings from Zoom';
$string['usepersonalmeeting'] = 'Use personal meeting ID {$a}';
$string['waitingroom'] = 'Waiting room';
$string['waitingroomenable'] = 'Enable waiting room';
$string['webinar'] = 'Webinar';
$string['webinarthisis'] = 'This is a webinar';
$string['webinar_desc'] = 'With this setting, you can control if the option to create a webinar is shown to users during the creation of a meeting or not. This setting only affects the Moodle activity instance settings. Even if you decide to always show the option, the user will still need a valid license for webinars to finally host a webinar.';
$string['webinar_help'] = "Webinars give hosts enhanced control and flexibility for hosting meetings with larger audiences.\n\nThis option is only available to pre-authorized Zoom accounts.";
$string['webinar_already_true'] = '<p><b>This module was already set as a webinar, not meeting. You cannot toggle this setting after creating the webinar.</b></p>';
$string['webinar_already_false'] = '<p><b>This module was already set as a meeting, not webinar. You cannot toggle this setting after creating the meeting.</b></p>';
$string['webinar_disable'] = 'Disable webinars';
$string['webinar_showonlyiflicense'] = 'Show webinar option only if the user has a license to host webinars';
$string['webinar_alwaysshow'] = 'Always show webinar option regardless if the user has a license to host webinars';
$string['zoom:addinstance'] = 'Add a new Zoom meeting';
$string['zoomerr'] = 'An error occured with Zoom.'; // Generic error.
$string['zoomerr_apikey_missing'] = 'Zoom API key not found';
$string['zoomerr_apilimit'] = 'Reached the maximum daily rate limit for this API. Retry at {$a}';
$string['zoomerr_apisecret_missing'] = 'Zoom API secret not found';
$string['zoomerr_id_missing'] = 'You must specify a course_module ID or an instance ID';
$string['zoomerr_licensesnumber_missing'] = 'Zoom utmost setting found but, licensesnumber setting not found';
$string['zoomerr_maxretries'] = 'Retried {$a->maxretries} times to make the call, but failed: {$a->response}';
$string['zoomerr_meetingnotfound'] = 'This meeting cannot be found on Zoom. You can <a href="{$a->recreate}">recreate it here</a> or <a href="{$a->delete}">delete it completely</a>.';
$string['zoomerr_meetingnotfound_info'] = 'This meeting cannot be found on Zoom. Please contact the meeting host if you have questions.';
$string['zoomerr_usernotfound'] = 'Unable to find your account on Zoom. If you are using Zoom for the first time, you must activate your Zoom account by logging into <a href="{$a}" target="_blank">{$a}</a>. Once you\'ve activated your Zoom account, reload this page and continue setting up your meeting. Else make sure your email on Zoom matches your email on this system.';
$string['zoomerr_alternativehostusernotfound'] = 'User {$a} was not found on Zoom.';
$string['zoomurl'] = 'Zoom home page URL';
$string['zoomurl_desc'] = '';
$string['zoom:eligiblealternativehost'] = 'Selectable as alternative host within Zoom meetings';
$string['zoom:refreshsessions'] = 'Refresh Zoom meeting reports';
$string['zoom:view'] = 'View Zoom meetings';
