<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\DivisionEnum;
use App\Enums\TurnoutFieldEnum;
use App\Models\County;
use App\Models\Locality;
use App\Models\Turnout;
use App\Services\CalculatorService;
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

    public int $ballotIdPrimarie = 114; //primarie

    public int $ballotIdConsiliuLocal = 116; //consiliu local

    public County $county;

    public string $url;

    /**
     * Create a new job instance.
     */
    public function __construct(County $county)
    {
        $this->county = $county;

        $this->url = Str::replace('{short_county}', strtolower($county->ShortName), config('services.import.local_presence_url'));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $data = Http::get($this->url)
            ->throw()
            ->json('precinct');

        collect($data)
            ->groupBy('uat.siruta')
            ->each(function (Collection $items, string $key) {
                $locality = Locality::where('Siruta', $key)->first();

                Turnout::where('LocalityId', $locality->id)
                    ->where('BallotId', $this->ballotIdPrimarie)
                    ->update($this->generateData($items, $locality, $this->ballotIdPrimarie));

                Turnout::where('LocalityId', $locality->id)
                    ->where('BallotId', $this->ballotIdConsiliuLocal)
                    ->update($this->generateData($items, $locality, $this->ballotIdConsiliuLocal));
            });
    }

    protected function generateData(Collection $items, Locality $locality, int $ballotId): array
    {
        return [
            'EligibleVoters' => CalculatorService::eligibleVoters($items),
            'TotalVotes' => CalculatorService::totalVotes($items),
            'NullVotes' => CalculatorService::nullVotes($items),
            'VotesByMail' => CalculatorService::votesByMail($items),
            'ValidVotes' => CalculatorService::validVotes($items),
            'TotalSeats' => CalculatorService::notUse(),
            'Coefficient' => CalculatorService::notUse(),
            'Threshold' => CalculatorService::notUse(),
            'Circumscription' => CalculatorService::notUse(),
            'MinVotes' => CalculatorService::notUse(),
            'Mandates' => CalculatorService::notUse(),
            'CorrespondenceVotes' => CalculatorService::notUse(),
            'SpecialListsVotes' => CalculatorService::notUse(),
            'PermanentListsVotes' => $items->sum(TurnoutFieldEnum::VOTERS_ON_PERMANENT_LIST->value),
            'SuplimentaryVotes' => $items->sum(TurnoutFieldEnum::VOTERS_ON_COMPLEMENTARY_LIST->value),
            'Division' => DivisionEnum::COUNTY->value,
            'BallotId' => $ballotId,
            'CountyId' => $locality->CountyId,
            'LocalityId' => $locality->LocalityId,
        ];
    }
}
