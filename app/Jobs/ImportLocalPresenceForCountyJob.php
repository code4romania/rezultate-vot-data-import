<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\DivisionEnum;
use App\Enums\TurnoutFieldEnum;
use App\Models\County;
use App\Models\Locality;
use App\Models\Turnout;
use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ImportLocalPresenceForCountyJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $ballotIdPrimarie = 114;

    public int $ballotIdConsiliuLocal = 116;

    public County $county;

    public string $url;

    /**
     * Create a new job instance.
     */
    public function __construct(County $county)
    {
        $this->county = $county;

        $this->url = Str::replace('{short_county}', $county->code, config('services.import.local_presence.url'));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $data = collect(
            Http::get($this->url)
                ->throw()
                ->json('precinct')
        )
            ->groupBy('uat.siruta');

        $turnouts = Turnout::query()
            ->whereIn('BallotId', [$this->ballotIdPrimarie, $this->ballotIdConsiliuLocal])
            ->where('CountyId', $this->county->getKey())
            ->get(['Id', 'BallotId', 'CountyId', 'LocalityId']);

        $localities = Locality::query()
            ->where('CountyId', $this->county->getKey())
            ->get(['Siruta', 'LocalityId', 'CountyId']);

        foreach (['ballotIdPrimarie', 'ballotIdConsiliuLocal'] as $ballot) {
            Turnout::upsert(
                $data
                    ->map(function (Collection $items, string $key) use ($localities, $turnouts, $ballot) {
                        $locality = $localities
                            ->where('Siruta', $key)
                            ->first();

                        $turnout = $turnouts
                            ->where('LocalityId', $locality->getKey())
                            ->where('BallotId', $this->{$ballot})
                            ->first();

                        return $this->generateData($items, $locality, $this->{$ballot}, $turnout);
                    })
                    ->all(),
                ['Id']
            );
        }
    }

    protected function generateData(Collection $items, Locality $locality, int $ballotId, ?Turnout $turnout): array
    {
        return [
            'Id' => $turnout?->getKey(),
            'EligibleVoters' => $this->getEligibleVoters($items),
            'TotalVotes' => $this->getTotalVotes($items),
            'NullVotes' => 0,
            'VotesByMail' => 0,
            'ValidVotes' => 0,
            'TotalSeats' => 0,
            'Coefficient' => 0,
            'Threshold' => 0,
            'Circumscription' => 0,
            'MinVotes' => 0,
            'Mandates' => 0,
            'CorrespondenceVotes' => 0,
            'SpecialListsVotes' => 0,
            'PermanentListsVotes' => $items->sum(TurnoutFieldEnum::VOTERS_ON_PERMANENT_LIST->value),
            'SuplimentaryVotes' => $items->sum(TurnoutFieldEnum::VOTERS_ON_COMPLEMENTARY_LIST->value),
            'Division' => DivisionEnum::LOCALITY->value,
            'BallotId' => $ballotId,
            'CountyId' => $locality->CountyId,
            'LocalityId' => $locality->LocalityId,
        ];
    }

    protected function getEligibleVoters(Collection $items): int
    {
        $votersOnPermanentLists = $items->sum(TurnoutFieldEnum::REGISTER_ON_PERMANENT_LIST->value);
        $votersOnComplementaryLists = $items->sum(TurnoutFieldEnum::REGISTER_ON_COMPLEMENTARY_LIST->value);

        return $votersOnPermanentLists + $votersOnComplementaryLists;
    }

    protected function getTotalVotes(Collection $items): int
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
}
