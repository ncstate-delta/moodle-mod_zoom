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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/zoom/locallib.php');
require_once($CFG->dirroot.'/lib/filelib.php');

// Some plugins already might include this library, like mod_bigbluebuttonbn.
// Hacky, but need to create list of plugins that might have JWT library.
// NOTE: Remove file_exists checks and the JWT library in mod when versions prior to Moodle 3.7 is no longer supported.
if (!class_exists('Firebase\JWT\JWT')) {
    if (file_exists($CFG->dirroot.'/lib/php-jwt/src/JWT.php')) {
        require_once($CFG->dirroot.'/lib/php-jwt/src/JWT.php');
    } else {
        if (file_exists($CFG->dirroot.'/mod/bigbluebuttonbn/vendor/firebase/php-jwt/src/JWT.php')) {
            require_once($CFG->dirroot.'/mod/bigbluebuttonbn/vendor/firebase/php-jwt/src/JWT.php');
        } else {
            require_once($CFG->dirroot.'/mod/zoom/jwt/JWT.php');
        }
    }
}

/**
 * Web service class.
 */
class mod_zoom_webservice {

    /**
     * API calls: maximum number of retries.
     * @var int
     */
    const MAX_RETRIES = 5;

    /**
     * Default meeting_password_requirement object.
     * @var array
     */
    const DEFAULT_MEETING_PASSWORD_REQUIREMENT = array(
        'length' => 0,
        'consecutive_characters_length' => 0,
        'have_letter' => false,
        'have_number' => false,
        'have_upper_and_lower_characters' => false,
        'have_special_character' => false,
        'only_allow_numeric' => false,
        'weak_enhance_detection' => false
    );

    /**
     * API key
     * @var string
     */
    protected $apikey;

    /**
     * API secret
     * @var string
     */
    protected $apisecret;

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
     * The constructor for the webservice class.
     * @throws moodle_exception Moodle exception is thrown for missing config settings.
     */
    public function __construct() {
        $config = get_config('zoom');

        // Get and remember the API key.
        // TODO: Remove when JWT is no longer supported in June 2023.
        if (!empty($config->apikey)) {
            $this->apikey = $config->apikey;
        } else {
            throw new moodle_exception('errorwebservice', 'mod_zoom', '', get_string('zoomerr_apikey_missing', 'zoom'));
        }

        // Get and remember the API secret.
        // TODO: Remove when JWT is no longer supported in June 2023.
        if (!empty($config->apisecret)) {
            $this->apisecret = $config->apisecret;
        } else {
            throw new moodle_exception('errorwebservice', 'mod_zoom', '', get_string('zoomerr_apisecret_missing', 'zoom'));
        }

        // Get and remember the client ID.
        if (!empty($config->clientid)) {
            $this->clientid = $config->clientid;
        } else {
            // TODO: When JWT is no longer supported in June 2023, Throw exception if not configured.
            $this->clientid = '';
        }

        // Get and remember the client secret.
        if (!empty($config->clientsecret)) {
            $this->clientsecret = $config->clientsecret;
        } else {
            // TODO: When JWT is no longer supported in June 2023, Throw exception if not configured.
            $this->clientsecret = '';
        }

        // Get and remember the account ID.
        if (!empty($config->accountid)) {
            $this->accountid = $config->accountid;
        } else {
            // TODO: When JWT is no longer supported in June 2023, Throw exception if not configured.
            $this->accountid = '';
        }

        // Get and remember the API URL.
        $this->apiurl = zoom_get_api_url();

        // Get and remember the plugin settings to recycle licenses.
        if (!empty($config->utmost)) {
            $this->recyclelicenses = $config->utmost;
        }
        if ($this->recyclelicenses) {
            if (!empty($config->licensesnumber)) {
                $this->numlicenses = $config->licensesnumber;
            } else {
                throw new moodle_exception('errorwebservice', 'mod_zoom', '', get_string('zoomerr_licensesnumber_missing', 'zoom'));
            }
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
        return new curl();
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
    private function make_call($path, $data = array(), $method = 'get') {
        global $CFG;
        $url = $this->apiurl . $path;
        $method = strtolower($method);
        $proxyhost = get_config('zoom', 'proxyhost');
        $cfg = new stdClass();
        if (!empty($proxyhost)) {
            $cfg->proxyhost = $CFG->proxyhost;
            $cfg->proxyport = $CFG->proxyport;
            $cfg->proxyuser = $CFG->proxyuser;
            $cfg->proxypassword = $CFG->proxypassword;
            $cfg->proxytype = $CFG->proxytype;
            // Parse string as host:port, delimited by a colon (:).
            list($host, $port) = explode(':', $proxyhost);
            // Temporarily set new values on the global $CFG.
            $CFG->proxyhost = $host;
            $CFG->proxyport = $port;
            $CFG->proxytype = 'HTTP';
            $CFG->proxyuser = '';
            $CFG->proxypassword = '';
        }
        $curl = $this->get_curl_object(); // Create $curl, which implicitly uses the proxy settings from $CFG.
        if (!empty($proxyhost)) {
            // Restore the stored global proxy settings from above.
            $CFG->proxyhost = $cfg->proxyhost;
            $CFG->proxyport = $cfg->proxyport;
            $CFG->proxyuser = $cfg->proxyuser;
            $CFG->proxypassword = $cfg->proxypassword;
            $CFG->proxytype = $cfg->proxytype;
        }

        // TODO: Remove JWT auth when deprecated in June 2023.
        if ($this->clientid != '' && $this->clientsecret != '' && $this->accountid != '') {
            try {
                $token = $this->get_access_token();
            } catch (moodle_exception $error) {
                throw new moodle_exception('errorwebservice', 'mod_zoom', '', $error->getMessage());
            }
        } else {
            $payload = array(
                'iss' => $this->apikey,
                'exp' => time() + 40
                );
            $token = \Firebase\JWT\JWT::encode($payload, $this->apisecret, 'HS256');
        }

        $curl->setHeader('Authorization: Bearer ' . $token);

        if ($method != 'get') {
            $curl->setHeader('Content-Type: application/json');
            $data = is_array($data) ? json_encode($data) : $data;
        }
        $response = $this->make_curl_call($curl, $method, $url, $data);

        if ($curl->get_errno()) {
            throw new moodle_exception('errorwebservice', 'mod_zoom', '', $curl->error);
        }

        $response = json_decode($response);

        $httpstatus = $curl->get_info()['http_code'];

        if ($httpstatus >= 400) {
            switch($httpstatus) {
                case 400:
                    throw new zoom_bad_request_exception($response->message, $response->code);
                case 404:
                    throw new zoom_not_found_exception($response->message, $response->code);
                case 429:
                    $this->makecallretries += 1;
                    if ($this->makecallretries > self::MAX_RETRIES) {
                        throw new zoom_api_retry_failed_exception($response->message, $response->code);
                    }
                    $header = $curl->getResponse();
                    // Header can have mixed case, normalize it.
                    $header = array_change_key_case($header, CASE_LOWER);

                    // Default to 1 second for max requests per second limit.
                    $timediff = 1;

                    // Check if we hit the max requests per minute (only for Dashboard API).
                    if (array_key_exists('x-ratelimit-type', $header) &&
                            $header['x-ratelimit-type'] == 'QPS' &&
                            strpos($path, 'metrics') !== false) {
                        $timediff = 60; // Try the next minute.
                    } else if (array_key_exists('retry-after', $header)) {
                        $retryafter = strtotime($header['retry-after']);
                        $timediff = $retryafter - time();
                        // If we have no API calls remaining, save retry-after.
                        if ($header['x-ratelimit-remaining'] == 0 && !empty($retryafter)) {
                            set_config('retry-after', $retryafter, 'zoom');
                            throw new zoom_api_limit_exception($response->message,
                                    $response->code, $retryafter);
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
                        $exception = new moodle_exception('errorwebservice', 'mod_zoom', '', $response->message);
                        $exception->response = $response->message;
                        $exception->zoomerrorcode = $response->code;
                        throw $exception;
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
     * @param array|string $data The data to attach to the call.
     * @param string $datatoget The name of the array of the data to get.
     * @return array The retrieved data.
     * @see make_call()
     * @link https://zoom.github.io/api/#list-users
     */
    private function make_paginated_call($url, $data, $datatoget) {
        $aggregatedata = array();
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
     * @link https://zoom.github.io/api/#create-a-user
     */
    public function autocreate_user($user) {
        $url = 'users';
        $data = array('action' => 'autocreate');
        $data['user_info'] = array(
            'email' => zoom_get_api_identifier($user),
            'type' => ZOOM_USER_TYPE_PRO,
            'first_name' => $user->firstname,
            'last_name' => $user->lastname,
            'password' => base64_encode(random_bytes(16))
        );

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
     * @link https://zoom.github.io/api/#list-users
     */
    public function list_users() {
        if (empty(self::$userslist)) {
            self::$userslist = $this->make_paginated_call('users', null, 'users');
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
            if ($user->type != ZOOM_USER_TYPE_BASIC && ++$numusers >= $this->numlicenses) {
                return true;
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
        $usertimes = array();
        $userslist = $this->list_users();
        foreach ($userslist as $user) {
            if ($user->type != ZOOM_USER_TYPE_BASIC && isset($user->last_login_time)) {
                $usertimes[$user->id] = strtotime($user->last_login_time);
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
     * @link https://zoom.github.io/api/#retrieve-a-users-settings
     */
    public function get_user_settings($userid) {
        return $this->make_call('users/' . $userid . '/settings');
    }

    /**
     * Gets the user's master account meeting security settings, including password requirements.
     *
     * @return stdClass The call's result in JSON format.
     * @link https://marketplace.zoom.us/docs/api-reference/zoom-api/accounts/accountsettings.
     */
    public function get_account_meeting_security_settings() {
        $url = 'accounts/me/settings?option=meeting_security';
        try {
            $response = $this->make_call($url);
            $meetingsecurity = $response->meeting_security;
        } catch (moodle_exception $error) {
            // Only available for Paid account, return default settings.
            $meetingsecurity = new stdClass();
            // If some other error, show debug message.
            if ($error->zoomerrorcode != 200) {
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
     * @link https://zoom.github.io/api/#users
     */
    public function get_user($identifier) {
        $founduser = false;

        $url = 'users/' . $identifier;

        try {
            $founduser = $this->make_call($url);
        } catch (moodle_exception $error) {
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
     * @link https://marketplace.zoom.us/docs/api-reference/zoom-api/users/userschedulers
     */
    public function get_schedule_for_users($identifier) {
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
     * @return array The formatted meetings for the meeting.
     */
    private function database_to_api($zoom) {
        global $CFG;

        $data = array(
            'topic' => $zoom->name,
            'settings' => array(
                'host_video' => (bool) ($zoom->option_host_video),
                'audio' => $zoom->option_audio
            )
        );
        if (isset($zoom->intro)) {
            $data['agenda'] = content_to_text($zoom->intro, FORMAT_MOODLE);
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
                } else {
                    $zoomapiidentifier = zoom_get_api_identifier($USER);
                    $zoomuser = zoom_get_user($zoomapiidentifier);
                }
                $autorecording = zoom_get_user_settings($zoomuser->id)->recording->auto_recording;
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

        if ($data['type'] === ZOOM_SCHEDULED_MEETING ||
            $data['type'] === ZOOM_RECURRING_FIXED_MEETING ||
            $data['type'] === ZOOM_SCHEDULED_WEBINAR ||
            $data['type'] === ZOOM_RECURRING_FIXED_WEBINAR) {
            // Convert timestamp to ISO-8601. The API seems to insist that it end with 'Z' to indicate UTC.
            $data['start_time'] = gmdate('Y-m-d\TH:i:s\Z', $zoom->start_time);
            $data['duration'] = (int) ceil($zoom->duration / 60);
        }

        // Add tracking field to data.
        $defaulttrackingfields = zoom_clean_tracking_fields();
        $tfarray = array();
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
            $breakoutroom = array('enable' => true, 'rooms' => $zoom->breakoutrooms);
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
        if ($this->recyclelicenses && $this->make_call("users/$zoomuserid")->type == ZOOM_USER_TYPE_BASIC) {
            if ($this->paid_user_limit_reached()) {
                $leastrecentlyactivepaiduserid = $this->get_least_recently_active_paid_user_id();
                // Changes least_recently_active_user to a basic user so we can use their license.
                $this->make_call("users/$leastrecentlyactivepaiduserid", array('type' => ZOOM_USER_TYPE_BASIC), 'patch');
            }
            // Changes current user to pro so they can make a meeting.
            $this->make_call("users/$zoomuserid", array('type' => ZOOM_USER_TYPE_PRO), 'patch');
        }
    }

    /**
     * Create a meeting/webinar on Zoom.
     * Take a $zoom object as returned from the Moodle form and respond with an object that can be saved to the database.
     *
     * @param stdClass $zoom The meeting to create.
     * @return stdClass The call response.
     */
    public function create_meeting($zoom) {
        // Provide license if needed.
        $this->provide_license($zoom->host_id);
        $url = "users/$zoom->host_id/" . (!empty($zoom->webinar) ? 'webinars' : 'meetings');
        return $this->make_call($url, $this->database_to_api($zoom), 'post');
    }

    /**
     * Update a meeting/webinar on Zoom.
     *
     * @param stdClass $zoom The meeting to update.
     * @return void
     */
    public function update_meeting($zoom) {
        $url = ($zoom->webinar ? 'webinars/' : 'meetings/') . $zoom->meeting_id;
        $this->make_call($url, $this->database_to_api($zoom), 'patch');
    }

    /**
     * Delete a meeting or webinar on Zoom.
     *
     * @param int $id The meeting_id or webinar_id of the meeting or webinar to delete.
     * @param bool $webinar Whether the meeting or webinar you want to delete is a webinar.
     * @return void
     */
    public function delete_meeting($id, $webinar) {
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
        $url = ($webinar ? 'webinars/' : 'meetings/') . $id;
        $response = null;
        try {
            $response = $this->make_call($url);
        } catch (moodle_exception $error) {
            throw $error;
        }
        return $response;
    }

    /**
     * Get the meeting invite note that was sent for a specific meeting from Zoom.
     *
     * @param stdClass $zoom The zoom meeting
     * @return \mod_zoom\invitation The meeting's invitation.
     * @link https://marketplace.zoom.us/docs/api-reference/zoom-api/meetings/meetinginvitation
     */
    public function get_meeting_invitation($zoom) {
        global $CFG;
        require_once($CFG->dirroot . '/mod/zoom/classes/invitation.php');

        // Webinar does not have meeting invite info.
        if ($zoom->webinar) {
            return new \mod_zoom\invitation(null);
        }
        $url = 'meetings/' . $zoom->meeting_id . '/invitation';
        try {
            $response = $this->make_call($url);
        } catch (moodle_exception $error) {
            debugging($error->getMessage());
            return new \mod_zoom\invitation(null);
        }
        return new \mod_zoom\invitation($response->invitation);
    }

    /**
     * Retrieve ended meetings report for a specified user and period. Handles multiple pages.
     *
     * @param int $userid Id of user of interest
     * @param string $from Start date of period in the form YYYY-MM-DD
     * @param string $to End date of period in the form YYYY-MM-DD
     * @return array The retrieved meetings.
     * @link https://zoom.github.io/api/#retrieve-meetings-report
     */
    public function get_user_report($userid, $from, $to) {
        $url = 'report/users/' . $userid . '/meetings';
        $data = array('from' => $from, 'to' => $to, 'page_size' => ZOOM_MAX_RECORDS_PER_CALL);
        return $this->make_paginated_call($url, $data, 'meetings');
    }

    /**
     * List all meeting or webinar information for a user.
     *
     * @param string $userid The user whose meetings or webinars to retrieve.
     * @param boolean $webinar Whether to list meetings or to list webinars.
     * @return array An array of meeting information.
     * @link https://zoom.github.io/api/#list-webinars
     * @link https://zoom.github.io/api/#list-meetings
     */
    public function list_meetings($userid, $webinar) {
        $url = 'users/' . $userid . ($webinar ? '/webinars' : '/meetings');
        $instances = $this->make_paginated_call($url, null, ($webinar ? 'webinars' : 'meetings'));
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
        return $this->make_paginated_call('report/' . ($webinar ? 'webinars' : 'meetings') . '/'
                                           . $meetinguuid . '/participants', null, 'participants');
    }

    /**
     * Retrieve the UUIDs of hosts that were active in the last 30 days.
     *
     * @param int $from The time to start the query from, in Unix timestamp format.
     * @param int $to The time to end the query at, in Unix timestamp format.
     * @return array An array of UUIDs.
     */
    public function get_active_hosts_uuids($from, $to) {
        $users = $this->make_paginated_call('report/users', array('type' => 'active', 'from' => $from, 'to' => $to), 'users');
        $uuids = array();
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
     * See https://marketplace.zoom.us/docs/api-reference/zoom-api/dashboards/dashboardmeetings
     *
     * NOTE: Requires Business or a higher plan and have "Dashboard" feature
     * enabled. This query is rated "Resource-intensive"
     *
     * @param int $from Start date in YYYY-MM-DD format.
     * @param int $to End date in YYYY-MM-DD format.
     * @return array An array of meeting objects.
     */
    public function get_meetings($from, $to) {
        return $this->make_paginated_call('metrics/meetings',
                ['type' => 'past', 'from' => $from, 'to' => $to], 'meetings');
    }

    /**
     * Retrieve past meetings that occurred in specified time period.
     *
     * Ignores meetings that were attended only by one user.
     *
     * See https://marketplace.zoom.us/docs/api-reference/zoom-api/dashboards/dashboardmeetings
     *
     * NOTE: Requires Business or a higher plan and have "Dashboard" feature
     * enabled. This query is rated "Resource-intensive"
     *
     * @param int $from Start date in YYYY-MM-DD format.
     * @param int $to End date in YYYY-MM-DD format.
     * @return array An array of meeting objects.
     */
    public function get_webinars($from, $to) {
        return $this->make_paginated_call('metrics/webinars',
                ['type' => 'past', 'from' => $from, 'to' => $to], 'webinars');
    }

    /**
     * Lists tracking fields configured on the account.
     *
     * @return ?stdClass The call's result in JSON format.
     * @link https://marketplace.zoom.us/docs/api-reference/zoom-api/trackingfield/trackingfieldlist
     */
    public function list_tracking_fields() {
        $response = null;
        try {
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
            $encodeuricomponent = function($str) {
                $revert = array('%21' => '!', '%2A' => '*', '%27' => "'", '%28' => '(', '%29' => ')');
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
     * @link https://marketplace.zoom.us/docs/api-reference/zoom-api/cloud-recording/recordingget
     * @param string $meetingid The string meeting ID.
     * @return array Returns the list of recording URLs and the type of recording that is being sent back.
     */
    public function get_recording_url_list($meetingid) {
        $meetingid = $this->encode_uuid($meetingid);
        $url = 'meetings/' . $meetingid . '/recordings';
        $settingsurl = 'meetings/' . $meetingid . '/recordings/settings';
        $allowedrecordingtypes = ['MP4', 'M4A'];
        $recordings = [];
        try {
            $response = $this->make_call($url);
            if (!empty($response->recording_files)) {
                $settingsresponse = $this->make_call($settingsurl);
                foreach ($response->recording_files as $rec) {
                    if (!empty($rec->play_url) && in_array($rec->file_type, $allowedrecordingtypes, true)) {
                        // Only pick the video recording and audio only recordings.
                        // The transcript is available in both of these, so the extra file is unnecessary.
                        $recordinginfo = new stdClass();
                        $recordinginfo->recordingid = $rec->id;
                        $recordinginfo->meetinguuid = $rec->meeting_id;
                        $recordinginfo->url = $rec->play_url;
                        $recordinginfo->filetype = $rec->file_type;
                        $recordinginfo->recordingtype = (!empty($rec->recording_type) && $rec->recording_type === 'audio_only') ?
                            get_string('recordingtypeaudio', 'mod_zoom') :
                            get_string('recordingtypevideo', 'mod_zoom');
                        $recordinginfo->passcode = $settingsresponse->password;
                        $recordings[strtotime($rec->recording_start)][] = $recordinginfo;
                    }
                }
                ksort($recordings);
            }
        } catch (moodle_exception $error) {
            // No recordings found for this meeting id.
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
    private function get_access_token() {
        $config = get_config('zoom');

        if (!empty($config->encryptedtoken) && !empty($config->encryptedtokenexpires) && time() < $config->encryptedtokenexpires) {
            return rc4decrypt($config->encryptedtoken);
        } else {
            $curl = $this->get_curl_object();
            $curl->setHeader('Authorization: Basic ' . base64_encode($this->clientid . ':' . $this->clientsecret));
            $curl->setHeader('Content-Type: application/json');

            $timecalled = time();
            $response = $this->make_curl_call($curl, 'post', 'https://zoom.us/oauth/token?grant_type=account_credentials&account_id=' . $this->accountid, array());

            if ($curl->get_errno()) {
                throw new moodle_exception('errorwebservice', 'mod_zoom', '', $curl->error);
            }

            $response = json_decode($response);
            if (property_exists($response, 'access_token')) {
                $token = $response->access_token;
                $encryptedtoken = rc4encrypt($token);
                set_config('encryptedtoken', $encryptedtoken, 'zoom');

                if (property_exists($response, 'expires_in')) {
                    $expirein = $response->expires_in + $timecalled;
                } else {
                    $expirein = 3599 + $timecalled;
                }
                set_config('encryptedtokenexpires', $expirein, 'zoom');

                return $token;
            } else {
                throw new moodle_exception('errorwebservice', 'mod_zoom', '', get_string('zoomerr_no_access_token', 'zoom'));
            }
        }
    }
}
