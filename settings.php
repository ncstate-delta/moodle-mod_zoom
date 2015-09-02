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
    $settings = new admin_settingpage('modsettingzoom', get_string('pluginname', 'mod_zoom'));

    $apiurl = new admin_setting_configtext('mod_zoom/apiurl', get_string('apiurl', 'mod_zoom'),
            get_string('apiurl_desc', 'mod_zoom'), 'https://api.zoom.us/v1/', PARAM_URL);
    $settings->add($apiurl);

    $apikey = new admin_setting_configtext('mod_zoom/apikey', get_string('apikey', 'mod_zoom'),
            get_string('apikey_desc', 'mod_zoom'), '', PARAM_ALPHANUMEXT);
    $settings->add($apikey);

    $apisecret = new admin_setting_configtext('mod_zoom/apisecret', get_string('apisecret', 'mod_zoom'),
            get_string('apisecret_desc', 'mod_zoom'), '', PARAM_ALPHANUMEXT);
    $settings->add($apisecret);

    $zoomurl = new admin_setting_configtext('mod_zoom/zoomurl', get_string('zoomurl', 'mod_zoom'),
            get_string('zoomurl_desc', 'mod_zoom'), '', PARAM_URL);
    $settings->add($zoomurl);

    $jointimechoices = array(
        0 => get_string('minutes', 'mod_zoom', 0),
        5 => get_string('minutes', 'mod_zoom', 5),
        10 => get_string('minutes', 'mod_zoom', 10),
        15 => get_string('minutes', 'mod_zoom', 15),
        20 => get_string('minutes', 'mod_zoom', 20),
        30 => get_string('minutes', 'mod_zoom', 30),
        45 => get_string('minutes', 'mod_zoom', 45),
        60 => get_string('minutes', 'mod_zoom', 60),
    );
    $firstabletojoin = new admin_setting_configselect('mod_zoom/firstabletojoin',
            get_string('firstjoin', 'mod_zoom'), get_string('firstjoin_desc', 'mod_zoom'),
            15, $jointimechoices);
    $settings->add($firstabletojoin);

    $logintypes = array(ZOOM_SNS_SSO => get_string('login_sso', 'mod_zoom'),
                        ZOOM_SNS_ZOOM => get_string('login_zoom', 'mod_zoom'),
                        ZOOM_SNS_API => get_string('login_api', 'mod_zoom'),
                        ZOOM_SNS_FACEBOOK => get_string('login_facebook', 'mod_zoom'),
                        ZOOM_SNS_GOOGLE => get_string('login_google', 'mod_zoom'));

    $settings->add(new admin_setting_configmultiselect('mod_zoom/logintypes',
            get_string('logintypes', 'mod_zoom'), get_string('logintypesexplain', 'mod_zoom'),
            array(ZOOM_SNS_SSO), $logintypes));
}
