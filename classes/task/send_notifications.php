<?php

namespace mod_zoom\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/zoom/lib.php');
require_once($CFG->dirroot.'/mod/zoom/locallib.php');

/**
 * Scheduled task to sychronize meeting data.
 *
 * @package   mod_zoom
 * @copyright 2018 UC Regents
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class send_notifications extends \core\task\scheduled_task {

    /**
     * Returns name of task.
     *
     * @return string
     */
    public function get_name() {
        return "send zoom meeting notifications and reminders";
    }

    /**
     * Updates meetings that are not expired.
     *
     * @return boolean
     */
    public function execute()
    {
        $config = get_config('mod_zoom');
        $current_time = time();

        // Mail notifications - Notification process should be carried out
        // before triggering the remainder process
        mtrace("Starting zoom mail notification method");

        if ($config->enablenotifymail == 1) {
            zoom_send_notification($config, $current_time);
        } else {
            mtrace('... Skipping because mail notification is disabled in the site level');
        }

        mtrace("Finishing zoom mail notification method");

        // Reminder mail
        mtrace("Starting zoom reminder method");

        if ($config->enableremindermail == 1) {
            zoom_send_reminder($config, $current_time);
        } else {
            mtrace('... Skipping because reminder is disabled in the site level');
        }

        mtrace("Finishing zoom reminder method");

        return true;
    }
}
