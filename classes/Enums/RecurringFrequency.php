<?php

require_once($CFG->dirroot.'/mod/zoom/classes/Enums/BaseEnum.php');

class RecurringFrequency extends BaseEnum
{
    const DAILY = 1;

    const WEEKLY = 2;

    const MONTHLY = 3;
}
