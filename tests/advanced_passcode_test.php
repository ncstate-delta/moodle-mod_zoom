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
require_once($CFG->dirroot.'/mod/zoom/locallib.php');

/**
 * PHPunit testcase class.
 *
 * @copyright  2020 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class advanced_passcode_test extends basic_testcase {

    /**
     * Fake data from get_user_security_settings().
     * @var object
     */
    private $zoomdata;

    /**
     * Tests that a default password of 6 numbers is created when settings are null.
     */
    public function test_settings_default() {
        $this->zoomdata = (object) mod_zoom_webservice::DEFAULT_MEETING_PASSWORD_REQUIREMENT;

        $passcode = zoom_create_default_passcode($this->zoomdata);
        $this->assertEquals(strlen($passcode), 6);
        $this->assertTrue(ctype_digit($passcode));
    }

    /**
     * Tests that a password has the given minimum length.
     */
    public function test_settings_length() {
        $data = array('length' => 8,
            'have_letter' => false,
            'have_upper_and_lower_characters' => false,
            'have_special_character' => false
        );
        $this->zoomdata = (object) $data;

        $passcode = zoom_create_default_passcode($this->zoomdata);
        $this->assertEquals(strlen($passcode), 8);
        $this->assertTrue(ctype_digit($passcode));
    }

    /**
     * Tests that a password is all numbers when the setting is specified.
     */
    public function test_settings_only_numeric() {
        $data = array('length' => 10,
            'have_letter' => false,
            'have_upper_and_lower_characters' => false,
            'have_special_character' => false,
            'only_allow_numeric' => true,
        );
        $this->zoomdata = (object) $data;

        $passcode = zoom_create_default_passcode($this->zoomdata);
        $this->assertEquals(strlen($passcode), 10);
        $this->assertTrue(ctype_digit($passcode));
    }

    /**
     * Tests that a password has a letter when the setting is specified.
     */
    public function test_settings_letter() {
        $data = array('length' => null,
            'have_letter' => true,
            'have_upper_and_lower_characters' => false,
            'have_special_character' => null
        );
        $this->zoomdata = (object) $data;

        $passcode = zoom_create_default_passcode($this->zoomdata);
        $this->assertEquals(strlen($passcode), 6);
        $this->assertRegExp('/\d/', $passcode);
        $this->assertRegExp('/[a-zA-Z]/', $passcode);
    }

    /**
     * Tests that a password has uppercase and lowercase letters when the setting is specified.
     */
    public function test_settings_upper_and_lower_letters() {
        $data = array('length' => null,
            'have_letter' => true,
            'have_upper_and_lower_characters' => true,
            'have_special_character' => null
        );
        $this->zoomdata = (object) $data;

        $passcode = zoom_create_default_passcode($this->zoomdata);
        $this->assertEquals(strlen($passcode), 6);
        $this->assertRegExp('/\d/', $passcode);
        $this->assertRegExp('/[A-Z]/', $passcode);
        $this->assertRegExp('/[a-z]/', $passcode);
    }

    /**
     * Tests that a password has a special character when the setting is specified.
     */
    public function test_settings_special_character() {
        $data = array('length' => null,
            'have_letter' => null,
            'have_upper_and_lower_characters' => null,
            'have_special_character' => true
        );
        $this->zoomdata = (object) $data;

        $passcode = zoom_create_default_passcode($this->zoomdata);
        $this->assertEquals(strlen($passcode), 6);
        $this->assertRegExp('/\d/', $passcode);
        $this->assertRegExp('/[^a-zA-Z\d]/', $passcode);
    }

    /**
     * Tests that a password has correct length, a letter, and a special character when setting is specified.
     */
    public function test_settings_all() {
        $data = array('length' => 7,
            'have_letter' => true,
            'have_upper_and_lower_characters' => true,
            'have_special_character' => true
        );
        $this->zoomdata = (object) $data;

        $passcode = zoom_create_default_passcode($this->zoomdata);
        $this->assertEquals(strlen($passcode), 7);
        $this->assertRegExp('/\d/', $passcode);
        $this->assertRegExp('/[a-zA-Z]/', $passcode);
        $this->assertRegExp('/[^a-zA-Z\d]/', $passcode);
    }

    /**
     * Tests that the password description is correct when all settings are present.
     */
    public function test_pasword_description_all() {
        $data = array('length' => 9,
            'have_letter' => true,
            'have_number' => true,
            'have_upper_and_lower_characters' => true,
            'have_special_character' => true,
            'consecutive_characters_length' => 4,
            'only_allow_numeric' => false,
        );
        $this->zoomdata = (object) $data;

        $description = zoom_create_passcode_description($this->zoomdata);
        $expected = 'Passcode must include both lower and uppercase characters. Passcode must contain at least 1 number. ' .
         'Passcode must have at least 1 special character (@-_*). Minimum of 9 character(s). Maximum of 3 consecutive ' .
         'characters (abcd, 1111, 1234, etc.). Maximum of 10 characters.';
        $this->assertEquals($description, $expected);
    }

    /**
     * Tests that the password description is correct when the only numeric option is present.
     */
    public function test_pasword_description_only_numeric() {
        $data = array('length' => 8,
            'have_letter' => false,
            'have_number' => true,
            'have_upper_and_lower_characters' => false,
            'have_special_character' => false,
            'consecutive_characters_length' => 0,
            'only_allow_numeric' => true,
        );
        $this->zoomdata = (object) $data;

        $description = zoom_create_passcode_description($this->zoomdata);
        $expected = 'Passcode may only contain numbers and no other characters. Minimum of 8 character(s). ' .
            'Maximum of 10 characters.';
        $this->assertEquals($description, $expected);
    }

    /**
     * Tests that the password description is correct when the default settings are present.
     */
    public function test_pasword_description_default() {
        $this->zoomdata = (object) mod_zoom_webservice::DEFAULT_MEETING_PASSWORD_REQUIREMENT;

        $description = zoom_create_passcode_description($this->zoomdata);
        $expected = 'Passcode may only contain the following characters: [a-z A-Z 0-9 @ - _ *]. Maximum of 10 characters.';
        $this->assertEquals($description, $expected);
    }
}
