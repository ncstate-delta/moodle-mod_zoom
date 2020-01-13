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
        $statusmessage = $OUTPUT->notification(get_string('connectionstatus', 'zoom') .
                ': ' . get_string($status, 'zoom') . $errormessage, $notifyclass);
        $connectionstatus = new admin_setting_heading('mod_zoom/connectionstatus', $statusmessage, '');
        $settings->add($connectionstatus);
    }

    $apikey = new admin_setting_configtext('mod_zoom/apikey', get_string('apikey', 'mod_zoom'),
            get_string('apikey_desc', 'mod_zoom'), '', PARAM_ALPHANUMEXT);
    $settings->add($apikey);

    $apisecret = new admin_setting_configpasswordunmask('mod_zoom/apisecret', get_string('apisecret', 'mod_zoom'),
            get_string('apisecret_desc', 'mod_zoom'), '');
    $settings->add($apisecret);

    $zoomurl = new admin_setting_configtext('mod_zoom/zoomurl', get_string('zoomurl', 'mod_zoom'),
            get_string('zoomurl_desc', 'mod_zoom'), '', PARAM_URL);
    $settings->add($zoomurl);

    $jointimechoices = array(0, 5, 10, 15, 20, 30, 45, 60);
    $jointimeselect = array();
    foreach ($jointimechoices as $minutes) {
        $jointimeselect[$minutes] = $minutes . ' ' . get_string('mins');
    }
    $firstabletojoin = new admin_setting_configselect('mod_zoom/firstabletojoin',
            get_string('firstjoin', 'mod_zoom'), get_string('firstjoin_desc', 'mod_zoom'),
            15, $jointimeselect);
    $settings->add($firstabletojoin);

    $licensescount = new admin_setting_configtext('mod_zoom/licensesnumber', get_string('licensesnumber', 'mod_zoom'),
            null, 0, PARAM_INT);
    $settings->add($licensescount);
    $utmost = new admin_setting_configcheckbox('mod_zoom/utmost', get_string('redefinelicenses', 'mod_zoom'),
            get_string('lowlicenses', 'mod_zoom'), 0, 1);
    $settings->add($utmost);

    $settings->add(new admin_setting_heading('defaultsettings', get_string('defaultsettings', 'mod_zoom'),
            get_string('defaultsettings_help', 'mod_zoom')));

    $defaultrecurring = new admin_setting_configcheckbox('mod_zoom/defaultrecurring', get_string('recurringmeeting', 'zoom'),
            get_string('recurringmeeting_help', 'zoom'), 0, 1, 0);
    $settings->add($defaultrecurring);

    $defaulthostvideo = new admin_setting_configcheckbox('mod_zoom/defaulthostvideo', get_string('option_host_video', 'zoom'),
            '', 1, 1, 0);
    $settings->add($defaulthostvideo);

    $defaultparticipantsvideo = new admin_setting_configcheckbox('mod_zoom/defaultparticipantsvideo',
            get_string('option_participants_video', 'zoom'), '', 1, 1, 0);
    $settings->add($defaultparticipantsvideo);

    $defaultmuteoption = new admin_setting_configcheckbox('mod_zoom/defaultmuteoption',
        get_string('option_mute_upon_entry', 'zoom'), '', 0, 1, 0);
    $settings->add($defaultmuteoption);

    $audiochoices = array(ZOOM_AUDIO_TELEPHONY => get_string('audio_telephony', 'zoom'),
                          ZOOM_AUDIO_VOIP => get_string('audio_voip', 'zoom'),
                          ZOOM_AUDIO_BOTH => get_string('audio_both', 'zoom'));
    $defaultaudiooption = new admin_setting_configselect('mod_zoom/defaultaudiooption', get_string('option_audio', 'zoom'),
            '', ZOOM_AUDIO_BOTH, $audiochoices);
    $settings->add($defaultaudiooption);

    $autorecordingchoices = array(ZOOM_REC_LOCAL => get_string('auto_rec_local', 'zoom'),
        ZOOM_REC_CLOUD => get_string('auto_rec_cloud', 'zoom'),
        ZOOM_REC_NONE => get_string('auto_rec_none', 'zoom'));
    $defaultautorecording = new admin_setting_configselect('mod_zoom/defaultautorecording', get_string('auto_recording', 'zoom'),
        	'', ZOOM_REC_NONE, $autorecordingchoices);
    $settings->add($defaultautorecording);

    $defaultjoinbeforehost = new admin_setting_configcheckbox('mod_zoom/defaultjoinbeforehost', get_string('option_jbh', 'zoom'),
            '', 0, 1, 0);
    $settings->add($defaultjoinbeforehost);

    $settings->add(new admin_setting_configcheckbox('mod_zoom/enable_notify_mail',get_string('enablenotifymail','zoom'),
        get_string('enablenotifymail_desc','zoom'),1));

    $settings->add(new admin_setting_configcheckbox('mod_zoom/enable_reminder_mail',get_string('enableremindermail','zoom'),
        get_string('enableremindermail_desc','zoom'),1));

    $settings->add(new admin_setting_configtext('mod_zoom/reminder_time',get_string('remindertime','zoom'),
        get_string('remindertime_desc','zoom'),15,PARAM_INT));
}
