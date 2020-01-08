<?php

require_once($CFG->dirroot.'/mod/zoom/classes/Enums/BaseEnum.php');

class EndType extends BaseEnum
{
    const END_BY_DATE = 1;

    const END_AFTER_X_OCCURRENCE = 2;
}
