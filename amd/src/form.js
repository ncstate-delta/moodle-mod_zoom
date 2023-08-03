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
 * @copyright  2018 UC Regents
 * @author     Kubilay Agi
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/form-autocomplete', 'core/str', 'core/notification'], function($, autocomplete, str, notification) {

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
        toggleStartTimeDuration();
        toggleRepeatIntervalText();
        limitRepeatValues();
        // Add listerner to "Repeat Every" drop-down.
        $(SELECTORS.REPEAT_SELECT).change(function() {
            toggleStartTimeDuration();
            toggleRepeatIntervalText();
            limitRepeatValues();
        });
        // Add listener for the "Recurring" checkbox
        $(SELECTORS.RECURRING).change(function() {
            toggleStartTimeDuration();
        });

        var breakoutroomsEditor = new BreakoutroomsEditor();
        breakoutroomsEditor.init();
    };

    /**
     * Toggle start time and duration elements.
     */
    var toggleStartTimeDuration = function() {
        // Disable start time and duration if "No Fixed Time" recurring meeting/webinar selected.
        var disabled = false;
        var repeatVal = parseInt($(SELECTORS.REPEAT_SELECT).val(), 10);
        if ($(SELECTORS.RECURRING).prop('checked') && repeatVal === REPEAT_OPTIONS.REPEAT_OPTION_NONE) {
            disabled = true;
        }
        $(SELECTORS.START_TIME).prop('disabled', disabled);
        $(SELECTORS.DURATION).prop('disabled', disabled);
    };

    /**
     * Toggle the text based on repeat type.
     * To show either Days, Weeks or Months
     */
    var toggleRepeatIntervalText = function() {
        $(SELECTORS.REPEAT_INTERVAL).hide();
        var repeatSelectVal = parseInt($(SELECTORS.REPEAT_SELECT).val(), 10);
        if (repeatSelectVal === REPEAT_OPTIONS.REPEAT_OPTION_DAILY) {
            $(SELECTORS.REPEAT_INTERVAL_DAILY).show();
        } else if (repeatSelectVal === REPEAT_OPTIONS.REPEAT_OPTION_WEEKLY) {
            $(SELECTORS.REPEAT_INTERVAL_WEEKLY).show();
        } else if (repeatSelectVal === REPEAT_OPTIONS.REPEAT_OPTION_MONTHLY) {
            $(SELECTORS.REPEAT_INTERVAL_MONTHLY).show();
        }
    };

    /**
     * Limit the options shown in the drop-down based on repeat type selected.
     * Max value for daily meeting is 90.
     * Max value for weekly meeting is 12.
     * Max value for monthly meeting is 3.
     */
    var limitRepeatValues = function() {
        var selectedValue = parseInt($(SELECTORS.REPEAT_SELECT).val(), 10);
        // Restrict options if weekly or monthly option selected.
        $(SELECTORS.REPEAT_INTERVAL_OPTIONS).each(function() {
            if (selectedValue === REPEAT_OPTIONS.REPEAT_OPTION_WEEKLY) {
                if (this.value > REPEAT_MAX_OPTIONS.REPEAT_OPTION_WEEKLY) {
                    $(this).hide();
                }
            } else if (selectedValue === REPEAT_OPTIONS.REPEAT_OPTION_MONTHLY) {
                if (this.value > REPEAT_MAX_OPTIONS.REPEAT_OPTION_MONTHLY) {
                    $(this).hide();
                }
            } else {
                $(this).show();
            }
        });
    };

    /**
     * Tabs component.
     * @param {object} tabsColumn
     * @param {object} tabsContentColumn
     * @param {int}    initialTabsCount
     * @param {object} emptyAlert
     */
    var TabsComponent = function(tabsColumn, tabsContentColumn, initialTabsCount, emptyAlert) {
        this.tabsColumn = tabsColumn;
        this.tabsContentColumn = tabsContentColumn;
        this.emptyAlert = emptyAlert;
        this.countTabs = initialTabsCount;

        /**
         * Build tab
         * @param {object} item
         * @returns {object} tab element
         */
        this.buildTab = function(item) {
            var tab = item.tab.element;
            var tabLink = $(".nav-link", tab);

            // Setting tab id.
            tab.attr('id', 'tab-' + this.countTabs);

            // Setting tab name.
            $(".tab-name", tabLink).text(item.tab.name);

            // Setting tab href.
            tabLink.attr('href', '#link' + this.countTabs);

            // Activating tab
            $("li a", this.tabsColumn).removeClass('active');
            tabLink.addClass('active');

            return tab;
        };

        /**
         * Build tab content.
         * @param {object} item
         * @returns {object} content of tab element
         */
        this.buildTabContent = function(item) {
            var tabContent = item.tabContent.element;

            // Setting tabContent id.
            tabContent.attr('id', 'link' + this.countTabs);

            // Activating tabContent.
            $(".tab-pane", this.tabsContentColumn).removeClass('active');
            tabContent.addClass('active');

            return tabContent;
        };


        /**
         * Add tab
         * @param {object} item
         * @returns {object} tab element
         */
        this.addTab = function(item) {
            var tab = this.buildTab(item);
            var tabContent = this.buildTabContent(item);

            this.emptyAlert.addClass('hidden');
            $("ul", this.tabsColumn).append(tab);
            $(".tab-content", this.tabsContentColumn).append(tabContent);

            return {"element": tab, "content": tabContent};
        };

        /**
         * Delete tab
         * @param {object} item
         */
        this.deleteTab = function(item) {
            var tab = item;
            var tabContent = $($('a', tab).attr('href'));

            tab.remove();
            tabContent.remove();

            var countTabs = $("li", this.tabsColumn).length;
            if (!countTabs) {
                this.emptyAlert.removeClass('hidden');
            } else {
                var countActiveTabs = $("li a.active", this.tabsColumn).length;
                if (!countActiveTabs) {
                    $("li:first-child a", this.tabsColumn).trigger('click');
                }
            }
        };
    };

    /**
     * Breakout rooms editor.
     */
    var BreakoutroomsEditor = function() {
        this.roomsListColumn = $("#meeting-rooms-list");
        this.roomsList = $("ul", this.roomsListColumn);
        this.addBtn = $("#add-room", this.roomsListColumn);
        this.emptyAlert = $(".empty-alert", this.roomsListColumn);
        this.deleteBtn = $(".delete-room", this.roomsListColumn);
        this.roomsDataColumn = $("#meeting-rooms-data");
        this.roomItemToClone = $('#rooms-list-item').html();
        this.roomItemDataToClone = $('#rooms-list-item-data').html();
        this.initialRoomsCount = parseInt(this.roomsListColumn.attr('data-initial-rooms-count'));
        this.tabsComponent = new TabsComponent(this.roomsListColumn, this.roomsDataColumn, this.initialRoomsCount, this.emptyAlert);

        // Add room event.
        this.init = function() {
            var stringkeys = [{key: 'room', component: 'zoom'}];
            str.get_strings(stringkeys).then(function() {
                return null;
            }).fail(notification.exception);

            this.addRoomEvent();
            this.deleteRoomEvent();
            var countRooms = $("li", this.roomsListColumn).length;
            if (countRooms) {
                this.changeRoomNameEvent();
                this.buildAutocompleteComponents();
            } else {
                this.emptyAlert.removeClass('hidden');
            }
        };
        // Add room event.
        this.addRoomEvent = function() {
            var thisObject = this;

            // Adding addroom button click event.
            thisObject.addBtn.click(function() {
                thisObject.tabsComponent.countTabs++;

                var newRoomName = M.util.get_string('room', 'zoom') + ' ' + thisObject.tabsComponent.countTabs;
                var newRoomElement = $(thisObject.roomItemToClone);
                var newRoomDataElement = $(thisObject.roomItemDataToClone);
                var newRoomIndex = thisObject.tabsComponent.countTabs;

                // Setting new room name.
                var roomNameInputId = 'room-name-' + newRoomIndex;
                $("input[type=text]", newRoomDataElement).prev().attr('for', roomNameInputId);
                $("input[type=text]", newRoomDataElement).attr('id', roomNameInputId);
                $("input[type=text]", newRoomDataElement).attr('name', roomNameInputId);
                $("input[type=text]", newRoomDataElement).val(newRoomName);
                $("input[type=text]", newRoomDataElement).next().attr('name', 'rooms[' + newRoomIndex + ']');
                $("input[type=text]", newRoomDataElement).next().val(newRoomName);

                // Setting new room participants select id/name.
                var roomParticipantsSelectId = 'participants-' + newRoomIndex;
                $(".room-participants", newRoomDataElement).attr('id', roomParticipantsSelectId);
                $(".room-participants", newRoomDataElement).attr('name', 'roomsparticipants[' + newRoomIndex + '][]');

                // Setting new room participant groups select id/name.
                var roomGroupsSelectId = 'groups-' + newRoomIndex;
                $(".room-groups", newRoomDataElement).attr('id', roomGroupsSelectId);
                $(".room-groups", newRoomDataElement).attr('name', 'roomsgroups[' + newRoomIndex + '][]');

                // Add new room tab
                var newRoom = {"tab": {"name": newRoomName, "element": newRoomElement},
                    "tabContent": {"element": newRoomDataElement}};

                var addedTab = thisObject.tabsComponent.addTab(newRoom);

                // Adding new room tab delete button click event.
                $("li:last .delete-room", thisObject.roomsList).click(function() {
                    var thisItem = $(this).closest('li');
                    thisObject.tabsComponent.deleteTab(thisItem);
                });

                // Adding new room change name event.
                $("input[type=text]", addedTab.content).on("change keyup paste", function() {
                    var newHiddenValue = this.value;
                    $(this).next().val(newHiddenValue);

                    $(".tab-name", addedTab.element).text(this.value);
                });

                // Convert select dropdowns to autocomplete component.
                thisObject.buildAutocompleteComponent(roomParticipantsSelectId, 'addparticipant');
                thisObject.buildAutocompleteComponent(roomGroupsSelectId, 'addparticipantgroup');
            });
        };

        // Delete room event.
        this.deleteRoomEvent = function() {
            var thisObject = this;

            // Adding deleteroom button click event.
            thisObject.deleteBtn.click(function() {
                var thisItem = $(this).closest('li');
                thisObject.tabsComponent.deleteTab(thisItem);
            });
        };

        // Change room name event.
        this.changeRoomNameEvent = function() {
            var thisObject = this;

            $("li", this.roomsListColumn).each(function() {
                var tabIdArr = $(this).attr('id').split('-');
                var tabIndex = tabIdArr[1];
                $('input[name="room-name-' + tabIndex + '"]', thisObject.roomsDataColumn).on("change keyup paste", function() {
                    var newHiddenValue = this.value;
                    $(this).next().val(newHiddenValue);

                    $("#tab-" + tabIndex + " .tab-name").text(this.value);
                });
            });
        };

        /**
         * Build autocomplete components.
         */
        this.buildAutocompleteComponents = function() {
            var thisObject = this;
            $(".room-participants", thisObject.roomsDataColumn).each(function() {
                var thisItemId = $(this).attr('id');
                thisObject.buildAutocompleteComponent(thisItemId, 'addparticipant');
            });

            $(".room-groups", thisObject.roomsDataColumn).each(function() {
                var thisItemId = $(this).attr('id');
                thisObject.buildAutocompleteComponent(thisItemId, 'addparticipantgroup');
            });
        };

        /**
         * Build autocomplete component.
         * @param {string} id
         * @param {string} placeholder
         */
        this.buildAutocompleteComponent = function(id, placeholder) {
            var stringkeys = [{key: placeholder, component: 'zoom'}, {key: 'selectionarea', component: 'zoom'}];
            str.get_strings(stringkeys).then(function(langstrings) {
                var placeholderString = langstrings[0];
                var noSelectionString = langstrings[1];

                autocomplete.enhance('#' + id, false, '', placeholderString, false, true, noSelectionString, true);
                return null;
            }).fail(notification.exception);
        };
    };

    return {
        init: init
    };
});
