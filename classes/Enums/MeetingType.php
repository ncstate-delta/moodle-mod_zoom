<?php

require_once($CFG->dirroot.'/mod/zoom/classes/Enums/BaseEnum.php');

class MeetingType extends BaseEnum
{
    const Instant_meeting = 1;

    const Scheduled_Meeting = 2;

    const Recurring_meeting_with_no_fixed_time = 3;

    const Recurring_meeting_with_a_fixed_time =  8;
}
