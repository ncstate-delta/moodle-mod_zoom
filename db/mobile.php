<?php
// This file is part of the Zoom module for Moodle - http://moodle.org/
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
 * Zoom module capability definition
 *
 * @package    mod_zoomyt
 * @copyright  2018 Nick Stefanski <nmstefanski@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$addons = [
    "mod_zoomyt" => [
        "handlers" => [
            'zoommeetingdetails' => [
                'displaydata' => [
                'title' => 'pluginname',
                    'icon' => $CFG->wwwroot . '/mod/zoomyt/pix/icon.gif',
                    'class' => '',
                ],

                'delegate' => 'CoreCourseModuleDelegate',
                'method' => 'mobile_course_view', // Main function in \mod_zoomyt\output\mobile.
                'offlinefunctions' => [
                    'mobile_course_view' => [],
                ],
            ],
        ],
        'lang' => [
            ['pluginname', 'zoomyt'],
            ['join_meeting', 'zoomyt'],
            ['unavailable', 'zoomyt'],
            ['meeting_time', 'zoomyt'],
            ['duration', 'zoomyt'],
            ['passwordprotected', 'zoomyt'],
            ['password', 'zoomyt'],
            ['joinlink', 'zoomyt'],
            ['joinbeforehost', 'zoomyt'],
            ['starthostjoins', 'zoomyt'],
            ['startpartjoins', 'zoomyt'],
            ['option_audio', 'zoomyt'],
            ['status', 'zoomyt'],
            ['recurringmeetinglong', 'zoomyt'],
        ],
    ],
];
