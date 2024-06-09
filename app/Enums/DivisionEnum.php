<?php

declare(strict_types=1);

namespace App\Enums;

enum DivisionEnum: int
{
    case NATIONAL = 0;
    case COUNTY = 2;
    case CITY = 3;
}
