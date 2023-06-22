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
 * Webservice exception class.
 */
class webservice_exception extends \moodle_exception {
    /**
     * Web service response.
     * @var string
     */
    public $response = null;

    /**
     * Web service error code.
     * @var int
     */
    public $zoomerrorcode = null;

    /**
     * Constructor
     *
     * @param string $response Webservice response body.
     * @param int $zoomerrorcode Webservice response error code.
     * @param string $errorcode The name of the string from error.php to print
     * @param string $module name of module
     * @param string $link The url where the user will be directed. Else, the user will be directed to the site index page.
     * @param mixed $a Extra words and phrases that might be required in the error string
     * @param string $debuginfo optional debugging information
     */
    public function __construct($response, $zoomerrorcode, $errorcode, $module = '', $link = '', $a = null, $debuginfo = null) {
        $this->response = $response;
        $this->zoomerrorcode = $zoomerrorcode;

        parent::__construct($errorcode, $module, $link, $a, $debuginfo);
    }
}
