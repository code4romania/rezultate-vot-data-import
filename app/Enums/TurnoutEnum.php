<?php

declare(strict_types=1);

namespace App\Enums;

enum TurnoutEnum: string
{
    case EligibleVoters = 'a';
    case Voters = 'b';
    case ValidVotes = 'e';
    case NullVotes = 'f';
}
