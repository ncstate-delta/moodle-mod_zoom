<?php

require_once($CFG->dirroot.'/mod/zoom/classes/Enums/BaseEnum.php');

class RecurringFrequency extends BaseEnum
{
    const Daily = 1;

    const Weekly = 2;

    const Monthly = 3;
}
