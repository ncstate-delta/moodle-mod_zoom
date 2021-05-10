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

    /**
     * CSS selectors used throughout the file.
     *
     * @type {object}
     */
    var SELECTORS = {
        REPEAT_SELECT: 'select[name="recurrence_type"]',
        REPEAT_INTERVAL: '.repeat_interval',
        REPEAT_INTERVAL_DAILY: '#interval_daily',
        REPEAT_INTERVAL_WEEKLY: '#interval_weekly',
        REPEAT_INTERVAL_MONTHLY: '#interval_monthly',
        REPEAT_INTERVAL_OPTIONS: 'select[name="repeat_interval"] option',
        START_TIME: 'select[name*="start_time"]',
        DURATION: '*[name*="duration"]',
        RECURRING: 'input[name="recurring"][type!="hidden"]',
        OPTION_JBH: 'input[name="option_jbh"][type!="hidden"]',
        OPTION_WAITING_ROOM: 'input[name="option_waiting_room"][type!="hidden"]'
    };

    /**
     * Repeat interval options.
     *
     * @type {object}
     */
    var REPEAT_OPTIONS = {
        REPEAT_OPTION_NONE: 0,
        REPEAT_OPTION_DAILY: 1,
        REPEAT_OPTION_WEEKLY: 2,
        REPEAT_OPTION_MONTHLY: 3
    };

    /**
     * The max values for each repeat option.
     *
     * @type {object}
     */
    var REPEAT_MAX_OPTIONS = {
        REPEAT_OPTION_DAILY: 90,
        REPEAT_OPTION_WEEKLY: 12,
        REPEAT_OPTION_MONTHLY: 3
    };

    /**
     * The init function.
     */
    var init = function() {
        var optionJoinBeforeHost = $(SELECTORS.OPTION_JBH);
        var optionWaitingRoom = $(SELECTORS.OPTION_WAITING_ROOM);
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

        // First toggle the values based on initial selections.
        toggle_start_time_duration();
        toggle_repeat_interval_text();
        limit_repeat_values();
        // Add listerner to "Repeat Every" drop-down.
        $(SELECTORS.REPEAT_SELECT).change(function() {
            toggle_start_time_duration();
            toggle_repeat_interval_text();
            limit_repeat_values();
        });
        // Add listener for the "Recurring" checkbox
        $(SELECTORS.RECURRING).change(function() {
            toggle_start_time_duration();
        });
    };

    /**
     * Toggle start time and duration elements.
     */
    var toggle_start_time_duration = function () {
        // Disable start time and duration if "No Fixed Time" recurring meeting/webinar selected.
        var disabled = false;
        if ($(SELECTORS.RECURRING).prop('checked') && $(SELECTORS.REPEAT_SELECT).val() == REPEAT_OPTIONS.REPEAT_OPTION_NONE) {
            disabled = true;
        }
        $(SELECTORS.START_TIME).prop('disabled', disabled);
        $(SELECTORS.DURATION).prop('disabled', disabled);
    };

    /**
     * Toggle the text based on repeat type.
     * To show either Days, Weeks or Months
     */
    var toggle_repeat_interval_text = function () {
        $(SELECTORS.REPEAT_INTERVAL).hide();
        var repeat_select = $(SELECTORS.REPEAT_SELECT);
        if (repeat_select.val() == REPEAT_OPTIONS.REPEAT_OPTION_DAILY) {
            $(SELECTORS.REPEAT_INTERVAL_DAILY).show();
        } else if (repeat_select.val() == REPEAT_OPTIONS.REPEAT_OPTION_WEEKLY) {
            $(SELECTORS.REPEAT_INTERVAL_WEEKLY).show();
        } else if (repeat_select.val() == REPEAT_OPTIONS.REPEAT_OPTION_MONTHLY) {
            $(SELECTORS.REPEAT_INTERVAL_MONTHLY).show();
        }
    };

    /**
     * Limit the options shown in the drop-down based on repeat type selected.
     * Max value for daily meeting is 90.
     * Max value for weekly meeting is 12.
     * Max value for monthly meeting is 3.
     */
    var limit_repeat_values = function () {
        var selectedvalue = $(SELECTORS.REPEAT_SELECT).val();
        // Restrict options if weekly or monthly option selected.
        $(SELECTORS.REPEAT_INTERVAL_OPTIONS).each(function() {
            if (selectedvalue == REPEAT_OPTIONS.REPEAT_OPTION_WEEKLY) {
                if (this.value > REPEAT_MAX_OPTIONS.REPEAT_OPTION_WEEKLY) {
                    $(this).hide();
                }
            } else if (selectedvalue == REPEAT_OPTIONS.REPEAT_OPTION_MONTHLY) {
                if (this.value > REPEAT_MAX_OPTIONS.REPEAT_OPTION_MONTHLY) {
                    $(this).hide();
                }
            } else {
                $(this).show();
            }
        });
    };

    return {
        init: init
    };
});
