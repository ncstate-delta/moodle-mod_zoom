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
        $config = get_config('zoom');
        $PAGE->requires->js_call_amd("mod_zoom/form", 'init');
        $zoomapiidentifier = zoom_get_api_identifier($USER);

        $isnew = empty($this->_cm);

        $service = new mod_zoom_webservice();
        $zoomuser = $service->get_user($zoomapiidentifier);

        // If creating a new instance, but the Zoom user does not exist.
        if ($isnew && $zoomuser === false) {
            // Assume user is using Zoom for the first time.
            $errstring = 'zoomerr_usernotfound';
            // After they set up their account, the user should continue to the page they were on.
            $nexturl = $PAGE->url;
            zoom_fatal_error($errstring, 'mod_zoom', $nexturl, $config->zoomurl);
        }

        // Array of emails and proper names of Moodle users in this course that
        // can add Zoom meetings, and the user can schedule.
        $scheduleusers = [];

        $canschedule = false;
        if ($zoomuser !== false) {
            // Get the array of users they can schedule.
            $canschedule = $service->get_schedule_for_users($zoomapiidentifier);
        }

        if (!empty($canschedule)) {
            // Add the current user.
            $canschedule[$zoomuser->id] = new stdClass();
            $canschedule[$zoomuser->id]->email = $USER->email;

            // If the activity exists and the current user is not the current host.
            if (!$isnew && $zoomuser->id !== $this->current->host_id) {
                // Get intersection of current host's schedulers and $USER's schedulers to prevent zoom errors.
                $currenthostschedulers = $service->get_schedule_for_users($this->current->host_id);
                if (!empty($currenthostschedulers)) {
                    // Since this is the second argument to array_intersect_key,
                    // the entry from $canschedule will be used, so we can just
                    // use true to avoid a service call.
                    $currenthostschedulers[$this->current->host_id] = true;
                }
                $canschedule = array_intersect_key($canschedule, $currenthostschedulers);
            }

            // Get list of users who can add Zoom activities in this context.
            $moodleusers = get_enrolled_users($this->context, 'mod/zoom:addinstance', 0, 'u.*', 'lastname');

            // Check each potential host to see if they are a valid host.
            foreach ($canschedule as $zoomuserinfo) {
                $zoomemail = strtolower($zoomuserinfo->email);
                if (isset($scheduleusers[$zoomemail])) {
                    continue;
                }
                if ($zoomemail === strtolower($USER->email)) {
                    $scheduleusers[$zoomemail] = get_string('scheduleforself', 'zoom');
                    continue;
                }
                foreach ($moodleusers as $muser) {
                    if ($zoomemail === strtolower($muser->email)) {
                        $scheduleusers[$zoomemail] = fullname($muser);
                        break;
                    }
                }
            }
        }

        $meetinginfo = new stdClass();
        if (!$isnew) {
            try {
                $meetinginfo = $service->get_meeting_webinar_info($this->current->meeting_id, $this->current->webinar);
            } catch (moodle_exception $error) {
                // If the meeting can't be found, offer to recreate the meeting on Zoom.
                if (zoom_is_meeting_gone_error($error)) {
                    $errstring = 'zoomerr_meetingnotfound';
                    $param = zoom_meetingnotfound_param($this->_cm->id);
                    $nexturl = "/mod/zoom/view.php?id=" . $this->_cm->id;
                    zoom_fatal_error($errstring, 'mod_zoom', $nexturl, $param, "meeting/get : $error");
                } else {
                    throw $error;
                }
            }
        }

        // If the current editing user has the host saved in the db for this meeting on their list
        // of people that they can schedule for, allow them to change the host, otherwise don't.
        $allowschedule = false;
        if (!$isnew) {
            try {
                $founduser = $service->get_user($meetinginfo->host_id);
                if ($founduser && array_key_exists($founduser->email, $scheduleusers)) {
                    $allowschedule = true;
                }
            } catch (moodle_exception $error) {
                // Don't need to throw an error, just leave allowschedule as false.
                $allowschedule = false;
            }
        } else {
            $allowschedule = true;
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

        // Add description 'intro' and 'introformat'.
        $this->standard_intro_elements();

        // Adding the "schedule" fieldset, where all settings relating to date and time are shown.
        $mform->addElement('header', 'general', get_string('schedule', 'mod_zoom'));

        // Add date/time. Validation in validation().
        $mform->addElement('date_time_selector', 'start_time', get_string('start_time', 'zoom'));
        // Disable for recurring meetings.
        $mform->disabledIf('start_time', 'recurring', 'checked');

        // Add duration.
        $mform->addElement('duration', 'duration', get_string('duration', 'zoom'), array('optional' => false));
        // Validation in validation(). Default to one hour.
        $mform->setDefault('duration', array('number' => 1, 'timeunit' => 3600));
        // Disable for recurring meetings.
        $mform->disabledIf('duration', 'recurring', 'checked');

        // Add recurring widget.
        $mform->addElement('advcheckbox', 'recurring', get_string('recurringmeeting', 'zoom'),
                get_string('recurringmeetingthisis', 'zoom'));
        $mform->setDefault('recurring', $config->defaultrecurring);
        $mform->addHelpButton('recurring', 'recurringmeeting', 'zoom');

        // Supplementary feature: Webinars.
        // Only show if the admin did not disable this feature completely.
        if ($config->showwebinars != ZOOM_WEBINAR_DISABLE) {
            // If we are creating a new instance.
            if ($isnew) {
                // Check if the user has a webinar license.
                $haswebinarlicense = $service->_get_user_settings($zoomuser->id)->feature->webinar;

                // Only show if the admin always wants to show this widget or
                // if the admin wants to show this widget conditionally and the user has a valid license.
                if ($config->showwebinars == ZOOM_WEBINAR_ALWAYSSHOW ||
                        ($config->showwebinars == ZOOM_WEBINAR_SHOWONLYIFLICENSE && $haswebinarlicense)) {
                    // Add webinar option, disabled if the user cannot create webinars.
                    $webinarattr = null;
                    if (!$haswebinarlicense) {
                        $webinarattr = array('disabled' => true, 'group' => null);
                    }
                    $mform->addElement('advcheckbox', 'webinar', get_string('webinar', 'zoom'),
                            get_string('webinarthisis', 'zoom'), $webinarattr);
                    $mform->setDefault('webinar', 0);
                    $mform->addHelpButton('webinar', 'webinar', 'zoom');
                }
            } else if ($this->current->webinar) {
                $mform->addElement('static', 'webinaralreadyset', get_string('webinar', 'zoom'),
                        get_string('webinar_already_true', 'zoom'));
            } else {
                $mform->addElement('static', 'webinaralreadyset', get_string('webinar', 'zoom'),
                        get_string('webinar_already_false', 'zoom'));
            }
        }

        // Adding the "security" fieldset, where all settings relating to securing and protecting the meeting are shown.
        $mform->addElement('header', 'general', get_string('security', 'mod_zoom'));

        // Deals with password manager issues.
        if (isset($this->current->password)) {
            $this->current->meetingcode = $this->current->password;
            unset($this->current->password);
        }

        // Add password requirement prompt.
        $mform->addElement('advcheckbox', 'requirepasscode', get_string('password', 'zoom'),
                get_string('requirepasscode', 'zoom'));
        if (isset($this->current->meetingcode) && strval($this->current->meetingcode) === "") {
            $mform->setDefault('requirepasscode', 0);
        } else {
            $mform->setDefault('requirepasscode', 1);
        }
        $mform->addHelpButton('requirepasscode', 'requirepasscode', 'zoom');

        // Set default passcode and description from Zoom security settings.
        $securitysettings = zoom_get_meeting_security_settings();
        // Add password.
        $mform->addElement('text', 'meetingcode', get_string('setpasscode', 'zoom'), array('maxlength' => '10'));
        $mform->setType('meetingcode', PARAM_TEXT);
        // Check password uses valid characters.
        $regex = '/^[a-zA-Z0-9@_*-]{1,10}$/';
        $mform->addRule('meetingcode', get_string('err_invalid_password', 'mod_zoom'), 'regex', $regex, 'client');
        $mform->setDefault('meetingcode', zoom_create_default_passcode($securitysettings->meeting_password_requirement));
        $mform->hideIf('meetingcode', 'requirepasscode', 'notchecked');
        // Add passcode requirements note (use mform group trick from MDL-66251 to be able to conditionally hide this).
        $passwordrequirementsgroup = [];
        $passwordrequirementsgroup[] =& $mform->createElement('static', 'passwordrequirements', '',
        zoom_create_passcode_description($securitysettings->meeting_password_requirement));
        $mform->addGroup($passwordrequirementsgroup, 'passwordrequirementsgroup', '', '', false);
        $mform->hideIf('passwordrequirementsgroup', 'requirepasscode', 'notchecked');

        // Supplementary feature: Encryption type.
        // Only show if the admin did not disable this feature completely.
        if ($config->showencryptiontype != ZOOM_ENCRYPTION_DISABLE) {
            // Check if the user can use e2e encryption.
            $e2eispossible = $securitysettings->end_to_end_encrypted_meetings;

            if ($config->showencryptiontype == ZOOM_ENCRYPTION_SHOWONLYIFPOSSIBLE && !$e2eispossible) {
                // If user cannot use e2e and option is not shown to user,
                // default to enhanced encryption.
                $mform->addElement('hidden', 'option_encryption_type', ZOOM_ENCRYPTION_TYPE_ENHANCED);
            } else if ($config->showencryptiontype == ZOOM_ENCRYPTION_ALWAYSSHOW ||
                    ($config->showencryptiontype == ZOOM_ENCRYPTION_SHOWONLYIFPOSSIBLE && $e2eispossible)) {
                // Only show if the admin always wants to show this widget or
                // if the admin wants to show this widget conditionally and the user can use e2e encryption.

                // Add encryption type option, disabled if the user can't use e2e encryption.
                $encryptionattr = null;
                $defaultencryptiontype = $config->defaultencryptiontypeoption;
                if (!$e2eispossible) {
                    $encryptionattr = array('disabled' => true);
                    $defaultencryptiontype = ZOOM_ENCRYPTION_TYPE_ENHANCED;
                }
                $mform->addGroup(array(
                        $mform->createElement('radio', 'option_encryption_type', '',
                                get_string('option_encryption_type_enhancedencryption', 'zoom'),
                                ZOOM_ENCRYPTION_TYPE_ENHANCED, $encryptionattr),
                        $mform->createElement('radio', 'option_encryption_type', '',
                                get_string('option_encryption_type_endtoendencryption', 'zoom'),
                                ZOOM_ENCRYPTION_TYPE_E2EE, $encryptionattr)
                ), 'option_encryption_type_group', get_string('option_encryption_type', 'zoom'), null, false);
                $mform->setDefault('option_encryption_type', $defaultencryptiontype);
                $mform->addHelpButton('option_encryption_type_group', 'option_encryption_type', 'zoom');
                $mform->disabledIf('option_encryption_type_group', 'webinar', 'checked');
            }
            $mform->setType('option_encryption_type', PARAM_ALPHANUMEXT);
        }

        // Add waiting room widget.
        $mform->addElement('advcheckbox', 'option_waiting_room', get_string('option_waiting_room', 'zoom'),
                get_string('waitingroomenable', 'zoom'));
        $mform->addHelpButton('option_waiting_room', 'option_waiting_room', 'zoom');
        $mform->setDefault('option_waiting_room', $config->defaultwaitingroomoption);
        $mform->disabledIf('option_waiting_room', 'webinar', 'checked');

        // Add join before host widget.
        $mform->addElement('advcheckbox', 'option_jbh', get_string('option_jbh', 'zoom'),
                get_string('joinbeforehostenable', 'zoom'));
        $mform->setDefault('option_jbh', $config->defaultjoinbeforehost);
        $mform->addHelpButton('option_jbh', 'option_jbh', 'zoom');
        $mform->disabledIf('option_jbh', 'webinar', 'checked');

        // Add authenticated users widget.
        $mform->addElement('advcheckbox', 'option_authenticated_users', get_string('authentication', 'zoom'),
                get_string('option_authenticated_users', 'zoom'));
        $mform->setDefault('option_authenticated_users', $config->defaultauthusersoption);
        $mform->addHelpButton('option_authenticated_users', 'option_authenticated_users', 'zoom');

        // Adding the "media" fieldset, where all settings relating to media streams in the meeting are shown.
        $mform->addElement('header', 'general', get_string('media', 'mod_zoom'));

        // Add host/participants video options.
        $mform->addGroup(array(
            $mform->createElement('radio', 'option_host_video', '', get_string('on', 'zoom'), true),
            $mform->createElement('radio', 'option_host_video', '', get_string('off', 'zoom'), false)
        ), 'option_host_video_group', get_string('option_host_video', 'zoom'), null, false);
        $mform->setDefault('option_host_video', $config->defaulthostvideo);
        $mform->addHelpButton('option_host_video_group', 'option_host_video', 'zoom');
        $mform->disabledIf('option_host_video_group', 'webinar', 'checked');

        $mform->addGroup(array(
            $mform->createElement('radio', 'option_participants_video', '', get_string('on', 'zoom'), true),
            $mform->createElement('radio', 'option_participants_video', '', get_string('off', 'zoom'), false)
        ), 'option_participants_video_group', get_string('option_participants_video', 'zoom'), null, false);
        $mform->setDefault('option_participants_video', $config->defaultparticipantsvideo);
        $mform->addHelpButton('option_participants_video_group', 'option_participants_video', 'zoom');
        $mform->disabledIf('option_participants_video_group', 'webinar', 'checked');

        // Add audio options.
        $mform->addGroup(array(
            $mform->createElement('radio', 'option_audio', '', get_string('audio_telephony', 'zoom'), ZOOM_AUDIO_TELEPHONY),
            $mform->createElement('radio', 'option_audio', '', get_string('audio_voip', 'zoom'), ZOOM_AUDIO_VOIP),
            $mform->createElement('radio', 'option_audio', '', get_string('audio_both', 'zoom'), ZOOM_AUDIO_BOTH)
        ), 'option_audio_group', get_string('option_audio', 'zoom'), null, false);
        $mform->addHelpButton('option_audio_group', 'option_audio', 'zoom');
        $mform->setDefault('option_audio', $config->defaultaudiooption);

        // Add mute participants upon entry widget.
        $mform->addElement('advcheckbox', 'option_mute_upon_entry', get_string('audiodefault', 'mod_zoom'),
                get_string('option_mute_upon_entry', 'mod_zoom'));
        $mform->setDefault('option_mute_upon_entry', $config->defaultmuteuponentryoption);
        $mform->addHelpButton('option_mute_upon_entry', 'option_mute_upon_entry', 'mod_zoom');

        // Check if there is any setting to be shown in the "host" fieldset.
        $showschedulingprivilege = ($config->showschedulingprivilege != ZOOM_SCHEDULINGPRIVILEGE_DISABLE) &&
                count($scheduleusers) > 1 && $allowschedule; // Check if the size is greater than 1 because
                                                             // we add the editing/creating user by default.
        $showalternativehosts = ($config->showalternativehosts != ZOOM_ALTERNATIVEHOSTS_DISABLE);
        if ($showschedulingprivilege || $showalternativehosts) {

            // Adding the "host" fieldset, where all settings relating to defining the meeting host are shown.
            $mform->addElement('header', 'general', get_string('host', 'mod_zoom'));

            // Supplementary feature: Alternative hosts.
            // Only show if the admin did not disable this feature completely.
            if ($showalternativehosts) {
                // Explain alternativehosts.
                $mform->addElement('static', 'hostintro', '', get_string('hostintro', 'zoom'));

                // If the admin wants to show the plain input field.
                if ($config->showalternativehosts == ZOOM_ALTERNATIVEHOSTS_INPUTFIELD) {
                    // Add alternative hosts.
                    $mform->addElement('text', 'alternative_hosts', get_string('alternative_hosts', 'zoom'), array('size' => '64'));
                    $mform->setType('alternative_hosts', PARAM_TEXT);
                    $mform->addHelpButton('alternative_hosts', 'alternative_hosts', 'zoom');

                    // If the admin wants to show the user picker.
                } else if ($config->showalternativehosts == ZOOM_ALTERNATIVEHOSTS_PICKER) {
                    // Get selectable alternative host users based on the capability.
                    $alternativehostschoices = zoom_get_selectable_alternative_hosts_list($this->context);
                    // Create autocomplete widget.
                    $alternativehostsoptions = array(
                            'multiple' => true,
                            'showsuggestions' => true,
                            'placeholder' => get_string('alternative_hosts_picker_placeholder', 'zoom'),
                            'noselectionstring' => get_string('alternative_hosts_picker_noneselected', 'zoom'));
                    $mform->addElement('autocomplete', 'alternative_hosts_picker', get_string('alternative_hosts', 'zoom'),
                            $alternativehostschoices, $alternativehostsoptions);
                    $mform->setType('alternative_hosts_picker', PARAM_EMAIL);
                    $mform->addHelpButton('alternative_hosts_picker', 'alternative_hosts_picker', 'zoom');
                }
            }

            // Supplementary feature: Scheduling privilege.
            // Only show if the admin did not disable this feature completely and if current user is able to use it.
            if ($showschedulingprivilege) {
                $mform->addElement('select', 'schedule_for', get_string('schedulefor', 'zoom'), $scheduleusers);
                $mform->setType('schedule_for', PARAM_EMAIL);
                if (!$isnew) {
                    $mform->disabledIf('schedule_for', 'change_schedule_for');
                    $mform->addElement('checkbox', 'change_schedule_for', get_string('changehost', 'zoom'));
                    $mform->setDefault('schedule_for', strtolower($service->get_user($this->current->host_id)->email));
                } else {
                    $mform->setDefault('schedule_for', strtolower($zoomapiidentifier));
                }
                $mform->addHelpButton('schedule_for', 'schedulefor', 'zoom');
            }
        }

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
        $this->apply_admin_defaults();

        // Add standard buttons, common to all modules.
        $this->add_action_buttons();
    }

    /**
     * Allows module to modify the data returned by form get_data().
     * This method is also called in the bulk activity completion form.
     *
     * Only available on moodleform_mod.
     *
     * @param stdClass $data the form data to be modified.
     */
    public function data_postprocessing($data) {
        global $DB;

        parent::data_postprocessing($data);

        // Get config.
        $config = get_config('zoom');

        // If the admin did show the alternative hosts user picker.
        if ($config->showalternativehosts == ZOOM_ALTERNATIVEHOSTS_PICKER) {
            // If there was at least one alternative host selected, process these users.
            if (count($data->alternative_hosts_picker) > 0) {
                // Populate the alternative_hosts field with a concatenated string of email addresses.
                // This is done as this is the format which Zoom expects and alternative_hosts is the field to store the data
                // in mod_zoom.
                // The alternative host user picker is just an add-on to help teachers to fill this field.
                $data->alternative_hosts = implode(',', $data->alternative_hosts_picker);

                // If there wasn't any alternative host selected.
            } else {
                $data->alternative_hosts = '';
            }

            // Unfortunately, the host is not only able to add alternative hosts in Moodle with the user picker.
            // He is also able to add any alternative host with an email address in Zoom directly.
            // Thus, we have to get the latest list of alternative hosts from the DB again now,
            // identify the users who were not selectable at all in this form and append them to the list
            // of selected alternative hosts.

            // Get latest list of alternative hosts from the DB.
            $result = $DB->get_field('zoom', 'alternative_hosts', array('meeting_id' => $data->meeting_id), IGNORE_MISSING);

            // Proceed only if there is a field of alternative hosts already.
            if ($result !== false) {
                $alternativehostsdb = zoom_get_alternative_host_array_from_string($result);

                // Get selectable alternative host users based on the capability.
                $alternativehostschoices = zoom_get_selectable_alternative_hosts_list($this->context);

                // Iterate over the latest list of alternative hosts from the DB.
                foreach ($alternativehostsdb as $ah) {
                    // If the existing alternative host would not have been selectable.
                    if (!array_key_exists($ah, $alternativehostschoices)) {
                        // Add the alternative host to the alternative_hosts field.
                        if ($data->alternative_hosts == '') {
                            $data->alternative_hosts = $ah;
                        } else {
                            $data->alternative_hosts .= ',' . $ah;
                        }
                    }
                }
            }
        }
    }

    /**
     * Allows module to modify data returned by get_moduleinfo_data() or prepare_new_moduleinfo_data() before calling set_data()
     * This method is also called in the bulk activity completion form.
     *
     * Only available on moodleform_mod.
     *
     * @param array $defaultvalues passed by reference
     */
    public function data_preprocessing(&$defaultvalues) {
        parent::data_preprocessing($defaultvalues);

        // Get config.
        $config = get_config('zoom');

        // If the admin wants to show the alternative hosts user picker.
        if ($config->showalternativehosts == ZOOM_ALTERNATIVEHOSTS_PICKER) {
            // If there is at least one alternative host set.
            if (isset($defaultvalues['alternative_hosts']) && strlen($defaultvalues['alternative_hosts']) > 0) {
                // Populate the alternative_hosts_picker field with an exploded array of email addresses.
                // This is done as alternative_hosts is the field to store the data in mod_zoom and
                // the alternative host user picker is just an add-on to help teachers to fill this field.

                // At this point, the alternative_hosts field might also contain users who are not selectable in the user picker
                // as they aren't a member of the course or do not have a Moodle account.
                // This does not matter as user picker default values which don't have a corresponding autocomplete suggestion
                // will be simply ignored.
                // When the form is submitted, these non-selectable alternative hosts will be added again in data_postprocessing().

                // According to the documentation, the Zoom API separates the email addresses with commas,
                // but we also want to deal with semicolon-separated lists just in case.
                $defaultvalues['alternative_hosts_picker'] = zoom_get_alternative_host_array_from_string(
                    $defaultvalues['alternative_hosts']
                );
            }
        }
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
        global $CFG, $USER;
        $errors = array();

        $config = get_config('zoom');

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

        require_once($CFG->dirroot.'/mod/zoom/classes/webservice.php');
        $service = new mod_zoom_webservice();

        if (!empty($data['requirepasscode']) && empty($data['meetingcode'])) {
            $errors['meetingcode'] = get_string('err_password_required', 'mod_zoom');
        }
        if (isset($data['schedule_for']) &&  $data['schedule_for'] !== $zoomapiidentifier) {
            $scheduleusers = $service->get_schedule_for_users($zoomapiidentifier);
            $scheduleok = false;
            foreach ($scheduleusers as $zuser) {
                if (strtolower($zuser->email) === strtolower($data['schedule_for'])) {
                    // Found a matching email address in the Zoom users list.
                    $scheduleok = true;
                    break;
                }
            }
            if (!$scheduleok) {
                $errors['schedule_for'] = get_string('invalidscheduleuser', 'mod_zoom');
            }
        }

        // Supplementary feature: Alternative hosts.
        // Only validate if the admin did not disable this feature completely.
        if ($config->showalternativehosts != ZOOM_ALTERNATIVEHOSTS_DISABLE) {
            // If the admin did show the plain input field.
            if ($config->showalternativehosts == ZOOM_ALTERNATIVEHOSTS_INPUTFIELD) {
                // Check if the listed alternative hosts are valid users on Zoom.
                $alternativehosts = zoom_get_alternative_host_array_from_string($data['alternative_hosts']);
                foreach ($alternativehosts as $alternativehost) {
                    if (!($service->get_user($alternativehost))) {
                        $errors['alternative_hosts'] = get_string('zoomerr_alternativehostusernotfound', 'zoom', $alternativehost);
                        break;
                    }
                }

                // If the admin did show the user picker.
            } else if ($config->showalternativehosts == ZOOM_ALTERNATIVEHOSTS_PICKER) {
                // Check if the picked alternative hosts are valid users on Zoom.
                foreach ($data['alternative_hosts_picker'] as $alternativehost) {
                    if (!($service->get_user($alternativehost))) {
                        $errors['alternative_hosts_picker'] =
                                get_string('zoomerr_alternativehostusernotfound', 'zoom', $alternativehost);
                        break;
                    }
                }
            }
        }

        // Supplementary feature: Encryption type.
        // Only validate if the admin did not disable this feature completely.
        if ($config->showencryptiontype != ZOOM_ENCRYPTION_DISABLE) {
            // Check if given encryption type is valid.
            if ($data['option_encryption_type'] !== ZOOM_ENCRYPTION_TYPE_ENHANCED &&
                    $data['option_encryption_type'] !== ZOOM_ENCRYPTION_TYPE_E2EE) {
                // This will not happen unless the user tampered with the form.
                // Because of this, we skip adding this string to the language pack.
                $errors['option_encryption_type_group'] = 'The submitted encryption type is not valid.';
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
