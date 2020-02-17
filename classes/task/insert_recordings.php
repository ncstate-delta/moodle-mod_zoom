<?php

namespace mod_zoom\task;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/mod/zoom/locallib.php');
require_once($CFG->dirroot.'/lib/modinfolib.php');
require_once($CFG->dirroot.'/mod/zoom/lib.php');
require_once($CFG->dirroot.'/mod/zoom/classes/webservice.php');


/**
 * Scheduled task to sychronize meeting data.
 *
 * @package   mod_zoom
 */
 class insert_recordings extends \core\task\scheduled_task {

    /**
     * Returns name of task.
     *
     * @return string
     */
    public function get_name() {
        return get_string("update_recording", "zoom");
    }

    /**
     * Updates recordings that are not expired.
     *
     * @return boolean
     */
    public function execute() {
        
        global $CFG, $DB;
        $config = get_config('mod_zoom');
       
        $sql = "SELECT e.*, mz.meeting_id, mz.auto_recording from mdl_event as e
                join mdl_zoom mz on e.instance = mz.id
                where e.modulename = 'zoom'and e.recording_created = 0
                and mz.deleted_at IS NULL and e.endtime < UNIX_TIMESTAMP(NOW())";

        $zoom_events = $DB->get_records_sql($sql);
        $service = new \mod_zoom_webservice();

        foreach ($zoom_events as $value) {
            try {
                $this->disable_download_in_stream($value->meeting_id);
                $recordings = $service->get_meeting_recording($value->meeting_id);
            } catch (\moodle_exception $error) {
                throw zoom_is_meeting_gone_error($error);
            }
            if (!empty($recordings)) {
                //Get only the first recording file
                $rec = $recordings->recording_files{0};
                $record = new\stdClass();
                $record->meeting_id = $recordings->id;
                $record->uuid = $recordings->uuid;
                $record->play_url = $rec->play_url;
                $record->download_url = $rec->download_url;
                $record->start_time = $rec->recording_start;
                $record->end_time = $rec->recording_end;
                $record->status = $rec->status;
                $DB->insert_record('zoom_recordings', $record);
                $DB->update_record('event', (object)['id' => $value->id, 'recording_created' => 1]);
            }
        }  
    }

     /**
      * Disable download option in stream
      * @param int $meeting_id
      */
    public function disable_download_in_stream($meeting_id)
    {
       $service = new \mod_zoom_webservice();
       $service->disable_view_downloader($meeting_id, ['viewer_download' => false]);
    }
}