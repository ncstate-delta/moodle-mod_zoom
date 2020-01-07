<?php

require_once($CFG->dirroot.'/mod/zoom/classes/Enums/BaseEnum.php');

class MonthlyWeek extends BaseEnum
{
    const Last_week = -1;

    const First_week = 1;

    const Second_week = 2;

    const Third_week = 3;

    const Fourth_week = 4;
}
