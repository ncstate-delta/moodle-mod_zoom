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
 * Handles API calls to Zoom REST API.
 *
 * @package   mod_zoom
 * @copyright 2015 UC Regents
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoom;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/zoom/lib.php');
require_once($CFG->dirroot . '/mod/zoom/locallib.php');
require_once($CFG->libdir . '/filelib.php');

use cache;
use core_user;
use curl;
use moodle_exception;
use stdClass;

/**
 * Web service class.
 */
class webservice {
    /**
     * API calls: maximum number of retries.
     * @var int
     */
    public const MAX_RETRIES = 5;

    /**
     * Default meeting_password_requirement object.
     * @var array
     */
    public const DEFAULT_MEETING_PASSWORD_REQUIREMENT = [
        'length' => 0,
        'consecutive_characters_length' => 0,
        'have_letter' => false,
        'have_number' => false,
        'have_upper_and_lower_characters' => false,
        'have_special_character' => false,
        'only_allow_numeric' => false,
        'weak_enhance_detection' => false,
    ];

    /**
     * Client ID
     * @var string
     */
    protected $clientid;

    /**
     * Client secret
     * @var string
     */
    protected $clientsecret;

    /**
     * Account ID
     * @var string
     */
    protected $accountid;

    /**
     * API base URL.
     * @var string
     */
    protected $apiurl;

    /**
     * Whether to recycle licenses.
     * @var bool
     */
    protected $recyclelicenses;

    /**
     * Whether to check instance users
     * @var bool
     */
    protected $instanceusers;

    /**
     * Maximum limit of paid users
     * @var int
     */
    protected $numlicenses;

    /**
     * List of users
     * @var array
     */
    protected static $userslist;

    /**
     * Number of retries we've made for make_call
     * @var int
     */
    protected $makecallretries = 0;

    /**
     * Granted OAuth scopes
     * @var array
     */
    protected $scopes;

    /**
     * The constructor for the webservice class.
     * @throws moodle_exception Moodle exception is thrown for missing config settings.
     */
    public function __construct() {
        $config = get_config('zoom');

        $requiredfields = [
            'clientid',
            'clientsecret',
            'accountid',
        ];

        try {
            // Get and remember each required field.
            foreach ($requiredfields as $requiredfield) {
                if (!empty($config->$requiredfield)) {
                    $this->$requiredfield = $config->$requiredfield;
                } else {
                    throw new moodle_exception('zoomerr_field_missing', 'mod_zoom', '', get_string($requiredfield, 'mod_zoom'));
                }
            }

            // Get and remember the API URL.
            $this->apiurl = zoom_get_api_url();

            // Get and remember the plugin settings to recycle licenses.
            if (!empty($config->utmost)) {
                $this->recyclelicenses = $config->utmost;
                $this->instanceusers = !empty($config->instanceusers);
            }

            if ($this->recyclelicenses) {
                if (!empty($config->licensesnumber)) {
                    $this->numlicenses = $config->licensesnumber;
                } else {
                    throw new moodle_exception('zoomerr_licensesnumber_missing', 'mod_zoom');
                }
            }
        } catch (moodle_exception $exception) {
            throw new moodle_exception('errorwebservice', 'mod_zoom', '', $exception->getMessage());
        }
    }

    /**
     * Makes the call to curl using the specified method, url, and parameter data.
     * This has been moved out of make_call to make unit testing possible.
     *
     * @param \curl $curl The curl object used to make the request.
     * @param string $method The HTTP method to use.
     * @param string $url The URL to append to the API URL
     * @param array|string $data The data to attach to the call.
     * @return stdClass The call's result.
     */
    protected function make_curl_call(&$curl, $method, $url, $data) {
        return $curl->$method($url, $data);
    }

    /**
     * Gets a curl object in order to make API calls. This function was created
     * to enable unit testing for the webservice class.
     * @return curl The curl object used to make the API calls
     */
    protected function get_curl_object() {
        global $CFG;

        $proxyhost = get_config('zoom', 'proxyhost');

        if (!empty($proxyhost)) {
            $cfg = new stdClass();
            $cfg->proxyhost = $CFG->proxyhost;
            $cfg->proxyport = $CFG->proxyport;
            $cfg->proxyuser = $CFG->proxyuser;
            $cfg->proxypassword = $CFG->proxypassword;
            $cfg->proxytype = $CFG->proxytype;

            // Parse string as host:port, delimited by a colon (:).
            [$host, $port] = explode(':', $proxyhost);

            // Temporarily set new values on the global $CFG.
            $CFG->proxyhost = $host;
            $CFG->proxyport = $port;
            $CFG->proxytype = 'HTTP';
            $CFG->proxyuser = '';
            $CFG->proxypassword = '';
        }

        // Create $curl, which implicitly uses the proxy settings from $CFG.
        $curl = new curl();

        if (!empty($proxyhost)) {
            // Restore the stored global proxy settings from above.
            $CFG->proxyhost = $cfg->proxyhost;
            $CFG->proxyport = $cfg->proxyport;
            $CFG->proxyuser = $cfg->proxyuser;
            $CFG->proxypassword = $cfg->proxypassword;
            $CFG->proxytype = $cfg->proxytype;
        }

        return $curl;
    }

    /**
     * Makes a REST call.
     *
     * @param string $path The path to append to the API URL
     * @param array|string $data The data to attach to the call.
     * @param string $method The HTTP method to use.
     * @return stdClass The call's result in JSON format.
     * @throws moodle_exception Moodle exception is thrown for curl errors.
     */
    private function make_call($path, $data = [], $method = 'get') {
        $url = $this->apiurl . $path;
        $method = strtolower($method);

        $token = $this->get_access_token();

        $curl = $this->get_curl_object();
        $curl->setHeader('Authorization: Bearer ' . $token);
        $curl->setHeader('Accept: application/json');

        if ($method != 'get') {
            $curl->setHeader('Content-Type: application/json');
            $data = is_array($data) ? json_encode($data) : $data;
        }

        $attempts = 0;
        do {
            if ($attempts > 0) {
                sleep(1);
                debugging('retrying after curl error 35, retry attempt ' . $attempts);
            }

            $rawresponse = $this->make_curl_call($curl, $method, $url, $data);
            $attempts++;
        } while ($curl->get_errno() === 35 && $attempts <= self::MAX_RETRIES);

        if ($curl->get_errno()) {
            throw new moodle_exception('errorwebservice', 'mod_zoom', '', $curl->error);
        }

        $response = json_decode($rawresponse);

        $httpstatus = $curl->get_info()['http_code'];

        if ($httpstatus >= 400) {
            switch ($httpstatus) {
                case 400:
                    $errorstring = '';
                    if (!empty($response->errors)) {
                        foreach ($response->errors as $error) {
                            $errorstring .= ' ' . $error->message;
                        }
                    }
                    throw new bad_request_exception($response->message . $errorstring, $response->code);

                case 404:
                    throw new not_found_exception($response->message, $response->code);

                case 429:
                    $this->makecallretries += 1;
                    if ($this->makecallretries > self::MAX_RETRIES) {
                        throw new retry_failed_exception($response->message, $response->code);
                    }

                    $header = $curl->getResponse();
                    // Header can have mixed case, normalize it.
                    $header = array_change_key_case($header, CASE_LOWER);

                    // Default to 1 second for max requests per second limit.
                    $timediff = 1;

                    // Check if we hit the max requests per minute (only for Dashboard API).
                    if (
                        array_key_exists('x-ratelimit-type', $header) &&
                        $header['x-ratelimit-type'] == 'QPS' &&
                        strpos($path, 'metrics') !== false
                    ) {
                        $timediff = 60; // Try the next minute.
                    } else if (array_key_exists('retry-after', $header)) {
                        $retryafter = strtotime($header['retry-after']);
                        $timediff = $retryafter - time();
                        // If we have no API calls remaining, save retry-after.
                        if ($header['x-ratelimit-remaining'] == 0 && !empty($retryafter)) {
                            set_config('retry-after', $retryafter, 'zoom');
                            throw new api_limit_exception($response->message, $response->code, $retryafter);
                        } else if (!(defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {
                            // When running CLI we might want to know how many calls remaining.
                            debugging('x-ratelimit-remaining = ' . $header['x-ratelimit-remaining']);
                        }
                    }

                    debugging('Received 429 response, sleeping ' . strval($timediff) .
                            ' seconds until next retry. Current retry: ' . $this->makecallretries);
                    if ($timediff > 0 && !(defined('PHPUNIT_TEST') && PHPUNIT_TEST)) {
                        sleep($timediff);
                    }
                    return $this->make_call($path, $data, $method);

                default:
                    if ($response) {
                        throw new webservice_exception(
                            $response->message,
                            $response->code,
                            'errorwebservice',
                            'mod_zoom',
                            '',
                            $response->message
                        );
                    } else {
                        throw new moodle_exception('errorwebservice', 'mod_zoom', '', "HTTP Status $httpstatus");
                    }
            }
        }

        $this->makecallretries = 0;

        return $response;
    }

    /**
     * Makes a paginated REST call.
     * Makes a call like make_call() but specifically for GETs with paginated results.
     *
     * @param string $url The URL to append to the API URL
     * @param array $data The data to attach to the call.
     * @param string $datatoget The name of the array of the data to get.
     * @return array The retrieved data.
     * @see make_call()
     */
    private function make_paginated_call($url, $data, $datatoget) {
        $aggregatedata = [];
        $data['page_size'] = ZOOM_MAX_RECORDS_PER_CALL;
        do {
            $callresult = null;
            $moredata = false;
            $callresult = $this->make_call($url, $data);

            if ($callresult) {
                $aggregatedata = array_merge($aggregatedata, $callresult->$datatoget);
                if (!empty($callresult->next_page_token)) {
                    $data['next_page_token'] = $callresult->next_page_token;
                    $moredata = true;
                } else if (!empty($callresult->page_number) && $callresult->page_number < $callresult->page_count) {
                    $data['page_number'] = $callresult->page_number + 1;
                    $moredata = true;
                }
            }
        } while ($moredata);

        return $aggregatedata;
    }

    /**
     * Autocreate a user on Zoom.
     *
     * @param stdClass $user The user to create.
     * @return bool Whether the user was succesfully created.
     * @deprecated Has never been used by internal code.
     */
    public function autocreate_user($user) {
        // Classic: user:write:admin.
        // Granular: user:write:user:admin.
        $url = 'users';
        $data = ['action' => 'autocreate'];
        $data['user_info'] = [
            'email' => zoom_get_api_identifier($user),
            'type' => ZOOM_USER_TYPE_PRO,
            'first_name' => $user->firstname,
            'last_name' => $user->lastname,
            'password' => base64_encode(random_bytes(16)),
        ];

        try {
            $this->make_call($url, $data, 'post');
        } catch (moodle_exception $error) {
            // If the user already exists, the error will contain 'User already in the account'.
            if (strpos($error->getMessage(), 'User already in the account') === true) {
                return false;
            } else {
                throw $error;
            }
        }

        return true;
    }

    /**
     * Get users list.
     *
     * @return array An array of users.
     */
    public function list_users() {
        if (empty(self::$userslist)) {
            // Classic: user:read:admin.
            // Granular: user:read:list_users:admin.
            self::$userslist = $this->make_paginated_call('users', [], 'users');
        }

        return self::$userslist;
    }

    /**
     * Checks whether the paid user license limit has been reached.
     *
     * Incrementally retrieves the active paid users and compares against $numlicenses.
     * @see $numlicenses
     * @return bool Whether the paid user license limit has been reached.
     */
    private function paid_user_limit_reached() {
        $userslist = $this->list_users();
        $numusers = 0;
        foreach ($userslist as $user) {
            if ($user->type != ZOOM_USER_TYPE_BASIC) {
                // Count the user if we're including all users or if the user is on this instance.
                if (!$this->instanceusers || core_user::get_user_by_email($user->email)) {
                    $numusers++;
                    if ($numusers >= $this->numlicenses) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Gets the ID of the user, of all the paid users, with the oldest last login time.
     *
     * @return string|false If user is found, returns the User ID. Otherwise, returns false.
     */
    private function get_least_recently_active_paid_user_id() {
        $usertimes = [];

        // Classic: user:read:admin.
        // Granular: user:read:list_users:admin.
        $userslist = $this->list_users();

        foreach ($userslist as $user) {
            if ($user->type != ZOOM_USER_TYPE_BASIC && isset($user->last_login_time)) {
                // Count the user if we're including all users or if the user is on this instance.
                if (!$this->instanceusers || core_user::get_user_by_email($user->email)) {
                    $usertimes[$user->id] = strtotime($user->last_login_time);
                }
            }
        }

        if (!empty($usertimes)) {
            return array_search(min($usertimes), $usertimes);
        }

        return false;
    }

    /**
     * Gets a user's settings.
     *
     * @param string $userid The user's ID.
     * @return stdClass The call's result in JSON format.
     */
    public function get_user_settings($userid) {
        // Classic: user:read:admin.
        // Granular: user:read:settings:admin.
        return $this->make_call('users/' . $userid . '/settings');
    }

    /**
     * Gets the user's meeting security settings, including password requirements.
     *
     * @param string $userid The user's ID.
     * @return stdClass The call's result in JSON format.
     */
    public function get_account_meeting_security_settings($userid) {
        // Classic: user:read:admin.
        // Granular: user:read:settings:admin.
        $url = 'users/' . $userid . '/settings?option=meeting_security';
        try {
            $response = $this->make_call($url);
            $meetingsecurity = $response->meeting_security;
        } catch (moodle_exception $error) {
            // Only available for Paid account, return default settings.
            $meetingsecurity = new stdClass();
            // If some other error, show debug message.
            if (isset($error->zoomerrorcode) && $error->zoomerrorcode != 200) {
                debugging($error->getMessage());
            }
        }

        // Set a default meeting password requirment if it is not present.
        if (!isset($meetingsecurity->meeting_password_requirement)) {
              $meetingsecurity->meeting_password_requirement = (object) self::DEFAULT_MEETING_PASSWORD_REQUIREMENT;
        }

        // Set a default encryption setting if it is not present.
        if (!isset($meetingsecurity->end_to_end_encrypted_meetings)) {
            $meetingsecurity->end_to_end_encrypted_meetings = false;
        }

        return $meetingsecurity;
    }

    /**
     * Gets a user.
     *
     * @param string|int $identifier The user's email or the user's ID per Zoom API.
     * @return stdClass|false If user is found, returns the User object. Otherwise, returns false.
     */
    public function get_user($identifier) {
        $founduser = false;

        // Classic: user:read:admin.
        // Granular: user:read:user:admin.
        $url = 'users/' . $identifier;

        try {
            $founduser = $this->make_call($url);
        } catch (webservice_exception $error) {
            if (zoom_is_user_not_found_error($error)) {
                return false;
            } else {
                throw $error;
            }
        }

        return $founduser;
    }

    /**
     * Gets a list of users that the given person can schedule meetings for.
     *
     * @param string $identifier The user's email or the user's ID per Zoom API.
     * @return array|false If schedulers are returned array of {id,email} objects. Otherwise returns false.
     */
    public function get_schedule_for_users($identifier) {
        // Classic: user:read:admin.
        // Granular: user:read:list_schedulers:admin.
        $url = "users/{$identifier}/schedulers";

        $schedulerswithoutkey = [];
        $schedulers = [];
        try {
            $response = $this->make_call($url);
            if (is_array($response->schedulers)) {
                $schedulerswithoutkey = $response->schedulers;
            }

            foreach ($schedulerswithoutkey as $s) {
                $schedulers[$s->id] = $s;
            }
        } catch (moodle_exception $error) {
            // We don't care if this throws an exception.
            $schedulers = [];
        }

        return $schedulers;
    }

    /**
     * Converts a zoom object from database format to API format.
     *
     * The database and the API use different fields and formats for the same information. This function changes the
     * database fields to the appropriate API request fields.
     *
     * @param stdClass $zoom The zoom meeting to format.
     * @param ?int $cmid The cmid if available.
     * @return array The formatted meetings for the meeting.
     */
    private function database_to_api($zoom, $cmid) {
        global $CFG;

        $options = [];
        if (!empty($cmid)) {
            $options['context'] = \context_module::instance($cmid);
        }

        $data = [
            // Process the meeting topic with proper filter.
            'topic' => zoom_apply_filter_on_meeting_name($zoom->name, $options),
            'settings' => [
                'host_video' => (bool) ($zoom->option_host_video),
                'audio' => $zoom->option_audio,
            ],
        ];
        if (isset($zoom->intro)) {
            // Process the description text with proper filter and then convert to plain text.
            $data['agenda'] = substr(content_to_text(format_text(
                $zoom->intro,
                FORMAT_MOODLE,
                $options
            ), false), 0, 2000);
        }

        if (isset($CFG->timezone) && !empty($CFG->timezone)) {
            $data['timezone'] = $CFG->timezone;
        } else {
            $data['timezone'] = date_default_timezone_get();
        }

        if (isset($zoom->password)) {
            $data['password'] = $zoom->password;
        }

        if (isset($zoom->schedule_for)) {
            $data['schedule_for'] = $zoom->schedule_for;
        }

        if (isset($zoom->alternative_hosts)) {
            $data['settings']['alternative_hosts'] = $zoom->alternative_hosts;
        }

        if (isset($zoom->option_authenticated_users)) {
            $data['settings']['meeting_authentication'] = (bool) $zoom->option_authenticated_users;
        }

        if (isset($zoom->registration)) {
            $data['settings']['approval_type'] = $zoom->registration;
            if ($zoom->registration != ZOOM_REGISTRATION_OFF) {
                $data['settings']['use_pmi'] = false;
            }
        }

        if (!empty($zoom->webinar)) {
            if ($zoom->recurring) {
                if ($zoom->recurrence_type == ZOOM_RECURRINGTYPE_NOTIME) {
                    $data['type'] = ZOOM_RECURRING_WEBINAR;
                } else {
                    $data['type'] = ZOOM_RECURRING_FIXED_WEBINAR;
                }
            } else {
                $data['type'] = ZOOM_SCHEDULED_WEBINAR;
            }
        } else {
            if ($zoom->recurring) {
                if ($zoom->recurrence_type == ZOOM_RECURRINGTYPE_NOTIME) {
                    $data['type'] = ZOOM_RECURRING_MEETING;
                } else {
                    $data['type'] = ZOOM_RECURRING_FIXED_MEETING;
                }
            } else {
                $data['type'] = ZOOM_SCHEDULED_MEETING;
            }
        }

        if (!empty($zoom->option_auto_recording)) {
            $data['settings']['auto_recording'] = $zoom->option_auto_recording;
        } else {
            $recordingoption = get_config('zoom', 'recordingoption');
            if ($recordingoption === ZOOM_AUTORECORDING_USERDEFAULT) {
                if (isset($zoom->schedule_for)) {
                    $zoomuser = zoom_get_user($zoom->schedule_for);
                    $zoomuserid = $zoomuser->id;
                } else {
                    $zoomuserid = zoom_get_user_id();
                }

                $autorecording = zoom_get_user_settings($zoomuserid)->recording->auto_recording;
                $data['settings']['auto_recording'] = $autorecording;
            } else {
                $data['settings']['auto_recording'] = $recordingoption;
            }
        }

        // Add fields which are effective for meetings only, but not for webinars.
        if (empty($zoom->webinar)) {
            $data['settings']['participant_video'] = (bool) ($zoom->option_participants_video);
            $data['settings']['join_before_host'] = (bool) ($zoom->option_jbh);
            $data['settings']['encryption_type'] = (isset($zoom->option_encryption_type) &&
                    $zoom->option_encryption_type === ZOOM_ENCRYPTION_TYPE_E2EE) ?
                    ZOOM_ENCRYPTION_TYPE_E2EE : ZOOM_ENCRYPTION_TYPE_ENHANCED;
            $data['settings']['waiting_room'] = (bool) ($zoom->option_waiting_room);
            $data['settings']['mute_upon_entry'] = (bool) ($zoom->option_mute_upon_entry);
        }

        // Add recurrence object.
        if ($zoom->recurring && $zoom->recurrence_type != ZOOM_RECURRINGTYPE_NOTIME) {
            $data['recurrence']['type'] = (int) $zoom->recurrence_type;
            $data['recurrence']['repeat_interval'] = (int) $zoom->repeat_interval;
            if ($zoom->recurrence_type == ZOOM_RECURRINGTYPE_WEEKLY) {
                $data['recurrence']['weekly_days'] = $zoom->weekly_days;
            }

            if ($zoom->recurrence_type == ZOOM_RECURRINGTYPE_MONTHLY) {
                if ($zoom->monthly_repeat_option == ZOOM_MONTHLY_REPEAT_OPTION_DAY) {
                    $data['recurrence']['monthly_day'] = (int) $zoom->monthly_day;
                } else {
                    $data['recurrence']['monthly_week'] = (int) $zoom->monthly_week;
                    $data['recurrence']['monthly_week_day'] = (int) $zoom->monthly_week_day;
                }
            }

            if ($zoom->end_date_option == ZOOM_END_DATE_OPTION_AFTER) {
                $data['recurrence']['end_times'] = (int) $zoom->end_times;
            } else {
                $data['recurrence']['end_date_time'] = gmdate('Y-m-d\TH:i:s\Z', $zoom->end_date_time);
            }
        }

        if (
            $data['type'] === ZOOM_SCHEDULED_MEETING ||
            $data['type'] === ZOOM_RECURRING_FIXED_MEETING ||
            $data['type'] === ZOOM_SCHEDULED_WEBINAR ||
            $data['type'] === ZOOM_RECURRING_FIXED_WEBINAR
        ) {
            // Convert timestamp to ISO-8601. The API seems to insist that it end with 'Z' to indicate UTC.
            $data['start_time'] = gmdate('Y-m-d\TH:i:s\Z', $zoom->start_time);
            $data['duration'] = (int) ceil($zoom->duration / 60);
        }

        // Add tracking field to data.
        $defaulttrackingfields = zoom_clean_tracking_fields();
        $tfarray = [];
        foreach ($defaulttrackingfields as $key => $defaulttrackingfield) {
            if (isset($zoom->$key)) {
                $tf = new stdClass();
                $tf->field = $defaulttrackingfield;
                $tf->value = $zoom->$key;
                $tfarray[] = $tf;
            }
        }

        $data['tracking_fields'] = $tfarray;

        if (isset($zoom->breakoutrooms)) {
            $breakoutroom = ['enable' => true, 'rooms' => $zoom->breakoutrooms];
            $data['settings']['breakout_room'] = $breakoutroom;
        }

        return $data;
    }

    /**
     * Provide a user with a license if needed and recycling is enabled.
     *
     * @param stdClass $zoomuserid The Zoom user to upgrade.
     * @return void
     */
    public function provide_license($zoomuserid) {
        // Checks whether we need to recycle licenses and acts accordingly.
        // Classic: user:read:admin.
        // Granular: user:read:user:admin.
        if ($this->recyclelicenses && $this->make_call("users/$zoomuserid")->type == ZOOM_USER_TYPE_BASIC) {
            if ($this->paid_user_limit_reached()) {
                $leastrecentlyactivepaiduserid = $this->get_least_recently_active_paid_user_id();
                // Changes least_recently_active_user to a basic user so we can use their license.
                $this->make_call("users/$leastrecentlyactivepaiduserid", ['type' => ZOOM_USER_TYPE_BASIC], 'patch');
            }

            // Changes current user to pro so they can make a meeting.
            // Classic: user:write:admin.
            // Granular: user:update:user:admin.
            $this->make_call("users/$zoomuserid", ['type' => ZOOM_USER_TYPE_PRO], 'patch');
        }
    }

    /**
     * Create a meeting/webinar on Zoom.
     * Take a $zoom object as returned from the Moodle form and respond with an object that can be saved to the database.
     *
     * @param stdClass $zoom The meeting to create.
     * @param ?int $cmid The cmid if available.
     * @return stdClass The call response.
     */
    public function create_meeting($zoom, $cmid) {
        // Provide license if needed.
        $this->provide_license($zoom->host_id);

        // Classic: meeting:write:admin.
        // Granular: meeting:write:meeting:admin.
        // Classic: webinar:write:admin.
        // Granular: webinar:write:webinar:admin.
        $url = "users/$zoom->host_id/" . (!empty($zoom->webinar) ? 'webinars' : 'meetings');
        return $this->make_call($url, $this->database_to_api($zoom, $cmid), 'post');
    }

    /**
     * Update a meeting/webinar on Zoom.
     *
     * @param stdClass $zoom The meeting to update.
     * @param ?int $cmid The cmid if available.
     * @return void
     */
    public function update_meeting($zoom, $cmid) {
        // Classic: meeting:write:admin.
        // Granular: meeting:update:meeting:admin.
        // Classic: webinar:write:admin.
        // Granular: webinar:update:webinar:admin.
        $url = ($zoom->webinar ? 'webinars/' : 'meetings/') . $zoom->meeting_id;
        $this->make_call($url, $this->database_to_api($zoom, $cmid), 'patch');
    }

    /**
     * Delete a meeting or webinar on Zoom.
     *
     * @param int $id The meeting_id or webinar_id of the meeting or webinar to delete.
     * @param bool $webinar Whether the meeting or webinar you want to delete is a webinar.
     * @return void
     */
    public function delete_meeting($id, $webinar) {
        // Classic: meeting:write:admin.
        // Granular: meeting:delete:meeting:admin.
        // Classic: webinar:write:admin.
        // Granular: webinar:delete:webinar:admin.
        $url = ($webinar ? 'webinars/' : 'meetings/') . $id . '?schedule_for_reminder=false';
        $this->make_call($url, null, 'delete');
    }

    /**
     * Get a meeting or webinar's information from Zoom.
     *
     * @param int $id The meeting_id or webinar_id of the meeting or webinar to retrieve.
     * @param bool $webinar Whether the meeting or webinar whose information you want is a webinar.
     * @return stdClass The meeting's or webinar's information.
     */
    public function get_meeting_webinar_info($id, $webinar) {
        // Classic: meeting:read:admin.
        // Granular: meeting:read:meeting:admin.
        // Classic: webinar:read:admin.
        // Granular: webinar:read:webinar:admin.
        $url = ($webinar ? 'webinars/' : 'meetings/') . $id;
        $response = $this->make_call($url);
        return $response;
    }

    /**
     * Get the meeting invite note that was sent for a specific meeting from Zoom.
     *
     * @param stdClass $zoom The zoom meeting
     * @return \mod_zoom\invitation The meeting's invitation.
     */
    public function get_meeting_invitation($zoom) {
        global $CFG;
        require_once($CFG->dirroot . '/mod/zoom/classes/invitation.php');

        // Webinar does not have meeting invite info.
        if ($zoom->webinar) {
            return new invitation(null);
        }

        // Classic: meeting:read:admin.
        // Granular: meeting:read:invitation:admin.
        $url = 'meetings/' . $zoom->meeting_id . '/invitation';

        try {
            $response = $this->make_call($url);
        } catch (moodle_exception $error) {
            debugging($error->getMessage());
            return new invitation(null);
        }

        return new invitation($response->invitation);
    }

    /**
     * Retrieve ended meetings report for a specified user and period. Handles multiple pages.
     *
     * @param string $userid Id of user of interest
     * @param string $from Start date of period in the form YYYY-MM-DD
     * @param string $to End date of period in the form YYYY-MM-DD
     * @return array The retrieved meetings.
     */
    public function get_user_report($userid, $from, $to) {
        // Classic: report:read:admin.
        // Granular: report:read:user:admin.
        $url = 'report/users/' . $userid . '/meetings';
        $data = ['from' => $from, 'to' => $to];
        return $this->make_paginated_call($url, $data, 'meetings');
    }

    /**
     * List all meeting or webinar information for a user.
     *
     * @param string $userid The user whose meetings or webinars to retrieve.
     * @param boolean $webinar Whether to list meetings or to list webinars.
     * @return array An array of meeting information.
     * @deprecated Has never been used by internal code.
     */
    public function list_meetings($userid, $webinar) {
        // Classic: meeting:read:admin.
        // Granular: meeting:read:list_meetings:admin.
        // Classic: webinar:read:admin.
        // Granular: webinar:read:list_webinars:admin.
        $url = 'users/' . $userid . ($webinar ? '/webinars' : '/meetings');
        $instances = $this->make_paginated_call($url, [], ($webinar ? 'webinars' : 'meetings'));
        return $instances;
    }

    /**
     * Get the participants who attended a meeting
     * @param string $meetinguuid The meeting or webinar's UUID.
     * @param bool $webinar Whether the meeting or webinar whose information you want is a webinar.
     * @return array An array of meeting participant objects.
     */
    public function get_meeting_participants($meetinguuid, $webinar) {
        $meetinguuid = $this->encode_uuid($meetinguuid);

        $meetingtype = ($webinar ? 'webinars' : 'meetings');
        $meetingtypesingular = ($webinar ? 'webinar' : 'meeting');

        $reportscopes = [
            // Classic.
            'report:read:admin',

            // Granular.
            'report:read:list_' . $meetingtypesingular . '_participants:admin',
        ];

        $dashboardscopes = [
            // Classic.
            'dashboard_' . $meetingtype . ':read:admin',

            // Granular.
            'dashboard:read:list_' . $meetingtypesingular . '_participants:admin',
        ];

        if ($this->has_scope($reportscopes)) {
            $apitype = 'report';
        } else if ($this->has_scope($dashboardscopes)) {
            $apitype = 'metrics';
        } else {
            mtrace('Missing OAuth scopes required for reports.');
            return [];
        }

        $url = $apitype . '/' . $meetingtype . '/' . $meetinguuid . '/participants';
        return $this->make_paginated_call($url, [], 'participants');
    }

    /**
     * Retrieve the UUIDs of hosts that were active in the last 30 days.
     *
     * @param int $from The time to start the query from, in Unix timestamp format.
     * @param int $to The time to end the query at, in Unix timestamp format.
     * @return array An array of UUIDs.
     */
    public function get_active_hosts_uuids($from, $to) {
        // Classic: report:read:admin.
        // Granular: report:read:list_users:admin.
        $users = $this->make_paginated_call('report/users', ['type' => 'active', 'from' => $from, 'to' => $to], 'users');
        $uuids = [];
        foreach ($users as $user) {
            $uuids[] = $user->id;
        }

        return $uuids;
    }

    /**
     * Retrieve past meetings that occurred in specified time period.
     *
     * Ignores meetings that were attended only by one user.
     *
     * NOTE: Requires Business or a higher plan and have "Dashboard" feature
     * enabled. This query is rated "Resource-intensive"
     *
     * @param int $from Start date in YYYY-MM-DD format.
     * @param int $to End date in YYYY-MM-DD format.
     * @return array An array of meeting objects.
     */
    public function get_meetings($from, $to) {
        // Classic: dashboard_meetings:read:admin.
        // Granular: dashboard:read:list_meetings:admin.
        return $this->make_paginated_call(
            'metrics/meetings',
            [
                'type' => 'past',
                'from' => $from,
                'to' => $to,
                'query_date_type' => 'end_time',
            ],
            'meetings'
        );
    }

    /**
     * Retrieve past meetings that occurred in specified time period.
     *
     * Ignores meetings that were attended only by one user.
     *
     * NOTE: Requires Business or a higher plan and have "Dashboard" feature
     * enabled. This query is rated "Resource-intensive"
     *
     * @param int $from Start date in YYYY-MM-DD format.
     * @param int $to End date in YYYY-MM-DD format.
     * @return array An array of meeting objects.
     */
    public function get_webinars($from, $to) {
        // Classic: dashboard_webinars:read:admin.
        // Granular: dashboard:read:list_webinars:admin.
        return $this->make_paginated_call('metrics/webinars', ['type' => 'past', 'from' => $from, 'to' => $to], 'webinars');
    }

    /**
     * Lists tracking fields configured on the account.
     *
     * @return ?stdClass The call's result in JSON format.
     */
    public function list_tracking_fields() {
        $response = null;
        try {
            // Classic: tracking_fields:read:admin.
            // Granular: Not yet implemented by Zoom.
            $response = $this->make_call('tracking_fields');
        } catch (moodle_exception $error) {
            debugging($error->getMessage());
        }

        return $response;
    }

    /**
     * If the UUID begins with a ‘/’ or contains ‘//’ in it we need to double encode it when using it for API calls.
     *
     * See https://devforum.zoom.us/t/cant-retrieve-data-when-meeting-uuid-contains-double-slash/2776
     *
     * @param string $uuid
     * @return string
     */
    public function encode_uuid($uuid) {
        if (substr($uuid, 0, 1) === '/' || strpos($uuid, '//') !== false) {
            // Use similar function to JS encodeURIComponent, see https://stackoverflow.com/a/1734255/6001.
            $encodeuricomponent = function ($str) {
                $revert = ['%21' => '!', '%2A' => '*', '%27' => "'", '%28' => '(', '%29' => ')'];
                return strtr(rawurlencode($str), $revert);
            };
            $uuid = $encodeuricomponent($encodeuricomponent($uuid));
        }

        return $uuid;
    }

    /**
     * Returns the download URLs and recording types for the cloud recording if one exists on zoom for a particular meeting id.
     * There can be more than one url for the same meeting if the host stops the recording in the middle
     * of the meeting and then starts recording again without ending the meeting.
     *
     * @param string $meetingid The string meeting UUID.
     * @return array Returns the list of recording URLs and the type of recording that is being sent back.
     * @throws moodle_exception
     */
    public function get_recording_url_list($meetingid) {
        $recordings = [];

        // Only pick the video recording and audio only recordings.
        // The transcript is available in both of these, so the extra file is unnecessary.
        $allowedrecordingtypes = [
            'MP4' => 'video',
            'M4A' => 'audio',
            'TRANSCRIPT' => 'transcript',
            'CHAT' => 'chat',
            'CC' => 'captions',
        ];

        // Classic: recording:read:admin.
        // Granular: cloud_recording:read:list_recording_files:admin.
        $url = 'meetings/' . $this->encode_uuid($meetingid) . '/recordings';
        $response = $this->make_call($url);

        if (!empty($response->recording_files)) {
            foreach ($response->recording_files as $recording) {
                $url = $recording->play_url ?? $recording->download_url ?? null;
                if (!empty($url) && isset($allowedrecordingtypes[$recording->file_type])) {
                    $recordinginfo = new stdClass();
                    $recordinginfo->recordingid = $recording->id;
                    $recordinginfo->meetinguuid = $response->uuid;
                    $recordinginfo->url = $url;
                    $recordinginfo->filetype = $recording->file_type;
                    $recordinginfo->recordingtype = $recording->recording_type ?? 'null';
                    $recordinginfo->passcode = $response->password;
                    $recordinginfo->recordingstart = strtotime($recording->recording_start);

                    $recordings[$recording->id] = $recordinginfo;
                }
            }
        }

        return $recordings;
    }

    /**
     * Retrieve recordings for a specified user and period. Handles multiple pages.
     *
     * @param string $userid User ID.
     * @param string $from Start date of period in the form YYYY-MM-DD
     * @param string $to End date of period in the form YYYY-MM-DD
     * @return array
     */
    public function get_user_recordings($userid, $from, $to) {
        $recordings = [];

        // Only pick the video recording and audio only recordings.
        // The transcript is available in both of these, so the extra file is unnecessary.
        $allowedrecordingtypes = [
            'MP4' => 'video',
            'M4A' => 'audio',
            'TRANSCRIPT' => 'transcript',
            'CHAT' => 'chat',
            'CC' => 'captions',
        ];

        try {
            // Classic: recording:read:admin.
            // Granular: cloud_recording:read:list_user_recordings:admin.
            $url = 'users/' . $userid . '/recordings';
            $data = ['from' => $from, 'to' => $to];
            $response = $this->make_paginated_call($url, $data, 'meetings');

            foreach ($response as $meeting) {
                foreach ($meeting->recording_files as $recording) {
                    $url = $recording->play_url ?? $recording->download_url ?? null;
                    if (!empty($url) && isset($allowedrecordingtypes[$recording->file_type])) {
                        $recordinginfo = new stdClass();
                        $recordinginfo->recordingid = $recording->id;
                        $recordinginfo->meetingid = $meeting->id;
                        $recordinginfo->meetinguuid = $meeting->uuid;
                        $recordinginfo->url = $url;
                        $recordinginfo->filetype = $recording->file_type;
                        $recordinginfo->recordingtype = $recording->recording_type ?? 'null';
                        $recordinginfo->recordingstart = strtotime($recording->recording_start);

                        $recordings[$recording->id] = $recordinginfo;
                    }
                }
            }
        } catch (moodle_exception $error) {
            // No recordings found for this user.
            $recordings = [];
        }

        return $recordings;
    }

    /**
     * Returns a server to server oauth access token, good for 1 hour.
     *
     * @throws moodle_exception
     * @return string access token
     */
    protected function get_access_token() {
        $cache = cache::make('mod_zoom', 'oauth');
        $token = $cache->get('accesstoken');
        $expires = $cache->get('expires');
        if (empty($token) || empty($expires) || time() >= $expires) {
            $token = $this->oauth($cache);
        } else {
            $this->scopes = $cache->get('scopes');
        }

        return $token;
    }

    /**
     * Has one of the required OAuth scopes been granted?
     *
     * @param array $scopes OAuth scopes.
     * @throws moodle_exception
     * @return bool
     */
    public function has_scope($scopes) {
        if (!isset($this->scopes)) {
            $this->get_access_token();
        }

        mtrace('checking has_scope(' . implode(' || ', $scopes) . ')');

        $matchingscopes = \array_intersect($scopes, $this->scopes);
        return !empty($matchingscopes);
    }

    /**
     * Stores token and expiration in cache, returns token from OAuth call.
     *
     * @param cache $cache
     * @throws moodle_exception
     * @return string access token
     */
    private function oauth($cache) {
        $curl = $this->get_curl_object();
        $curl->setHeader('Authorization: Basic ' . base64_encode($this->clientid . ':' . $this->clientsecret));
        $curl->setHeader('Accept: application/json');

        // Force HTTP/1.1 to avoid HTTP/2 "stream not closed" issue.
        $curl->setopt([
            'CURLOPT_HTTP_VERSION' => \CURL_HTTP_VERSION_1_1,
        ]);

        $timecalled = time();
        $data = [
            'grant_type' => 'account_credentials',
            'account_id' => $this->accountid,
        ];
        $response = $this->make_curl_call($curl, 'post', 'https://zoom.us/oauth/token', $data);

        if ($curl->get_errno()) {
            throw new moodle_exception('errorwebservice', 'mod_zoom', '', $curl->error);
        }

        $response = json_decode($response);

        if (empty($response->access_token)) {
            throw new moodle_exception('errorwebservice', 'mod_zoom', '', get_string('zoomerr_no_access_token', 'mod_zoom'));
        }

        $scopes = explode(' ', $response->scope);

        // Assume that we are using granular scopes.
        $requiredscopes = [
            'meeting:read:meeting:admin',
            'meeting:read:invitation:admin',
            'meeting:delete:meeting:admin',
            'meeting:update:meeting:admin',
            'meeting:write:meeting:admin',
            'user:read:list_schedulers:admin',
            'user:read:settings:admin',
            'user:read:user:admin',
        ];

        // Check if we received classic scopes.
        if (in_array('meeting:read:admin', $scopes, true)) {
            $requiredscopes = [
                'meeting:read:admin',
                'meeting:write:admin',
                'user:read:admin',
            ];
        }

        $missingscopes = array_diff($requiredscopes, $scopes);

        // Keep the scope information in memory.
        $this->scopes = $scopes;

        if (!empty($missingscopes)) {
            $missingscopes = implode(', ', $missingscopes);
            throw new moodle_exception('errorwebservice', 'mod_zoom', '', get_string('zoomerr_scopes', 'mod_zoom', $missingscopes));
        }

        $token = $response->access_token;

        if (isset($response->expires_in)) {
            $expires = $response->expires_in + $timecalled;
        } else {
            $expires = 3599 + $timecalled;
        }

        $cache->set_many([
            'accesstoken' => $token,
            'expires' => $expires,
            'scopes' => $scopes,
        ]);

        return $token;
    }

    /**
     * List the meeting or webinar registrants from Zoom.
     *
     * @param string $id The meeting_id or webinar_id of the meeting or webinar to retrieve.
     * @param bool $webinar Whether the meeting or webinar whose information you want is a webinar.
     * @return stdClass The meeting's or webinar's information.
     */
    public function get_meeting_registrants($id, $webinar) {
        // Classic: meeting:read:admin.
        // Granular: meeting:read:list_registrants:admin.
        // Classic: webinar:read:admin.
        // Granular: webinar:read:list_registrants:admin.
        $url = ($webinar ? 'webinars/' : 'meetings/') . $id . '/registrants';
        $response = $this->make_call($url);
        return $response;
    }

    /**
     * Get the recording settings for a meeting.
     *
     * @param string $meetinguuid The UUID of a meeting with recordings.
     * @return stdClass The meeting's recording settings.
     */
    public function get_recording_settings($meetinguuid) {
        // Classic: recording:read:admin.
        // Granular: cloud_recording:read:recording_settings:admin.
        $url = 'meetings/' . $this->encode_uuid($meetinguuid) . '/recordings/settings';
        $response = $this->make_call($url);
        return $response;
    }

    /**
     * Returns whether or not the current user is permitted to create a meeting/webinar that requires registration.
     * @return boolean
     */
    public function is_user_permitted_to_require_registration() {
        global $USER;
        $zoomuser = zoom_get_user(zoom_get_api_identifier($USER));
        if ($zoomuser && $zoomuser->type == ZOOM_USER_TYPE_PRO) {
            return true;
        }
        return false;
    }
}
