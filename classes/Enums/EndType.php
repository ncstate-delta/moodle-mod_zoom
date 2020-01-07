<?php

require_once($CFG->dirroot.'/mod/zoom/classes/Enums/BaseEnum.php');

class EndType extends BaseEnum
{
    const End_by_date = 1;

    const End_after_X_occurrence = 2;
}
