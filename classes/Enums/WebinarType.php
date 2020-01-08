<?php

require_once($CFG->dirroot.'/mod/zoom/classes/Enums/BaseEnum.php');

class WebinarType extends BaseEnum
{
    const WEBINAR = 5;

    const RECURRING_WITH_NO_FIXED_TIME = 6;

    const RECURRING_WITH_FIXED_TIME =  9;
}
