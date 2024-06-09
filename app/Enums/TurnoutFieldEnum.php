<?php

namespace App\Enums;

enum TurnoutFieldEnum:string
{
    case REGISTER_ON_PERMANENT_LIST = 'initial_count_lp';
    case REGISTER_ON_COMPLEMENTARY_LIST = 'initial_count_lc';
    case VOTERS_ON_PERMANENT_LIST = 'LP';
    case VOTERS_ON_COMPLEMENTARY_LIST = 'LC';
    case VOTERS_ON_ADDITIONAL_LIST = 'LS';

    case VOTERS_ON_MOBILE_BOX = 'UM';
    case TOTAL_VOTERS = 'LT';
}
