<?php

declare(strict_types=1);

namespace App\Imports;

use App\Concerns\EuroparlJob;
use App\Enums\DivisionEnum;
use App\Enums\TurnoutEnum;
use App\Models\CandidateResult;
use App\Models\Locality;
use App\Models\Turnout;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class EuroparlImport implements ToCollection, WithHeadingRow
{
    use Importable;
    use RegistersEventListeners;
    use EuroparlJob;

    public string $countyCode;

    public ?int $countyId;

    public Collection $localities;

    public function __construct(string $countyCode, ?int $countyId)
    {
        $this->countyCode = $countyCode;
        $this->countyId = $countyId;
        $this->loadLocalities();
        $this->loadData();
    }

    /**
     * @param Collection $collection
     */
    public function collection(Collection $collection)
    {
        $collection->map(function ($row) {
            $row->put('total_votes', $row->get(TurnoutEnum::ValidVotes->value) + $row->get(TurnoutEnum::NullVotes->value));

            return $row;
        });
        $collection->groupBy('uat_siruta')
            ->map(function ($item,$key){

             $localityId = $this->getLocalityId($key);
                $data = [
                            'ValidVotes' => $item->sum(TurnoutEnum::ValidVotes->value),
                            'NullVotes' => $item->sum(TurnoutEnum::NullVotes->value),
                            'TotalVotes' => $item->sum('total_votes'),
                            'CountyId' => $this->countyId,
                            'LocalityId' => $localityId,
                            'Division' => DivisionEnum::LOCALITY->value,
                            'BallotId' => 118,
                        ];
                        $id = Turnout::where('Division', DivisionEnum::LOCALITY->value)
                            ->where('LocalityId', $localityId)
                            ->where('BallotId', 118)
                            ->first()?->Id;
                        $data['Id'] = $id ?? null;
                if (! empty($data['Id'])) {
                    Turnout::where('Id', $data['Id'])->update($data);
                } else {
                    $tmp = ['EligibleVoters' => 0,
                        'VotesByMail' => 0,
                        'TotalSeats' => 0,
                        'Coefficient' => 0,
                        'Threshold' => 0,
                        'Circumscription' => 0,
                        'MinVotes' => 0,
                        'Mandates' => 0,
                        'CorrespondenceVotes' => 0,
                        'PermanentListsVotes' => 0,
                        'SpecialListsVotes' => 0,
                        'SuplimentaryVotes' => 0, ];
                    $data = array_merge($data, $tmp);
                    Turnout::insert($data);
                }

//                        return $data;
            });
        // Locality level
        $collection
            ->groupBy('uat_siruta')
            ->map(
                fn (Collection $group, $uat_siruta) => $this->candidates
                    ->map(fn (array $candidate) => $this->makeCandidateResult(
                        $candidate,
                        $group,
                        division: DivisionEnum::LOCALITY,
                        localityId: $this->getLocalityId($uat_siruta),
                        countyId: $this->countyId,
                    ))
            )
            ->flatten(1)
            ->chunk(1000)
            ->map(fn (Collection $chunk) =>CandidateResult::upsert($chunk->all(), ['Id']));


        // County level
        $this->candidates
            ->map(fn (array $candidate) => $this->makeCandidateResult(
                $candidate,
                $collection,
                division: DivisionEnum::COUNTY,
                countyId: $this->countyId,
            ))
            ->tap(function (Collection $chunk) {
                Cache::driver('file')->rememberForever("europarl_county_results_{$this->countyCode}", fn () => $chunk);
                CandidateResult::upsert($chunk->all(), ['Id']);
            });

        $data = [
            'ValidVotes' => $collection->sum(TurnoutEnum::ValidVotes->value),
            'NullVotes' => $collection->sum(TurnoutEnum::NullVotes->value),
            'TotalVotes' => $collection->sum('total_votes'),
            'CountyId' => $this->countyId,
            'Division' => DivisionEnum::COUNTY->value,
            'BallotId' => 118,
        ];
        $id = Turnout::where('Division', DivisionEnum::COUNTY->value)
            ->where('CountyId', $this->countyId)
            ->where('BallotId', 118)->first()?->Id;
        $data['Id'] = $id ?? null;
        if (! empty($data['Id'])) {
            Turnout::where('Id', $data['Id'])->update($data);
        } else {
            $tmp = ['EligibleVoters' => 0,
                'VotesByMail' => 0,
                'TotalSeats' => 0,
                'Coefficient' => 0,
                'Threshold' => 0,
                'Circumscription' => 0,
                'MinVotes' => 0,
                'Mandates' => 0,
                'CorrespondenceVotes' => 0,
                'PermanentListsVotes' => 0,
                'SpecialListsVotes' => 0,
                'SuplimentaryVotes' => 0, ];
            $data = array_merge($data, $tmp);
            Turnout::insert($data);
        }
    }

    protected function getLocalityId(?int $code): ?int
    {
        $locality = $this->localities
            ->firstWhere('code', $code);

        return data_get($locality, 'id') ?? throw new Exception("Locality not found for: {$code}");
    }

    protected function loadLocalities(): void
    {
        $this->localities = Locality::query()
            ->where('CountyId', $this->countyId)
            ->get(['LocalityId', 'Siruta'])
            ->map(fn (Locality $locality) => [
                'id' => $locality->LocalityId,
                'code' => $locality->Siruta,
            ]);
    }
}
