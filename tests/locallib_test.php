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
 * Unit tests for the locallib functions.
 *
 * @package    mod_zoom
 * @category   test
 * @copyright  2020 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/mod/zoom/locallib.php');

/**
 * PHPunit testcase class.
 *
 * @package    mod_zoom
 * @copyright  2020 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_zoom
 */
class locallib_test extends advanced_testcase {
    /**
     * Setup.
     */
    public function setUp() {
        $this->resetAfterTest(true);
    }

    /**
     * Tests that zoom_userishost() correctly identifies if user is host or
     * alternative host.
     */
    public function test_zoom_userishost() {
        global $DB;

        // Setup course.
        $teacher = $DB->get_record('role', ['shortname' => 'editingteacher']);
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $teacher->id);

        // Set user mapping cache so webservice isn't called.
        $hostid = uniqid();
        $cache = cache::make('mod_zoom', 'zoomid');
        $cache->set($user->id, $hostid);

        // Create Zoom meeting in which user is not host.
        $zoom = $this->getDataGenerator()->create_module('zoom',
                ['course' => $course]);

        // Test that user is not the host.
        $this->assertFalse(zoom_userishost($zoom));
        $this->assertNotEquals($hostid, $zoom->host_id);

        // Create Zoom meeting in which user is the host.
        $zoom = $this->getDataGenerator()->create_module('zoom',
                ['course' => $course, 'host_id' => $hostid]);

        // Test that user is the host.
        $this->assertTrue(zoom_userishost($zoom));
        $this->assertEquals($hostid, $zoom->host_id);

        // Create Zoom meeting in which user is an alternative host.
        $zoom = $this->getDataGenerator()->create_module('zoom',
                ['course' => $course, 'alternative_hosts' => $user->email]);

        // Test that user is the alternative host.
        $this->assertTrue(zoom_userishost($zoom));
        $this->assertNotEquals($hostid, $zoom->host_id);

        // Make email uppercase.
        $zoom = $this->getDataGenerator()->create_module('zoom',
                ['course' => $course, 'alternative_hosts' => core_text::strtoupper($user->email)]);

        // Test that user is the alternative host.
        $this->assertTrue(zoom_userishost($zoom));
        $this->assertNotEquals($hostid, $zoom->host_id);
    }
}
