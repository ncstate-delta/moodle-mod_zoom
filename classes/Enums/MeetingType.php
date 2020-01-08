<?php

require_once($CFG->dirroot.'/mod/zoom/classes/Enums/BaseEnum.php');

class MeetingType extends BaseEnum
{
    const INSTANT_MEETING = 1;

    const SCHEDULED_MEETING = 2;

    const RECURRING_WITH_NO_FIXED_TIME = 3;

    const RECURRING_WITH_FIXED_TIME =  8;
}
