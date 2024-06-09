<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TurnoutFieldEnum;
use Exception;
use Illuminate\Support\Collection;

class CalculatorService
{
    public static function eligibleVoters(Collection $items): int
    {
        $votersOnPermanentLists = $items->sum(TurnoutFieldEnum::REGISTER_ON_PERMANENT_LIST->value);
        $votersOnComplementaryLists = $items->sum(TurnoutFieldEnum::REGISTER_ON_COMPLEMENTARY_LIST->value);

        return $votersOnPermanentLists + $votersOnComplementaryLists;
    }

    public static function totalVotes(Collection $items)
    {
        $votersOnPermanentLists = $items->sum(TurnoutFieldEnum::VOTERS_ON_PERMANENT_LIST->value);
        $votersOnComplementaryLists = $items->sum(TurnoutFieldEnum::VOTERS_ON_COMPLEMENTARY_LIST->value);
        $votersOnAdditionalLists = $items->sum(TurnoutFieldEnum::VOTERS_ON_ADDITIONAL_LIST->value);
        $votersOnMobileBox = $items->sum(TurnoutFieldEnum::VOTERS_ON_MOBILE_BOX->value);

        $totalVoters = $votersOnPermanentLists + $votersOnComplementaryLists + $votersOnAdditionalLists + $votersOnMobileBox;
        $totalVotesFromEP = $items->sum(TurnoutFieldEnum::TOTAL_VOTERS->value);
        if ($totalVoters !== $totalVotesFromEP) {
            throw new Exception('Total voters do not match');
        }

        return $votersOnPermanentLists + $votersOnComplementaryLists + $votersOnAdditionalLists + $votersOnMobileBox;
    }

    public static function nullVotes(Collection $items): int
    {
        return 0; /// @todo implement null votes after counting votes after the election is finished and we start counting the votes
    }

    public static function votesByMail(Collection $items): int
    {
        return 0; /// @todo implement votes by mail after counting votes after the election is finished and we start counting the votes
    }

    public static function validVotes(Collection $items): int
    {
        return 0; /// @todo implement votes by mail after counting votes after the election is finished and we start counting the votes
    }

    public static function totalSeats(Collection $items): int
    {
        return 0; /// @todo implement votes by mail after counting votes after the election is finished and we start counting the votes
    }

    public static function notUse(): int
    {
        return 0; /// @todo implement votes by mail after counting votes after the election is finished and we start counting the votes
    }
}
