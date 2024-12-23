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
 * Settings.
 *
 * @package    mod_zoom
 * @copyright  2015 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/zoom/locallib.php');
require_once($CFG->libdir . '/environmentlib.php');

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot . '/mod/zoom/classes/invitation.php');

    $moodlehashideif = version_compare(normalize_version($CFG->release), '3.7.0', '>=');

    $settings = new admin_settingpage('modsettingzoom', get_string('pluginname', 'mod_zoom'));

    // Test whether connection works and display result to user.
    if (!CLI_SCRIPT && $PAGE->url == $CFG->wwwroot . '/' . $CFG->admin . '/settings.php?section=modsettingzoom') {
        $status = 'connectionfailed';
        $notifyclass = 'notifyproblem';
        $errormessage = '';
        try {
            zoom_get_user(zoom_get_api_identifier($USER));
            $status = 'connectionok';
            $notifyclass = 'notifysuccess';
        } catch (\mod_zoom\webservice_exception $error) {
            $errormessage = $error->response;
        } catch (moodle_exception $error) {
            $errormessage = $error->a;
        }

        $statusmessage = $OUTPUT->notification(get_string('connectionstatus', 'mod_zoom') .
                ': ' . get_string($status, 'mod_zoom') . $errormessage, $notifyclass);
        $connectionstatus = new admin_setting_heading('zoom/connectionstatus', $statusmessage, '');
        $settings->add($connectionstatus);
    }

    // Connection settings.
    $settings->add(new admin_setting_heading(
        'zoom/connectionsettings',
        get_string('connectionsettings', 'mod_zoom'),
        get_string('connectionsettings_desc', 'mod_zoom')
    ));

    $accountid = new admin_setting_configtext(
        'zoom/accountid',
        get_string('accountid', 'mod_zoom'),
        get_string('accountid_desc', 'mod_zoom'),
        '',
        PARAM_ALPHANUMEXT
    );
    $settings->add($accountid);

    $clientid = new admin_setting_configtext(
        'zoom/clientid',
        get_string('clientid', 'mod_zoom'),
        get_string('clientid_desc', 'mod_zoom'),
        '',
        PARAM_ALPHANUMEXT
    );
    $settings->add($clientid);

    $clientsecret = new admin_setting_configpasswordunmask(
        'zoom/clientsecret',
        get_string('clientsecret', 'mod_zoom'),
        get_string('clientsecret_desc', 'mod_zoom'),
        ''
    );
    $settings->add($clientsecret);

    $zoomurl = new admin_setting_configtext(
        'zoom/zoomurl',
        get_string('zoomurl', 'mod_zoom'),
        get_string('zoomurl_desc', 'mod_zoom'),
        '',
        PARAM_URL
    );
    $settings->add($zoomurl);

    $apiendpointchoices = [
        ZOOM_API_ENDPOINT_GLOBAL => get_string('apiendpoint_global', 'mod_zoom'),
        ZOOM_API_ENDPOINT_EU => get_string('apiendpoint_eu', 'mod_zoom'),
    ];
    $apiendpoint = new admin_setting_configselect(
        'zoom/apiendpoint',
        get_string('apiendpoint', 'mod_zoom'),
        get_string('apiendpoint_desc', 'mod_zoom'),
        ZOOM_API_ENDPOINT_GLOBAL,
        $apiendpointchoices
    );
    $settings->add($apiendpoint);

    $proxyhost = new admin_setting_configtext(
        'zoom/proxyhost',
        get_string('option_proxyhost', 'mod_zoom'),
        get_string('option_proxyhost_desc', 'mod_zoom'),
        '',
        '/^[a-zA-Z0-9.-]+:[0-9]+$|^$/'
    );
    $settings->add($proxyhost);

    $apiidentifier = new admin_setting_configselect(
        'zoom/apiidentifier',
        get_string('apiidentifier', 'mod_zoom'),
        get_string('apiidentifier_desc', 'mod_zoom'),
        'email',
        zoom_get_api_identifier_fields()
    );
    $settings->add($apiidentifier);

    // License settings.
    $settings->add(new admin_setting_heading(
        'zoom/licensesettings',
        get_string('licensesettings', 'mod_zoom'),
        get_string('licensesettings_desc', 'mod_zoom')
    ));

    $licensescount = new admin_setting_configtext(
        'zoom/licensesnumber',
        get_string('licensesnumber', 'mod_zoom'),
        null,
        0,
        PARAM_INT
    );
    $settings->add($licensescount);

    $utmost = new admin_setting_configcheckbox(
        'zoom/utmost',
        get_string('redefinelicenses', 'mod_zoom'),
        get_string('lowlicenses', 'mod_zoom'),
        0,
        1
    );
    $settings->add($utmost);

    $instanceusers = new admin_setting_configcheckbox(
        'zoom/instanceusers',
        get_string('instanceusers', 'mod_zoom'),
        get_string('instanceusers_desc', 'mod_zoom'),
        0,
        1,
        0
    );
    $settings->add($instanceusers);

    $recycleonjoin = new admin_setting_configcheckbox(
        'zoom/recycleonjoin',
        get_string('recycleonjoin', 'mod_zoom'),
        get_string('licenseonjoin', 'mod_zoom'),
        0,
        1
    );
    $settings->add($recycleonjoin);

    // Only call to the web services and load the setting if the connection is OK.
    if (isset($status) && $status === 'connectionok') {
        $zoomgroups = [];
        $groups = zoom_webservice()->get_groups();
        foreach ($groups as $group) {
            $zoomgroups[$group->id] = $group->name;
        }

        $protectedgroups = new admin_setting_configmultiselect(
            'zoom/protectedgroups',
            get_string('protectedgroups', 'mod_zoom'),
            get_string('protectedgroups_desc', 'mod_zoom'),
            [],
            $zoomgroups
        );
        $settings->add($protectedgroups);
    }

    // Global settings.
    $settings->add(new admin_setting_heading(
        'zoom/globalsettings',
        get_string('globalsettings', 'mod_zoom'),
        get_string('globalsettings_desc', 'mod_zoom')
    ));

    $jointimechoices = [0, 5, 10, 15, 20, 30, 45, 60];
    $jointimeselect = [];
    foreach ($jointimechoices as $minutes) {
        $jointimeselect[$minutes] = $minutes . ' ' . get_string('mins');
    }

    $firstabletojoin = new admin_setting_configselect(
        'zoom/firstabletojoin',
        get_string('firstjoin', 'mod_zoom'),
        get_string('firstjoin_desc', 'mod_zoom'),
        15,
        $jointimeselect
    );
    $settings->add($firstabletojoin);

    if ($moodlehashideif) {
        $displayleadtime = new admin_setting_configcheckbox(
            'zoom/displayleadtime',
            get_string('displayleadtime', 'mod_zoom'),
            get_string('displayleadtime_desc', 'mod_zoom'),
            0,
            1,
            0
        );
        $settings->add($displayleadtime);
        $settings->hide_if('zoom/displayleadtime', 'zoom/firstabletojoin', 'eq', 0);
    } else {
        $displayleadtime = new admin_setting_configcheckbox(
            'zoom/displayleadtime',
            get_string('displayleadtime', 'mod_zoom'),
            get_string('displayleadtime_desc', 'mod_zoom') . '<br />' .
                        get_string('displayleadtime_nohideif', 'mod_zoom', get_string('firstjoin', 'mod_zoom')),
            0,
            1,
            0
        );
        $settings->add($displayleadtime);
    }

    $displaypassword = new admin_setting_configcheckbox(
        'zoom/displaypassword',
        get_string('displaypassword', 'mod_zoom'),
        get_string('displaypassword_help', 'mod_zoom'),
        0,
        1,
        0
    );
    $settings->add($displaypassword);

    $maskparticipantdata = new admin_setting_configcheckbox(
        'zoom/maskparticipantdata',
        get_string('maskparticipantdata', 'mod_zoom'),
        get_string('maskparticipantdata_help', 'mod_zoom'),
        0,
        1
    );
    $settings->add($maskparticipantdata);

    $viewrecordings = new admin_setting_configcheckbox(
        'zoom/viewrecordings',
        get_string('option_view_recordings', 'mod_zoom'),
        '',
        0,
        1,
        0
    );
    $settings->add($viewrecordings);

    // Adding options for the display name using uname parameter in zoom join_url.
    $options = [
        'fullname' => get_string('displayfullname', 'mod_zoom'),
        'firstname' => get_string('displayfirstname', 'mod_zoom'),
        'idfullname' => get_string('displayidfullname', 'mod_zoom'),
        'id' => get_string('displayid', 'mod_zoom'),
    ];
    $settings->add(new admin_setting_configselect(
        'zoom/unamedisplay',
        get_string('unamedisplay', 'mod_zoom'),
        get_string('unamedisplay_help', 'mod_zoom'),
        'fullname',
        $options
    ));

    // Supplementary features settings.
    $settings->add(new admin_setting_heading(
        'zoom/supplementaryfeaturessettings',
        get_string('supplementaryfeaturessettings', 'mod_zoom'),
        get_string('supplementaryfeaturessettings_desc', 'mod_zoom')
    ));

    $webinarchoices = [
        ZOOM_WEBINAR_DISABLE => get_string('webinar_disable', 'mod_zoom'),
        ZOOM_WEBINAR_SHOWONLYIFLICENSE => get_string('webinar_showonlyiflicense', 'mod_zoom'),
        ZOOM_WEBINAR_ALWAYSSHOW => get_string('webinar_alwaysshow', 'mod_zoom'),
    ];
    $offerwebinar = new admin_setting_configselect(
        'zoom/showwebinars',
        get_string('webinar', 'mod_zoom'),
        get_string('webinar_desc', 'mod_zoom'),
        ZOOM_WEBINAR_ALWAYSSHOW,
        $webinarchoices
    );
    $settings->add($offerwebinar);

    $webinardefault = new admin_setting_configcheckbox(
        'zoom/webinardefault',
        get_string('webinar_by_default', 'mod_zoom'),
        get_string('webinar_by_default_desc', 'mod_zoom'),
        0,
        1,
        0
    );
    $settings->add($webinardefault);

    $encryptionchoices = [
        ZOOM_ENCRYPTION_DISABLE => get_string('encryptiontype_disable', 'mod_zoom'),
        ZOOM_ENCRYPTION_SHOWONLYIFPOSSIBLE => get_string('encryptiontype_showonlyife2epossible', 'mod_zoom'),
        ZOOM_ENCRYPTION_ALWAYSSHOW => get_string('encryptiontype_alwaysshow', 'mod_zoom'),
    ];
    $offerencryption = new admin_setting_configselect(
        'zoom/showencryptiontype',
        get_string('encryptiontype', 'mod_zoom'),
        get_string('encryptiontype_desc', 'mod_zoom'),
        ZOOM_ENCRYPTION_SHOWONLYIFPOSSIBLE,
        $encryptionchoices
    );
    $settings->add($offerencryption);

    $schedulingprivilegechoices = [
        ZOOM_SCHEDULINGPRIVILEGE_DISABLE => get_string('schedulingprivilege_disable', 'mod_zoom'),
        ZOOM_SCHEDULINGPRIVILEGE_ENABLE => get_string('schedulingprivilege_enable', 'mod_zoom'),
    ];
    $offerschedulingprivilege = new admin_setting_configselect(
        'zoom/showschedulingprivilege',
        get_string('schedulingprivilege', 'mod_zoom'),
        get_string('schedulingprivilege_desc', 'mod_zoom'),
        ZOOM_SCHEDULINGPRIVILEGE_ENABLE,
        $schedulingprivilegechoices
    );
    $settings->add($offerschedulingprivilege);

    $alternativehostschoices = [
        ZOOM_ALTERNATIVEHOSTS_DISABLE => get_string('alternative_hosts_disable', 'mod_zoom'),
        ZOOM_ALTERNATIVEHOSTS_INPUTFIELD => get_string('alternative_hosts_inputfield', 'mod_zoom'),
        ZOOM_ALTERNATIVEHOSTS_PICKER => get_string('alternative_hosts_picker', 'mod_zoom'),
    ];
    $alternativehostsroles = zoom_get_selectable_alternative_hosts_rolestring(context_system::instance());
    $offeralternativehosts = new admin_setting_configselect(
        'zoom/showalternativehosts',
        get_string('alternative_hosts', 'mod_zoom'),
        get_string('alternative_hosts_desc', 'mod_zoom', ['roles' => $alternativehostsroles]),
        ZOOM_ALTERNATIVEHOSTS_INPUTFIELD,
        $alternativehostschoices
    );
    $settings->add($offeralternativehosts);

    $capacitywarningchoices = [
        ZOOM_CAPACITYWARNING_DISABLE => get_string('meetingcapacitywarning_disable', 'mod_zoom'),
        ZOOM_CAPACITYWARNING_ENABLE => get_string('meetingcapacitywarning_enable', 'mod_zoom'),
    ];
    $offercapacitywarning = new admin_setting_configselect(
        'zoom/showcapacitywarning',
        get_string('meetingcapacitywarning', 'mod_zoom'),
        get_string('meetingcapacitywarning_desc', 'mod_zoom'),
        ZOOM_CAPACITYWARNING_ENABLE,
        $capacitywarningchoices
    );
    $settings->add($offercapacitywarning);

    $allmeetingschoices = [
        ZOOM_ALLMEETINGS_DISABLE => get_string('allmeetings_disable', 'mod_zoom'),
        ZOOM_ALLMEETINGS_ENABLE => get_string('allmeetings_enable', 'mod_zoom'),
    ];
    $offerallmeetings = new admin_setting_configselect(
        'zoom/showallmeetings',
        get_string('allmeetings', 'mod_zoom'),
        get_string('allmeetings_desc', 'mod_zoom'),
        ZOOM_ALLMEETINGS_ENABLE,
        $allmeetingschoices
    );
    $settings->add($offerallmeetings);

    $downloadicalchoices = [
        ZOOM_DOWNLOADICAL_DISABLE => get_string('downloadical_disable', 'mod_zoom'),
        ZOOM_DOWNLOADICAL_ENABLE => get_string('downloadical_enable', 'mod_zoom'),
    ];
    $offerdownloadical = new admin_setting_configselect(
        'zoom/showdownloadical',
        get_string('downloadical', 'mod_zoom'),
        get_string('downloadical_desc', 'mod_zoom'),
        ZOOM_DOWNLOADICAL_ENABLE,
        $downloadicalchoices
    );
    $settings->add($offerdownloadical);

    // Default Zoom settings.
    $settings->add(new admin_setting_heading(
        'zoom/defaultsettings',
        get_string('defaultsettings', 'mod_zoom'),
        get_string('defaultsettings_help', 'mod_zoom')
    ));

    $defaultrecurring = new admin_setting_configcheckbox(
        'zoom/defaultrecurring',
        get_string('recurringmeeting', 'mod_zoom'),
        get_string('recurringmeeting_help', 'mod_zoom'),
        0,
        1,
        0
    );
    $settings->add($defaultrecurring);

    $defaultshowschedule = new admin_setting_configcheckbox(
        'zoom/defaultshowschedule',
        get_string('showschedule', 'mod_zoom'),
        get_string('showschedule_help', 'mod_zoom'),
        1,
        1,
        0
    );
    $settings->add($defaultshowschedule);

    $defaultregistration = new admin_setting_configcheckbox(
        'zoom/defaultregistration',
        get_string('registration', 'mod_zoom'),
        get_string('registration_help', 'mod_zoom'),
        ZOOM_REGISTRATION_OFF,
        ZOOM_REGISTRATION_AUTOMATIC,
        ZOOM_REGISTRATION_OFF
    );
    $settings->add($defaultregistration);

    $defaultrequirepasscode = new admin_setting_configcheckbox(
        'zoom/requirepasscode',
        get_string('requirepasscode', 'mod_zoom'),
        get_string('requirepasscode_help', 'mod_zoom'),
        1
    );
    $defaultrequirepasscode->set_locked_flag_options(admin_setting_flag::ENABLED, true);
    $settings->add($defaultrequirepasscode);

    $encryptionchoices = [
        ZOOM_ENCRYPTION_TYPE_ENHANCED => get_string('option_encryption_type_enhancedencryption', 'mod_zoom'),
        ZOOM_ENCRYPTION_TYPE_E2EE => get_string('option_encryption_type_endtoendencryption', 'mod_zoom'),
    ];
    $defaultencryptiontypeoption = new admin_setting_configselect(
        'zoom/defaultencryptiontypeoption',
        get_string('option_encryption_type', 'mod_zoom'),
        get_string('option_encryption_type_help', 'mod_zoom'),
        ZOOM_ENCRYPTION_TYPE_ENHANCED,
        $encryptionchoices
    );
    $settings->add($defaultencryptiontypeoption);

    $defaultwaitingroomoption = new admin_setting_configcheckbox(
        'zoom/defaultwaitingroomoption',
        get_string('option_waiting_room', 'mod_zoom'),
        get_string('option_waiting_room_help', 'mod_zoom'),
        1,
        1,
        0
    );
    $settings->add($defaultwaitingroomoption);

    $defaultjoinbeforehost = new admin_setting_configcheckbox(
        'zoom/defaultjoinbeforehost',
        get_string('option_jbh', 'mod_zoom'),
        get_string('option_jbh_help', 'mod_zoom'),
        0,
        1,
        0
    );
    $settings->add($defaultjoinbeforehost);

    $defaultauthusersoption = new admin_setting_configcheckbox(
        'zoom/defaultauthusersoption',
        get_string('option_authenticated_users', 'mod_zoom'),
        get_string('option_authenticated_users_help', 'mod_zoom'),
        0,
        1,
        0
    );
    $settings->add($defaultauthusersoption);

    $defaultshowsecurity = new admin_setting_configcheckbox(
        'zoom/defaultshowsecurity',
        get_string('showsecurity', 'mod_zoom'),
        get_string('showsecurity_help', 'mod_zoom'),
        1,
        1,
        0
    );
    $settings->add($defaultshowsecurity);

    $defaulthostvideo = new admin_setting_configcheckbox(
        'zoom/defaulthostvideo',
        get_string('option_host_video', 'mod_zoom'),
        get_string('option_host_video_help', 'mod_zoom'),
        0,
        1,
        0
    );
    $settings->add($defaulthostvideo);

    $defaultparticipantsvideo = new admin_setting_configcheckbox(
        'zoom/defaultparticipantsvideo',
        get_string('option_participants_video', 'mod_zoom'),
        get_string('option_participants_video_help', 'mod_zoom'),
        0,
        1,
        0
    );
    $settings->add($defaultparticipantsvideo);

    $audiochoices = [
        ZOOM_AUDIO_TELEPHONY => get_string('audio_telephony', 'mod_zoom'),
        ZOOM_AUDIO_VOIP => get_string('audio_voip', 'mod_zoom'),
        ZOOM_AUDIO_BOTH => get_string('audio_both', 'mod_zoom'),
    ];
    $defaultaudiooption = new admin_setting_configselect(
        'zoom/defaultaudiooption',
        get_string('option_audio', 'mod_zoom'),
        get_string('option_audio_help', 'mod_zoom'),
        ZOOM_AUDIO_BOTH,
        $audiochoices
    );
    $settings->add($defaultaudiooption);

    $defaultmuteuponentryoption = new admin_setting_configcheckbox(
        'zoom/defaultmuteuponentryoption',
        get_string('option_mute_upon_entry', 'mod_zoom'),
        get_string('option_mute_upon_entry_help', 'mod_zoom'),
        1,
        1,
        0
    );
    $settings->add($defaultmuteuponentryoption);

    $autorecordingchoices = [
        ZOOM_AUTORECORDING_NONE => get_string('autorecording_none', 'mod_zoom'),
        ZOOM_AUTORECORDING_USERDEFAULT => get_string('autorecording_userdefault', 'mod_zoom'),
        ZOOM_AUTORECORDING_LOCAL => get_string('autorecording_local', 'mod_zoom'),
        ZOOM_AUTORECORDING_CLOUD => get_string('autorecording_cloud', 'mod_zoom'),
    ];
    $recordingoption = new admin_setting_configselect(
        'zoom/recordingoption',
        get_string('option_auto_recording', 'mod_zoom'),
        get_string('option_auto_recording_help', 'mod_zoom'),
        ZOOM_AUTORECORDING_USERDEFAULT,
        $autorecordingchoices
    );
    $settings->add($recordingoption);

    $allowrecordingchangeoption = new admin_setting_configcheckbox(
        'zoom/allowrecordingchangeoption',
        get_string('option_allow_recording_change', 'mod_zoom'),
        get_string('option_allow_recording_change_help', 'mod_zoom'),
        1,
        1,
        0
    );
    $settings->add($allowrecordingchangeoption);

    $defaultshowmedia = new admin_setting_configcheckbox(
        'zoom/defaultshowmedia',
        get_string('showmedia', 'mod_zoom'),
        get_string('showmedia_help', 'mod_zoom'),
        1,
        1,
        0
    );
    $settings->add($defaultshowmedia);

    $defaulttrackingfields = new admin_setting_configtextarea(
        'zoom/defaulttrackingfields',
        get_string('trackingfields', 'mod_zoom'),
        get_string('trackingfields_help', 'mod_zoom'),
        ''
    );
    $defaulttrackingfields->set_updatedcallback('mod_zoom_update_tracking_fields');
    $settings->add($defaulttrackingfields);

    $invitationregexhelp = get_string('invitationregex_help', 'mod_zoom');
    if (!$moodlehashideif) {
        $invitationregexhelp .= "\n\n" . get_string(
            'invitationregex_nohideif',
            'mod_zoom',
            get_string('invitationregexenabled', 'mod_zoom')
        );
    }

    $settings->add(new admin_setting_heading(
        'zoom/invitationregex',
        get_string('invitationregex', 'mod_zoom'),
        $invitationregexhelp
    ));

    $settings->add(new admin_setting_configcheckbox(
        'zoom/invitationregexenabled',
        get_string('invitationregexenabled', 'mod_zoom'),
        get_string('invitationregexenabled_help', 'mod_zoom'),
        0,
        1,
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'zoom/invitationremoveinvite',
        get_string('invitationremoveinvite', 'mod_zoom'),
        get_string('invitationremoveinvite_help', 'mod_zoom'),
        0,
        1,
        0
    ));
    if ($moodlehashideif) {
        $settings->hide_if('zoom/invitationremoveinvite', 'zoom/invitationregexenabled', 'eq', 0);
    }

    $settings->add(new admin_setting_configcheckbox(
        'zoom/invitationremoveicallink',
        get_string('invitationremoveicallink', 'mod_zoom'),
        get_string('invitationremoveicallink_help', 'mod_zoom'),
        0,
        1,
        0
    ));
    if ($moodlehashideif) {
        $settings->hide_if('zoom/invitationremoveicallink', 'zoom/invitationregexenabled', 'eq', 0);
    }

    // Allow admin to modify regex for invitation parts if zoom api changes.
    foreach (\mod_zoom\invitation::get_default_invitation_regex() as $element => $pattern) {
        $name = 'zoom/' . \mod_zoom\invitation::PREFIX . $element;
        $visiblename = get_string(\mod_zoom\invitation::PREFIX . $element, 'mod_zoom');
        $description = get_string(\mod_zoom\invitation::PREFIX . $element . '_help', 'mod_zoom');
        $settings->add(new admin_setting_configtext($name, $visiblename, $description, $pattern));
        if ($moodlehashideif) {
            $settings->hide_if(
                'zoom/' . \mod_zoom\invitation::PREFIX . $element,
                'zoom/invitationregexenabled',
                'eq',
                0
            );
        }
    }

    // Extra hideif for elements which can be enabled / disabled individually.
    if ($moodlehashideif) {
        $settings->hide_if('zoom/invitation_invite', 'zoom/invitationremoveinvite', 'eq', 0);
        $settings->hide_if('zoom/invitation_icallink', 'zoom/invitationremoveicallink', 'eq', 0);
    }

    // Adding options for grading methods.
    $settings->add(new admin_setting_heading(
        'zoom/gradingmethod',
        get_string('gradingmethod_heading', 'mod_zoom'),
        get_string('gradingmethod_heading_help', 'mod_zoom')
    ));

    // Grading method upon entry: the user gets the full score when clicking to join the session through Moodle.
    // Grading method upon period: the user is graded based on how long they attended the actual session.
    $options = [
        'entry' => get_string('gradingentry', 'mod_zoom'),
        'period' => get_string('gradingperiod', 'mod_zoom'),
    ];
    $settings->add(new admin_setting_configselect(
        'zoom/gradingmethod',
        get_string('gradingmethod', 'mod_zoom'),
        get_string('gradingmethod_help', 'mod_zoom'),
        'entry',
        $options
    ));
}
