<?php
// This file is part of Moodle - http://moodle.org/
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
 * The mod_zoom generator tests.
 *
 * @package    mod_zoom
 * @category   test
 * @copyright  2020 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Genarator tests class for mod_zoom.
 *
 * @package    mod_zoom
 * @copyright  2020 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      mod_zoom
 */
class mod_zoom_generator_testcase extends advanced_testcase {

    /**
     * Tests that generator sets defaults.
     */
    public function test_create_instance() {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();

        $this->assertFalse($DB->record_exists('zoom', array('course' => $course->id)));
        $zoom = $this->getDataGenerator()->create_module('zoom', array('course' => $course));
        $records = $DB->get_records('zoom', array('course' => $course->id), 'id');
        $this->assertEquals(1, count($records));
        $this->assertTrue(array_key_exists($zoom->id, $records));

        $params = array('course' => $course->id, 'name' => 'Zoom generator test');
        $zoom = $this->getDataGenerator()->create_module('zoom', $params);
        $records = $DB->get_records('zoom', array('course' => $course->id), 'id');
        $this->assertEquals(2, count($records));
        $this->assertEquals('Zoom generator test', $records[$zoom->id]->name);
    }
}
