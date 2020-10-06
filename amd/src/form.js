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
 * Populates or de-populates password field based on whether the
 * password is required or not.
 *
 * @package    mod_zoom
 * @copyright  2018 UC Regents
 * @author     Kubilay Agi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {
    return {
        init: function() {
//            var pwd = $('input[name="meetingcode"]');
//            var reqpwd = $('input[name="requirepassword"][type!="hidden"]');
            var option_jbh = $('input[name="option_jbh"][type!="hidden"]');
            var option_waiting_room = $('input[name="option_waiting_room"][type!="hidden"]');
//            $(document).ready(function() {
//                if (!reqpwd.is(':checked')) {
//                    pwd.val('');
//                }
//            });
//            reqpwd.change(function() {
//                if (pwd.attr('disabled') == 'disabled') {
//                    pwd.val('');
//                } else {
//                    // Set value to be a new random 6 digit number
//                    pwd.val(Math.floor(Math.random() * (999999 - 100000) + 100000).toString());
//                }
//            });
            option_jbh.change(function() {
                if (option_jbh.is(':checked') == true) {
                    option_waiting_room.prop('checked', false);
                }
            });
            option_waiting_room.change(function() {
                if (option_waiting_room.is(':checked') == true) {
                    option_jbh.prop('checked', false);
                }
            });
        }
    };
});
