<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\DivisionEnum;
use App\Enums\TurnoutFieldEnum;
use App\Models\County;
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

class ImportCountyPresenceForCountyJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $ballotIdPrimarie = 114;

    public int $ballotIdConsiliuLocal = 116;

    public int $ballotIdPresedinteConsiliuJudetean = 115;

    public int $ballotIdConsiliuJudetean = 117;

    public Collection $counties;

    public string $url;

    /**
     * Create a new job instance.
     */
    public function __construct(string $county)
    {
        $this->url = Str::replace('{short_county}', $county, config('services.import.local_presence.url'));

        $this->counties = County::all();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $data = collect(
            Http::get($this->url)
                ->throw()
                ->json('county')
        );

        $ballots = collect([
            'ballotIdPrimarie',
            'ballotIdConsiliuLocal',
            'ballotIdPresedinteConsiliuJudetean',
            'ballotIdConsiliuJudetean',
        ]);

        $turnouts = Turnout::query()
            ->whereIn('BallotId', $ballots->map(fn (string $ballot) => $this->{$ballot})->all())
            ->get(['Id', 'BallotId', 'CountyId']);

        $ballots->each(function (string $ballot) use ($data, $turnouts) {
            Turnout::upsert(
                $data
                    ->map(function (array $item) use ($turnouts, $ballot) {
                        $county = $this->counties
                            ->where('ShortName', $item['county']['code'])
                            ->first();

                        $turnout = $turnouts
                            ->where('CountyId', $county->getKey())
                            ->where('BallotId', $this->{$ballot})
                            ->first();

                        return $this->generateData($item, $county, $this->{$ballot}, $turnout);
                    })
                    ->all(),
                ['Id']
            );
        });
    }

    protected function generateData(array $item, County $county, int $ballotId, ?Turnout $turnout): array
    {
        return [
            'Id' => $turnout?->getKey(),
            'EligibleVoters' => $this->getEligibleVoters($item),
            'TotalVotes' => $this->getTotalVotes($item),
            'NullVotes' => $this->todo($item),
            'VotesByMail' => $this->todo($item),
            'ValidVotes' => $this->todo($item),
            'TotalSeats' => $this->todo($item),
            'Coefficient' => $this->todo($item),
            'Threshold' => $this->todo($item),
            'Circumscription' => $this->todo($item),
            'MinVotes' => $this->todo($item),
            'Mandates' => $this->todo($item),
            'CorrespondenceVotes' => $this->todo($item),
            'SpecialListsVotes' => $this->todo($item),
            'PermanentListsVotes' => data_get($item, TurnoutFieldEnum::VOTERS_ON_PERMANENT_LIST->value, 0),
            'SuplimentaryVotes' => data_get($item, TurnoutFieldEnum::VOTERS_ON_COMPLEMENTARY_LIST->value, 0),
            'Division' => DivisionEnum::COUNTY->value,
            'BallotId' => $ballotId,
            'CountyId' => $county->getKey(),
            'LocalityId' => null,
        ];
    }

    protected function getEligibleVoters(array $item): int
    {
        $votersOnPermanentLists = data_get($item, TurnoutFieldEnum::REGISTER_ON_PERMANENT_LIST->value, 0);
        $votersOnComplementaryLists = data_get($item, TurnoutFieldEnum::REGISTER_ON_COMPLEMENTARY_LIST->value, 0);

        return $votersOnPermanentLists + $votersOnComplementaryLists;
    }

    protected function getTotalVotes(array $item): int
    {
        $votersOnPermanentLists = data_get($item, TurnoutFieldEnum::VOTERS_ON_PERMANENT_LIST->value, 0);
        $votersOnComplementaryLists = data_get($item, TurnoutFieldEnum::VOTERS_ON_COMPLEMENTARY_LIST->value, 0);
        $votersOnAdditionalLists = data_get($item, TurnoutFieldEnum::VOTERS_ON_ADDITIONAL_LIST->value, 0);
        $votersOnMobileBox = data_get($item, TurnoutFieldEnum::VOTERS_ON_MOBILE_BOX->value, 0);

        $totalVoters = $votersOnPermanentLists + $votersOnComplementaryLists + $votersOnAdditionalLists + $votersOnMobileBox;
        $totalVotesFromEP = data_get($item, TurnoutFieldEnum::TOTAL_VOTERS->value, 0);

        if ($totalVoters !== $totalVotesFromEP) {
            throw new Exception('Total voters do not match');
        }

        return $votersOnPermanentLists + $votersOnComplementaryLists + $votersOnAdditionalLists + $votersOnMobileBox;
    }

    /**
     * @todo implement
     */
    protected function todo(array $item): int
    {
        return 0;
    }
}
