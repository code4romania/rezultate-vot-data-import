<?php

declare(strict_types=1);

namespace App\Enums;

enum DivisionEnum: int
{
    case NATIONAL = 0;
    case DIASPORA = 1;
    case COUNTY = 2;
    case LOCALITY = 3;
    case DIASPORA_COUNTRY = 4;
}
