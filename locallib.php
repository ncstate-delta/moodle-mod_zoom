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
define('ZOOM_SCHEDULED_WEBINAR', 5);
define('ZOOM_RECURRING_WEBINAR', 6);
// Meeting status.
define('ZOOM_MEETING_EXPIRED', 0);
define('ZOOM_MEETING_EXISTS', 1);

// Number of meetings per page from zoom's get user report.
define('ZOOM_DEFAULT_RECORDS_PER_CALL', 30);
define('ZOOM_MAX_RECORDS_PER_CALL', 300);
// User types. Numerical values from Zoom API.
define('ZOOM_USER_TYPE_BASIC', 1);
define('ZOOM_USER_TYPE_PRO', 2);
define('ZOOM_USER_TYPE_CORP', 3);
define('ZOOM_MEETING_NOT_FOUND_ERROR_CODE', 3001);
define('ZOOM_USER_NOT_FOUND_ERROR_CODE', 1001);
define('ZOOM_INVALID_USER_ERROR_CODE', 1120);
// Webinar options.
define('ZOOM_WEBINAR_DISABLE', 0);
define('ZOOM_WEBINAR_SHOWONLYIFLICENSE', 1);
define('ZOOM_WEBINAR_ALWAYSSHOW', 2);
// Encryption type options.
define('ZOOM_ENCRYPTION_DISABLE', 0);
define('ZOOM_ENCRYPTION_SHOWONLYIFPOSSIBLE', 1);
define('ZOOM_ENCRYPTION_ALWAYSSHOW', 2);
// Encryption types. String values for Zoom API.
define('ZOOM_ENCRYPTION_TYPE_ENHANCED', 'enhanced_encryption');
define('ZOOM_ENCRYPTION_TYPE_E2EE', 'e2ee');
// Alternative hosts options.
define('ZOOM_ALTERNATIVEHOSTS_DISABLE', 0);
define('ZOOM_ALTERNATIVEHOSTS_INPUTFIELD', 1);
define('ZOOM_ALTERNATIVEHOSTS_PICKER', 2);
// Scheduling privilege options.
define('ZOOM_SCHEDULINGPRIVILEGE_DISABLE', 0);
define('ZOOM_SCHEDULINGPRIVILEGE_ENABLE', 1);
// All meetings options.
define('ZOOM_ALLMEETINGS_DISABLE', 0);
define('ZOOM_ALLMEETINGS_ENABLE', 1);
// Download iCal options.
define('ZOOM_DOWNLOADICAL_DISABLE', 0);
define('ZOOM_DOWNLOADICAL_ENABLE', 1);
// Capacity warning options.
define('ZOOM_CAPACITYWARNING_DISABLE', 0);
define('ZOOM_CAPACITYWARNING_ENABLE', 1);

/**
 * Entry not found on Zoom.
 *
 * @copyright  2020 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class zoom_not_found_exception extends moodle_exception {
    /**
     * Web service response.
     * @var string
     */
    public $response = null;

    /**
     * Constructor
     * @param string $response      Web service response message
     * @param int $errorcode     Web service response error code
     */
    public function __construct($response, $errorcode) {
        $this->response = $response;
        $this->zoomerrorcode = $errorcode;
        parent::__construct('errorwebservice_notfound', 'zoom');
    }
}

/**
 * Bad request received by Zoom.
 *
 * @copyright  2020 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class zoom_bad_request_exception extends moodle_exception {
    /**
     * Web service response.
     * @var string
     */
    public $response = null;

    /**
     * Constructor
     * @param string $response      Web service response message
     * @param int $errorcode     Web service response error code
     */
    public function __construct($response, $errorcode) {
        $this->response = $response;
        $this->zoomerrorcode = $errorcode;
        parent::__construct('errorwebservice_badrequest', 'zoom', '', $response);
    }
}

/**
 * Couldn't succeed within the allowed number of retries.
 *
 * @copyright  2020 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class zoom_api_retry_failed_exception extends moodle_exception {
    /**
     * Web service response.
     * @var string
     */
    public $response = null;

    /**
     * Constructor
     * @param string $response      Web service response
     * @param int $errorcode     Web service response error code
     */
    public function __construct($response, $errorcode) {
        $this->response = $response;
        $this->zoomerrorcode = $errorcode;
        $a = new stdClass();
        $a->response = $response;
        $a->maxretries = mod_zoom_webservice::MAX_RETRIES;
        parent::__construct('zoomerr_maxretries', 'zoom', '', $a);
    }
}

/**
 * Exceeded daily API limit.
 *
 * @copyright  2020 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class zoom_api_limit_exception extends moodle_exception {
    /**
     * Web service response.
     * @var string
     */
    public $response = null;

    /**
     * Unix timestamp of next time to API can be called.
     * @var int
     */
    public $retryafter = null;

    /**
     * Constructor
     * @param string $response  Web service response
     * @param int $errorcode    Web service response error code
     * @param int $retryafter   Unix timestamp of next time to API can be called.
     */
    public function __construct($response, $errorcode, $retryafter) {
        $this->response = $response;
        $this->zoomerrorcode = $errorcode;
        $this->retryafter = $retryafter;
        $a = new stdClass();
        $a->response = $response;
        parent::__construct('zoomerr_apilimit', 'zoom', '',
                userdate($retryafter, get_string('strftimedaydatetime', 'core_langconfig')));
    }
}

/**
 * Terminate the current script with a fatal error.
 *
 * Adapted from core_renderer's fatal_error() method. Needed because throwing errors with HTML links in them will convert links
 * to text using htmlentities. See MDL-66161 - Reflected XSS possible from some fatal error messages.
 *
 * So need custom error handler for fatal Zoom errors that have links to help people.
 *
 * @param string $errorcode The name of the string from error.php to print
 * @param string $module name of module
 * @param string $continuelink The url where the user will be prompted to continue.
 *                             If no url is provided the user will be directed to
 *                             the site index page.
 * @param mixed $a Extra words and phrases that might be required in the error string
 */
function zoom_fatal_error($errorcode, $module='', $continuelink='', $a=null) {
    global $CFG, $COURSE, $OUTPUT, $PAGE;

    $output = '';
    $obbuffer = '';

    // Assumes that function is run before output is generated.
    if ($OUTPUT->has_started()) {
        // If not then have to default to standard error.
        throw new moodle_exception($errorcode, $module, $continuelink, $a);
    }

    $PAGE->set_heading($COURSE->fullname);
    $output .= $OUTPUT->header();

    // Output message without messing with HTML content of error.
    $message = '<p class="errormessage">' . get_string($errorcode, $module, $a) . '</p>';

    $output .= $OUTPUT->box($message, 'errorbox alert alert-danger', null, array('data-rel' => 'fatalerror'));

    if ($CFG->debugdeveloper) {
        if (!empty($debuginfo)) {
            $debuginfo = s($debuginfo); // Removes all nasty JS.
            $debuginfo = str_replace("\n", '<br />', $debuginfo); // Keep newlines.
            $output .= $OUTPUT->notification('<strong>Debug info:</strong> '.$debuginfo, 'notifytiny');
        }
        if (!empty($backtrace)) {
            $output .= $OUTPUT->notification('<strong>Stack trace:</strong> '.format_backtrace($backtrace), 'notifytiny');
        }
        if ($obbuffer !== '' ) {
            $output .= $OUTPUT->notification('<strong>Output buffer:</strong> '.s($obbuffer), 'notifytiny');
        }
    }

    if (!empty($continuelink)) {
        $output .= $OUTPUT->continue_button($continuelink);
    }

    $output .= $OUTPUT->footer();

    // Padding to encourage IE to display our error page, rather than its own.
    $output .= str_repeat(' ', 512);

    echo $output;

    exit(1); // General error code.
}

/**
 * Get course/cm/zoom objects from url parameters, and check for login/permissions.
 *
 * @return array Array of ($course, $cm, $zoom)
 */
function zoom_get_instance_setup() {
    global $DB;

    $id = optional_param('id', 0, PARAM_INT); // Course_module ID.
    $n  = optional_param('n', 0, PARAM_INT);  // Zoom instance ID.

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
 * @return array information about the meeting
 */
function zoom_get_sessions_for_display($meetingid) {
    require_once(__DIR__.'/../../lib/moodlelib.php');
    global $DB;

    $sessions = array();
    $format = get_string('strftimedatetimeshort', 'langconfig');

    $instances = $DB->get_records('zoom_meeting_details', array('meeting_id' => $meetingid));

    foreach ($instances as $instance) {
        // The meeting uuid, not the participant's uuid.
        $uuid = $instance->uuid;
        $participantlist = zoom_get_participants_report($instance->id);
        $sessions[$uuid]['participants'] = $participantlist;

        $uniquevalues = [];
        $uniqueparticipantcount = 0;
        foreach ($participantlist as $participant) {
            $unique = true;
            if ($participant->uuid != null) {
                if (array_key_exists($participant->uuid, $uniquevalues)) {
                    $unique = false;
                } else {
                    $uniquevalues[$participant->uuid] = true;
                }
            }
            if ($participant->userid != null) {
                if (!$unique || !array_key_exists($participant->userid, $uniquevalues)) {
                    $uniquevalues[$participant->userid] = true;
                } else {
                    $unique = false;
                }
            }
            if ($participant->user_email != null) {
                if (!$unique || !array_key_exists($participant->user_email, $uniquevalues)) {
                    $uniquevalues[$participant->user_email] = true;
                } else {
                    $unique = false;
                }
            }
            $uniqueparticipantcount += $unique ? 1 : 0;
        }

        $sessions[$uuid]['count'] = $uniqueparticipantcount;
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
    $config = get_config('zoom');
    $now = time();

    $firstavailable = $zoom->start_time - ($config->firstabletojoin * 60);
    $lastavailable = $zoom->start_time + $zoom->duration;
    $inprogress = ($firstavailable <= $now && $now <= $lastavailable);

    $available = $zoom->recurring || $inprogress;

    $finished = !$zoom->recurring && $now > $lastavailable;

    return array($inprogress, $available, $finished);
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
            $zoomuser = $service->get_user(zoom_get_api_identifier());
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
 * Get the Zoom meeting security settings, including meeting password requirements of the user's master account.
 *
 * @return stdClass
 */
function zoom_get_meeting_security_settings() {
    $cache = cache::make('mod_zoom', 'zoommeetingsecurity');
    if (!($zoommeetingsecurity = $cache->get('meetingsecurity'))) {
        $service = new mod_zoom_webservice();
        try {
            $zoommeetingsecurity = $service->get_account_meeting_security_settings();
        } catch (moodle_exception $error) {
            throw $error;
        }
        $cache->set('meetingsecurity', $zoommeetingsecurity);
    }

    return $zoommeetingsecurity;
}

/**
 * Check if the error indicates that a meeting is gone.
 *
 * @param moodle_exception $error
 * @return bool
 */
function zoom_is_meeting_gone_error($error) {
    // If the meeting's owner/user cannot be found, we consider the meeting to be gone.
    return ($error->zoomerrorcode === ZOOM_MEETING_NOT_FOUND_ERROR_CODE) || zoom_is_user_not_found_error($error);
}

/**
 * Check if the error indicates that a user is not found or does not belong to the current account.
 *
 * @param moodle_exception $error
 * @return bool
 */
function zoom_is_user_not_found_error($error) {
    return ($error->zoomerrorcode === ZOOM_USER_NOT_FOUND_ERROR_CODE) || ($error->zoomerrorcode === ZOOM_INVALID_USER_ERROR_CODE);
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
    $sql = 'SELECT zmp.id,
                   zmp.name,
                   zmp.userid,
                   zmp.user_email,
                   zmp.join_time,
                   zmp.leave_time,
                   zmp.duration,
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
 * Creates a default passcode from the user's Zoom meeting security settings.
 *
 * @param stdClass $meetingpasswordrequirement
 * @return string passcode
 */
function zoom_create_default_passcode($meetingpasswordrequirement) {
    $length = max($meetingpasswordrequirement->length, 6);
    $random = rand(0, pow(10, $length) - 1);
    $passcode = str_pad(strval($random), $length, '0', STR_PAD_LEFT);

    // Get a random set of indexes to replace with non-numberic values.
    $indexes = range(0, $length - 1);
    shuffle($indexes);

    if ($meetingpasswordrequirement->have_letter || $meetingpasswordrequirement->have_upper_and_lower_characters) {
        // Random letter from A-Z.
        $passcode[$indexes[0]] = chr(rand(65, 90));
        // Random letter from a-z.
        $passcode[$indexes[1]] = chr(rand(97, 122));
    }

    if ($meetingpasswordrequirement->have_special_character) {
        $specialchar = '@_*-';
        $passcode[$indexes[2]] = substr(str_shuffle($specialchar), 0, 1);
    }

    return $passcode;
}

/**
 * Creates a description string from the user's Zoom meeting security settings.
 *
 * @param stdClass $meetingpasswordrequirement
 * @return string description of password requirements
 */
function zoom_create_passcode_description($meetingpasswordrequirement) {
    $description = '';
    if ($meetingpasswordrequirement->only_allow_numeric) {
        $description .= get_string('password_only_numeric', 'mod_zoom') . ' ';
    } else {
        if ($meetingpasswordrequirement->have_letter && !$meetingpasswordrequirement->have_upper_and_lower_characters) {
            $description .= get_string('password_letter', 'mod_zoom') . ' ';
        } else if ($meetingpasswordrequirement->have_upper_and_lower_characters) {
            $description .= get_string('password_lower_upper', 'mod_zoom') . ' ';
        }

        if ($meetingpasswordrequirement->have_number) {
            $description .= get_string('password_number', 'mod_zoom') . ' ';
        }

        if ($meetingpasswordrequirement->have_special_character) {
            $description .= get_string('password_special', 'mod_zoom') . ' ';
        } else {
            $description .= get_string('password_allowed_char', 'mod_zoom') . ' ';
        }
    }

    if ($meetingpasswordrequirement->length) {
        $description .= get_string('password_length', 'mod_zoom', $meetingpasswordrequirement->length) . ' ';
    }

    if ($meetingpasswordrequirement->consecutive_characters_length &&
        $meetingpasswordrequirement->consecutive_characters_length > 0) {
        $description .= get_string('password_consecutive', 'mod_zoom',
            $meetingpasswordrequirement->consecutive_characters_length - 1) . ' ';
    }

    $description .= get_string('password_max_length', 'mod_zoom');
    return $description;
}

/**
 * Creates an array of users who can be selected as alternative host in a given context.
 *
 * @param context $context The context to be used.
 *
 * @return array Array of users (mail => fullname).
 */
function zoom_get_selectable_alternative_hosts_list(context $context) {
    // Get selectable alternative host users based on the capability.
    $users = get_enrolled_users($context, 'mod/zoom:eligiblealternativehost', 0, 'u.*', 'lastname');

    // Create array of users.
    $selectablealternativehosts = array();

    // Create Zoom API instance.
    $service = new mod_zoom_webservice();

    // Iterate over selectable alternative host users.
    foreach ($users as $u) {
        // Note: Basically, if this is the user's own data row, the data row should be skipped.
        // But this would then not cover the case when a user is scheduling the meeting _for_ another user
        // and wants to be an alternative host himself.
        // As this would have to be handled at runtime in the browser, we just offer all users with the
        // capability as selectable and leave this aspect as possible improvement for the future.
        // At least, Zoom does not care if the user who is the host adds himself as alternative host as well.

        // Verify that the user really has a Zoom account.
        // Furthermore, verify that the user's status is active. Adding a pending or inactive user as alternative host will result
        // in a Zoom API error otherwise.
        $zoomuser = $service->get_user($u->email);
        if ($zoomuser !== false && $zoomuser->status === 'active') {
            // Add user to array of users.
            $selectablealternativehosts[$u->email] = fullname($u);
        }
    }

    return $selectablealternativehosts;
}

/**
 * Creates a string of roles who can be selected as alternative host in a given context.
 *
 * @param context $context The context to be used.
 *
 * @return string The string of roles.
 */
function zoom_get_selectable_alternative_hosts_rolestring(context $context) {
    // Get selectable alternative host users based on the capability.
    $roles = get_role_names_with_caps_in_context($context, array('mod/zoom:eligiblealternativehost'));

    // Compose string.
    $rolestring = implode(', ', $roles);

    return $rolestring;
}

/**
 * Get existing Moodle users from a given set of alternative hosts.
 *
 * @param array $alternativehosts The array of alternative hosts email addresses.
 *
 * @return array The array of existing Moodle user objects.
 */
function zoom_get_users_from_alternativehosts(array $alternativehosts) {
    global $DB;

    // Get the existing Moodle user objects from the DB.
    list($insql, $inparams) = $DB->get_in_or_equal($alternativehosts);
    $sql = 'SELECT *
            FROM {user}
            WHERE email '.$insql.'
            ORDER BY lastname ASC';
    $alternativehostusers = $DB->get_records_sql($sql, $inparams);

    return $alternativehostusers;
}

/**
 * Get non-Moodle users from a given set of alternative hosts.
 *
 * @param array $alternativehosts The array of alternative hosts email addresses.
 *
 * @return array The array of non-Moodle user mail addresses.
 */
function zoom_get_nonusers_from_alternativehosts(array $alternativehosts) {
    global $DB;

    // Get the non-Moodle user mail addresses by checking which one does not exist in the DB.
    $alternativehostnonusers = array();
    list($insql, $inparams) = $DB->get_in_or_equal($alternativehosts);
    $sql = 'SELECT email
            FROM {user}
            WHERE email '.$insql.'
            ORDER BY email ASC';
    $alternativehostusersmails = $DB->get_records_sql($sql, $inparams);
    foreach ($alternativehosts as $ah) {
        if (!array_key_exists($ah, $alternativehostusersmails)) {
            $alternativehostnonusers[] = $ah;
        }
    }

    return $alternativehostnonusers;
}

/**
 * Get the unavailability note based on the Zoom plugin configuration.
 *
 * @param object $zoom The Zoom meeting object.
 * @param bool|null $finished The function needs to know if the meeting is already finished.
 *                       You can provide this information, if already available, to the function.
 *                       Otherwise it will determine it with a small overhead.
 *
 * @return string The unavailability note.
 */
function zoom_get_unavailability_note($zoom, $finished = null) {
    // Get config.
    $config = get_config('zoom');

    // Get the plain unavailable string.
    $strunavailable = get_string('unavailable', 'mod_zoom');

    // If this is a recurring meeting, just use the plain unavailable string.
    if (!empty($zoom->recurring)) {
        $unavailabilitynote = $strunavailable;

        // Otherwise we add some more information to the unavailable string.
    } else {
        // If we don't have the finished information yet, get it with a small overhead.
        if ($finished === null) {
            list($inprogress, $available, $finished) = zoom_get_state($zoom);
        }

        // If this meeting is still pending.
        if ($finished !== true) {
            // If the admin wants to show the leadtime.
            if (!empty($config->displayleadtime) && $config->firstabletojoin > 0) {
                $unavailabilitynote = $strunavailable . '<br />' .
                        get_string('unavailablefirstjoin', 'mod_zoom', array('mins' => ($config->firstabletojoin)));

                // Otherwise.
            } else {
                $unavailabilitynote = $strunavailable . '<br />' . get_string('unavailablenotstartedyet', 'mod_zoom');
            }

            // Otherwise, the meeting has finished.
        } else {
            $unavailabilitynote = $strunavailable . '<br />' . get_string('unavailablefinished', 'mod_zoom');
        }
    }

    return $unavailabilitynote;
}

/**
 * Gets the meeting capacity of a given Zoom user.
 * Please note: This function does not check if the Zoom user really exists, this has to be checked before calling this function.
 *
 * @param string $zoomhostid The Zoom ID of the host.
 * @param boolean $iswebinar The meeting is a webinar.
 *
 * @return int|boolean The meeting capacity of the Zoom user or false if the user does not have any meeting capacity at all.
 */
function zoom_get_meeting_capacity(string $zoomhostid, bool $iswebinar = false) {
    // Get Zoom API service instance.
    $service = new mod_zoom_webservice();

    // Get the 'feature' section of the user's Zoom settings.
    $userfeatures = $service->_get_user_settings($zoomhostid)->feature;

    // If this is a webinar.
    if ($iswebinar == true) {
        // Get the 'webinar_capacity' value.
        $meetingcapacity = $userfeatures->webinar_capacity;

        // If the user does not have a webinar capacity for any reason, return.
        if (is_int($meetingcapacity) == false || $meetingcapacity <= 0) {
            return false;
        }

        // If this isn't a webinar but a regular meeting.
    } else {
        // Get the 'meeting_capacity' value.
        $meetingcapacity = $userfeatures->meeting_capacity;

        // If the user does not have a meeting capacity for any reason, return.
        if (is_int($meetingcapacity) == false || $meetingcapacity <= 0) {
            return false;
        }

        // Check if the user has a 'large_meeting' license and, if yes, if this is bigger than the given 'meeting_capacity' value.
        if ($userfeatures->large_meeting === true &&
                isset($userfeatures->large_meeting_capacity) &&
                is_int($userfeatures->large_meeting_capacity) != false &&
                $userfeatures->large_meeting_capacity > $userfeatures->meeting_capacity) {
            $meetingcapacity = $userfeatures->large_meeting_capacity;
        }
    }

    return $meetingcapacity;
}

/**
 * Gets the number of eligible meeting participants in a given context.
 * Please note: This function only covers users who are enrolled into the given context.
 * It does _not_ include users who have the necessary capability on a higher context without being enrolled.
 *
 * @param context $context The context which we want to check.
 *
 * @return int The number of eligible meeting participants.
 */
function zoom_get_eligible_meeting_participants(context $context) {
    global $DB;

    // Compose SQL query.
    $sqlsnippets = get_enrolled_with_capabilities_join($context, '', 'mod/zoom:view', 0, true);
    $sql = 'SELECT count(DISTINCT u.id)
            FROM {user} u '.$sqlsnippets->joins.' WHERE '.$sqlsnippets->wheres;

    // Run query and count records.
    $eligibleparticipantcount = $DB->count_records_sql($sql, $sqlsnippets->params);

    return $eligibleparticipantcount;
}

/**
 * Get all custom user profile fields of type text
 *
 * @return array list of user profile fields
 */
function zoom_get_user_profile_fields() {
    global $DB;

    $userfields = [];
    $records = $DB->get_records('user_info_field', ['datatype' => 'text']);
    foreach ($records as $record) {
        $userfields[$record->shortname] = $record->name;
    }

    return $userfields;
}

/**
 * Get all valid options for API Identifier field
 *
 * @return array list of all valid options
 */
function zoom_get_api_identifier_fields() {
    $options = [
        'email' => get_string('email'),
        'username' => get_string('username'),
        'idnumber' => get_string('idnumber'),
    ];

    $userfields = zoom_get_user_profile_fields();
    if (!empty($userfields)) {
        $options = array_merge($options, $userfields);
    }

    return $options;
}

/**
 * Get the zoom api identifier
 *
 * @return string the value of the identifier
 */
function zoom_get_api_identifier() {
    global $USER;

    // Get the value from the config first.
    $field = get_config('zoom', 'apiidentifier');

    $identifier = '';
    if (isset($USER->$field)) {
        // If one of the standard user fields.
        $identifier = $USER->$field;
    } else if (isset($USER->profile[$field])) {
        // If one of the custom user fields.
        $identifier = $USER->profile[$field];
    }
    if (empty($identifier)) {
        // Fallback to email if the field is not set.
        $identifier = $USER->email;
    }

    return $identifier;
}
