<?php

namespace mod_zoom\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/zoom/locallib.php');

/**
 * Scheduled task to sychronize meeting data.
 *
 * @package   mod_zoom
 * @copyright 2018 UC Regents
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 class insert_recordings extends \core\task\scheduled_task {

    /**
     * Returns name of task.
     *
     * @return string
     */
    public function get_name() {
        return "update zoom recordings";
    }

      /**
     * Updates recordings that are not expired.
     *
     * @return boolean
     */
    public function execute() {
        global $CFG, $DB;
        $config = get_config('mod_zoom');
        require_once($CFG->dirroot.'/lib/modinfolib.php');
        require_once($CFG->dirroot.'/mod/zoom/lib.php');
        require_once($CFG->dirroot.'/mod/zoom/classes/webservice.php');

        $sql = "SELECT e.*, mz.meeting_id from mdl_event as e
                join mdl_zoom mz on e.instance = mz.id
                where e.modulename = 'zoom'and e.recording_created = 0
                and mz.deleted_at IS NULL and e.endtime < now()";

        $zoom_events = $DB->get_records_sql($sql);
        foreach ($zoom_events as $value) {
            $service = new \mod_zoom_webservice();
            try {
            $recordings = $service->get_meeting_recording($value->meeting_id);
            } catch (\moodle_exception $error) {
                throw zoom_is_meeting_gone_error($error);
            }
        }

        if (!empty($recordings)) {
            $rec = $recordings->recording_files{0};
            $record = new\stdClass();
            $record->meeting_id = $recordings->id;
            $record->uuid = $recordings->uuid;
            $record->play_url = $rec->play_url;
            $record->download_url = $rec->download_url;
            $record->start_time = $rec->recording_start;
            $record->end_time = $rec->recording_end;
            $record->status = $rec->status;
            if($rec->status == 'completed'){
                $DB->insert_record('zoom_recordings',$record);
            }   
        }
            
	}	
}