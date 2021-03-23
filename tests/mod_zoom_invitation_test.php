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
 * Tests for the invitation class.
 *
 * @package    mod_zoom
 * @author     Andrew Madden <andrewmadden@catalyst-au.net>
 * @copyright  2021 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->libdir . '/accesslib.php');

class mod_zoom_invitation_testcase extends advanced_testcase {

    /**
     * Test zoom invitation display message for user with all capabilities.
     */
    public function test_display_message_when_user_has_all_capabilities() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $zoom = $this->getDataGenerator()->create_module('zoom', ['course' => $course]);
        $message = (new \mod_zoom\invitation($this->get_mock_invitation_message()))->get_display_string($zoom->cmid);
        $expectedmessage = trim($this->get_mock_invitation_message());
        $this->assertEquals($expectedmessage, $message);
    }

    /**
     * Test zoom invitation display message for user with only the mod/zoom:viewjoinurl capability.
     */
    public function test_display_message_when_user_has_viewjoinurl_capability() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $role = $this->getDataGenerator()->create_role();
        $zoom = $this->getDataGenerator()->create_module('zoom', ['course' => $course]);
        assign_capability('mod/zoom:viewjoinurl', CAP_ALLOW, $role, context_system::instance()->id);
        role_assign($role, $user->id, context_course::instance($course->id));
        $message = (new \mod_zoom\invitation($this->get_mock_invitation_message()))->get_display_string($zoom->cmid, $user->id);
        $expectedmessage = "Organization is inviting you to a scheduled Zoom meeting.\r\n\r\nTopic: Zoom Meeting\r\n"
                . "Time: Mar 15, 2021 06:08 AM London\r\nJoin Zoom Meeting\r\nhttps://us02web.zoom.us/j/12341234123?pwd=THBLWExVS0QyYnV1Z1nZTDJGYVI2QT09\r\n\r\n"
                . "Meeting ID: 123 1234 1234\r\nPasscode: 123123";
        $this->assertEquals($expectedmessage, $message);
    }

    /**
     * Test zoom invitation display message for user with only the mod/zoom:viewjoinurl capability.
     */
    public function test_display_message_when_user_has_viewjoinurl_capability_with_alt_invitation() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $role = $this->getDataGenerator()->create_role();
        $zoom = $this->getDataGenerator()->create_module('zoom', ['course' => $course]);
        assign_capability('mod/zoom:viewjoinurl', CAP_ALLOW, $role, context_system::instance()->id);
        role_assign($role, $user->id, context_course::instance($course->id));
        $message = (new \mod_zoom\invitation($this->get_alt_mock_invitation_message()))->get_display_string($zoom->cmid, $user->id);
        $expectedmessage = "Organization is inviting you to a scheduled Zoom meeting.\r\n\r\nTopic: Zoom Meeting\r\n"
                . "Time: Mar 15, 2021 06:08 AM London\r\nJoin directly:\r\nhttps://us02web.zoom.us/j/12341234123?pwd=THBLWExVS0QyYnV1Z1nZTDJGYVI2QT09\r\n\r\n"
                . "Join from the Zoom client:\r\nMeeting ID: 123 1234 1234\r\nPasscode: 123123";
        $this->assertEquals($expectedmessage, $message);
    }

    /**
     * Test zoom invitation display message for user with only the mod/zoom:viewdialin capability.
     */
    public function test_display_message_when_user_has_viewdialin_capability() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $role = $this->getDataGenerator()->create_role();
        $zoom = $this->getDataGenerator()->create_module('zoom', ['course' => $course]);
        assign_capability('mod/zoom:viewdialin', CAP_ALLOW, $role, context_system::instance());
        role_assign($role, $user->id, context_course::instance($course->id));
        $message = (new \mod_zoom\invitation($this->get_mock_invitation_message()))->get_display_string($zoom->cmid, $user->id);
        $expectedmessage = "Organization is inviting you to a scheduled Zoom meeting.\r\n\r\nTopic: Zoom Meeting\r\n"
                . "Time: Mar 15, 2021 06:08 AM London\r\n\r\nOne tap mobile\r\n+61323452345,,12341234123#,,,,*123456# Australia\r\n"
                . "+61312341234,,12341234123#,,,,*123456# Australia\r\n\r\nDial by your location\r\n        +61 3 5678 5678 Australia\r\n"
                . "        +61 3 4567 4567 Australia\r\n        +61 3 3456 3456 Australia\r\n        +61 3 2345 2345 Australia\r\n"
                . "        +61 3 1234 1234 Australia\r\nMeeting ID: 123 1234 1234\r\nPasscode: 123456\r\n"
                . "Find your local number: https://us02web.zoom.us/u/abcde12345";
        $this->assertEquals($expectedmessage, $message);
    }

    /**
     * Test zoom invitation display message for user with only the mod/zoom:viewdialin capability.
     */
    public function test_display_message_when_user_has_viewdialin_capability_with_alt_invitation() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $role = $this->getDataGenerator()->create_role();
        $zoom = $this->getDataGenerator()->create_module('zoom', ['course' => $course]);
        assign_capability('mod/zoom:viewdialin', CAP_ALLOW, $role, context_system::instance());
        role_assign($role, $user->id, context_course::instance($course->id));
        $message = (new \mod_zoom\invitation($this->get_alt_mock_invitation_message()))->get_display_string($zoom->cmid, $user->id);
        $expectedmessage = "Organization is inviting you to a scheduled Zoom meeting.\r\n\r\nTopic: Zoom Meeting\r\n"
                . "Time: Mar 15, 2021 06:08 AM London\r\n\r\nJoin through dial-in:\r\nOne tap mobile\r\n"
            . "+61323452345,,12341234123#,,,,*123456# Australia\r\n+61312341234,,12341234123#,,,,*123456# Australia\r\n\r\n"
            . "Dial by your location\r\n        +61 3 5678 5678 Australia\r\n        +61 3 4567 4567 Australia\r\n"
            . "        +61 3 3456 3456 Australia\r\n        +61 3 2345 2345 Australia\r\n        +61 3 1234 1234 Australia\r\n"
            . "Meeting ID: 123 1234 1234\r\nPasscode: 123456\r\nFind your local number: https://us02web.zoom.us/u/abcde12345";
        $this->assertEquals($expectedmessage, $message);
    }

    /**
     * Test zoom invitation display message for user has no capabilities.
     */
    public function test_display_message_when_user_has_no_capabilities() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $role = $this->getDataGenerator()->create_role();
        $zoom = $this->getDataGenerator()->create_module('zoom', ['course' => $course]);
        role_assign($role, $user->id, context_course::instance($course->id));
        $message = (new \mod_zoom\invitation($this->get_mock_invitation_message()))->get_display_string($zoom->cmid, $user->id);
        $expectedmessage = "Organization is inviting you to a scheduled Zoom meeting.\r\n\r\nTopic: Zoom Meeting\r\nTime: Mar 15, 2021 06:08 AM London";
        $this->assertEquals($expectedmessage, $message);
    }

    /**
     * Test debug message if regex pattern is not valid for an element.
     */
    public function test_display_message_when_a_regex_pattern_is_invalid() {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('invitation_joinurl', '', 'zoom');
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $role = $this->getDataGenerator()->create_role();
        $zoom = $this->getDataGenerator()->create_module('zoom', ['course' => $course]);
        role_assign($role, $user->id, context_course::instance($course->id));
        // Set mock zoom activity URL for page as exception messages expect it.
        $PAGE->set_url(new moodle_url('/mod/zoom/view.php?id=123'));
        $message = (new \mod_zoom\invitation($this->get_mock_invitation_message()))->get_display_string($zoom->cmid, $user->id);
        $this->assertDebuggingCalled('Error in regex for zoom invitation element: "joinurl" with pattern: "".');
    }

    /**
     * Test debug message if no match is found using regex pattern for an element.
     */
    public function test_display_message_when_a_regex_pattern_is_finds_no_match() {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('invitation_joinurl', '/nomatch/mi', 'zoom');
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $role = $this->getDataGenerator()->create_role();
        $zoom = $this->getDataGenerator()->create_module('zoom', ['course' => $course]);
        role_assign($role, $user->id, context_course::instance($course->id));
        $message = (new \mod_zoom\invitation($this->get_mock_invitation_message()))->get_display_string($zoom->cmid, $user->id);
        $this->assertDebuggingCalled('No match found in zoom invitation for element: "joinurl" with pattern: "/nomatch/mi".');
    }

    /**
     * Test removing the invite sentence from the zoom meeting message.
     */
    public function test_display_message_has_invite_removed_if_setting_enabled() {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('invitationremoveinvite', '1', 'zoom');
        $course = $this->getDataGenerator()->create_course();
        $zoom = $this->getDataGenerator()->create_module('zoom', ['course' => $course]);
        $message = (new \mod_zoom\invitation($this->get_mock_invitation_message()))->get_display_string($zoom->cmid);
        $expectedmessage = "Topic: Zoom Meeting\r\nTime: Mar 15, 2021 06:08 AM London\r\n"
            . "Join Zoom Meeting\r\nhttps://us02web.zoom.us/j/12341234123?pwd=THBLWExVS0QyYnV1Z1nZTDJGYVI2QT09\r\n\r\n"
            . "Meeting ID: 123 1234 1234\r\nPasscode: 123123\r\nOne tap mobile\r\n+61323452345,,12341234123#,,,,*123456# Australia\r\n"
            . "+61312341234,,12341234123#,,,,*123456# Australia\r\n\r\nDial by your location\r\n        +61 3 5678 5678 Australia\r\n"
            . "        +61 3 4567 4567 Australia\r\n        +61 3 3456 3456 Australia\r\n        +61 3 2345 2345 Australia\r\n"
            . "        +61 3 1234 1234 Australia\r\nMeeting ID: 123 1234 1234\r\nPasscode: 123456\r\nFind your local number: https://us02web.zoom.us/u/abcde12345";
        $this->assertEquals($expectedmessage, $message);
    }

    /**
     * Test not removing the invite sentence from the zoom meeting message.
     */
    public function test_display_message_does_not_have_invite_removed_if_setting_disabled() {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('invitationremoveinvite', '0', 'zoom');
        $course = $this->getDataGenerator()->create_course();
        $zoom = $this->getDataGenerator()->create_module('zoom', ['course' => $course]);
        $message = (new \mod_zoom\invitation($this->get_mock_invitation_message()))->get_display_string($zoom->cmid);
        $expectedmessage = trim($this->get_mock_invitation_message());
        $this->assertEquals($expectedmessage, $message);
    }

    /**
     * Test get_display_string returns null without throwing an error if the invitation string provided is null.
     */
    public function test_display_message_when_instantiated_with_null_zoom_meeting_invitation() {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $zoom = $this->getDataGenerator()->create_module('zoom', ['course' => $course]);
        $message = (new \mod_zoom\invitation(null))->get_display_string($zoom->cmid);
        $this->assertNull($message);
    }

    /**
     * Get a mock zoom invitation email message.
     *
     * @return string
     */
    private function get_mock_invitation_message(): string {
        return "Organization is inviting you to a scheduled Zoom meeting.\r\n\r\nTopic: Zoom Meeting\r\nTime: Mar 15, 2021 06:08 AM London\r\n"
                . "Join Zoom Meeting\r\nhttps://us02web.zoom.us/j/12341234123?pwd=THBLWExVS0QyYnV1Z1nZTDJGYVI2QT09\r\n\r\n"
                . "Meeting ID: 123 1234 1234\r\nPasscode: 123123\r\nOne tap mobile\r\n+61323452345,,12341234123#,,,,*123456# Australia\r\n"
                . "+61312341234,,12341234123#,,,,*123456# Australia\r\n\r\nDial by your location\r\n        +61 3 5678 5678 Australia\r\n"
                . "        +61 3 4567 4567 Australia\r\n        +61 3 3456 3456 Australia\r\n        +61 3 2345 2345 Australia\r\n"
                . "        +61 3 1234 1234 Australia\r\nMeeting ID: 123 1234 1234\r\nPasscode: 123456\r\nFind your local number: https://us02web.zoom.us/u/abcde12345\r";
    }

    /**
     * Get an alternative mock zoom invitation email message.
     *
     * @return string
     */
    private function get_alt_mock_invitation_message(): string {
        return "Organization is inviting you to a scheduled Zoom meeting.\r\n\r\nTopic: Zoom Meeting\r\nTime: Mar 15, 2021 06:08 AM London\r\n"
                . "Join directly:\r\nhttps://us02web.zoom.us/j/12341234123?pwd=THBLWExVS0QyYnV1Z1nZTDJGYVI2QT09\r\n\r\n"
                . "Join from the Zoom client:\r\nMeeting ID: 123 1234 1234\r\nPasscode: 123123\r\n\r\nJoin through dial-in:\r\n"
                . "One tap mobile\r\n+61323452345,,12341234123#,,,,*123456# Australia\r\n+61312341234,,12341234123#,,,,*123456# Australia\r\n\r\n"
                . "Dial by your location\r\n        +61 3 5678 5678 Australia\r\n        +61 3 4567 4567 Australia\r\n        +61 3 3456 3456 Australia\r\n"
                . "        +61 3 2345 2345 Australia\r\n        +61 3 1234 1234 Australia\r\nMeeting ID: 123 1234 1234\r\nPasscode: 123456\r\n"
                . "Find your local number: https://us02web.zoom.us/u/abcde12345\r";
    }
}
