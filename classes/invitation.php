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

defined('MOODLE_INTERNAL') || die();

class invitation {

    const PREFIX = 'invitation_';

    /** @var string $invitation The unaltered zoom invitation text. */
    private $invitation;

    /** @var array $configregex Array of regex patterns defined in plugin settings. */
    private $configregex;

    /**
     * invitation constructor.
     *
     * @param string $invitation Zoom invitation returned from
     * https://marketplace.zoom.us/docs/api-reference/zoom-api/meetings/meetinginvitation.
     */
    public function __construct(string $invitation) {
        $this->invitation = $invitation;
    }

    /**
     * Get the display string to show on the module page.
     *
     * @param int $courseid Course where the user will view the invitation.
     * @param int|null $userid Optionally supply the intended user to view the string. Defaults to global $USER.
     * @return string
     */
    public function get_display_string(int $courseid, int $userid = null): string {
        $displaystring = $this->invitation;
        try {
            // If setting enabled, strip the invite message.
            if (get_config('zoom', 'invitationremoveinvite')) {
                $displaystring = $this->remove_element($displaystring, 'invite');
            }
            // Check user capabilities, and remove parts of the invitation they don't have permission to view.
            if (!has_capability('mod/zoom:viewjoinurl', \context_course::instance($courseid), $userid)) {
                $displaystring = $this->remove_element($displaystring, 'joinurl');
            }
            if (!has_capability('mod/zoom:viewdialin', \context_course::instance($courseid), $userid)) {
                $displaystring = $this->remove_element($displaystring, 'onetapmobile');
                $displaystring = $this->remove_element($displaystring, 'dialin');
            }
        } catch (\moodle_exception $e) {
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
        if (!in_array($element, array_keys($configregex))) {
            throw new \coding_exception('Cannot remove element: ' . $element
                    . '. See mod/zoom/classes/invitation.php:get_default_invitation_regex for valid elements.');
        }
        $count = 0;
        $invitation = @preg_replace($configregex[$element], "", $invitation, -1, $count);
        // If invitation is null, an error occurred in preg_replace.
        if ($invitation === null) {
            throw new \moodle_exception('invitationmodificationfailed', 'mod_zoom', $PAGE->url,
                    ['element' => $element, 'pattern' => $configregex[$element]]);
        }
        // Add debugging message to assist site administrator in testing regex patterns if no match is found.
        if (empty($count)) {
            debugging(get_string('invitationmatchnotfound', 'mod_zoom',
                    ['element' => $element, 'pattern' => $configregex[$element]]),
                    DEBUG_DEVELOPER);
        }
        return $invitation;
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
            'joinurl' => '/(^join zoom meeting.*|^join directly.*)(\n.*)+?(\nmeeting id.+\npasscode.+)$/mi',
            'onetapmobile' => '/(^join through dial\-in.*|(?<!join through dial\-in))\none tap mobile.*(\n\s*\+.+)+$/mi',
            'dialin' => '/^dial by your location.*(\n\s*\+.+)+(\n.*)+find your local number.+$/mi',
        ];
    }
}
