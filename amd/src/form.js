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
            var optionJoinBeforeHost = $('input[name="option_jbh"][type!="hidden"]');
            var optionWaitingRoom = $('input[name="option_waiting_room"][type!="hidden"]');
            optionJoinBeforeHost.change(function() {
                if (optionJoinBeforeHost.is(':checked') === true) {
                    optionWaitingRoom.prop('checked', false);
                }
            });
            optionWaitingRoom.change(function() {
                if (optionWaitingRoom.is(':checked') === true) {
                    optionJoinBeforeHost.prop('checked', false);
                }
            });

            var repeat_select = $('select[name="recurrence_type"]');
            var start_time = $('select[name*="start_time"]');
            var duration = $('*[name*="duration"]');
            var recurring = $('input[name="recurring"][type!="hidden"]');

            if (recurring.prop('checked')) {
                start_time.prop('disabled', (repeat_select.val() == 0));
                duration.prop('disabled', (repeat_select.val() == 0));
            }

            recurring.change(function() {
                var disabled = false;
                if (recurring.prop('checked') && repeat_select.val() == 0) {
                    disabled = true;
                }
                start_time.prop('disabled', disabled);
                duration.prop('disabled', disabled);
            });

            $('.repeat_interval').hide();
            if (repeat_select.val() > 0) {
                start_time.prop('disabled', (repeat_select.val() == 0));
                duration.prop('disabled', (repeat_select.val() == 0));
                if (repeat_select.val() == 1) {
                    $('#interval_daily').show();
                } else if (repeat_select.val() == 2) {
                    $('#interval_weekly').show();
                } else if (repeat_select.val() == 3) {
                    $('#interval_monthly').show();
                }
            }
            repeat_select.change(function() {
                start_time.prop('disabled', (this.value == 0));
                duration.prop('disabled', (this.value == 0));
                $('.repeat_interval').hide();
                if (this.value == 1) {
                    $('#interval_daily').show();
                } else if (this.value == 2) {
                    $('#interval_weekly').show();
                } else if (this.value == 3) {
                    $('#interval_monthly').show();
                }
            });
        }
    };
});
