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
 * @package    mod_zoom_yt
 * @copyright  2018 Nick Stefanski <nmstefanski@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

$addons = [
    "mod_zoom_yt" => [
        "handlers" => [
            'zoommeetingdetails' => [
                'displaydata' => [
                'title' => 'pluginname',
                    'icon' => $CFG->wwwroot . '/mod/zoom_yt/pix/icon.gif',
                    'class' => '',
                ],

                'delegate' => 'CoreCourseModuleDelegate',
                'method' => 'mobile_course_view', // Main function in \mod_zoom_yt\output\mobile.
                'offlinefunctions' => [
                    'mobile_course_view' => [],
                ],
            ],
        ],
        'lang' => [
            ['pluginname', 'zoom_yt'],
            ['join_meeting', 'zoom_yt'],
            ['unavailable', 'zoom_yt'],
            ['meeting_time', 'zoom_yt'],
            ['duration', 'zoom_yt'],
            ['passwordprotected', 'zoom_yt'],
            ['password', 'zoom_yt'],
            ['joinlink', 'zoom_yt'],
            ['joinbeforehost', 'zoom_yt'],
            ['starthostjoins', 'zoom_yt'],
            ['startpartjoins', 'zoom_yt'],
            ['option_audio', 'zoom_yt'],
            ['status', 'zoom_yt'],
            ['recurringmeetinglong', 'zoom_yt'],
        ],
    ],
];
