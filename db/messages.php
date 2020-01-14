<?php

/**
 * Message configuration
 *
 * @package   mod_zoom
 */

defined('MOODLE_INTERNAL') || die();

$messageproviders = array(
    // Notify users about session creation.
    'session_notification' => array(
        'defaults' => array(
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_LOGGEDIN + MESSAGE_DEFAULT_LOGGEDOFF
        )
    ),

    // Send remainder about the session.
    'session_reminder' => array(
        'defaults' => array(
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_LOGGEDIN,
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_LOGGEDIN + MESSAGE_DEFAULT_LOGGEDOFF
        )
    )
);