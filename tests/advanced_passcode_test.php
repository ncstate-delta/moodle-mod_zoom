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

namespace mod_zoom;

use basic_testcase;

/**
 * PHPunit testcase class.
 */
class advanced_passcode_test extends basic_testcase {
    /**
     * Fake data from get_user_security_settings().
     * @var object
     */
    private $zoomdata;

    // phpcs:disable moodle.NamingConventions.ValidFunctionName.LowercaseMethod
    /**
     * Backward compatibility support for PHPUnit 8 (PHP 7.2 and 7.3).
     *
     * @param string $pattern Regular expression.
     * @param string $string String.
     * @param string $message Message.
     */
    public static function assertMatchesRegularExpression($pattern, $string, $message = ''): void {
        // phpcs:enable
        if (method_exists('basic_testcase', 'assertMatchesRegularExpression')) {
            parent::assertMatchesRegularExpression($pattern, $string, $message);
        } else {
            parent::assertRegExp($pattern, $string, $message);
        }
    }

    /**
     * Setup to ensure that fixtures are loaded.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/zoom/locallib.php');
    }

    /**
     * Tests that a default password of 6 numbers is created when settings are null.
     * @covers ::zoom_create_default_passcode
     */
    public function test_settings_default(): void {
        $this->zoomdata = (object) webservice::DEFAULT_MEETING_PASSWORD_REQUIREMENT;

        $passcode = zoom_create_default_passcode($this->zoomdata);
        $this->assertEquals(strlen($passcode), 6);
        $this->assertTrue(ctype_digit($passcode));
    }

    /**
     * Tests that a password has the given minimum length.
     * @covers ::zoom_create_default_passcode
     */
    public function test_settings_length(): void {
        $data = [
            'length' => 8,
            'have_letter' => false,
            'have_upper_and_lower_characters' => false,
            'have_special_character' => false,
        ];
        $this->zoomdata = (object) $data;

        $passcode = zoom_create_default_passcode($this->zoomdata);
        $this->assertEquals(strlen($passcode), 8);
        $this->assertTrue(ctype_digit($passcode));
    }

    /**
     * Tests that a password is all numbers when the setting is specified.
     * @covers ::zoom_create_default_passcode
     */
    public function test_settings_only_numeric(): void {
        $data = [
            'length' => 10,
            'have_letter' => false,
            'have_upper_and_lower_characters' => false,
            'have_special_character' => false,
            'only_allow_numeric' => true,
        ];
        $this->zoomdata = (object) $data;

        $passcode = zoom_create_default_passcode($this->zoomdata);
        $this->assertEquals(strlen($passcode), 10);
        $this->assertTrue(ctype_digit($passcode));
    }

    /**
     * Tests that a password has a letter when the setting is specified.
     * @covers ::zoom_create_default_passcode
     */
    public function test_settings_letter(): void {
        $data = [
            'length' => null,
            'have_letter' => true,
            'have_upper_and_lower_characters' => false,
            'have_special_character' => null,
        ];
        $this->zoomdata = (object) $data;

        $passcode = zoom_create_default_passcode($this->zoomdata);
        $this->assertEquals(strlen($passcode), 6);
        $this->assertMatchesRegularExpression('/\d/', $passcode);
        $this->assertMatchesRegularExpression('/[a-zA-Z]/', $passcode);
    }

    /**
     * Tests that a password has uppercase and lowercase letters when the setting is specified.
     * @covers ::zoom_create_default_passcode
     */
    public function test_settings_upper_and_lower_letters(): void {
        $data = [
            'length' => null,
            'have_letter' => true,
            'have_upper_and_lower_characters' => true,
            'have_special_character' => null,
        ];
        $this->zoomdata = (object) $data;

        $passcode = zoom_create_default_passcode($this->zoomdata);
        $this->assertEquals(strlen($passcode), 6);
        $this->assertMatchesRegularExpression('/\d/', $passcode);
        $this->assertMatchesRegularExpression('/[A-Z]/', $passcode);
        $this->assertMatchesRegularExpression('/[a-z]/', $passcode);
    }

    /**
     * Tests that a password has a special character when the setting is specified.
     * @covers ::zoom_create_default_passcode
     */
    public function test_settings_special_character(): void {
        $data = [
            'length' => null,
            'have_letter' => null,
            'have_upper_and_lower_characters' => null,
            'have_special_character' => true,
        ];
        $this->zoomdata = (object) $data;

        $passcode = zoom_create_default_passcode($this->zoomdata);
        $this->assertEquals(strlen($passcode), 6);
        $this->assertMatchesRegularExpression('/\d/', $passcode);
        $this->assertMatchesRegularExpression('/[^a-zA-Z\d]/', $passcode);
    }

    /**
     * Tests that a password has correct length, a letter, and a special character when setting is specified.
     * @covers ::zoom_create_default_passcode
     */
    public function test_settings_all(): void {
        $data = [
            'length' => 7,
            'have_letter' => true,
            'have_upper_and_lower_characters' => true,
            'have_special_character' => true,
        ];
        $this->zoomdata = (object) $data;

        $passcode = zoom_create_default_passcode($this->zoomdata);
        $this->assertEquals(strlen($passcode), 7);
        $this->assertMatchesRegularExpression('/\d/', $passcode);
        $this->assertMatchesRegularExpression('/[a-zA-Z]/', $passcode);
        $this->assertMatchesRegularExpression('/[^a-zA-Z\d]/', $passcode);
    }

    /**
     * Tests that the password description is correct when all settings are present.
     * @covers ::zoom_create_passcode_description
     */
    public function test_pasword_description_all(): void {
        $data = [
            'length' => 9,
            'have_letter' => true,
            'have_number' => true,
            'have_upper_and_lower_characters' => true,
            'have_special_character' => true,
            'consecutive_characters_length' => 4,
            'only_allow_numeric' => false,
        ];
        $this->zoomdata = (object) $data;

        $description = zoom_create_passcode_description($this->zoomdata);
        $expected = 'Passcode must include both lower and uppercase characters. Passcode must contain at least 1 number. ' .
         'Passcode must have at least 1 special character (@-_*). Minimum of 9 character(s). Maximum of 3 consecutive ' .
         'characters (abcd, 1111, 1234, etc.). Maximum of 10 characters.';
        $this->assertEquals($description, $expected);
    }

    /**
     * Tests that the password description is correct when the only numeric option is present.
     * @covers ::zoom_create_passcode_description
     */
    public function test_pasword_description_only_numeric(): void {
        $data = [
            'length' => 8,
            'have_letter' => false,
            'have_number' => true,
            'have_upper_and_lower_characters' => false,
            'have_special_character' => false,
            'consecutive_characters_length' => 0,
            'only_allow_numeric' => true,
        ];
        $this->zoomdata = (object) $data;

        $description = zoom_create_passcode_description($this->zoomdata);
        $expected = 'Passcode may only contain numbers and no other characters. Minimum of 8 character(s). ' .
            'Maximum of 10 characters.';
        $this->assertEquals($description, $expected);
    }

    /**
     * Tests that the password description is correct when the default settings are present.
     * @covers ::zoom_create_passcode_description
     */
    public function test_pasword_description_default(): void {
        $this->zoomdata = (object) webservice::DEFAULT_MEETING_PASSWORD_REQUIREMENT;

        $description = zoom_create_passcode_description($this->zoomdata);
        $expected = 'Passcode may only contain the following characters: [a-z A-Z 0-9 @ - _ *]. Maximum of 10 characters.';
        $this->assertEquals($description, $expected);
    }
}
