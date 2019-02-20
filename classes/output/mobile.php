<?php
namespace mod_zoom\output;
 
defined('MOODLE_INTERNAL') || die();

use context_module;
use mod_zoom_external;
 
/**
 * Mobile output class for zoom
 *
 * @package	mod_zoom
 * @copyright  2018 Nick Stefanski <nmstefanski@gmail.com>
 * @license	http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {
 
	/**
 	* Returns the zoom course view for the mobile app,
	*  including meeting details and launch button (if applicable).
 	* @param  array $args Arguments from tool_mobile_get_content WS
 	*
 	* @return array   	HTML, javascript and otherdata
 	*/
	public static function mobile_course_view($args) {
    	global $OUTPUT, $USER, $DB;
 
    	$args = (object) $args;
    	$cm = get_coursemodule_from_id('zoom', $args->cmid);
 
    	// Capabilities check.
    	require_login($args->courseid , false , $cm, true, true);
 
    	$context = context_module::instance($cm->id);
 
    	require_capability ('mod/zoom:view', $context);
	  	// right now we're just implementing basic viewing, otherwise we may need to check other capabilities
    	$zoom = $DB->get_record('zoom', array('id' => $cm->instance));
		
		//WS to get zoom state
		try {
	  		$zoom_state = mod_zoom_external::get_state($cm->id);
		} catch (Exception $e) {
        	$zoom_state = array();
    	}
	  	
	  	//format date and time
	  	$start_time = userdate($zoom->start_time);
	  	$duration = format_time($zoom->duration);
	  	
		//get audio option string
	  	$option_audio = get_string('audio_' . $zoom->option_audio, 'mod_zoom');
		
	  	$data = array(
	  		'zoom' => $zoom,
	  		'available' => $zoom_state['available'],
	  		'status' => $zoom_state['status'],
	  		'start_time' => $start_time,
	  		'duration' => $duration,
	  		'option_audio' => $option_audio,
			//'userishost' => $userishost,
	  		'cmid' => $cm->id,
	  		'courseid' => $args->courseid
	  	);
		
    	return array(
        	'templates' => array(
            	array(
                	'id' => 'main',
                	'html' => $OUTPUT->render_from_template('mod_zoom/mobile_view_page', $data),
            	),
        	),
        	'javascript' => "this.loadMeeting = function(result) { window.open(result.joinurl, '_system'); };",
				// this JS will redirect to a joinurl passed by the mod_zoom_grade_item_update WS
        	'otherdata' => '',
        	'files' => ''
    	);
	}
}