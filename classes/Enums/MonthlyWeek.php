<?php

require_once($CFG->dirroot.'/mod/zoom/classes/Enums/BaseEnum.php');

class MonthlyWeek extends BaseEnum
{
    const LAST_WEEK = -1;

    const FIRST_WEEK = 1;

    const SECOND_WEEK = 2;

    const THIRD_WEEK = 3;

    const FOURTH_WEEK = 4;
}
