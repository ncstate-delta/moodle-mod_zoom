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

require_once($CFG->dirroot.'/mod/zoom/locallib.php');

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/zoom/locallib.php');
    require_once($CFG->dirroot.'/mod/zoom/classes/webservice.php');

    $settings = new admin_settingpage('modsettingzoom', get_string('pluginname', 'mod_zoom'));

    // Test whether connection works and display result to user.
    if (!CLI_SCRIPT && $PAGE->url == $CFG->wwwroot . '/' . $CFG->admin . '/settings.php?section=modsettingzoom') {
        $status = 'connectionok';
        $notifyclass = 'notifysuccess';
        $errormessage = '';
        try {
            $service = new mod_zoom_webservice();
            $service->get_user($USER->email);
        } catch (moodle_exception $error) {
            $notifyclass = 'notifyproblem';
            $status = 'connectionfailed';
            $errormessage = $error->a;
        }
        $statusmessage = $OUTPUT->notification(get_string('connectionstatus', 'mod_zoom') .
                ': ' . get_string($status, 'mod_zoom') . $errormessage, $notifyclass);
        $connectionstatus = new admin_setting_heading('zoom/connectionstatus', $statusmessage, '');
        $settings->add($connectionstatus);
    }

    // Connection settings.
    $settings->add(new admin_setting_heading('zoom/connectionsettings',
            get_string('connectionsettings', 'mod_zoom'),
            get_string('connectionsettings_desc', 'mod_zoom')));

    $apikey = new admin_setting_configtext('zoom/apikey', get_string('apikey', 'mod_zoom'),
            get_string('apikey_desc', 'mod_zoom'), '', PARAM_ALPHANUMEXT);
    $settings->add($apikey);

    $apisecret = new admin_setting_configpasswordunmask('zoom/apisecret', get_string('apisecret', 'mod_zoom'),
            get_string('apisecret_desc', 'mod_zoom'), '');
    $settings->add($apisecret);

    $zoomurl = new admin_setting_configtext('zoom/zoomurl', get_string('zoomurl', 'mod_zoom'),
            get_string('zoomurl_desc', 'mod_zoom'), '', PARAM_URL);
    $settings->add($zoomurl);

    $proxyhost = new admin_setting_configtext('zoom/proxyhost',
            get_string('option_proxyhost', 'mod_zoom'),
            get_string('option_proxyhost_desc', 'mod_zoom'), '', '/^[a-zA-Z0-9.-]+:[0-9]+$|^$/');
    $settings->add($proxyhost);

    // License settings.
    $settings->add(new admin_setting_heading('zoom/licensesettings',
            get_string('licensesettings', 'mod_zoom'),
            get_string('licensesettings_desc', 'mod_zoom')));

    $licensescount = new admin_setting_configtext('zoom/licensesnumber',
            get_string('licensesnumber', 'mod_zoom'),
            null, 0, PARAM_INT);
    $settings->add($licensescount);

    $utmost = new admin_setting_configcheckbox('zoom/utmost',
            get_string('redefinelicenses', 'mod_zoom'),
            get_string('lowlicenses', 'mod_zoom'), 0, 1);
    $settings->add($utmost);

    $recycleonjoin = new admin_setting_configcheckbox('zoom/recycleonjoin',
            get_string('recycleonjoin', 'mod_zoom'),
            get_string('licenseonjoin', 'mod_zoom'), 0, 1);
    $settings->add($recycleonjoin);

    // Global settings.
    $settings->add(new admin_setting_heading('zoom/globalsettings',
            get_string('globalsettings', 'mod_zoom'),
            get_string('globalsettings_desc', 'mod_zoom')));

    $jointimechoices = array(0, 5, 10, 15, 20, 30, 45, 60);
    $jointimeselect = array();
    foreach ($jointimechoices as $minutes) {
        $jointimeselect[$minutes] = $minutes . ' ' . get_string('mins');
    }
    $firstabletojoin = new admin_setting_configselect('zoom/firstabletojoin',
            get_string('firstjoin', 'mod_zoom'), get_string('firstjoin_desc', 'mod_zoom'),
            15, $jointimeselect);
    $settings->add($firstabletojoin);

    $displaypassword = new admin_setting_configcheckbox('zoom/displaypassword',
            get_string('displaypassword', 'mod_zoom'),
            get_string('displaypassword_help', 'mod_zoom'), 0, 1, 0);
    $settings->add($displaypassword);

    $maskparticipantdata = new admin_setting_configcheckbox('zoom/maskparticipantdata',
            get_string('maskparticipantdata', 'mod_zoom'),
            get_string('maskparticipantdata_help', 'mod_zoom'), 0, 1);
    $settings->add($maskparticipantdata);

    // Supplementary features settings.
    $settings->add(new admin_setting_heading('zoom/supplementaryfeaturessettings',
            get_string('supplementaryfeaturessettings', 'mod_zoom'),
            get_string('supplementaryfeaturessettings_desc', 'mod_zoom')));

    $webinarchoices = array(ZOOM_WEBINAR_DISABLE => get_string('webinar_disable', 'mod_zoom'),
            ZOOM_WEBINAR_SHOWONLYIFLICENSE => get_string('webinar_showonlyiflicense', 'mod_zoom'),
            ZOOM_WEBINAR_ALWAYSSHOW => get_string('webinar_alwaysshow', 'mod_zoom'));
    $offerwebinar = new admin_setting_configselect('zoom/showwebinars',
            get_string('webinar', 'mod_zoom'),
            get_string('webinar_desc', 'mod_zoom'),
            ZOOM_WEBINAR_ALWAYSSHOW,
            $webinarchoices);
    $settings->add($offerwebinar);

    $encryptionchoices = array(ZOOM_ENCRYPTION_DISABLE => get_string('encryptiontype_disable', 'mod_zoom'),
            ZOOM_ENCRYPTION_SHOWONLYIFPOSSIBLE => get_string('encryptiontype_showonlyife2epossible', 'mod_zoom'),
            ZOOM_ENCRYPTION_ALWAYSSHOW => get_string('encryptiontype_alwaysshow', 'mod_zoom'));
    $offerencryption = new admin_setting_configselect('zoom/showencryptiontype',
            get_string('encryptiontype', 'mod_zoom'),
            get_string('encryptiontype_desc', 'mod_zoom'),
            ZOOM_ENCRYPTION_SHOWONLYIFPOSSIBLE,
            $encryptionchoices);
    $settings->add($offerencryption);

    // Default Zoom settings.
    $settings->add(new admin_setting_heading('zoom/defaultsettings',
            get_string('defaultsettings', 'mod_zoom'),
            get_string('defaultsettings_help', 'mod_zoom')));

    $defaultrecurring = new admin_setting_configcheckbox('zoom/defaultrecurring',
            get_string('recurringmeeting', 'mod_zoom'),
            get_string('recurringmeeting_help', 'mod_zoom'), 0, 1, 0);
    $settings->add($defaultrecurring);

    $defaultrequirepasscode = new admin_setting_configcheckbox('zoom/requirepasscode',
            get_string('requirepasscode', 'mod_zoom'),
            get_string('requirepasscode_help', 'mod_zoom'),
            1);
    $defaultrequirepasscode->set_locked_flag_options(admin_setting_flag::ENABLED, true);
    $settings->add($defaultrequirepasscode);

    $encryptionchoices = array(ZOOM_ENCRYPTION_TYPE_ENHANCED => get_string('option_encryption_type_enhancedencryption', 'mod_zoom'),
            ZOOM_ENCRYPTION_TYPE_E2EE => get_string('option_encryption_type_endtoendencryption', 'mod_zoom'));
    $defaultencryptiontypeoption = new admin_setting_configselect('zoom/defaultencryptiontypeoption',
            get_string('option_encryption_type', 'mod_zoom'),
            get_string('option_encryption_type_help', 'mod_zoom'),
            ZOOM_ENCRYPTION_TYPE_ENHANCED, $encryptionchoices);
    $settings->add($defaultencryptiontypeoption);

    $defaultwaitingroomoption = new admin_setting_configcheckbox('zoom/defaultwaitingroomoption',
            get_string('option_waiting_room', 'mod_zoom'),
            get_string('option_waiting_room_help', 'mod_zoom'),
            1, 1, 0);
    $settings->add($defaultwaitingroomoption);

    $defaultjoinbeforehost = new admin_setting_configcheckbox('zoom/defaultjoinbeforehost',
            get_string('option_jbh', 'mod_zoom'),
            get_string('option_jbh_help', 'mod_zoom'),
            0, 1, 0);
    $settings->add($defaultjoinbeforehost);

    $defaultauthusersoption = new admin_setting_configcheckbox('zoom/defaultauthusersoption',
            get_string('option_authenticated_users', 'mod_zoom'),
            get_string('option_authenticated_users_help', 'mod_zoom'),
            0, 1, 0);
    $settings->add($defaultauthusersoption);

    $defaulthostvideo = new admin_setting_configcheckbox('zoom/defaulthostvideo',
            get_string('option_host_video', 'mod_zoom'),
            get_string('option_host_video_help', 'mod_zoom'),
            0, 1, 0);
    $settings->add($defaulthostvideo);

    $defaultparticipantsvideo = new admin_setting_configcheckbox('zoom/defaultparticipantsvideo',
            get_string('option_participants_video', 'mod_zoom'),
            get_string('option_participants_video_help', 'mod_zoom'),
            0, 1, 0);
    $settings->add($defaultparticipantsvideo);

    $audiochoices = array(ZOOM_AUDIO_TELEPHONY => get_string('audio_telephony', 'mod_zoom'),
                          ZOOM_AUDIO_VOIP => get_string('audio_voip', 'mod_zoom'),
                          ZOOM_AUDIO_BOTH => get_string('audio_both', 'mod_zoom'));
    $defaultaudiooption = new admin_setting_configselect('zoom/defaultaudiooption',
            get_string('option_audio', 'mod_zoom'),
            get_string('option_audio_help', 'mod_zoom'),
            ZOOM_AUDIO_BOTH, $audiochoices);
    $settings->add($defaultaudiooption);

    $defaultmuteuponentryoption = new admin_setting_configcheckbox('zoom/defaultmuteuponentryoption',
            get_string('option_mute_upon_entry', 'mod_zoom'),
            get_string('option_mute_upon_entry_help', 'mod_zoom'),
            1, 1, 0);
    $settings->add($defaultmuteuponentryoption);
}
