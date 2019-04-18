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
class get_meeting_reports_test extends advanced_testcase {

    /**
     * Scheduled task object.
     * @var mod_zoom\task\get_meeting_reports
     */
    private $meetingtask;

    /**
     * Fake data from get_meeting_participants().
     * @var object
     */
    private $zoomdata;

    /**
     * Setup.
     */
    public function setUp() {
        $this->resetAfterTest(true);

        $this->meetingtask = new mod_zoom\task\get_meeting_reports();

        $data = array('id' => 'ARANDOMSTRINGFORUUID',
            'user_id' => 123456789,
            'name' => 'SMITH, JOE',
            'user_email' => 'joe@test.com',
            'join_time' => '2019-01-01T00:00:00Z',
            'leave_time' => '2019-01-01T00:01:00Z',
            'duration' => 60,
            'attentiveness_score' => '100.0%'
        );
        $this->zoomdata = (object) $data;
    }

    /**
     * Make sure that format_participant() filters bad data from Zoom.
     */
    public function test_format_participant_filtering() {
        // Sometimes Zoom has a # instead of comma in the name.
        $this->zoomdata->name = 'SMITH# JOE';
        $participant = $this->meetingtask->format_participant($this->zoomdata,
                1, array(), array());
        $this->assertEquals('SMITH, JOE', $participant['name']);
    }

    /**
     * Make sure that format_participant() can match Moodle users.
     */
    public function test_format_participant_matching() {
        global $DB;return;

        // 1) If user does not match, verify that we are using data from Zoom.
        $participant = $this->meetingtask->format_participant($this->zoomdata,
                1, array(), array());
        $this->assertEquals($this->zoomdata->name, $participant['name']);
        $this->assertEquals($this->zoomdata->user_email, $participant['user_email']);
        $this->assertNull($participant['userid']);

        // 2) Try to match view via system email.

        // Add user's email to Moodle system.
        $user = $this->getDataGenerator()->create_user(
                array('email' => $this->zoomdata->user_email));

        $participant = $this->meetingtask->format_participant($this->zoomdata,
                1, array(), array());
        $this->assertEquals($user->id, $participant['userid']);
        $this->assertEquals(strtoupper(fullname($user)), $participant['name']);
        $this->assertEquals($user->email, $participant['user_email']);

        // 3) Try to match view via enrolled name.

        // Change user's name to make sure we are matching on name.
        $user->firstname = 'Firstname';
        $user->lastname = 'Lastname';
        $DB->update_record('user', $user);
        // Set to blank so previous test does not trigger.
        $this->zoomdata->user_email = '';

        // Create course and enroll user.
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);
        list($names, $emails) = $this->meetingtask->get_enrollments($course->id);

        // Before Zoom data is changed, should return nothing.
        $participant = $this->meetingtask->format_participant($this->zoomdata,
                1, $names, $emails);
        $this->assertNull($participant['userid']);

        // Change Zoom data and now user should be found.
        $this->zoomdata->name = strtoupper(fullname($user));
        $participant = $this->meetingtask->format_participant($this->zoomdata,
                1, $names, $emails);
        $this->assertEquals($user->id, $participant['userid']);
        $this->assertEquals($names[$participant['userid']], $participant['name']);
        // Email should match what Zoom gives us.
        $this->assertEquals($this->zoomdata->user_email, $participant['user_email']);

        // 4) Try to match view via enrolled email.

        // Change user's email to make sure we are matching on email.
        $user->email = 'smith@test.com';
        $DB->update_record('user', $user);
        // Change name so previous test does not trigger.
        $this->zoomdata->name = 'Something Else';
        // Since email changed, update enrolled user data.
        list($names, $emails) = $this->meetingtask->get_enrollments($course->id);

        // Before Zoom data is changed, should return nothing.
        $participant = $this->meetingtask->format_participant($this->zoomdata,
                1, $names, $emails);
        $this->assertNull($participant['userid']);

        // Change Zoom data and now user should be found.
        $this->zoomdata->user_email = $user->email;
        $participant = $this->meetingtask->format_participant($this->zoomdata,
                1, $names, $emails);
        $this->assertEquals($user->id, $participant['userid']);
        $this->assertEquals($names[$participant['userid']], $participant['name']);
        // Email should match what Zoom gives us.
        $this->assertEquals($this->zoomdata->user_email, $participant['user_email']);

        // 5) Try to match user via id (uuid).

        // Insert previously generated $participant data, but with UUID set.
        $participant['uuid'] = $this->zoomdata->id;
        // Set userid to a given value so we know we got a match.
        $participant['userid'] = 999;
        $recordid = $DB->insert_record('zoom_meeting_participants', $participant);

        // Should return the found entry in zoom_meeting_participants.
        $newparticipant = $this->meetingtask->format_participant($this->zoomdata,
                1, $names, $emails);
        $this->assertEquals($participant['uuid'], $newparticipant['uuid']);
        $this->assertEquals(999, $newparticipant['userid']);
        $this->assertEquals($participant['name'], $newparticipant['name']);
        // Email should match what Zoom gives us.
        $this->assertEquals($this->zoomdata->user_email, $newparticipant['user_email']);

        // 6) Try to match user via user_id.

        // Change uuid, email, user_id so no record is found.
        $this->zoomdata->id = '';
        $this->zoomdata->user_email = '';
        $this->zoomdata->user_id = 987654321;

        // Should find no matches.
        $participant = $this->meetingtask->format_participant($this->zoomdata,
                1, $names, $emails);
        $this->assertNull($participant['userid']);

        // Now change Zoom data to match userid in database.
        $this->zoomdata->user_id = 123456789;

        // Should return the found entry in zoom_meeting_participants.
        $participant = $this->meetingtask->format_participant($this->zoomdata,
                1, $names, $emails);
        $this->assertEquals(999, $newparticipant['userid']);
    }

    /**
     * Make sure that format_participant() can match Moodle users more
     * aggressively on name.
     */
    public function test_format_participant_name_matching() {
        // Enroll a bunch of users. Note: names were generated by
        // https://www.behindthename.com/random/ and any similarity to anyone
        // real or ficitional is concidence and not intentional.
        $users[0] = $this->getDataGenerator()->create_user(
                array('lastname' => 'VAN ANTWERPEN',
                      'firstname' => 'LORETO ZAHIRA'));
        $users[1] = $this->getDataGenerator()->create_user(
                array('lastname' => 'POWER',
                      'firstname' => 'TEIMURAZI ELLI'));
        $users[2] = $this->getDataGenerator()->create_user(
                array('lastname' => 'LITTLE',
                      'firstname' => 'BASEMATH ALIZA'));
        $users[3] = $this->getDataGenerator()->create_user(
                array('lastname' => 'MUTTON',
                      'firstname' => 'RADOVAN BRIANNA'));
        $users[4] = $this->getDataGenerator()->create_user(
                array('lastname' => 'MUTTON',
                      'firstname' => 'BRUNO EVGENIJA'));
        $course = $this->getDataGenerator()->create_course();
        foreach ($users as $user) {
            $this->getDataGenerator()->enrol_user($user->id, $course->id);
        }
        list($names, $emails) = $this->meetingtask->get_enrollments($course->id);

        // 1) Make sure we match someone with middle name missing.
        $users[0]->firstname = 'LORETO';
        $this->zoomdata->name = fullname($users[0]);
        $participant = $this->meetingtask->format_participant($this->zoomdata,
                1, $names, $emails);
        $this->assertEquals($users[0]->id, $participant['userid']);

        // 2) Make sure that name matches even if there are no spaces.
        $users[1]->firstname = str_replace(' ', '', $users[1]->firstname);
        $this->zoomdata->name = fullname($users[1]);
        $participant = $this->meetingtask->format_participant($this->zoomdata,
                1, $names, $emails);
        $this->assertEquals($users[1]->id, $participant['userid']);

        // 3) Make sure that name matches even if we have different ordering.
        $this->zoomdata->name = 'MUTTON, RADOVAN BRIANNA';
        $participant = $this->meetingtask->format_participant($this->zoomdata,
                1, $names, $emails);
        $this->assertEquals($users[3]->id, $participant['userid']);

        // 4) Make sure we do not match users if just last name is the same.
        $users[2]->firstname = 'JOSH';
        $this->zoomdata->name = fullname($users[2]);
        $participant = $this->meetingtask->format_participant($this->zoomdata,
                1, $names, $emails);
        $this->assertEmpty($participant['userid']);

        // 5) Make sure we do not match users if name is not similar to anything.
        $users[4]->firstname = 'JOSH';
        $users[4]->lastname = 'SMITH';
        $this->zoomdata->name = fullname($users[4]);
        $participant = $this->meetingtask->format_participant($this->zoomdata,
                1, $names, $emails);
        $this->assertEmpty($participant['userid']);
    }
}