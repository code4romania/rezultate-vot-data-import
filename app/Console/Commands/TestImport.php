<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\DivisionEnum;
use App\Enums\TurnoutFieldEnum;
use App\Models\County;
use App\Models\Locality;
use App\Models\Turnout;
use App\Services\CalculatorService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class TestImport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:test-import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Test import command');
        $url = config('aep.presence');
        $countySlug = config('aep.presence_county');
        $counties = County::all();
        $startTime  = now();
        foreach ($counties as $county) {

            $countyId = Str::lower($county->ShortName);
            $countySlug = Str::replace('{short_county}', $countyId, config('aep.presence_county'));
            $data = \Http::get($url.$countySlug)->json('precinct');
            $bulkInsert = [];
            $this->info($url.$countySlug);
            collect($data)->groupBy('siruta')->each(function ($item, $key) use ($bulkInsert) {
                $items = collect($item);
                $locality = Locality::where('Siruta', $key)->first();

                $ballotIdPrimarie = 114;//primarie
                $ballotIdConsiliuLocal = 116;//consiliu local

                $bulkInsert[] = $this->generateDataToInsert($items, $locality, $ballotIdPrimarie);
                $bulkInsert[] = $this->generateDataToInsert($items, $locality, $ballotIdConsiliuLocal);
                Turnout::where('LocalityId', $locality->id)
                    ->whereIn('BallotId', [$ballotIdPrimarie, $ballotIdConsiliuLocal])
                    ->delete();
                Turnout::insert($bulkInsert);
            });
        }
        $endTime = now();
        $this->info('Time to import: ' . $startTime->diff($endTime)->format('%H:%I:%S'));


    }

    private function generateDataToInsert(Collection $items, Locality $locality, int $ballotId)
    {
        return [
            'EligibleVoters' => CalculatorService::eligibleVoters($items),
            'TotalVotes' => CalculatorService::totalVotes($items),
            'NullVotes' => CalculatorService::nullVotes($items),
            'VotesByMail' => CalculatorService::votesByMail($items),
            'ValidVotes' =>CalculatorService::validVotes($items),
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
            'CountyId' =>$locality->CountyId,
            'LocalityId' => $locality->LocalityId,
        ];
    }
}
