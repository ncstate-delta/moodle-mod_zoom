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
 * The main zoom configuration form
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package    mod_zoom
 * @copyright  2015 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');
require_once($CFG->dirroot.'/mod/zoom/classes/Enums/MeetingType.php');
require_once($CFG->dirroot.'/mod/zoom/classes/Enums/DaysOfWeek.php');
require_once($CFG->dirroot.'/mod/zoom/classes/Enums/RecurringFrequency.php');
require_once($CFG->dirroot.'/mod/zoom/classes/Enums/MonthlyWeek.php');
require_once($CFG->dirroot.'/mod/zoom/classes/Enums/EndType.php');
require_once($CFG->dirroot.'/mod/zoom/lib.php');
require_once($CFG->dirroot.'/mod/zoom/locallib.php');

/**
 * Module instance settings form
 *
 * @package    mod_zoom
 * @copyright  2015 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_zoom_mod_form extends moodleform_mod {

    /**
     * Defines forms elements
     */
    public function definition() {
        global $PAGE, $USER;
        $config = get_config('mod_zoom');
        $service = new mod_zoom_webservice();
        $zoomuser = $service->get_user($USER->email);
        if ($zoomuser === false) {
            // Assume user is using Zoom for the first time.
            $errstring = 'zoomerr_usernotfound';
            // After they set up their account, the user should continue to the page they were on.
            $nexturl = $PAGE->url;
            throw new moodle_exception($errstring, 'mod_zoom', $nexturl, $config->zoomurl);
        }

        // If updating, ensure we can get the meeting on Zoom.
        $isnew = empty($this->_cm);
        if (!$isnew) {
            try {
                $service->get_meeting_webinar_info($this->current->meeting_id, $this->current->webinar);
            } catch (moodle_exception $error) {
                // If the meeting can't be found, offer to recreate the meeting on Zoom.
                if (zoom_is_meeting_gone_error($error)) {
                    $errstring = 'zoomerr_meetingnotfound';
                    $param = zoom_meetingnotfound_param($this->_cm->id);
                    $nexturl = "/mod/zoom/view.php?id=" . $this->_cm->id;
                    throw new moodle_exception($errstring, 'mod_zoom', $nexturl, $param, "meeting/get : $error");
                } else {
                    throw $error;
                }
            }
        }

        // Start of form definition.
        $mform = $this->_form;

        // Adding the "general" fieldset, where all the common settings are showed.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Add topic (stored in database as 'name').
        $mform->addElement('text', 'name', get_string('topic', 'zoom'), array('size' => '64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 300), 'maxlength', 300, 'client');

        // Add description ('intro' and 'introformat').
        $this->standard_intro_elements();
        // Repeat type
        $mform->addElement('select', 'type', 'Type', MeetingType::get());
        $mform->addHelpButton('type', 'form_repeat_type', 'zoom');
        $mform->setDefault('type', 1);
        $mform->addRule('type', null, 'required', null, 'client');

        $mform->addElement('select', 'timezone', 'Time zone', [
            'Pacific/Midway' => 'Midway Island, Samoa',
            'Pacific/Pago_Pago' => 	'Pago Pago',
            'Pacific/Honolulu' => 'Hawaii',
            'America/Anchorage' => 'Alaska',
            'America/Vancouver' => 'Vancouver',
            'America/Los_Angeles' => 'Pacific Time (US and Canada)',
            'America/Tijuana' => 	'Tijuana',
            'America/Edmonton' => 	'Edmonton',
            'America/Denver' => 	'Mountain Time (US and Canada)',
            'America/Phoenix' => 	'Arizona',
            'America/Mazatlan' => 	'Mazatlan',
            'America/Winnipeg' => 	'Winnipeg',
            'America/Regina' => 	'Saskatchewan',
            'America/Chicago' => 	'Central Time (US and Canada)',
            'America/Mexico_City' => 	'Mexico City',
            'America/Guatemala' => 	'Guatemala',
            'America/El_Salvador' => 	'El Salvador',
            'America/Managua' => 'Managua',
            'America/Costa_Rica' => 	'Costa Rica',
            'America/Montreal' => 	'Montreal',
            'America/New_York' => 	'Eastern Time (US and Canada)',
            'America/Indianapolis' => 	'Indiana (East)',
            'America/Panama' => 	'Panama',
            'America/Bogota' => 	'Bogota',
            'America/Lima' => 	'Lima',
            'America/Halifax' => 	'Halifax',
            'America/Puerto_Rico' => 	'Puerto Rico',
            'America/Caracas' =>	'Caracas',
            'America/Santiago' => 	'Santiago',
            'America/St_Johns' => 	'Newfoundland and Labrador',
            'America/Montevideo' => 	'Montevideo',
            'America/Araguaina' => 	'Brasilia',
            'America/Argentina/Buenos_Aires' => 'Buenos Aires, Georgetown',
            'America/Godthab'	=> 'Greenland',
            'America/Sao_Paulo' => 	'Sao Paulo',
            'Atlantic/Azores' => 'Azores',
            'Canada/Atlantic' => 'Atlantic Time (Canada)',
            'Atlantic/Cape_Verde' => 'Cape Verde Islands',
            'UTC' => 'Universal Time UTC',
            'Etc/Greenwich' => 	'Greenwich Mean Time',
            'Europe/Belgrade' => 'Belgrade, Bratislava, Ljubljana',
            'CET' => 'Sarajevo, Skopje, Zagreb',
            'Atlantic/Reykjavik' => 'Reykjavik',
            'Europe/Dublin' => 	'Dublin',
            'Europe/London' => 	'London',
            'Europe/Lisbon' => 	'Lisbon',
            'Africa/Casablanca' => 	'Casablanca',
            'Africa/Nouakchott' => 	'Nouakchott',
            'Europe/Oslo' => 	'Oslo',
            'Europe/Copenhagen' => 	'Copenhagen',
            'Europe/Brussels' => 'Brussels',
            'Europe/Berlin' => 	'Amsterdam, Berlin, Rome, Stockholm, Vienna',
            'Europe/Helsinki' => 	'Helsinki',
            'Europe/Amsterdam' => 	'Amsterdam',
            'Europe/Rome' => 'Rome',
            'Europe/Stockholm' => 	'Stockholm',
            'Europe/Vienna' => 	'Vienna',
            'Europe/Luxembourg' => 	'Luxembourg',
            'Europe/Paris' => 'Paris',
            'Europe/Zurich' => 	'Zurich',
            'Europe/Madrid' => 	'Madrid',
            'Africa/Bangui' => 	'West Central Africa',
            'Africa/Algiers' => 'Algiers',
            'Africa/Tunis' => 	'Tunis',
            'Africa/Harare' => 	'Harare, Pretoria',
            'Africa/Nairobi' => 'Nairobi',
            'Europe/Warsaw' => 	'Warsaw',
            'Europe/Prague' => 	'Prague Bratislava',
            'Europe/Budapest' => 'Budapest',
            'Europe/Sofia' => 'Sofia',
            'Europe/Istanbul' => 'Istanbul',
            'Europe/Athens' => 	'Athens',
            'Europe/Bucharest' => 	'Bucharest',
            'Asia/Nicosia' => 'Nicosia',
            'Asia/Beirut' => 'Beirut',
            'Asia/Damascus' => 	'Damascus',
            'Asia/Jerusalem' => 	'Jerusalem',
            'Asia/Amman' => 	'Amman',
            'Africa/Tripoli' => 	'Tripoli',
            'Africa/Cairo' => 	'Cairo',
            'Africa/Johannesburg' => 	'Johannesburg',
            'Europe/Moscow' => 	'Moscow',
            'Asia/Baghdad' => 	'Baghdad',
            'Asia/Kuwait' => 	'Kuwait',
            'Asia/Riyadh' => 	'Riyadh',
            'Asia/Bahrain' => 	'Bahrain',
            'Asia/Qatar' => 	'Qatar',
            'Asia/Aden' => 	'Aden',
            'Asia/Tehran' => 	'Tehran',
            'Africa/Khartoum' => 	'Khartoum',
            'Africa/Djibouti' => 	'Djibouti',
            'Africa/Mogadishu' => 	'Mogadishu',
            'Asia/Dubai' => 	'Dubai',
            'Asia/Muscat' => 	'Muscat',
            'Asia/Baku' => 	'Baku, Tbilisi, Yerevan',
            'Asia/Kabul' => 	'Kabul',
            'Asia/Yekaterinburg' => 	'Yekaterinburg',
            'Asia/Tashkent' => 	'Islamabad, Karachi, Tashkent',
            'Asia/Kathmandu' => 	'Kathmandu',
            'Asia/Novosibirsk' => 	'Novosibirsk',
            'Asia/Almaty' => 	'Almaty',
            'Asia/Dacca' => 	'Dacca',
            'Asia/Krasnoyarsk' => 	'Krasnoyarsk',
            'Asia/Dhaka' => 'Astana, Dhaka',
            'Asia/Bangkok' => 	'Bangkok',
            'Asia/Saigon' => 	'Vietnam',
            'Asia/Jakarta' => 	'Jakarta',
            'Asia/Irkutsk' => 'Irkutsk, Ulaanbaatar',
            'Asia/Shanghai' => 	'Beijing, Shanghai',
            'Asia/Hong_Kong' => 	'Hong Kong',
            'Asia/Taipei' => 	'Taipei',
            'Asia/Kuala_Lumpur' => 	'Kuala Lumpur',
            'Asia/Singapore' => 	'Singapore',
            'Australia/Perth' => 	'Perth',
            'Asia/Yakutsk' => 	'Yakutsk',
            'Asia/Seoul' => 	'Seoul',
            'Asia/Tokyo' => 	'Osaka, Sapporo, Tokyo',
            'Australia/Darwin' => 	'Darwin',
            'Australia/Adelaide' => 	'Adelaide',
            'Asia/Vladivostok' => 	'Vladivostok',
            'Pacific/Port_Moresby' => 	'Guam, Port Moresby',
            'Australia/Brisbane' => 'Brisbane',
            'Australia/Sydney' => 'Canberra, Melbourne, Sydney',
            'Australia/Hobart'=> 'Hobart',
            'Asia/Magadan' => 'Magadan',
            'SST' => 'Solomon Islands',
            'Pacific/Noumea' => 'New Caledonia',
            'Asia/Kamchatka' => 	'Kamchatka',
            'Pacific/Fiji' => 	'Fiji Islands, Marshall Islands',
            'Pacific/Auckland' => 	'Auckland, Wellington',
            'Asia/Kolkata' => 	'Mumbai, Kolkata, New Delhi',
            'Europe/Kiev' => 'Kiev',
            'America/Tegucigalpa' => 'Tegucigalpa',
            'Pacific/Apia' => 'Independent State of Samoa',
        ]);
        $mform->setDefault('timezone', 'Asia/Kolkata');

        // Add date/time. Validation in validation().
        $mform->addElement('date_time_selector', 'start_time', get_string('start_time', 'zoom'));

        // Add duration.
        $mform->addElement('duration', 'duration', get_string('duration', 'zoom'), array('optional' => false));

        // Adding the "recurring" type
        $mform->addElement('select', 'recurring', 'Recurring', RecurringFrequency::get());
        $mform->addHelpButton('recurring', 'form_recurring', 'webex');
        $mform->hideIf('recurring', 'type', 'neq', MeetingType::Recurring_meeting_with_a_fixed_time);

        $days_count = [];
        for ($i = 1; $i <= 90; $i++) {
            $days_count[$i] = $i;
        }

        // Adding the recurring interval in days
        $mform->addElement('select', 'recurringdays', 'Repeat after X days', $days_count);
        $mform->addHelpButton('recurringdays', 'form_recurringdays', 'webex');
        $mform->hideIf('recurringdays', 'type', 'neq', MeetingType::Recurring_meeting_with_a_fixed_time);
        $mform->hideIf('recurringdays', 'recurring', 'neq', RecurringFrequency::Daily);
        $mform->setDefault('recurringdays', null);

        $weeks_count = [];
        for ($i = 1; $i <= 12; $i++) {
            $weeks_count[$i] = $i;
        }

        // Adding the recurring interval in weeks
        $mform->addElement('select', 'recurringweeks', 'Repeat after X weeks', $weeks_count);
        $mform->addHelpButton('recurringdays', 'form_recurringdays', 'webex');
        $mform->hideIf('recurringweeks', 'type', 'neq', MeetingType::Recurring_meeting_with_a_fixed_time);
        $mform->hideIf('recurringweeks', 'recurring', 'neq', RecurringFrequency::Weekly);
        $mform->setDefault('recurringweeks', null);

        $months_count = [];
        for ($i = 1; $i <= 3; $i++) {
            $months_count[$i] = $i;
        }

        // Adding the recurring interval in months
        $mform->addElement('select', 'recurringmonths', 'Repeat after X months', $months_count);
        $mform->addHelpButton('recurringdays', 'form_recurringdays', 'webex');
        $mform->hideIf('recurringmonths', 'type', 'neq', MeetingType::Recurring_meeting_with_a_fixed_time);
        $mform->hideIf('recurringmonths', 'recurring', 'neq', RecurringFrequency::Monthly);
        $mform->setDefault('recurringmonths', null);

        // Adding the week days for weekly recurring meetings
        $weeklygrp = [];
        foreach (DaysOfWeek::get() as $key => $day){
            $weeklygrp[] = &$mform->createElement('checkbox', strtolower($day), '', $day);
        }
        $mform->addGroup($weeklygrp, 'weeklygrp', get_string('form_weeklygrp', 'webex'), array('<br />','<br />','<br />','<br />','<br />','<br />'), false);
        $mform->addHelpButton('weeklygrp', 'form_weeklygrp', 'webex');
        $mform->hideIf('weeklygrp', 'type', 'neq', MeetingType::Recurring_meeting_with_a_fixed_time);
        $mform->hideIf('weeklygrp', 'recurring', 'neq', RecurringFrequency::Weekly);

        // Monthly recurring settings
        $dates = [];
        for ($i = 1; $i <= 31; $i++) {
            $dates[$i] = $i;
        }

        //Add days of month for monthly recurring meetings
        $monthlygrp1 = [];
        $monthlygrp1[] = &$mform->createElement('checkbox','monthdaysenable','', 'Day', 0);
        $monthlygrp1[] = &$mform->createElement('select','dayofmonth', '', $dates);
        $mform->addGroup($monthlygrp1, 'monthlygrp1', 'Monthly', array('    '), false);
        $mform->addHelpButton('monthlygrp1', 'form_monthlygrp', 'webex');
        $mform->hideIf('monthlygrp1', 'type', 'neq', MeetingType::Recurring_meeting_with_a_fixed_time);
        $mform->hideIf('monthlygrp1', 'recurring', 'neq', RecurringFrequency::Monthly);
        $mform->disabledIf('monthlygrp1','dayofweekenable','checked');
        $mform->disabledIf('monthlygrp1','monthdaysenable');
        $mform->disabledIf('dayofmonth','monthdaysenable');

        //Add days of week for monthly recurring meetings
        $monthlygrp2 = [];
        $monthlygrp2[] = &$mform->createElement('checkbox','dayofweekenable', '','Week',0);
        $monthlygrp2[] = &$mform->createElement('select','weekofmonth', '', MonthlyWeek::get());
        $monthlygrp2[] = &$mform->createElement('select','dayofweek', '', DaysOfWeek::get());
        $mform->addGroup($monthlygrp2, 'monthlygrp2', '', ['  '], false);
        $mform->hideIf('monthlygrp2', 'type', 'neq', MeetingType::Recurring_meeting_with_a_fixed_time);
        $mform->hideIf('monthlygrp2', 'recurring', 'neq', RecurringFrequency::Monthly);
        $mform->disabledIf('monthlygrp2','monthdaysenable','checked');
        $mform->disabledIf('weekofmonth','dayofweekenable');
        $mform->disabledIf('dayofweek','dayofweekenable');
        //End of monthly recurring mettings settings

        //Add the end type of recurring meeting
        $mform->addElement('select', 'endtype', get_string('form_endingtype','webex'), EndType::get());
        $mform->addHelpButton('endtype', 'form_endingtype', 'webex');
        $mform->setType('endtype', PARAM_INT);
        $mform->hideIf('endtype', 'type', 'neq', MeetingType::Recurring_meeting_with_a_fixed_time);

        //Add the end date of recurring meeting
        $mform->addElement('date_selector', 'enddate', 'End by', ['startyear' => 2018, 'optional' => false]);
        $mform->addHelpButton('enddate', 'form_enddate', 'webex');
        $mform->hideIf('enddate', 'type', 'neq', MeetingType::Recurring_meeting_with_a_fixed_time);
        $mform->hideIf('enddate', 'endtype', 'neq', EndType::End_by_date);

        //Add the occurrence count to end after
        $occurrences = [];
        for ($i = 1; $i <= 20; $i++) {
            $occurrences[$i] = $i;
        }
        $mform->addElement('select','endafter', 'End after X occurrences', $occurrences);
        $mform->addHelpButton('endafter', 'form_endafter', 'webex');
        $mform->hideIf('endafter', 'type', 'neq', MeetingType::Recurring_meeting_with_a_fixed_time);
        $mform->hideIf('endafter', 'endtype', 'neq', EndType::End_after_X_occurrence);


        // Adding the "recurring" fieldset, where all the recurring based settings are showed
        $mform->addElement('header', 'other', 'Others');
        // Validation in validation(). Default to one hour.
        $mform->setDefault('duration', array('number' => 1, 'timeunit' => 3600));
        // Disable for recurring meetings.
        $mform->disabledIf('duration', 'recurring', 'checked');

        if ($isnew) {
            // Add webinar, disabled if the user cannot create webinars.
            $webinarattr = null;
            if (!$service->_get_user_settings($zoomuser->id)->feature->webinar) {
                $webinarattr = array('disabled' => true, 'group' => null);
            }
            $mform->addElement('advcheckbox', 'webinar', get_string('webinar', 'zoom'), '', $webinarattr);
            $mform->setDefault('webinar', 0);
            $mform->addHelpButton('webinar', 'webinar', 'zoom');
        } else if ($this->current->webinar) {
            $mform->addElement('html', get_string('webinar_already_true', 'zoom'));
        } else {
            $mform->addElement('html', get_string('webinar_already_false', 'zoom'));
        }

        // Add password.
        $mform->addElement('passwordunmask', 'password', get_string('password', 'zoom'), array('maxlength' => '10'));
        // Check password uses valid characters.
        $regex = '/^[a-zA-Z0-9@_*-]{1,10}$/';
        $mform->addRule('password', get_string('err_password', 'mod_zoom'), 'regex', $regex, 'client');
        $mform->disabledIf('password', 'webinar', 'checked');

        // Add host/participants video (checked by default).
        $mform->addGroup(array(
            $mform->createElement('radio', 'option_host_video', '', get_string('on', 'zoom'), true),
            $mform->createElement('radio', 'option_host_video', '', get_string('off', 'zoom'), false)
        ), null, get_string('option_host_video', 'zoom'));
        $mform->setDefault('option_host_video', $config->defaulthostvideo);
        $mform->disabledIf('option_host_video', 'webinar', 'checked');

        $mform->addGroup(array(
            $mform->createElement('radio', 'option_participants_video', '', get_string('on', 'zoom'), true),
            $mform->createElement('radio', 'option_participants_video', '', get_string('off', 'zoom'), false)
        ), null, get_string('option_participants_video', 'zoom'));
        $mform->setDefault('option_participants_video', $config->defaultparticipantsvideo);
        $mform->disabledIf('option_participants_video', 'webinar', 'checked');

        // Add audio options.
        $mform->addGroup(array(
            $mform->createElement('radio', 'option_audio', '', get_string('audio_telephony', 'zoom'), ZOOM_AUDIO_TELEPHONY),
            $mform->createElement('radio', 'option_audio', '', get_string('audio_voip', 'zoom'), ZOOM_AUDIO_VOIP),
            $mform->createElement('radio', 'option_audio', '', get_string('audio_both', 'zoom'), ZOOM_AUDIO_BOTH)
        ), null, get_string('option_audio', 'zoom'));
        $mform->setDefault('option_audio', $config->defaultaudiooption);

        //Add mute upon entry (false default)
        $mform->addGroup(array(
            $mform->createElement('radio', 'option_mute_upon_entry', '', get_string('on', 'zoom'), true),
            $mform->createElement('radio', 'option_mute_upon_entry', '', get_string('off', 'zoom'), false)
        ), null, get_string('option_mute_upon_entry', 'zoom'));
        $mform->setDefault('option_mute_upon_entry', $config->defaultmuteoption);

        // Add meeting options. Make sure we pass $appendName as false
        // so the options aren't nested in a 'meetingoptions' array.
        $mform->addGroup(array(
            // Join before host.
            $mform->createElement('advcheckbox', 'option_jbh', '', get_string('option_jbh', 'zoom'))
        ), 'meetingoptions', get_string('meetingoptions', 'zoom'), null, false);
        $mform->setDefault('option_jbh', $config->defaultjoinbeforehost);
        $mform->addHelpButton('meetingoptions', 'meetingoptions', 'zoom');
        $mform->disabledIf('meetingoptions', 'webinar', 'checked');

        //Add Auto recording option
        $mform->addGroup(array(
            $mform->createElement('radio', 'auto_recording', '', get_string('auto_rec_none', 'zoom'), ZOOM_REC_NONE),
            $mform->createElement('radio', 'auto_recording', '', get_string('auto_rec_local', 'zoom'), ZOOM_REC_LOCAL),
            $mform->createElement('radio', 'auto_recording', '', get_string('auto_rec_cloud', 'zoom'), ZOOM_REC_CLOUD)
        ), null , get_string('auto_recording', 'zoom'));
        $mform->setDefault('auto_recording', $config->defaultautorecording);

        // Add alternative hosts.
        $mform->addElement('text', 'alternative_hosts', get_string('alternative_hosts', 'zoom'), array('size' => '64'));
        $mform->setType('alternative_hosts', PARAM_TEXT);
        // Set the maximum field length to 255 because that's the limit on Zoom's end.
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('alternative_hosts', 'alternative_hosts', 'zoom');

        // Add meeting id.
        $mform->addElement('hidden', 'meeting_id', -1);
        $mform->setType('meeting_id', PARAM_ALPHANUMEXT);

        // Add host id (will error if user does not have an account on Zoom).
        $mform->addElement('hidden', 'host_id', zoom_get_user_id());
        $mform->setType('host_id', PARAM_ALPHANUMEXT);

        // Add standard grading elements.
        $this->standard_grading_coursemodule_elements();
        $mform->setDefault('grade', false);

        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();

        // Add standard buttons, common to all modules.
        $this->add_action_buttons();
    }

    /**
     * More validation on form data.
     * See documentation in lib/formslib.php.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        global $CFG;
        $errors = array();

        // Only check for scheduled meetings.
        if (empty($data['recurring'])) {
            // Make sure start date is in the future.
            if ($data['start_time'] < strtotime('today')) {
                $errors['start_time'] = get_string('err_start_time_past', 'zoom');
            }

            // Make sure duration is positive and no more than 150 hours.
            if ($data['duration'] <= 0) {
                $errors['duration'] = get_string('err_duration_nonpositive', 'zoom');
            } else if ($data['duration'] > 150 * 60 * 60) {
                $errors['duration'] = get_string('err_duration_too_long', 'zoom');
            }
        }

        // Check if the listed alternative hosts are valid users on Zoom.
        require_once($CFG->dirroot.'/mod/zoom/classes/webservice.php');
        $service = new mod_zoom_webservice();
        $alternativehosts = explode(',', $data['alternative_hosts']);
        foreach ($alternativehosts as $alternativehost) {
            if (!($service->get_user($alternativehost))) {
                $errors['alternative_hosts'] = 'User ' . $alternativehost . ' was not found on Zoom.';
                break;
            }
        }

        return $errors;
    }
}

/**
 * Form to search for meeting reports.
 *
 * @package    mod_zoom
 * @copyright  2015 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_zoom_report_form extends moodleform {
    /**
     * Define form elements.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement('date_selector', 'from', get_string('from'));

        $mform->addElement('date_selector', 'to', get_string('to'));

        $mform->addElement('submit', 'submit', get_string('go'));
    }
}
