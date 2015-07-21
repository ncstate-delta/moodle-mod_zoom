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
 * Web service related unit tests
 *
 * @package    mod_zoom
 * @copyright  2015 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

/**
 * PHPunit testcase class.
 *
 * @copyright  2015 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class createuser_test extends advanced_testcase {

    /**
     * Stores mocked version of mod_zoom_webservice.
     * @var mod_zoom_webservice
     */
    private $mockwebservice = null;

    /**
     * Specifies what to make mocked make_call() method return.
     * @var mixed   If exception, it will throw it instead.
     */
    private $makecallreturn = true;

    /**
     * Randomly created user object.
     * @var object
     */
    private $user = null;

    /**
     * Mocks the web service call so that it doesn't actually contact Zoom.
     * @param string $url
     * @param array $data
     * @return array
     */
    public function mocked_make_call($url, $data) {
        if ($this->makecallreturn instanceof moodle_exception) {
            throw $this->makecallreturn;
        }

        return $this->makecallreturn;
    }

    /**
     * Setup webservice mocking.
     */
    public function setUp() {
        $this->resetAfterTest(true);

        set_config('apiurl', 'https://api.zoom.us/v1/', 'mod_zoom');
        set_config('apikey', uniqid(), 'mod_zoom');
        set_config('apisecret', uniqid(), 'mod_zoom');

        $this->mockwebservice = $this->getMockBuilder('mod_zoom_webservice')->setMethods(array('make_call'))->getMock();
        $this->mockwebservice->expects($this->any())->method('make_call')->will(
            $this->returnCallback(array($this, 'mocked_make_call')));

        $this->user = $this->getDataGenerator()->create_user();
    }

    /**
     * Make sure that we handle errors appropriately.
     */
    public function test_user_autocreate_false() {
        // Force webservice to return failure.
        $this->makecallreturn = new moodle_exception('errorwebservice', 'mod_zoom', '', 'Missing password');

        $result = $this->mockwebservice->user_autocreate($this->user);
        $this->assertFalse($result);

        // Force curl error.
        $this->makecallreturn = new moodle_exception('errorwebservice', 'mod_zoom', '', 'Couldn\'t resolve host');
        $result = $this->mockwebservice->user_autocreate($this->user);
        $this->assertFalse($result);
    }

    /**
     * Make sure that we can handle the case if a user exists.
     */
    public function test_user_autocreate_existing() {
        // Force webservice to return existing user error.
        $this->makecallreturn = new moodle_exception('errorwebservice',
                'mod_zoom', '', 'User already in the account: ' .
                $this->user->email);

        $result = $this->mockwebservice->user_autocreate($this->user);
        $this->assertTrue($result);
    }
}