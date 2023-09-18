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
 * Exception class for Zoom API errors.
 *
 * @package   mod_zoom
 * @copyright 2023 Jonathan Champ <jrchamp@ncsu.edu>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoom;

/**
 * Couldn't succeed within the allowed number of retries.
 */
class retry_failed_exception extends webservice_exception {
    /**
     * Constructor
     * @param string $response      Web service response
     * @param int $errorcode     Web service response error code
     */
    public function __construct($response, $errorcode) {
        $a = new \stdClass();
        $a->response = $response;
        $a->maxretries = webservice::MAX_RETRIES;
        parent::__construct($response, $errorcode, 'zoomerr_maxretries', 'mod_zoom', '', $a);
    }
}
