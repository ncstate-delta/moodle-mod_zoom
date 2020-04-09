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
 * Unit tests for get_meeting_reports task class.
 *
 * @package    mod_zoom
 * @copyright  2019 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * PHPunit testcase class.
 *
 * @copyright  2019 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class webservice_test extends advanced_testcase {

    /**
     * Setup before every test.
     */
    public function setUp() {
        $this->resetAfterTest();
        // Set fake values so we can test methods in class.
        set_config('apikey', 'test', 'mod_zoom');
        set_config('apisecret', 'test', 'mod_zoom');
    }

    /**
     * Tests that uuid are encoded properly for use in web service calls.
     */
    public function test_encode_uuid() {
        $service = new \mod_zoom_webservice();

        // If uuid includes / or // it needs to be double encoded.
        $uuid = $service->encode_uuid('/u2F0gUNSqqC7DT+08xKrw==');
        $this->assertEquals('%252Fu2F0gUNSqqC7DT%252B08xKrw%253D%253D', $uuid);

        $uuid = $service->encode_uuid('Ahqu+zVcQpO//RcAUUWkNA==');
        $this->assertEquals('Ahqu%252BzVcQpO%252F%252FRcAUUWkNA%253D%253D', $uuid);

        // If not, then it can be used as is.
        $uuid = $service->encode_uuid('M8TigfzxRTKJmhXnV7bNjw==');
        $this->assertEquals('M8TigfzxRTKJmhXnV7bNjw==', $uuid);
    }
}