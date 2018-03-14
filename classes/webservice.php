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
require_once($CFG->dirroot.'/mod/zoom/jwt/JWT.php');

/**
 * Web service class.
 *
 * @package    mod_zoom
 * @copyright  2015 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_zoom_webservice {

    /**
     * Last error.
     * @var string
     */
    public $lasterror = '';

    /**
     * Last response.
     * @var string
     */
    public $lastresponse = '';

    /**
     * Plugin config object
     * @var object
     */
    protected $_config = null;

    /**
     * API url
     * @var string
     */
    protected $_apiurl = '';

    /**
     * API key
     * @var string
     */
    protected $_apikey = '';

    /**
     * API secret
     * @var string
     */
    protected $_apisecret = '';

    /**
     * Current API version
     * @var string|int
     */
    protected $_version = '1';

    /**
     * API authorization token
     * @var string
     */
    protected $_token = '';

    /**
     * Response code
     * @var int
     */
    protected $_code = 0;
    
    /**
     * Error message
     * @var string
     */
    protected $_error_message = '';
    
    public $current_user = false;
    
    /**
     * Min limit of paid users
     * @var int
     */
    protected $_limitfrom = 5;

    public function __construct() {
        $this->_config = get_config('mod_zoom');
        $this->_apiurl = (isset($this->_config->apiurl) &&
                !empty($this->_config->apiurl)) ? $this->_config->apiurl : '';
        $this->_apikey = (isset($this->_config->apikey) &&
                !empty($this->_config->apikey)) ? $this->_config->apikey : '';
        $this->_apisecret = (isset($this->_config->apisecret) &&
                !empty($this->_config->apisecret)) ? $this->_config->apisecret : '';
        $this->_version = $this->_get_version($this->_apiurl);
    }

    /**
     * Get current API version by API url
     *
     * @param string|int $apiurl current API url
     * @return string|int|boolean
     */
    protected function _get_version( $apiurl ) {
        if ($apiurl) {
            if ($arr = explode('/', trim($apiurl, '/'))) {
                return str_ireplace('v', '', $arr[count($arr)-1]);
            }
            return 1;
        }
        return false;
    }

    /**
     * Check API version by given $check
     *
     * @param int $check
     * @param string $operator
     * @return boolean
     */
    protected function _is_api_version($check, $operator = '=') {
        return version_compare($this->_version, $check, $operator);
    }

    /**
     * Makes given REST call and returns result.
     *
     * See https://support.zoom.us/hc/en-us/articles/201811633-Sample-REST-API-via-PHP
     *
     * @param string $url   Will be appended to apiurl.
     * @param array|string $data
     * @param $method HTTP method API call
     * @return array
     */
    public function make_call($url, $data = array(), $method = 'get') {
        $url = $this->_apiurl . $url;
        $curl = new curl();
        switch ($this->_version) {
            case $this->_is_api_version(1):
                $curl->setHeader('Content-Type: application/x-www-form-urlencoded; charset=utf-8');
                $data['api_key'] = $this->_apikey;
                $data['api_secret'] = $this->_apisecret;
                $data['data_type'] = 'JSON';
                $postfields = http_build_query($data, '', '&');
                $response = $curl->post($url, $postfields, array('CURLOPT_FAILONERROR' => true));
                break;
            //version 2
            case $this->_is_api_version(2):
                $token = array(
                    'iss' => $this->_apikey,
                    'exp' => time() + 40
                );
                if ($this->_token = \Firebase\JWT\JWT::encode($token, $this->_apisecret)) {
                    if (!empty($method)) {
                        if (strtolower($method) == 'get') {
                            $data['access_token'] = $this->_token;
                        } else {
                            $curl->setHeader('Authorization: Bearer '.$this->_token);
                            $curl->setHeader('Content-Type: application/json');
                            $data = is_array($data) ? json_encode($data) : $data;
                        }
                        $response = call_user_func_array(array($curl,
                            strtolower($method)), array($url, $data));
                    }
                }
                break;
        }

        if ($curl->get_errno()) {
            // Curl error.
            $this->lasterror = $curl->error;
            throw new moodle_exception('errorwebservice', 'mod_zoom', '', $curl->error);
        }

        $response = json_decode($response);
        if ($this->_is_api_version(1) && isset($response->error)) {
            // Web service error.
            $this->lasterror = $response->error->message;
            throw new moodle_exception('errorwebservice', 'mod_zoom', '', $response->error->message);
        }
        if ($this->_is_api_version(2) && isset($response->code) && $response->code != 200) {
            $this->_code = intval($response->code);
            if (isset($response->message) && !empty($response->message)) {
                $this->_error_message = $response->message;
            }
        }

        $this->lastresponse = $response;
        return $response;
    }

    // User API calls
    // -----------------------------------------------------------------------
    // See https://support.zoom.us/hc/en-us/articles/201363033-REST-User-API .

    /**
     * Autocreate a user on Zoom.
     *
     * @param object $user
     * @return bool
     */
    public function user_autocreate($user) {
        $data = array();
        switch ($this->_version) {
            case $this->_is_api_version(1):
                $url = 'user/autocreate';
                $data['email'] = $user->email;
                $data['type'] = 2;
                $data['password'] = str_shuffle(uniqid());
                $data['first_name'] = $user->firstname;
                $data['last_name'] = $user->lastname;
                break;
            case $this->_is_api_version(2):
                $url = 'users';
                $data['action'] = 'autocreate';
                $data['user_info'] = array(
                    'email' => $user->email,
                    'type' => 2,
                    'first_name' => $user->firstname,
                    'last_name' => $user->lastname,
                    'password' => str_shuffle(uniqid())
                );
                break;
        }

        try {
            $this->make_call($url, $data, 'post');
        } catch (moodle_exception $e) {
            // If user already exists, it will return "User already in the account".
            if (strpos($e->getMessage(), 'User already in the account') === false) {
                // Error is not something expected.
                return false;
            }
        }

        return true;
    }

    /**
     * Get users list
     *
     * @return boolean|object
     */
    public function list_users() {
        if($this->_is_api_version(2)) {
            return $this->make_call('users');
        }

        return false;
    }

    /**
     * Get active users with paid licences
     *
     * @param  int $type Licence type
     * @return array [userid] => email
     */
    public function get_active_paid_users($type = 2) {
        $arr = array();
        $users_list = $this->list_users();
        if (!empty($users_list) && isset($users_list->users) &&
                !empty($users_list->users) && is_array($users_list->users)) {
            foreach ($users_list->users as $user) {
                if ((isset($user->verified) && $user->verified == 1 &&
                        isset($user->type) && $user->type == $type) ||
                        ((empty($type) && in_array($user->type, array(2 ,3)))
                                || (!empty($type) && isset($user->type) &&
                                $user->type == $type))) {
                    $arr[$user->id] = $user->email;
                }
            }
        }

        return $arr;
    }

    /**
     * Check for make utmost
     *
     * @param  int $type
     * @return boolean
     */
    protected function _make_utmost($type = 2) {
        if ($paid_users = $this->get_active_paid_users($type)) {
            return $this->_config->utmost && (count($paid_users) >= $this->_limitfrom);
        }

        return false;
    }

    /**
     * Get utmost user id
     *
     * @access protected
     * @param $type Needle type to get
     * @return bool|string
     */
    protected function _get_utmost_user_id($type = 2) {
        $arr = array();
        $users_list = $this->list_users();
        if (!empty($users_list) && isset($users_list->users) &&
                !empty($users_list->users) && is_array($users_list->users)) {
            foreach ($users_list->users as $user) {
                if ((($type && $user->type == $type) || empty($type)) &&
                        isset($user->last_login_time)) {
                    $arr[$user->id] = strtotime($user->last_login_time);
                }
            }
            if (!empty($arr)) {
                $min = min($arr);
                $flip = array_flip($arr);

                return $flip[$min];
            }
        }

        return false;
    }

    /**
     * Get user settings by user id
     *
     * @param  string $userid
     * @return object
     */
    protected function _get_user_settings($userid) {
        return $this->make_call('users/'.$userid.'/settings');
    }

    /**
     * Find a user via their email.
     *
     * @param string $email
     * @return bool
     */
    public function user_getbyemail($email) {
        global $CFG, $USER;
        $data = array();
        switch ($this->_version) {
            case $this->_is_api_version(1):
                $logintypes = get_config('mod_zoom', 'logintypes');
                $allowedtypes = explode(',', $logintypes);

                $url = 'user/getbyemail';

                $data = array('email' => $email);

                foreach ($allowedtypes as $logintype) {
                    $data['login_type'] = $logintype;
                    try {
                        $this->make_call($url, $data);
                        return true;
                    } catch (moodle_exception $e) {
                        require_once($CFG->dirroot.'/mod/zoom/lib.php');
                        if (!zoom_is_user_not_found_error($e->getMessage())) {
                            return false;
                        }
                    }
                }
                break;
            case $this->_is_api_version(2):
                $url = 'users/'.$email;
                try {
                    $user = $this -> make_call($url);
                }catch (moodle_exception $e) {
                    require_once($CFG->dirroot.'/mod/ncmzoom/lib.php');
                    if (!zoom_is_user_not_found_error($e->getMessage())) {
                        return false;
                    }
                }
                if(empty(!$user)){
                    return true;
                } 
                break;
        }

        return false;
    }

    /**
     * Make request data for meeting object
     *
     * @param object $zoom
     * @return array
     */
    protected function _make_meeting_data_v2($zoom) {
        global $CFG;

        $data = array(
            'topic' => $zoom->name,
            'start_time' => $zoom->start_time,
            'duration' => intval($zoom->duration / 60),
            'agenda' => strip_tags($zoom->intro),
            'settings' => array(
                'host_video' => $zoom->option_host_video ? true : false,
                'participant_video' => $zoom->option_participants_video ? true : false,
                'join_before_host' => $zoom->option_jbh ? true : false,
                'audio' => $zoom->option_audio ? true : false,
                'alternative_hosts' => $zoom->option_alternative_hosts
            )
        );
        if (isset($CFG->timezone) && !empty($CFG->timezone)) {
            $data['timezone'] = $CFG->timezone;
        }
        if ($zoom->password) {
            $data['password'] = $zoom->password;
        }
        if ($zoom->recurring) {
            $data['type'] = ZOOM_RECURRING_MEETING;
            unset($data['start_time']);
            unset($data['duration']);
        }

        return $data;
    }

    // Meeting/webinar API calls
    // --------------------------------------------------------------------------
    // See https://support.zoom.us/hc/en-us/articles/201363053-REST-Meeting-API
    // and https://support.zoom.us/hc/en-us/articles/204484645-REST-Webinar-API .

    /**
     * Create a meeting/webinar on Zoom.
     * Take a $zoom object as returned from the Moodle form,
     * and respond with an object that can be saved to the database.
     *
     * @param object $zoom
     * @return bool
     */
    public function meeting_create($zoom) {
        if ( $this->get_active_paid_users() ) {
            switch ($this->_version) {
                case $this->_is_api_version(1):
                    $url = $zoom->webinar ? 'webinar/create' : 'meeting/create';

                    if (!$this->parse_zoom_object($zoom,
                            array('topic', 'host_id'), $data)) {
                        return false;
                    }
                    try {
                        $this->make_call($url, $data, 'post');
                    } catch (moodle_exception $e) {
                        return false;
                    }
                    $this->format_meeting_response($zoom);
                    break;
                case $this->_is_api_version(2):
                    $createurl = $zoom->webinar ? 'users/'.$zoom->host_id.'/webinars' :
                    'users/'.$zoom->host_id.'/meetings';
                    if ($fresponse = $this->make_call($createurl,
                            $this->_make_meeting_data_v2($zoom), 'post')) {
                        if ($this->parse_zoom_object($zoom, array('topic', 'host_id'), $data)) {
                            if ($this->_make_utmost()) {
                                if ($utmost_user_id = $this->_get_utmost_user_id()) {
                                    $url = 'users/'.$utmost_user_id;
                                    $response = $this->make_call($url,
                                            array('type' => 1), 'patch');
                                    if (empty($response)) {
                                        $url = 'users/'.$zoom->host_id;
                                        $response = $this->make_call($url,
                                                array('type' => 2), 'patch');
                                        if (empty($response)) {
                                            $this->lastresponse = $fresponse;
                                            $this->format_meeting_response($zoom);
                                        }
                                    }
                                } else {

                                }
                            }
                        } else {
                            
                        }
                    } else {
                        
                    }
                    break;
            }

            return true;
        }

        return false;
    }

    /**
     * Update a meeting/webinar on Zoom.
     * Interpret $zoom the same way as meeting_create().
     *
     * @param object $zoom
     * @return bool
     */
    public function meeting_update($zoom) {
        switch ($this->_version) {
            case $this->_is_api_version(1):
                $url = $zoom->webinar ? 'webinar/update' : 'meeting/update';

                if (!$this->parse_zoom_object($zoom, array('id', 'host_id'), $data)) {
                    return false;
                }

                try {
                    $this->make_call($url, $data);
                    $zoom->updated_at = $this->lastresponse->updated_at;
                } catch (moodle_exception $e) {
                    return false;
                }
                break;
            case $this->_is_api_version(2):
                $url = $zoom->webinar ? 'webinars/'.$zoom->meeting_id : 'meetings/'.
                    $zoom->meeting_id;
                $this->make_call($url, $this->_make_meeting_data_v2($zoom), 'patch');
                break;
        }

        $zoom->id = $zoom->instance;
        $this->lastresponse = $zoom;

        return true;
    }

    /**
     * Delete a meeting/webinar on Zoom.
     *
     * @param object $zoom
     * @return bool Success/Failure
     */
    public function meeting_delete($zoom) {
        global $CFG;

        switch ($this->_version) {
            case $this->_is_api_version(1):
                $url = $zoom->webinar ? 'webinar/delete' : 'meeting/delete';
                $data = array('id' => $zoom->meeting_id, 'host_id' => $zoom->host_id);

                try {
                    $this->make_call($url, $data, 'post');
                } catch (moodle_exception $e) {
                    // If meeting isn't found or has expired, that's fine.
                    require_once($CFG->dirroot.'/mod/zoom/lib.php');
                    return zoom_is_meeting_gone_error($e->getMessage());
                }
                break;
            case $this->_is_api_version(2):
                $url = $zoom->webinar ? 'webinars/'.$zoom->meeting_id : 'meetings/'.
                    $zoom->meeting_id;
                $this->make_call($url, null, 'delete');
                break;
        }

        return true;
    }

    /**
     * Get a meeting/webinar's information from Zoom.
     * Interpret $zoom the same way as meeting_create().
     *
     * @param object $zoom
     * @return bool Success/Failure
     */
    public function get_meeting_info($zoom) {
        $data = array();
        switch ($this->_version) {
            case $this->_is_api_version(1):
                $url = $zoom->webinar ? 'webinar/get' : 'meeting/get';
                $data = array('id' => $zoom->meeting_id, 'host_id' => $zoom->host_id);

                $this->format_meeting_response($zoom);
                break;
            case $this->_is_api_version(2):
                $url = $zoom->webinar ? 'webinars/'.$zoom->meeting_id : 'meetings/'.
                    $zoom->meeting_id;
                break;
        }

        try {
            return $this->make_call($url, $data);
        } catch (moodle_exception $e) {
            
        }

        return false;
    }

    // Reporting API calls
    // --------------------------------------------------------------------------
    // See https://support.zoom.us/hc/en-us/articles/201363083-REST-Report-API .

    /**
     * Get user report for a specific period.
     * "from" and "to" dates are of the form YYYY-MM-DD
     * ex) 2015-07-15
     *
     * @param int $userid Id of user of interest
     * @param string $from Start date of period
     * @param string $to End date of period
     * @param int $pagesize Optional; number of records per page
     * @param int $pagenumber Optional; which page to request
     * @return bool Success/Failure
     */
    public function get_user_report($userid, $from, $to,
            $pagesize = ZOOM_DEFAULT_RECORDS_PER_CALL, $pagenumber = 1) {
        switch ($this->_version) {
            case $this->_is_api_version(1):
                $url = 'report/getuserreport';
                $data = array('user_id' => $userid,
                              'from' => $from,
                              'to' => $to,
                              'page_size' => $pagesize,
                              'page_number' => $pagenumber);
                break;
            case $this->_is_api_version(2):
                $url = 'report/users/'.$userid.'/meetings';
                $data = array('from' => $from,
                              'to' => $to,
                              'page_size' => $pagesize);
                break;
        }
        try {
            $this->make_call($url, $data);
        } catch (moodle_exception $e) {
            return false;
        }

        return true;
    }

    /**
     * List UUIDs ("sessions") for a webinar.
     *
     * @param object $zoom
     * @return bool
     */
    public function webinar_uuid_list($zoom) {
        if (!$zoom->webinar) {
            $this->lasterror = 'Provided zoom must be a webinar.';
            return false;
        }

        switch ($this->_version) {
            case $this->_is_api_version(1):
                $url = 'webinar/uuid/list';
                $data = array('id' => $zoom->meeting_id, 'host_id' => $zoom->host_id);
                break;
            case $this->_is_api_version(2):
                $url = 'users/'.$zoom->host_id.'/webinars';
                break;
        }

        try {
            $this->make_call($url, $data);
        } catch (moodle_exception $e) {
            return false;
        }

        return true;
    }

    /**
     * List attendees for a particular UUID ("session") of a webinar.
     * Default to the most recent UUID/session if none specified.
     *
     * @param object $zoom
     * @param string $uuid
     * @return bool
     */
    public function webinar_attendees_list($zoom, $uuid = '') {
        if (!$zoom->webinar) {
            $this->lasterror = 'Provided zoom must be a webinar.';
            return false;
        }

        switch ($this->_version) {
            case $this->_is_api_version(1):
                $url = 'webinar/attendees/list';
                $data = array('id' => $zoom->meeting_id, 'host_id' => $zoom->host_id);
                if (!empty($uuid)) {
                    $data['uuid'] = $uuid;
                }
                try {
                    $this->make_call($url, $data);
                } catch (moodle_exception $e) {
                    return false;
                }
                return true;
            case $this->_is_api_version(2):
                $url = 'webinars/'.$zoom->meeting_id.'/registrants';
                try {
                    $response = $this->make_call($url, $data);
                    return isset($response->registrants) ? $response->registrants : null;
                } catch (moodle_exception $e) {
                    return false;
                }
        }

        return true;
    }

    /**
     * Get details about a particular webinar UUID/session
     * using Dashboard API.
     *
     * @param string $uuid
     * @param int $pagesize Optional; number of records per page
     * @param int $pagenumber Optional; which page to request
     * @return bool
     */
    public function metrics_webinar_detail($uuid,
            $pagesize = ZOOM_DEFAULT_RECORDS_PER_CALL, $pagenumber = 1) {
        $data = array();
        switch ($this->_version) {
            case $this->_is_api_version(1):
                $url = 'metrics/webinardetail';
                $data = array(
                    'meeting_id' => $uuid,
                    'type' => 2,
                    'page_size' => $pagesize,
                    'page_number' => $pagenumber
                );
                break;
            case $this->_is_api_version(2):
                $url = 'webinars/'.$uuid;
                break;
        }

        try {
            $this->make_call($url, $data);
        } catch (moodle_exception $e) {
            return false;
        }

        return true;
    }

    // Helper functions
    // --------------------------------

    /**
     * Extract relevant fields, check for required fields, and
     * rename/reformat properties.
     *
     * @param object $zoom
     * @param array $required
     * @param array $data
     * @return boolean
     */
    protected function parse_zoom_object(&$zoom, $required, &$data) {
        // Convert $zoom to an array and extract only the relevant keys.
        $keys = array('host_id', 'start_time', 'duration', 'timezone', 'option_audio');
        // Add meeting-specific options if this is a meeting.
        if (!$zoom->webinar) {
            $keys = array_merge($keys, array('password', 'option_jbh', 'option_host_video', 'option_participants_video'));
        }
        $data = array_intersect_key((array)$zoom, array_flip($keys));

        // Rename/reformat properties for API call (assume these properties are set for now).
        // Convert duration to minutes.
        $data['duration'] = (int)round($data['duration'] / 60);
        // Convert timestamp to ISO-8601. The API seems to insist that it end with 'Z' to indicate UTC.
        $data['start_time'] = gmdate('Y-m-d\TH:i:s\Z', $data['start_time']);
        // API uses 'id' but database uses 'meeting_id'.
        $data['id'] = $zoom->meeting_id;
        // API uses 'topic' but database uses 'name'.
        $data['topic'] = $zoom->name;
        // API uses 'type' but database uses 'recurring' and 'webinar'.
        if ($zoom->webinar) {
            $data['type'] = $zoom->recurring ? ZOOM_RECURRING_WEBINAR : ZOOM_WEBINAR;
        } else {
            $data['type'] = $zoom->recurring ? ZOOM_RECURRING_MEETING : ZOOM_SCHEDULED_MEETING;
        }
        // Webinar API uses 'agenda' but database uses 'intro'.
        if ($zoom->webinar) {
            $data['agenda'] = strip_tags($zoom->intro);
        }

        // Required parameters.
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                $this->lasterror = 'Missing required parameter.';
                return false;
            }
        }

        // Default to server timezone.
        if (!isset($data['timezone'])) {
            $data['timezone'] = date_default_timezone_get();
        }

        return true;
    }

    /**
     * Rename/format the API response to match the database.
     * Add properties in $zoom not found in lastresponse to lastresponse.
     *
     * @param object $zoom
     */
    protected function format_meeting_response($zoom) {
        $response = &$this->lastresponse;
        // Undoing the transformations in parse_zoom_object.
        // Convert duration to seconds.
        $response->duration *= 60;
        // Convert string to timestamp.
        $response->start_time = strtotime($response->start_time);
        // Strip any parameters if provided in REST response for join_url.
        $response->join_url = preg_replace('/\?.*$/', '', $response->join_url);
        // Database uses 'meeting_id' but API uses 'id'.
        $response->meeting_id = $response->id;
        if (isset($zoom->instance)) {
            $response->id = $zoom->instance;
        } else {
            unset($response->id);
        }
        // Database uses 'recurring' and 'webinar' but API uses 'type'.
        $response->recurring = $response->type == ZOOM_RECURRING_MEETING || $response->type == ZOOM_RECURRING_WEBINAR;
        $response->webinar = $response->type == ZOOM_WEBINAR || $response->type == ZOOM_RECURRING_WEBINAR;

        // Database uses 'name' but API uses 'topic'.
        $response->name = $response->topic;
        unset($response->topic);
        // For now, we will not set our copy ("intro") of the description (Zoom's "agenda")
        // in webinars, since we use HTML formatting but they use plain text.

        // Merge in other properties from $zoom object.
        foreach ($zoom as $key => $value) {
            if (!isset($response->$key)) {
                $response->$key = $value;
            }
        }
    }
}
