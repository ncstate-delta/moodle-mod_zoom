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
 * Unit tests for supporting advanced password requirements in Zoom.
 *
 * @package    mod_zoom
 * @copyright  2020 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot.'/mod/zoom/lib.php');
require_once($CFG->dirroot.'/mod/zoom/locallib.php');

/**
 * PHPunit testcase class.
 *
 * @copyright  2020 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_zoom_grade_test extends advanced_testcase {

    /**
     * Setup before every test.
     */
    public function setUp(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->course = $this->getDataGenerator()->create_course();
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'teacher');
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->generator = $this->getDataGenerator()->get_plugin_generator('mod_zoom');
    }

    /**
     * Tests that Zoom grades can be added and updated in the gradebook.
     */
    public function test_grade_added() {
        $params['course'] = $this->course->id;
        $params['grade'] = 100;

        $instance = $this->generator->create_instance($params);
        $gradebook = grade_get_grades($this->course->id, 'mod', 'zoom', $instance->id);

        // Gradebook should be empty.
        $this->assertEquals(0, count($gradebook->items[0]->grades));

        // Insert grade for student.
        $studentgrade = array('userid' => $this->student->id, 'rawgrade' => 50);
        zoom_grade_item_update($instance, $studentgrade);

        // Gradebook should contain a grade for student.
        $gradebook = grade_get_grades($this->course->id, 'mod', 'zoom', $instance->id, $this->student->id);
        $this->assertEquals(1, count($gradebook->items[0]->grades));
        $this->assertEquals(50, $gradebook->items[0]->grades[$this->student->id]->grade);

        // Update grade for student.
        $studentgrade = array('userid' => $this->student->id, 'rawgrade' => 75);
        zoom_grade_item_update($instance, $studentgrade);
        $gradebook = grade_get_grades($this->course->id, 'mod', 'zoom', $instance->id, $this->student->id);

        // Verify grade has been updated.
        $this->assertEquals(1, count($gradebook->items[0]->grades));
        $this->assertEquals(75, $gradebook->items[0]->grades[$this->student->id]->grade);
    }

    /**
     * Tests that the Zoom grade type cannot be changed to NONE if grades are already inputted.
     */
    public function test_grade_type_not_none() {
        $params['course'] = $this->course->id;
        $params['grade'] = 100;

        $instance = $this->generator->create_instance($params);
        $gradebook = grade_get_grades($this->course->id, 'mod', 'zoom', $instance->id);

        // Gradebook should be empty.
        $this->assertEquals(0, count($gradebook->items[0]->grades));

        // Insert grade for student.
        $studentgrade = array('userid' => $this->student->id, 'rawgrade' => 100);
        zoom_grade_item_update($instance, $studentgrade);

        // Gradebook should contain a grade for student.
        $gradebook = grade_get_grades($this->course->id, 'mod', 'zoom', $instance->id, $this->student->id);
        $this->assertEquals(1, count($gradebook->items[0]->grades));

        // Try to change grade type to NONE.
        $instance->grade = 0;
        zoom_grade_item_update($instance);
        $gradebook = grade_get_grades($this->course->id, 'mod', 'zoom', $instance->id);

        // Verify grade type is not changed.
        $this->assertEquals(100, $gradebook->items[0]->grademax);
    }

    /**
     * Tests that the Zoom grades can be deleted.
     */
    public function test_grade_delete() {
        $params['course'] = $this->course->id;
        $params['grade'] = 100;

        $instance = $this->generator->create_instance($params);
        $gradebook = grade_get_grades($this->course->id, 'mod', 'zoom', $instance->id);

        // Gradebook should be empty.
        $this->assertEquals(0, count($gradebook->items[0]->grades));

        // Insert grade for student.
        $studentgrade = array('userid' => $this->student->id, 'rawgrade' => 100);
        zoom_grade_item_update($instance, $studentgrade);

        // Gradebook should contain a grade for student.
        $gradebook = grade_get_grades($this->course->id, 'mod', 'zoom', $instance->id, $this->student->id);
        $this->assertEquals(1, count($gradebook->items[0]->grades));

        // Delete the grade items.
        zoom_grade_item_delete($instance);
        $gradebook = grade_get_grades($this->course->id, 'mod', 'zoom', $instance->id);

        // Verify gradebook is empty.
        $this->assertEmpty($gradebook->items);
    }
}
