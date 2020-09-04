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
 * This file contains the forms to add zoom meeting recordings.
 *
 * @package   mod_zoom
 * @author    Nick Stefanski <nmstefanski@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/formslib.php');
require_once($CFG->dirroot.'/mod/url/locallib.php'); // needed for url check

/**
 * Class for displaying the form.
 *
 * @package    mod_zoom
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_zoom_recording_form extends moodleform {

    /**
     * Defines forms elements
     *
     * @return void
     */
    public function definition() {
        global $DB;
        // Start of form definition.
        $mform = $this->_form;

        $recordingid = $this->_customdata['recordingid'];
        if ($recordingid) {
            $rec = $DB->get_record('zoom_meeting_recordings', array('id' => $recordingid), '*', MUST_EXIST);
            $data = array(
                'name' => $rec->name,
                'externalurl' => $rec->externalurl,
            );
        } else {
            $data = array('name' => get_string('recording', 'zoom'));
        }

        $mform->addElement('header', 'general', get_string('recordingadd', 'zoom'));

        $mform->addElement('text', 'name', get_string('recordingname', 'zoom'), array('size' => '64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 310), 'maxlength', 310, 'client');

        $mform->addElement('url', 'externalurl', get_string('recordingurl', 'zoom'), 
                           array('size'=>'60'), array('usefilepicker'=>true));
        $mform->setType('externalurl', PARAM_RAW_TRIMMED);
        $mform->addRule('externalurl', null, 'required', null, 'client');

        $mform->setDefaults($data);
        $this->add_action_buttons(true);
    }

    /**
     * Perform validation of form data
     * @param array $data
     * @param array $files
     * @return array
     */
    public function validation($data, $files) {
        // Basic url validation based on url activity, checks for obvious errors only.
        if (!empty($data['externalurl'])) {
            $url = $data['externalurl'];
            if (preg_match('|^/|', $url)) {
                // links relative to server root are ok - no validation necessary

            } else if (preg_match('|^[a-z]+://|i', $url) or preg_match('|^https?:|i', $url) or preg_match('|^ftp:|i', $url)) {
                // normal URL
                if (!url_appears_valid_url($url)) {
                    $errors['externalurl'] = get_string('recordinginvalidurl', 'zoom');//lang
                }

            } else {
                // invalid URI, we try to fix it by adding 'http://' prefix,
                // relative links are NOT allowed because we display the link on different pages!
                if (!url_appears_valid_url('http://'.$url)) {
                    $errors['externalurl'] = get_string('recordinginvalidurl', 'zoom');
                }
            }
        }
        return $errors;
    }
}
