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

require_once($CFG->dirroot.'/mod/zoom/locallib.php');

/**
 * PHPunit testcase class.
 *
 * @copyright  2019 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class webservice_test extends advanced_testcase {

    private $notfoundmockcurl = null;
    private $invalidmockcurl = null;
    private $retrymockcurl = null;

    /**
     * Setup before every test.
     */
    public function setUp() {
        $this->resetAfterTest();
        // Set fake values so we can test methods in class.
        set_config('apikey', 'test', 'mod_zoom');
        set_config('apisecret', 'test', 'mod_zoom');

        $this->notfoundmockcurl = new class {
            public function setHeader($unusedparam) {
                return;
            }
            public function get_errno() {
                return false;
            }
            public function get_info() {
                return array('http_code' => 404);
            }
        };

        $this->invalidmockcurl = new class {
            public function setHeader($unusedparam) {
                return;
            }
            public function get_errno() {
                return false;
            }
            public function get_info() {
                return array('http_code' => 400);
            }
        };

        $this->retrywithheadermockcurl = new class {
            public $numgetinfocalls = 0;
            public function setHeader($unusedparam) {
                return;
            }
            public function get_errno() {
                return false;
            }
            public function get_info() {
                $this->numgetinfocalls++;
                if ($this->numgetinfocalls <= 6) {
                    return array('http_code' => 429, 'Retry-After' => gmdate('Y-m-d\TH:i:s\Z', time() + 3));
                }
                return array('http_code' => 200);
            }
        };

        $this->retrynoheadermockcurl = new class {
            public $numgetinfocalls = 0;
            public function setHeader($unusedparam) {
                return;
            }
            public function get_errno() {
                return false;
            }
            public function get_info() {
                $this->numgetinfocalls++;
                if ($this->numgetinfocalls <= 6) {
                    return array('http_code' => 429);
                }
                return array('http_code' => 200);
            }
        };

        $this->retryfailuremockcurl = new class {
            public function setHeader($unusedparam) {
                return;
            }
            public function get_errno() {
                return false;
            }
            public function get_info() {
                return array('http_code' => 429);
            }
        };
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

    /**
     * Tests whether the meeting not found errors are properly parsed.
     */
    public function test_meeting_not_found_exception() {
        $mockservice = $this->getMockBuilder('\mod_zoom_webservice')
                            ->setMethods(array('_make_curl_call', '_get_curl_object'))
                            ->getMock();

        $mockservice->expects($this->any())
                    ->method('_make_curl_call')
                    ->willReturn('{"code":3001,"message":"réunion introuvable"}');

        $mockservice->expects($this->any())
                    ->method('_get_curl_object')
                    ->willReturn($this->notfoundmockcurl);

        $foundexception = false;
        try {
            $response = $mockservice->get_meeting_webinar_info('-1', false);
        } catch (moodle_exception $error) {
            $this->assertEquals(3001, $error->zoomerrorcode);
            $this->assertTrue(zoom_is_meeting_gone_error($error));
            $foundexception = true;
        }
        $this->assertTrue($foundexception);
    }

    /**
     * Tests whether user not found errors are properly parsed.
     */
    public function test_user_not_found_exception() {
        $mockservice = $this->getMockBuilder('\mod_zoom_webservice')
                            ->setMethods(array('_make_curl_call', '_get_curl_object'))
                            ->getMock();

        $mockservice->expects($this->any())
                    ->method('_make_curl_call')
                    ->willReturn('{"code":1001,"message":"n’existe pas ou n’appartient pas à ce compte"}');

        $mockservice->expects($this->any())
                    ->method('_get_curl_object')
                    ->willReturn($this->notfoundmockcurl);

        $foundexception = false;
        try {
            $founduser = $mockservice->get_user('-1');
        } catch (moodle_exception $error) {
            $this->assertEquals(1001, $error->zoomerrorcode);
            $this->assertTrue(zoom_is_meeting_gone_error($error));
            $this->assertTrue(zoom_is_user_not_found_error($error));
            $foundexception = true;
        }
        $this->assertTrue($foundexception || !$founduser);
    }

    /**
     * Tests whether invalid user errors are parsed properly
     */
    public function test_invalid_user_exception() {
        $mockservice = $this->getMockBuilder('\mod_zoom_webservice')
                            ->setMethods(array('_make_curl_call', '_get_curl_object'))
                            ->getMock();

        $mockservice->expects($this->any())
                    ->method('_make_curl_call')
                    ->willReturn('{"code":1120,"message":"utilisateur invalide"}');

        $mockservice->expects($this->any())
                    ->method('_get_curl_object')
                    ->willReturn($this->invalidmockcurl);

        $foundexception = false;
        try {
            $founduser = $mockservice->get_user('-1');
        } catch (moodle_exception $error) {
            $this->assertEquals(1120, $error->zoomerrorcode);
            $this->assertTrue(zoom_is_meeting_gone_error($error));
            $this->assertTrue(zoom_is_user_not_found_error($error));
            $foundexception = true;
        }
        $this->assertTrue($foundexception || !$founduser);
    }

    /**
     * Tests whether the retry on a 429 works properly when the Retry-After header
     * is in the curl response to specify the time that the retry should be sent.
     */
    public function test_retry_with_header() {
        $mockservice = $this->getMockBuilder('\mod_zoom_webservice')
                            ->setMethods(array('_make_curl_call', '_get_curl_object'))
                            ->getMock();

        $mockservice->expects($this->any())
                    ->method('_make_curl_call')
                    ->willReturn('{"response":"success"}');

        $mockservice->expects($this->any())
                    ->method('_get_curl_object')
                    ->willReturn($this->retrywithheadermockcurl);

        $result = $mockservice->get_user("1");
        $this->assertDebuggingCalledCount($expectedcount = 3);
        $this->assertEquals($this->retrywithheadermockcurl->numgetinfocalls, 7);
        $this->assertEquals($result->response, 'success');
    }

    /**
     * Tests whether the retry on a 429 response works when the Retry-After
     * header is not sent in the curl response.
     */
    public function test_retry_without_header() {
        $mockservice = $this->getMockBuilder('\mod_zoom_webservice')
                            ->setMethods(array('_make_curl_call', '_get_curl_object'))
                            ->getMock();

        $mockservice->expects($this->any())
                    ->method('_make_curl_call')
                    ->willReturn('{"response":"success"}');

        $mockservice->expects($this->any())
                    ->method('_get_curl_object')
                    ->willReturn($this->retrynoheadermockcurl);

        $result = $mockservice->get_user("1");
        $this->assertDebuggingCalledCount($expectedcount = 3);
        $this->assertEquals($this->retrynoheadermockcurl->numgetinfocalls, 7);
        $this->assertEquals($result->response, 'success');
    }

    /**
     * Tests whether the retry on a 429 response works when the Retry-After
     * header is not sent in the curl response.
     */
    public function test_retry_exception() {
        $mockservice = $this->getMockBuilder('\mod_zoom_webservice')
                            ->setMethods(array('_make_curl_call', '_get_curl_object'))
                            ->getMock();

        $mockservice->expects($this->any())
                    ->method('_make_curl_call')
                    ->willReturn('{"code":-1, "message":"too many retries"}');

        $mockservice->expects($this->any())
                    ->method('_get_curl_object')
                    ->willReturn($this->retryfailuremockcurl);

        $foundexception = false;
        try {
            $result = $mockservice->get_user("1");
        } catch (moodle_exception $error) {
            $foundexception = true;
            $this->assertEquals($error->response, 'too many retries');
        }
        $this->assertDebuggingCalledCount($expectedcount = 20);
        $this->assertTrue($foundexception);
    }
}
