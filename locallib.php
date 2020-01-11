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
 * Internal library of functions for module zoom
 *
 * All the zoom specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package    mod_zoom
 * @copyright  2015 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/mod/zoom/lib.php');
require_once($CFG->dirroot.'/mod/zoom/classes/webservice.php');

// Constants.
// Audio options.
define('ZOOM_AUDIO_TELEPHONY', 'telephony');
define('ZOOM_AUDIO_VOIP', 'voip');
define('ZOOM_AUDIO_BOTH', 'both');
// Meeting types.
define('ZOOM_INSTANT_MEETING', 1);
define('ZOOM_SCHEDULED_MEETING', 2);
define('ZOOM_RECURRING_MEETING', 3);
define('ZOOM_RECURRING_MEETING_WITH_FIXED_TIME', 8);
define('ZOOM_SCHEDULED_WEBINAR', 5);
define('ZOOM_RECURRING_WEBINAR', 6);
// Number of meetings per page from zoom's get user report.
define('ZOOM_DEFAULT_RECORDS_PER_CALL', 30);
define('ZOOM_MAX_RECORDS_PER_CALL', 300);
// User types. Numerical values from Zoom API.
define('ZOOM_USER_TYPE_BASIC', 1);
define('ZOOM_USER_TYPE_PRO', 2);
define('ZOOM_USER_TYPE_CORP', 3);

//Auto Recording options
define('ZOOM_REC_LOCAL', 'local');
define('ZOOM_REC_CLOUD', 'cloud');
define('ZOOM_REC_NONE', 'none');
/**
 * Get course/cm/zoom objects from url parameters, and check for login/permissions.
 *
 * @return array Array of ($course, $cm, $zoom)
 */
function zoom_get_instance_setup() {
    global $DB;

    $id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
    $n  = optional_param('n', 0, PARAM_INT);  // ... zoom instance ID - it should be named as the first character of the module.

    if ($id) {
        $cm         = get_coursemodule_from_id('zoom', $id, 0, false, MUST_EXIST);
        $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
        $zoom  = $DB->get_record('zoom', array('id' => $cm->instance), '*', MUST_EXIST);
    } else if ($n) {
        $zoom  = $DB->get_record('zoom', array('id' => $n), '*', MUST_EXIST);
        $course     = $DB->get_record('course', array('id' => $zoom->course), '*', MUST_EXIST);
        $cm         = get_coursemodule_from_instance('zoom', $zoom->id, $course->id, false, MUST_EXIST);
    } else {
        print_error(get_string('zoomerr_id_missing', 'zoom'));
    }

    require_login($course, true, $cm);

    $context = context_module::instance($cm->id);
    require_capability('mod/zoom:view', $context);

    return array($course, $cm, $zoom);
}

/**
 * Retrieves information for a meeting.
 *
 * @param int $meetingid
 * @param bool $webinar
 * @param string $hostid the host's uuid
 * @return array information about the meeting
 */
function zoom_get_sessions_for_display($meetingid, $webinar, $hostid) {
    require_once(__DIR__.'/../../lib/moodlelib.php');
    global $DB;
    $service = new mod_zoom_webservice();
    $sessions = array();
    $format = get_string('strftimedatetimeshort', 'langconfig');

    $instances = $DB->get_records('zoom_meeting_details', array('meeting_id' => $meetingid));

    foreach ($instances as $instance) {
        // The meeting uuid, not the participant's uuid.
        $uuid = $instance->uuid;
        $participantlist = zoom_get_participants_report($instance->id);
        $sessions[$uuid]['participants'] = $participantlist;
        $sessions[$uuid]['count'] = count($participantlist);
        $sessions[$uuid]['topic'] = $instance->topic;
        $sessions[$uuid]['duration'] = $instance->duration;
        $sessions[$uuid]['starttime'] = userdate($instance->start_time, $format);
        $sessions[$uuid]['endtime'] = userdate($instance->start_time + $instance->duration * 60, $format);
    }
    return $sessions;
}

/**
 * Determine if a zoom meeting is in progress, is available, and/or is finished.
 *
 * @param stdClass $zoom
 * @return array Array of booleans: [in progress, available, finished].
 */
function zoom_get_state($zoom) {
    $config = get_config('mod_zoom');
    $now = time();

    if ($zoom->type == ZOOM_RECURRING_MEETING_WITH_FIXED_TIME) {
        $service = new mod_zoom_webservice();
        $meetings = $service->get_meeting_webinar_info($zoom->meeting_id, $zoom->webinar)->occurrences;
        //Get the latest meeting start time
        $start_time = strtotime($meetings{0}->start_time);
    } else {
        $start_time = $zoom->start_time;
    }

    $firstavailable = $start_time - ($config->firstabletojoin * 60);
    $lastavailable = $start_time + $zoom->duration;
    $inprogress = ($firstavailable <= $now && $now <= $lastavailable);

    $available = $zoom->type == ZOOM_RECURRING_MEETING || $inprogress;

    $finished = !$zoom->type == ZOOM_RECURRING_MEETING && $now > $lastavailable;

    return array($inprogress, $available, $finished, $start_time);
}

/**
 * Get the Zoom id of the currently logged-in user.
 *
 * @param boolean $required If true, will error if the user doesn't have a Zoom account.
 * @return string
 */
function zoom_get_user_id($required = true) {
    global $USER;

    $cache = cache::make('mod_zoom', 'zoomid');
    if (!($zoomuserid = $cache->get($USER->id))) {
        $zoomuserid = false;
        $service = new mod_zoom_webservice();
        try {
            $zoomuser = $service->get_user($USER->email);
            if ($zoomuser !== false) {
                $zoomuserid = $zoomuser->id;
            }
        } catch (moodle_exception $error) {
            if ($required) {
                throw $error;
            } else {
                $zoomuserid = $zoomuser->id;
            }
        }
        $cache->set($USER->id, $zoomuserid);
    }

    return $zoomuserid;
}

/**
 * Check if the error indicates that a meeting is gone.
 *
 * @param string $error
 * @return bool
 */
function zoom_is_meeting_gone_error($error) {
    // If the meeting's owner/user cannot be found, we consider the meeting to be gone.
    return strpos($error, 'not found') !== false || zoom_is_user_not_found_error($error);
}

/**
 * Check if the error indicates that a user is not found or does not belong to the current account.
 *
 * @param string $error
 * @return bool
 */
function zoom_is_user_not_found_error($error) {
    return strpos($error, 'not exist') !== false || strpos($error, 'not belong to this account') !== false
        || strpos($error, 'not found on this account') !== false;
}

/**
 * Return the string parameter for zoomerr_meetingnotfound.
 *
 * @param string $cmid
 * @return stdClass
 */
function zoom_meetingnotfound_param($cmid) {
    // Provide links to recreate and delete.
    $recreate = new moodle_url('/mod/zoom/recreate.php', array('id' => $cmid, 'sesskey' => sesskey()));
    $delete = new moodle_url('/course/mod.php', array('delete' => $cmid, 'sesskey' => sesskey()));

    // Convert links to strings and pass as error parameter.
    $param = new stdClass();
    $param->recreate = $recreate->out();
    $param->delete = $delete->out();

    return $param;
}

/**
 * Get the data of each user for the participants report.
 * @param string $detailsid The meeting ID that you want to get the participants report for.
 * @return array The user data as an array of records (array of arrays).
 */
function zoom_get_participants_report($detailsid) {
    global $DB;
    $service = new mod_zoom_webservice();
    $sql = 'SELECT zmp.id,
                   zmp.name,
                   zmp.userid,
                   zmp.user_email,
                   zmp.join_time,
                   zmp.leave_time,
                   zmp.duration,
                   zmp.attentiveness_score,
                   zmp.uuid
              FROM {zoom_meeting_participants} zmp
             WHERE zmp.detailsid = :detailsid
    ';
    $params = [
        'detailsid' => $detailsid
    ];
    $participants = $DB->get_records_sql($sql, $params);
    return $participants;
}

/**
 * The datetime from the datetime selector is Already timezone adjusted for that user.
 * So when they think they are setting 12am the timestamp really becomes 8am or so.
 * We roll back this change by removing the offset so WebEx
 * has to original time value. That way when WebEx add's in their timeZone adjustment
 * or when moodle displays the time userdate will take tz into account.
 *
 * @param int $datetime
 * @param string $format
 * @return string
 * @throws coding_exception
 */
function zoom_convert_date_time($datetime, $format = 'Y-m-d\TH:i:s') {
    try {
        return (new DateTimeImmutable('@'.$datetime))
            ->setTimezone(core_date::get_user_timezone_object())
            ->format($format);
    } catch (Exception $e) {
        throw new coding_exception($e->getMessage(), $e->getTraceAsString());
    }
}

/**
 * Get zoom supported time zones
 * @return array
 */
function zoom_get_time_zones() {
    return [
        'Pacific/Midway' => 'Midway Island, Samoa',
        'Pacific/Pago_Pago' => 'Pago Pago',
        'Pacific/Honolulu' => 'Hawaii',
        'America/Anchorage' => 'Alaska',
        'America/Vancouver' => 'Vancouver',
        'America/Los_Angeles' => 'Pacific Time (US and Canada)',
        'America/Tijuana' => 'Tijuana',
        'America/Edmonton' => 'Edmonton',
        'America/Denver' => 'Mountain Time (US and Canada)',
        'America/Phoenix' => 'Arizona',
        'America/Mazatlan' => 'Mazatlan',
        'America/Winnipeg' => 'Winnipeg',
        'America/Regina' => 'Saskatchewan',
        'America/Chicago' => 'Central Time (US and Canada)',
        'America/Mexico_City' => 'Mexico City',
        'America/Guatemala' => 'Guatemala',
        'America/El_Salvador' => 'El Salvador',
        'America/Managua' => 'Managua',
        'America/Costa_Rica' => 'Costa Rica',
        'America/Montreal' => 'Montreal',
        'America/New_York' => 'Eastern Time (US and Canada)',
        'America/Indianapolis' => 'Indiana (East)',
        'America/Panama' => 'Panama',
        'America/Bogota' => 'Bogota',
        'America/Lima' => 'Lima',
        'America/Halifax' => 'Halifax',
        'America/Puerto_Rico' => 'Puerto Rico',
        'America/Caracas' => 'Caracas',
        'America/Santiago' => 'Santiago',
        'America/St_Johns' => 'Newfoundland and Labrador',
        'America/Montevideo' => 'Montevideo',
        'America/Araguaina' => 'Brasilia',
        'America/Argentina/Buenos_Aires' => 'Buenos Aires, Georgetown',
        'America/Godthab' => 'Greenland',
        'America/Sao_Paulo' => 'Sao Paulo',
        'Atlantic/Azores' => 'Azores',
        'Canada/Atlantic' => 'Atlantic Time (Canada)',
        'Atlantic/Cape_Verde' => 'Cape Verde Islands',
        'UTC' => 'Universal Time UTC',
        'Etc/Greenwich' => 'Greenwich Mean Time',
        'Europe/Belgrade' => 'Belgrade, Bratislava, Ljubljana',
        'CET' => 'Sarajevo, Skopje, Zagreb',
        'Atlantic/Reykjavik' => 'Reykjavik',
        'Europe/Dublin' => 'Dublin',
        'Europe/London' => 'London',
        'Europe/Lisbon' => 'Lisbon',
        'Africa/Casablanca' => 'Casablanca',
        'Africa/Nouakchott' => 'Nouakchott',
        'Europe/Oslo' => 'Oslo',
        'Europe/Copenhagen' => 'Copenhagen',
        'Europe/Brussels' => 'Brussels',
        'Europe/Berlin' => 'Amsterdam, Berlin, Rome, Stockholm, Vienna',
        'Europe/Helsinki' => 'Helsinki',
        'Europe/Amsterdam' => 'Amsterdam',
        'Europe/Rome' => 'Rome',
        'Europe/Stockholm' => 'Stockholm',
        'Europe/Vienna' => 'Vienna',
        'Europe/Luxembourg' => 'Luxembourg',
        'Europe/Paris' => 'Paris',
        'Europe/Zurich' => 'Zurich',
        'Europe/Madrid' => 'Madrid',
        'Africa/Bangui' => 'West Central Africa',
        'Africa/Algiers' => 'Algiers',
        'Africa/Tunis' => 'Tunis',
        'Africa/Harare' => 'Harare, Pretoria',
        'Africa/Nairobi' => 'Nairobi',
        'Europe/Warsaw' => 'Warsaw',
        'Europe/Prague' => 'Prague Bratislava',
        'Europe/Budapest' => 'Budapest',
        'Europe/Sofia' => 'Sofia',
        'Europe/Istanbul' => 'Istanbul',
        'Europe/Athens' => 'Athens',
        'Europe/Bucharest' => 'Bucharest',
        'Asia/Nicosia' => 'Nicosia',
        'Asia/Beirut' => 'Beirut',
        'Asia/Damascus' => 'Damascus',
        'Asia/Jerusalem' => 'Jerusalem',
        'Asia/Amman' => 'Amman',
        'Africa/Tripoli' => 'Tripoli',
        'Africa/Cairo' => 'Cairo',
        'Africa/Johannesburg' => 'Johannesburg',
        'Europe/Moscow' => 'Moscow',
        'Asia/Baghdad' => 'Baghdad',
        'Asia/Kuwait' => 'Kuwait',
        'Asia/Riyadh' => 'Riyadh',
        'Asia/Bahrain' => 'Bahrain',
        'Asia/Qatar' => 'Qatar',
        'Asia/Aden' => 'Aden',
        'Asia/Tehran' => 'Tehran',
        'Africa/Khartoum' => 'Khartoum',
        'Africa/Djibouti' => 'Djibouti',
        'Africa/Mogadishu' => 'Mogadishu',
        'Asia/Dubai' => 'Dubai',
        'Asia/Muscat' => 'Muscat',
        'Asia/Baku' => 'Baku, Tbilisi, Yerevan',
        'Asia/Kabul' => 'Kabul',
        'Asia/Yekaterinburg' => 'Yekaterinburg',
        'Asia/Tashkent' => 'Islamabad, Karachi, Tashkent',
        'Asia/Kathmandu' => 'Kathmandu',
        'Asia/Novosibirsk' => 'Novosibirsk',
        'Asia/Almaty' => 'Almaty',
        'Asia/Dacca' => 'Dacca',
        'Asia/Krasnoyarsk' => 'Krasnoyarsk',
        'Asia/Dhaka' => 'Astana, Dhaka',
        'Asia/Bangkok' => 'Bangkok',
        'Asia/Saigon' => 'Vietnam',
        'Asia/Jakarta' => 'Jakarta',
        'Asia/Irkutsk' => 'Irkutsk, Ulaanbaatar',
        'Asia/Shanghai' => 'Beijing, Shanghai',
        'Asia/Hong_Kong' => 'Hong Kong',
        'Asia/Taipei' => 'Taipei',
        'Asia/Kuala_Lumpur' => 'Kuala Lumpur',
        'Asia/Singapore' => 'Singapore',
        'Australia/Perth' => 'Perth',
        'Asia/Yakutsk' => 'Yakutsk',
        'Asia/Seoul' => 'Seoul',
        'Asia/Tokyo' => 'Osaka, Sapporo, Tokyo',
        'Australia/Darwin' => 'Darwin',
        'Australia/Adelaide' => 'Adelaide',
        'Asia/Vladivostok' => 'Vladivostok',
        'Pacific/Port_Moresby' => 'Guam, Port Moresby',
        'Australia/Brisbane' => 'Brisbane',
        'Australia/Sydney' => 'Canberra, Melbourne, Sydney',
        'Australia/Hobart' => 'Hobart',
        'Asia/Magadan' => 'Magadan',
        'SST' => 'Solomon Islands',
        'Pacific/Noumea' => 'New Caledonia',
        'Asia/Kamchatka' => 'Kamchatka',
        'Pacific/Fiji' => 'Fiji Islands, Marshall Islands',
        'Pacific/Auckland' => 'Auckland, Wellington',
        'Asia/Kolkata' => 'Mumbai, Kolkata, New Delhi',
        'Europe/Kiev' => 'Kiev',
        'America/Tegucigalpa' => 'Tegucigalpa',
        'Pacific/Apia' => 'Independent State of Samoa',
    ];
}
