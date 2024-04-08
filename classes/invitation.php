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
 * Represents a Zoom invitation.
 *
 * @package    mod_zoom
 * @author     Andrew Madden <andrewmadden@catalyst-au.net>
 * @copyright  2021 Catalyst IT
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoom;

use coding_exception;
use context_module;
use moodle_exception;

/**
 * Invitation class.
 */
class invitation {
    /**
     * Invitation settings prefix.
     * @var string
     */
    public const PREFIX = 'invitation_';

    /** @var string|null $invitation The unaltered zoom invitation text. */
    private $invitation;

    /** @var array $configregex Array of regex patterns defined in plugin settings. */
    private $configregex;

    /**
     * invitation constructor.
     *
     * @param string|null $invitation Zoom invitation returned from
     * https://marketplace.zoom.us/docs/api-reference/zoom-api/meetings/meetinginvitation.
     */
    public function __construct($invitation) {
        $this->invitation = $invitation;
    }

    /**
     * Get the display string to show on the module page.
     *
     * @param int $coursemoduleid Course module where the user will view the invitation.
     * @param int|null $userid Optionally supply the intended user to view the string. Defaults to global $USER.
     * @return ?string
     */
    public function get_display_string(int $coursemoduleid, ?int $userid = null) {
        if (empty($this->invitation)) {
            return null;
        }

        // If regex patterns are disabled, return the raw zoom meeting invitation.
        if (!get_config('zoom', 'invitationregexenabled')) {
            return $this->invitation;
        }

        $displaystring = $this->invitation;
        try {
            // If setting enabled, strip the invite message.
            if (get_config('zoom', 'invitationremoveinvite')) {
                $displaystring = $this->remove_element($displaystring, 'invite');
            }

            // If setting enabled, strip the iCal link.
            if (get_config('zoom', 'invitationremoveicallink')) {
                $displaystring = $this->remove_element($displaystring, 'icallink');
            }

            // Check user capabilities, and remove parts of the invitation they don't have permission to view.
            if (!has_capability('mod/zoom:viewjoinurl', context_module::instance($coursemoduleid), $userid)) {
                $displaystring = $this->remove_element($displaystring, 'joinurl');
            }

            if (!has_capability('mod/zoom:viewdialin', context_module::instance($coursemoduleid), $userid)) {
                $displaystring = $this->remove_element($displaystring, 'onetapmobile');
                $displaystring = $this->remove_element($displaystring, 'dialin');
                $displaystring = $this->remove_element($displaystring, 'sip');
                $displaystring = $this->remove_element($displaystring, 'h323');
            } else {
                // Fix the formatting of the onetapmobile section if it exists.
                $displaystring = $this->add_paragraph_break_above_element($displaystring, 'onetapmobile');
            }
        } catch (moodle_exception $e) {
            // If the regex parsing fails, log a debugging message and return the whole invitation.
            debugging($e->getMessage(), DEBUG_DEVELOPER);
            return $this->invitation;
        }

        $displaystring = trim($this->clean_paragraphs($displaystring));
        return $displaystring;
    }

    /**
     * Remove instances of a zoom invitation element using a regex pattern.
     *
     * @param string $invitation
     * @param string $element
     * @return string
     *
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     */
    private function remove_element(string $invitation, string $element): string {
        global $PAGE;

        $configregex = $this->get_config_invitation_regex();
        if (!array_key_exists($element, $configregex)) {
            throw new coding_exception('Cannot remove element: ' . $element
                    . '. See mod/zoom/classes/invitation.php:get_default_invitation_regex for valid elements.');
        }

        // If the element pattern is intentionally empty, return the invitation string unaltered.
        if (empty($configregex[$element])) {
            return $invitation;
        }

        $count = 0;
        $invitation = @preg_replace($configregex[$element], "", $invitation, -1, $count);

        // If invitation is null, an error occurred in preg_replace.
        if ($invitation === null) {
            throw new moodle_exception(
                'invitationmodificationfailed',
                'mod_zoom',
                $PAGE->url,
                ['element' => $element, 'pattern' => $configregex[$element]]
            );
        }

        // Add debugging message to assist site administrator in testing regex patterns if no match is found.
        if (empty($count)) {
            debugging(
                get_string(
                    'invitationmatchnotfound',
                    'mod_zoom',
                    ['element' => $element, 'pattern' => $configregex[$element]]
                ),
                DEBUG_DEVELOPER
            );
        }

        return $invitation;
    }

    /**
     * Add a paragraph break above an element defined by a regex pattern in a zoom invitation.
     *
     * @param string $invitation
     * @param string $element
     * @return string
     *
     * @throws \coding_exception
     * @throws \dml_exception
     */
    private function add_paragraph_break_above_element(string $invitation, string $element): string {
        $matches = [];
        $configregex = $this->get_config_invitation_regex();
        // If no pattern found for element, return the invitation string unaltered.
        if (empty($configregex[$element])) {
            return $invitation;
        }

        $result = preg_match($configregex[$element], $invitation, $matches, PREG_OFFSET_CAPTURE);
        // If error occurred in preg_match, show debugging message to help site administrator.
        if ($result === false) {
            debugging(
                get_string(
                    'invitationmodificationfailed',
                    'mod_zoom',
                    ['element' => $element, 'pattern' => $configregex[$element]]
                ),
                DEBUG_DEVELOPER
            );
        }

        // No match found, so return invitation string unaltered.
        if (empty($matches)) {
            return $invitation;
        }

        // Get the position of the element in the full invitation string.
        $pos = $matches[0][1];
        // Inject a paragraph break above element. Use $this->clean_paragraphs() to fix uneven breaks between paragraphs.
        return substr_replace($invitation, "\r\n\r\n", $pos, 0);
    }

    /**
     * Ensure that paragraphs in string have correct spacing.
     *
     * @param string $invitation
     * @return string
     */
    private function clean_paragraphs(string $invitation): string {
        // Replace partial paragraph breaks with exactly two line breaks.
        $invitation = preg_replace("/\r\n\n/m", "\r\n\r\n", $invitation);
        // Replace breaks of more than two line breaks with exactly two.
        $invitation = preg_replace("/\r\n\r\n[\r\n]+/m", "\r\n\r\n", $invitation);
        return $invitation;
    }

    /**
     * Get regex patterns from site config to find the different zoom invitation elements.
     *
     * @return array
     * @throws \dml_exception
     */
    private function get_config_invitation_regex(): array {
        if ($this->configregex !== null) {
            return $this->configregex;
        }

        $config = get_config('zoom');
        $this->configregex = [];
        // Get the regex defined in the plugin settings for each element.
        foreach (self::get_default_invitation_regex() as $element => $pattern) {
            $settingname = self::PREFIX . $element;
            $this->configregex[$element] = $config->$settingname;
        }

        return $this->configregex;
    }

    /**
     * Get default regex patterns to find the different zoom invitation elements.
     *
     * @return string[]
     */
    public static function get_default_invitation_regex(): array {
        return [
            'invite' => '/^.+is inviting you to a scheduled zoom meeting.+$/mi',
            'joinurl' => '/^join zoom meeting.*(\n.*)+?(\nmeeting id.+\npasscode.+)$/mi',
            'onetapmobile' => '/^one tap mobile.*(\n\s*\+.+)+$/mi',
            'dialin' => '/^dial by your location.*(\n\s*\+.+)+(\n.*)+find your local number.+$/mi',
            'sip' => '/^join by sip.*\n.+$/mi',
            'h323' => '/^join by h\.323.*(\n.*)+?(\nmeeting id.+\npasscode.+)$/mi',
            'icallink' => '/^.+download and import the following iCalendar.+$\n.+$/mi',
        ];
    }
}
