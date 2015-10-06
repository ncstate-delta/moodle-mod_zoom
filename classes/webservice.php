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
     * Makes given REST call and returns result.
     *
     * See https://support.zoom.us/hc/en-us/articles/201811633-Sample-REST-API-via-PHP
     *
     * @param string $url   Will be appended to apiurl.
     * @param array $data
     * @return array
     */
    public function make_call($url, $data = array()) {
        $config = get_config('mod_zoom');
        if (!isset($config->apiurl) || !isset($config->apikey) ||
                !isset($config->apisecret)) {
            // Give error.
            throw new moodle_exception('errorapikeynotfound', 'mod_zoom');
        }

        $url = $config->apiurl . $url;
        $data['api_key'] = $config->apikey;
        $data['api_secret'] = $config->apisecret;
        $data['data_type'] = 'JSON';

        $postfields = http_build_query($data, '', '&');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);

        $response = curl_exec($ch);
        if ($response === false) {
            // Curl error.
            $error = curl_error($ch);
            curl_close($ch);
            $this->lasterror = $error;
            throw new moodle_exception('errorwebservice', 'mod_zoom', '', $error);
        }

        curl_close($ch);
        $response = json_decode($response);
        if (isset($response->error)) {
            // Web service error.
            $this->lasterror = $response->error->message;
            throw new moodle_exception('errorwebservice', 'mod_zoom', '', $response->error->message);
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
        $url = 'user/autocreate';

        $data = array();
        $data['email'] = $user->email;
        $data['type'] = 2;
        $data['password'] = str_shuffle(uniqid());
        $data['first_name'] = $user->firstname;
        $data['last_name'] = $user->lastname;

        try {
            $this->make_call($url, $data);
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
     * Find a user via their email.
     *
     * @param string $email
     * @return bool
     */
    public function user_getbyemail($email) {
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
                global $CFG;
                require_once($CFG->dirroot.'/mod/zoom/lib.php');
                if (!zoom_is_user_not_found_error($e->getMessage())) {
                    return false;
                }
            }
        }

        return false;
    }

    // Meeting API calls
    // --------------------------------------------------------------------------
    // See https://support.zoom.us/hc/en-us/articles/201363053-REST-Meeting-API .

    /**
     * Create a meeting on Zoom.
     * Take a $zoom object as returned from the Moodle form,
     * and respond with an object that can be saved to the database.
     * Only scheduled meetings (ZOOM_SCHEDULED_MEETING) are supported at this time.
     *
     * @param object $zoom
     * @return bool
     */
    public function meeting_create($zoom) {
        $url = 'meeting/create';

        if (!$this->parse_zoom_object($zoom, array('topic', 'host_id'), $data)) {
            return false;
        }

        // Default to server timezone.
        if (!isset($data['timezone'])) {
            $data['timezone'] = date_default_timezone_get();
        }

        try {
            $this->make_call($url, $data);
        } catch (moodle_exception $e) {
            return false;
        }

        $this->format_meeting_response($zoom);

        return true;
    }

    /**
     * Update a meeting on Zoom.
     * Interpret $zoom the same way as meeting_create().
     *
     * @param object $zoom
     * @return bool
     */
    public function meeting_update($zoom) {
        $url = 'meeting/update';

        if (!$this->parse_zoom_object($zoom, array('id', 'host_id'), $data)) {
            return false;
        }

        // Throws moodle_Exception.
        $this->make_call($url, $data);

        // The only fields that need changing are id and updated_at.
        $zoom->id = $zoom->instance;
        $zoom->updated_at = $this->lastresponse->updated_at;
        $this->lastresponse = $zoom;

        return true;
    }

    /**
     * Delete a meeting on Zoom.
     *
     * @param int $id Id of meeting on zoom
     * @param int $hostid Id of host on zoom
     * @return bool Success/Failure
     */
    public function delete_meeting($id, $hostid) {
        $url = 'meeting/delete';
        $data = array('id' => $id, 'host_id' => $hostid);

        try {
            $this->make_call($url, $data);
        } catch (moodle_exception $e) {
            // If meeting isn't found or has expired, that's fine.
            global $CFG;
            require_once($CFG->dirroot.'/mod/zoom/lib.php');
            return zoom_is_meeting_gone_error($e->getMessage());
        }

        return true;
    }

    /**
     * Get a meeting's information from Zoom.
     * Interpret $zoom the same way as meeting_create().
     *
     * @param object $zoom
     * @return bool Success/Failure
     */
    public function get_meeting_info($zoom) {
        $url = 'meeting/get';
        $data = array('id' => $zoom->meeting_id, 'host_id' => $zoom->host_id);

        try {
            $this->make_call($url, $data);
        } catch (moodle_exception $e) {
            return false;
        }

        $this->format_meeting_response($zoom);

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
        $data = array_intersect_key((array)$zoom, array_flip(array(
            'host_id', 'type', 'start_time', 'duration', 'timezone', 'password',
            'option_jbh', 'option_host_video', 'option_participants_video', 'option_audio'
        )));

        // Rename/reformat properties for API call (assume these properties are set for now).
        // Convert duration to minutes.
        $data['duration'] = (int)round($data['duration'] / 60);
        // Convert timestamp to ISO-8601. The API seems to insist that it end with 'Z' to indicate UTC.
        $data['start_time'] = gmdate('Y-m-d\TH:i:s\Z', $data['start_time']);
        // API uses 'id' but database uses 'meeting_id'.
        $data['id'] = $zoom->meeting_id;
        // API uses 'topic' but database uses 'name'.
        $data['topic'] = $zoom->name;

        // Default to scheduled meeting.
        if (!isset($data['type'])) {
            $data['type'] = ZOOM_SCHEDULED_MEETING;
        }

        // Required parameters.
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return false;
            }
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
        // Database uses 'name' but API uses 'topic'.
        $response->name = $response->topic;
        unset($response->topic);

        // Merge in other properties from $zoom object.
        foreach ($zoom as $key => $value) {
            if (!isset($response->$key)) {
                $response->$key = $value;
            }
        }
    }
}
