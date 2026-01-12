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
 * @package    mod_zoomyt
 * @copyright  2015 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/zoomyt/locallib.php');
require_once($CFG->libdir . '/environmentlib.php');

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot . '/mod/zoomyt/classes/invitation.php');

    $moodlehashideif = version_compare(normalize_version($CFG->release), '3.7.0', '>=');

    $settings = new admin_settingpage('modsettingzoomyt', get_string('pluginname', 'mod_zoomyt'));

    // Test whether connection works and display result to user.
    if (!CLI_SCRIPT && $PAGE->url == $CFG->wwwroot . '/' . $CFG->admin . '/settings.php?section=modsettingzoomyt') {
        $status = 'connectionfailed';
        $notifyclass = 'notifyproblem';
        $errormessage = '';
        try {
            zoomyt_get_user(zoomyt_get_api_identifier($USER));
            $status = 'connectionok';
            $notifyclass = 'notifysuccess';
        } catch (\mod_zoomyt\webservice_exception $error) {
            $errormessage = $error->response;
        } catch (moodle_exception $error) {
            $errormessage = $error->a;
        }

        $statusmessage = $OUTPUT->notification(get_string('connectionstatus', 'mod_zoomyt') .
                ': ' . get_string($status, 'mod_zoomyt') . $errormessage, $notifyclass);
        $connectionstatus = new admin_setting_heading('zoomyt/connectionstatus', $statusmessage, '');
        $settings->add($connectionstatus);
    }

    // Category Settings link.
    $categorysettingsurl = new moodle_url('/mod/zoomyt/categorylist.php');
    $categorysettingslink = html_writer::link($categorysettingsurl, get_string('manage_category_settings', 'zoomyt'),
        ['class' => 'btn btn-secondary']);
    $settings->add(new admin_setting_heading(
        'zoomyt/categorysettingslink',
        get_string('category_settings_header', 'zoomyt'),
        get_string('category_settings_header_desc', 'zoomyt') . '<br><br>' . $categorysettingslink
    ));

    // Connection settings.
    $settings->add(new admin_setting_heading(
        'zoomyt/connectionsettings',
        get_string('connectionsettings', 'mod_zoomyt'),
        get_string('connectionsettings_desc', 'mod_zoomyt')
    ));

    $accountid = new admin_setting_configtext(
        'zoomyt/accountid',
        get_string('accountid', 'mod_zoomyt'),
        get_string('accountid_desc', 'mod_zoomyt'),
        '',
        PARAM_ALPHANUMEXT
    );
    $settings->add($accountid);

    $clientid = new admin_setting_configtext(
        'zoomyt/clientid',
        get_string('clientid', 'mod_zoomyt'),
        get_string('clientid_desc', 'mod_zoomyt'),
        '',
        PARAM_ALPHANUMEXT
    );
    $settings->add($clientid);

    $clientsecret = new admin_setting_configpasswordunmask(
        'zoomyt/clientsecret',
        get_string('clientsecret', 'mod_zoomyt'),
        get_string('clientsecret_desc', 'mod_zoomyt'),
        ''
    );
    $settings->add($clientsecret);

    $zoomurl = new admin_setting_configtext(
        'zoomyt/zoomurl',
        get_string('zoomurl', 'mod_zoomyt'),
        get_string('zoomurl_desc', 'mod_zoomyt'),
        '',
        PARAM_URL
    );
    $settings->add($zoomurl);

    $apiendpointchoices = [
        ZOOM_API_ENDPOINT_GLOBAL => get_string('apiendpoint_global', 'mod_zoomyt'),
        ZOOM_API_ENDPOINT_EU => get_string('apiendpoint_eu', 'mod_zoomyt'),
    ];
    $apiendpoint = new admin_setting_configselect(
        'zoomyt/apiendpoint',
        get_string('apiendpoint', 'mod_zoomyt'),
        get_string('apiendpoint_desc', 'mod_zoomyt'),
        ZOOM_API_ENDPOINT_GLOBAL,
        $apiendpointchoices
    );
    $settings->add($apiendpoint);

    $proxyhost = new admin_setting_configtext(
        'zoomyt/proxyhost',
        get_string('option_proxyhost', 'mod_zoomyt'),
        get_string('option_proxyhost_desc', 'mod_zoomyt'),
        '',
        '/^[a-zA-Z0-9.-]+:[0-9]+$|^$/'
    );
    $settings->add($proxyhost);

    $apiidentifier = new admin_setting_configselect(
        'zoomyt/apiidentifier',
        get_string('apiidentifier', 'mod_zoomyt'),
        get_string('apiidentifier_desc', 'mod_zoomyt'),
        'email',
        zoomyt_get_api_identifier_fields()
    );
    $settings->add($apiidentifier);

    // License settings.
    $settings->add(new admin_setting_heading(
        'zoomyt/licensesettings',
        get_string('licensesettings', 'mod_zoomyt'),
        get_string('licensesettings_desc', 'mod_zoomyt')
    ));

    $licensescount = new admin_setting_configtext(
        'zoomyt/licensesnumber',
        get_string('licensesnumber', 'mod_zoomyt'),
        null,
        0,
        PARAM_INT
    );
    $settings->add($licensescount);

    $utmost = new admin_setting_configcheckbox(
        'zoomyt/utmost',
        get_string('redefinelicenses', 'mod_zoomyt'),
        get_string('lowlicenses', 'mod_zoomyt'),
        0,
        1
    );
    $settings->add($utmost);

    $instanceusers = new admin_setting_configcheckbox(
        'zoomyt/instanceusers',
        get_string('instanceusers', 'mod_zoomyt'),
        get_string('instanceusers_desc', 'mod_zoomyt'),
        0,
        1,
        0
    );
    $settings->add($instanceusers);

    $recycleonjoin = new admin_setting_configcheckbox(
        'zoomyt/recycleonjoin',
        get_string('recycleonjoin', 'mod_zoomyt'),
        get_string('licenseonjoin', 'mod_zoomyt'),
        0,
        1
    );
    $settings->add($recycleonjoin);

    // Only call to the web services and load the setting if the connection is OK.
    if (isset($status) && $status === 'connectionok') {
        $zoomgroups = [];
        $groups = zoomyt_webservice()->get_groups();
        foreach ($groups as $group) {
            $zoomgroups[$group->id] = $group->name;
        }

        $protectedgroups = new admin_setting_configmultiselect(
            'zoomyt/protectedgroups',
            get_string('protectedgroups', 'mod_zoomyt'),
            get_string('protectedgroups_desc', 'mod_zoomyt'),
            [],
            $zoomgroups
        );
        $settings->add($protectedgroups);
    }

    // Global settings.
    $settings->add(new admin_setting_heading(
        'zoomyt/globalsettings',
        get_string('globalsettings', 'mod_zoomyt'),
        get_string('globalsettings_desc', 'mod_zoomyt')
    ));

    $jointimechoices = [0, 5, 10, 15, 20, 30, 45, 60];
    $jointimeselect = [];
    foreach ($jointimechoices as $minutes) {
        $jointimeselect[$minutes] = $minutes . ' ' . get_string('mins');
    }

    $firstabletojoin = new admin_setting_configselect(
        'zoomyt/firstabletojoin',
        get_string('firstjoin', 'mod_zoomyt'),
        get_string('firstjoin_desc', 'mod_zoomyt'),
        15,
        $jointimeselect
    );
    $settings->add($firstabletojoin);

    // Host/teacher early access time (fixed at 15 minutes before meeting start).
    $hostearlymins = new admin_setting_configselect(
        'zoomyt/hostearlyaccess',
        get_string('hostearlyaccess', 'mod_zoomyt'),
        get_string('hostearlyaccess_desc', 'mod_zoomyt'),
        15,
        $jointimeselect
    );
    $settings->add($hostearlymins);

    if ($moodlehashideif) {
        $displayleadtime = new admin_setting_configcheckbox(
            'zoomyt/displayleadtime',
            get_string('displayleadtime', 'mod_zoomyt'),
            get_string('displayleadtime_desc', 'mod_zoomyt'),
            0,
            1,
            0
        );
        $settings->add($displayleadtime);
        $settings->hide_if('zoomyt/displayleadtime', 'zoomyt/firstabletojoin', 'eq', 0);
    } else {
        $displayleadtime = new admin_setting_configcheckbox(
            'zoomyt/displayleadtime',
            get_string('displayleadtime', 'mod_zoomyt'),
            get_string('displayleadtime_desc', 'mod_zoomyt') . '<br />' .
                        get_string('displayleadtime_nohideif', 'mod_zoomyt', get_string('firstjoin', 'mod_zoomyt')),
            0,
            1,
            0
        );
        $settings->add($displayleadtime);
    }

    $displaypassword = new admin_setting_configcheckbox(
        'zoomyt/displaypassword',
        get_string('displaypassword', 'mod_zoomyt'),
        get_string('displaypassword_help', 'mod_zoomyt'),
        0,
        1,
        0
    );
    $settings->add($displaypassword);

    $maskparticipantdata = new admin_setting_configcheckbox(
        'zoomyt/maskparticipantdata',
        get_string('maskparticipantdata', 'mod_zoomyt'),
        get_string('maskparticipantdata_help', 'mod_zoomyt'),
        0,
        1
    );
    $settings->add($maskparticipantdata);

    $viewrecordings = new admin_setting_configcheckbox(
        'zoomyt/viewrecordings',
        get_string('option_view_recordings', 'mod_zoomyt'),
        '',
        0,
        1,
        0
    );
    $settings->add($viewrecordings);

    // Adding options for the display name using uname parameter in zoom join_url.
    $options = [
        'fullname' => get_string('displayfullname', 'mod_zoomyt'),
        'firstname' => get_string('displayfirstname', 'mod_zoomyt'),
        'idfullname' => get_string('displayidfullname', 'mod_zoomyt'),
        'id' => get_string('displayid', 'mod_zoomyt'),
    ];
    $settings->add(new admin_setting_configselect(
        'zoomyt/unamedisplay',
        get_string('unamedisplay', 'mod_zoomyt'),
        get_string('unamedisplay_help', 'mod_zoomyt'),
        'fullname',
        $options
    ));

    // Supplementary features settings.
    $settings->add(new admin_setting_heading(
        'zoomyt/supplementaryfeaturessettings',
        get_string('supplementaryfeaturessettings', 'mod_zoomyt'),
        get_string('supplementaryfeaturessettings_desc', 'mod_zoomyt')
    ));

    $webinarchoices = [
        ZOOM_WEBINAR_DISABLE => get_string('webinar_disable', 'mod_zoomyt'),
        ZOOM_WEBINAR_SHOWONLYIFLICENSE => get_string('webinar_showonlyiflicense', 'mod_zoomyt'),
        ZOOM_WEBINAR_ALWAYSSHOW => get_string('webinar_alwaysshow', 'mod_zoomyt'),
    ];
    $offerwebinar = new admin_setting_configselect(
        'zoomyt/showwebinars',
        get_string('webinar', 'mod_zoomyt'),
        get_string('webinar_desc', 'mod_zoomyt'),
        ZOOM_WEBINAR_ALWAYSSHOW,
        $webinarchoices
    );
    $settings->add($offerwebinar);

    $webinardefault = new admin_setting_configcheckbox(
        'zoomyt/webinardefault',
        get_string('webinar_by_default', 'mod_zoomyt'),
        get_string('webinar_by_default_desc', 'mod_zoomyt'),
        0,
        1,
        0
    );
    $settings->add($webinardefault);

    $encryptionchoices = [
        ZOOM_ENCRYPTION_DISABLE => get_string('encryptiontype_disable', 'mod_zoomyt'),
        ZOOM_ENCRYPTION_SHOWONLYIFPOSSIBLE => get_string('encryptiontype_showonlyife2epossible', 'mod_zoomyt'),
        ZOOM_ENCRYPTION_ALWAYSSHOW => get_string('encryptiontype_alwaysshow', 'mod_zoomyt'),
    ];
    $offerencryption = new admin_setting_configselect(
        'zoomyt/showencryptiontype',
        get_string('encryptiontype', 'mod_zoomyt'),
        get_string('encryptiontype_desc', 'mod_zoomyt'),
        ZOOM_ENCRYPTION_SHOWONLYIFPOSSIBLE,
        $encryptionchoices
    );
    $settings->add($offerencryption);

    $schedulingprivilegechoices = [
        ZOOM_SCHEDULINGPRIVILEGE_DISABLE => get_string('schedulingprivilege_disable', 'mod_zoomyt'),
        ZOOM_SCHEDULINGPRIVILEGE_ENABLE => get_string('schedulingprivilege_enable', 'mod_zoomyt'),
    ];
    $offerschedulingprivilege = new admin_setting_configselect(
        'zoomyt/showschedulingprivilege',
        get_string('schedulingprivilege', 'mod_zoomyt'),
        get_string('schedulingprivilege_desc', 'mod_zoomyt'),
        ZOOM_SCHEDULINGPRIVILEGE_ENABLE,
        $schedulingprivilegechoices
    );
    $settings->add($offerschedulingprivilege);

    $alternativehostschoices = [
        ZOOM_ALTERNATIVEHOSTS_DISABLE => get_string('alternative_hosts_disable', 'mod_zoomyt'),
        ZOOM_ALTERNATIVEHOSTS_INPUTFIELD => get_string('alternative_hosts_inputfield', 'mod_zoomyt'),
        ZOOM_ALTERNATIVEHOSTS_PICKER => get_string('alternative_hosts_picker', 'mod_zoomyt'),
    ];
    $alternativehostsroles = zoomyt_get_selectable_alternative_hosts_rolestring(context_system::instance());
    $offeralternativehosts = new admin_setting_configselect(
        'zoomyt/showalternativehosts',
        get_string('alternative_hosts', 'mod_zoomyt'),
        get_string('alternative_hosts_desc', 'mod_zoomyt', ['roles' => $alternativehostsroles]),
        ZOOM_ALTERNATIVEHOSTS_INPUTFIELD,
        $alternativehostschoices
    );
    $settings->add($offeralternativehosts);

    $capacitywarningchoices = [
        ZOOM_CAPACITYWARNING_DISABLE => get_string('meetingcapacitywarning_disable', 'mod_zoomyt'),
        ZOOM_CAPACITYWARNING_ENABLE => get_string('meetingcapacitywarning_enable', 'mod_zoomyt'),
    ];
    $offercapacitywarning = new admin_setting_configselect(
        'zoomyt/showcapacitywarning',
        get_string('meetingcapacitywarning', 'mod_zoomyt'),
        get_string('meetingcapacitywarning_desc', 'mod_zoomyt'),
        ZOOM_CAPACITYWARNING_ENABLE,
        $capacitywarningchoices
    );
    $settings->add($offercapacitywarning);

    $allmeetingschoices = [
        ZOOM_ALLMEETINGS_DISABLE => get_string('allmeetings_disable', 'mod_zoomyt'),
        ZOOM_ALLMEETINGS_ENABLE => get_string('allmeetings_enable', 'mod_zoomyt'),
    ];
    $offerallmeetings = new admin_setting_configselect(
        'zoomyt/showallmeetings',
        get_string('allmeetings', 'mod_zoomyt'),
        get_string('allmeetings_desc', 'mod_zoomyt'),
        ZOOM_ALLMEETINGS_ENABLE,
        $allmeetingschoices
    );
    $settings->add($offerallmeetings);

    $downloadicalchoices = [
        ZOOM_DOWNLOADICAL_DISABLE => get_string('downloadical_disable', 'mod_zoomyt'),
        ZOOM_DOWNLOADICAL_ENABLE => get_string('downloadical_enable', 'mod_zoomyt'),
    ];
    $offerdownloadical = new admin_setting_configselect(
        'zoomyt/showdownloadical',
        get_string('downloadical', 'mod_zoomyt'),
        get_string('downloadical_desc', 'mod_zoomyt'),
        ZOOM_DOWNLOADICAL_ENABLE,
        $downloadicalchoices
    );
    $settings->add($offerdownloadical);

    $sendicalnotificationshelp = get_string('sendicalnotifications_help', 'mod_zoomyt');
    if (empty($CFG->allowattachments)) {
        $sendicalnotificationshelp .= '<div class="alert alert-block alert-warning" role="alert">'
                                      . get_string('sendicalnotifications_warning', 'mod_zoomyt') . '</div>';
    }

    $sendicalnotifications = new admin_setting_configcheckbox(
        'zoomyt/sendicalnotifications',
        get_string('sendicalnotifications', 'mod_zoomyt'),
        $sendicalnotificationshelp,
        0,
        1,
        0
    );
    $settings->add($sendicalnotifications);

    // Default Zoom settings.
    $settings->add(new admin_setting_heading(
        'zoomyt/defaultsettings',
        get_string('defaultsettings', 'mod_zoomyt'),
        get_string('defaultsettings_help', 'mod_zoomyt')
    ));

    $defaultrecurring = new admin_setting_configcheckbox(
        'zoomyt/defaultrecurring',
        get_string('recurringmeeting', 'mod_zoomyt'),
        get_string('recurringmeeting_help', 'mod_zoomyt'),
        0,
        1,
        0
    );
    $settings->add($defaultrecurring);

    $defaultshowschedule = new admin_setting_configcheckbox(
        'zoomyt/defaultshowschedule',
        get_string('showschedule', 'mod_zoomyt'),
        get_string('showschedule_help', 'mod_zoomyt'),
        1,
        1,
        0
    );
    $settings->add($defaultshowschedule);

    $defaultregistration = new admin_setting_configcheckbox(
        'zoomyt/defaultregistration',
        get_string('registration', 'mod_zoomyt'),
        get_string('registration_help', 'mod_zoomyt'),
        ZOOM_REGISTRATION_OFF,
        ZOOM_REGISTRATION_AUTOMATIC,
        ZOOM_REGISTRATION_OFF
    );
    $settings->add($defaultregistration);

    $defaultrequirepasscode = new admin_setting_configcheckbox(
        'zoomyt/requirepasscode',
        get_string('requirepasscode', 'mod_zoomyt'),
        get_string('requirepasscode_help', 'mod_zoomyt'),
        1
    );
    $defaultrequirepasscode->set_locked_flag_options(admin_setting_flag::ENABLED, true);
    $settings->add($defaultrequirepasscode);

    $encryptionchoices = [
        ZOOM_ENCRYPTION_TYPE_ENHANCED => get_string('option_encryption_type_enhancedencryption', 'mod_zoomyt'),
        ZOOM_ENCRYPTION_TYPE_E2EE => get_string('option_encryption_type_endtoendencryption', 'mod_zoomyt'),
    ];
    $defaultencryptiontypeoption = new admin_setting_configselect(
        'zoomyt/defaultencryptiontypeoption',
        get_string('option_encryption_type', 'mod_zoomyt'),
        get_string('option_encryption_type_help', 'mod_zoomyt'),
        ZOOM_ENCRYPTION_TYPE_ENHANCED,
        $encryptionchoices
    );
    $settings->add($defaultencryptiontypeoption);

    $defaultwaitingroomoption = new admin_setting_configcheckbox(
        'zoomyt/defaultwaitingroomoption',
        get_string('option_waiting_room', 'mod_zoomyt'),
        get_string('option_waiting_room_help', 'mod_zoomyt'),
        1,
        1,
        0
    );
    $settings->add($defaultwaitingroomoption);

    $defaultjoinbeforehost = new admin_setting_configcheckbox(
        'zoomyt/defaultjoinbeforehost',
        get_string('option_jbh', 'mod_zoomyt'),
        get_string('option_jbh_help', 'mod_zoomyt'),
        0,
        1,
        0
    );
    $settings->add($defaultjoinbeforehost);

    $defaultauthusersoption = new admin_setting_configcheckbox(
        'zoomyt/defaultauthusersoption',
        get_string('option_authenticated_users', 'mod_zoomyt'),
        get_string('option_authenticated_users_help', 'mod_zoomyt'),
        0,
        1,
        0
    );
    $settings->add($defaultauthusersoption);

    $defaultshowsecurity = new admin_setting_configcheckbox(
        'zoomyt/defaultshowsecurity',
        get_string('showsecurity', 'mod_zoomyt'),
        get_string('showsecurity_help', 'mod_zoomyt'),
        1,
        1,
        0
    );
    $settings->add($defaultshowsecurity);

    $defaulthostvideo = new admin_setting_configcheckbox(
        'zoomyt/defaulthostvideo',
        get_string('option_host_video', 'mod_zoomyt'),
        get_string('option_host_video_help', 'mod_zoomyt'),
        0,
        1,
        0
    );
    $settings->add($defaulthostvideo);

    $defaultparticipantsvideo = new admin_setting_configcheckbox(
        'zoomyt/defaultparticipantsvideo',
        get_string('option_participants_video', 'mod_zoomyt'),
        get_string('option_participants_video_help', 'mod_zoomyt'),
        0,
        1,
        0
    );
    $settings->add($defaultparticipantsvideo);

    $audiochoices = [
        ZOOM_AUDIO_TELEPHONY => get_string('audio_telephony', 'mod_zoomyt'),
        ZOOM_AUDIO_VOIP => get_string('audio_voip', 'mod_zoomyt'),
        ZOOM_AUDIO_BOTH => get_string('audio_both', 'mod_zoomyt'),
    ];
    $defaultaudiooption = new admin_setting_configselect(
        'zoomyt/defaultaudiooption',
        get_string('option_audio', 'mod_zoomyt'),
        get_string('option_audio_help', 'mod_zoomyt'),
        ZOOM_AUDIO_BOTH,
        $audiochoices
    );
    $settings->add($defaultaudiooption);

    $defaultmuteuponentryoption = new admin_setting_configcheckbox(
        'zoomyt/defaultmuteuponentryoption',
        get_string('option_mute_upon_entry', 'mod_zoomyt'),
        get_string('option_mute_upon_entry_help', 'mod_zoomyt'),
        1,
        1,
        0
    );
    $settings->add($defaultmuteuponentryoption);

    $autorecordingchoices = [
        ZOOM_AUTORECORDING_NONE => get_string('autorecording_none', 'mod_zoomyt'),
        ZOOM_AUTORECORDING_USERDEFAULT => get_string('autorecording_userdefault', 'mod_zoomyt'),
        ZOOM_AUTORECORDING_LOCAL => get_string('autorecording_local', 'mod_zoomyt'),
        ZOOM_AUTORECORDING_CLOUD => get_string('autorecording_cloud', 'mod_zoomyt'),
    ];
    $recordingoption = new admin_setting_configselect(
        'zoomyt/recordingoption',
        get_string('option_auto_recording', 'mod_zoomyt'),
        get_string('option_auto_recording_help', 'mod_zoomyt'),
        ZOOM_AUTORECORDING_USERDEFAULT,
        $autorecordingchoices
    );
    $settings->add($recordingoption);

    $allowrecordingchangeoption = new admin_setting_configcheckbox(
        'zoomyt/allowrecordingchangeoption',
        get_string('option_allow_recording_change', 'mod_zoomyt'),
        get_string('option_allow_recording_change_help', 'mod_zoomyt'),
        1,
        1,
        0
    );
    $settings->add($allowrecordingchangeoption);

    $defaultshowmedia = new admin_setting_configcheckbox(
        'zoomyt/defaultshowmedia',
        get_string('showmedia', 'mod_zoomyt'),
        get_string('showmedia_help', 'mod_zoomyt'),
        1,
        1,
        0
    );
    $settings->add($defaultshowmedia);

    $defaultshowjoinbutton = new admin_setting_configcheckbox(
        'zoomyt/defaultshowjoinbutton',
        get_string('showjoinbutton', 'zoomyt'),
        get_string('showjoinbutton_help', 'zoomyt'),
        1,
        1,
        0
    );
    $settings->add($defaultshowjoinbutton);

    $defaulttrackingfields = new admin_setting_configtextarea(
        'zoomyt/defaulttrackingfields',
        get_string('trackingfields', 'mod_zoomyt'),
        get_string('trackingfields_help', 'mod_zoomyt'),
        ''
    );
    $defaulttrackingfields->set_updatedcallback('mod_zoomyt_update_tracking_fields');
    $settings->add($defaulttrackingfields);

    $invitationregexhelp = get_string('invitationregex_help', 'mod_zoomyt');
    if (!$moodlehashideif) {
        $invitationregexhelp .= "\n\n" . get_string(
            'invitationregex_nohideif',
            'mod_zoomyt',
            get_string('invitationregexenabled', 'mod_zoomyt')
        );
    }

    $settings->add(new admin_setting_heading(
        'zoomyt/invitationregex',
        get_string('invitationregex', 'mod_zoomyt'),
        $invitationregexhelp
    ));

    $settings->add(new admin_setting_configcheckbox(
        'zoomyt/invitationregexenabled',
        get_string('invitationregexenabled', 'mod_zoomyt'),
        get_string('invitationregexenabled_help', 'mod_zoomyt'),
        0,
        1,
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'zoomyt/invitationremoveinvite',
        get_string('invitationremoveinvite', 'mod_zoomyt'),
        get_string('invitationremoveinvite_help', 'mod_zoomyt'),
        0,
        1,
        0
    ));
    if ($moodlehashideif) {
        $settings->hide_if('zoomyt/invitationremoveinvite', 'zoomyt/invitationregexenabled', 'eq', 0);
    }

    $settings->add(new admin_setting_configcheckbox(
        'zoomyt/invitationremoveicallink',
        get_string('invitationremoveicallink', 'mod_zoomyt'),
        get_string('invitationremoveicallink_help', 'mod_zoomyt'),
        0,
        1,
        0
    ));
    if ($moodlehashideif) {
        $settings->hide_if('zoomyt/invitationremoveicallink', 'zoomyt/invitationregexenabled', 'eq', 0);
    }

    // Allow admin to modify regex for invitation parts if zoom api changes.
    foreach (\mod_zoomyt\invitation::get_default_invitation_regex() as $element => $pattern) {
        $name = 'zoomyt/' . \mod_zoomyt\invitation::PREFIX . $element;
        $visiblename = get_string(\mod_zoomyt\invitation::PREFIX . $element, 'mod_zoomyt');
        $description = get_string(\mod_zoomyt\invitation::PREFIX . $element . '_help', 'mod_zoomyt');
        $settings->add(new admin_setting_configtext($name, $visiblename, $description, $pattern));
        if ($moodlehashideif) {
            $settings->hide_if(
                'zoomyt/' . \mod_zoomyt\invitation::PREFIX . $element,
                'zoomyt/invitationregexenabled',
                'eq',
                0
            );
        }
    }

    // Extra hideif for elements which can be enabled / disabled individually.
    if ($moodlehashideif) {
        $settings->hide_if('zoomyt/invitation_invite', 'zoomyt/invitationremoveinvite', 'eq', 0);
        $settings->hide_if('zoomyt/invitation_icallink', 'zoomyt/invitationremoveicallink', 'eq', 0);
    }

    // Adding options for grading methods.
    $settings->add(new admin_setting_heading(
        'zoomyt/gradingmethod',
        get_string('gradingmethod_heading', 'mod_zoomyt'),
        get_string('gradingmethod_heading_help', 'mod_zoomyt')
    ));

    // Grading method upon entry: the user gets the full score when clicking to join the session through Moodle.
    // Grading method upon period: the user is graded based on how long they attended the actual session.
    $options = [
        'entry' => get_string('gradingentry', 'mod_zoomyt'),
        'period' => get_string('gradingperiod', 'mod_zoomyt'),
    ];
    $settings->add(new admin_setting_configselect(
        'zoomyt/gradingmethod',
        get_string('gradingmethod', 'mod_zoomyt'),
        get_string('gradingmethod_help', 'mod_zoomyt'),
        'entry',
        $options
    ));

    // YouTube Integration Settings.
    $settings->add(new admin_setting_heading(
        'zoomyt/youtubesettings',
        get_string('youtube_settings', 'zoomyt'),
        get_string('youtube_settings_desc', 'zoomyt')
    ));

    // YouTube Client ID (site-wide).
    $settings->add(new admin_setting_configtext(
        'zoomyt/youtube_client_id',
        get_string('youtube_client_id', 'zoomyt'),
        get_string('youtube_client_id_desc', 'zoomyt'),
        ''
    ));

    // YouTube Client Secret (site-wide).
    $settings->add(new admin_setting_configpasswordunmask(
        'zoomyt/youtube_client_secret',
        get_string('youtube_client_secret', 'zoomyt'),
        get_string('youtube_client_secret_desc', 'zoomyt'),
        ''
    ));

    // Site-wide default YouTube channel connection.
    $currentchannelname = get_config('zoomyt', 'youtube_default_channel_name');
    $manageurl = new moodle_url('/mod/zoomyt/youtube_oauth_site.php');
    if (!empty($currentchannelname)) {
        $channelhtml = html_writer::tag('span',
            get_string('youtube_site_channel_connected', 'zoomyt', $currentchannelname),
            ['class' => 'text-success mr-2']
        );
        $channelhtml .= html_writer::link($manageurl, get_string('youtube_manage_connection', 'zoomyt'),
            ['class' => 'btn btn-sm btn-outline-secondary']);
    } else {
        $channelhtml = html_writer::tag('span',
            get_string('youtube_site_channel_not_connected', 'zoomyt'),
            ['class' => 'text-muted mr-2']
        );
        if (!empty($config->youtube_client_id) && !empty($config->youtube_client_secret)) {
            $channelhtml .= html_writer::link($manageurl, get_string('youtube_connect', 'zoomyt'),
                ['class' => 'btn btn-sm btn-primary']);
        } else {
            $channelhtml .= html_writer::tag('span',
                get_string('youtube_credentials_required', 'zoomyt'),
                ['class' => 'text-warning']);
        }
    }
    $settings->add(new admin_setting_heading(
        'zoomyt/youtube_default_channel_status',
        get_string('youtube_site_default_channel', 'zoomyt'),
        $channelhtml
    ));

    // Default YouTube visibility.
    $visibilityoptions = [
        'unlisted' => get_string('youtube_visibility_unlisted', 'zoomyt'),
        'public' => get_string('youtube_visibility_public', 'zoomyt'),
        'private' => get_string('youtube_visibility_private', 'zoomyt'),
    ];
    $settings->add(new admin_setting_configselect(
        'zoomyt/youtube_default_visibility',
        get_string('youtube_default_visibility', 'zoomyt'),
        get_string('youtube_default_visibility_desc', 'zoomyt'),
        'unlisted',
        $visibilityoptions
    ));

    // Zoom recording delete days (global default).
    $deleteoptions = [
        '' => get_string('never_delete', 'zoomyt'),
        '7' => '7 ' . get_string('days'),
        '14' => '14 ' . get_string('days'),
        '30' => '30 ' . get_string('days'),
        '60' => '60 ' . get_string('days'),
        '90' => '90 ' . get_string('days'),
    ];
    $settings->add(new admin_setting_configselect(
        'zoomyt/zoom_recording_delete_days',
        get_string('zoom_recording_delete_days', 'zoomyt'),
        get_string('zoom_recording_delete_days_desc', 'zoomyt'),
        '',
        $deleteoptions
    ));

    // Temporary storage settings.
    $settings->add(new admin_setting_heading(
        'zoomyt/storagesettings',
        get_string('storage_settings', 'zoomyt'),
        get_string('storage_settings_desc', 'zoomyt')
    ));

    // Temp directory path.
    $settings->add(new admin_setting_configtext(
        'zoomyt/temp_directory',
        get_string('temp_directory', 'zoomyt'),
        get_string('temp_directory_desc', 'zoomyt'),
        ''
    ));

    // Temp storage limit in GB.
    $storagelimitoptions = [
        '1073741824' => '1 GB',
        '2147483648' => '2 GB',
        '5368709120' => '5 GB',
        '10737418240' => '10 GB',
        '21474836480' => '20 GB',
        '53687091200' => '50 GB',
    ];
    $settings->add(new admin_setting_configselect(
        'zoomyt/temp_storage_limit',
        get_string('temp_storage_limit', 'zoomyt'),
        get_string('temp_storage_limit_desc', 'zoomyt'),
        '5368709120',
        $storagelimitoptions
    ));
}
