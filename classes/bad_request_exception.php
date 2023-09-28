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
 * Bad request received by Zoom.
 */
class bad_request_exception extends webservice_exception {
    /**
     * Constructor
     * @param string $response      Web service response message
     * @param int $errorcode     Web service response error code
     */
    public function __construct($response, $errorcode) {
        parent::__construct($response, $errorcode, 'errorwebservice_badrequest', 'mod_zoom', '', $response);
    }
}
