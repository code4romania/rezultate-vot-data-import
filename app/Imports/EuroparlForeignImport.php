<?php

declare(strict_types=1);

namespace App\Imports;

use App\Concerns\EuroparlJob;
use App\Enums\DivisionEnum;
use App\Enums\TurnoutEnum;
use App\Models\CandidateResult;
use App\Models\Country;
use App\Models\Turnout;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class EuroparlForeignImport implements ToCollection, WithHeadingRow
{
    use Importable;
    use RegistersEventListeners;
    use EuroparlJob;

    public Collection $countries;

    public function __construct()
    {
        $this->loadCountries();
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
        $tmp = $collection->groupBy('uat_name')
            ->map(function (Collection $group, $uat_name) {
                $countryId = Country::where('Name', $uat_name)->first()->Id;
                $data = [
                    'ValidVotes' => $group->sum(TurnoutEnum::ValidVotes->value),
                    'NullVotes' => $group->sum(TurnoutEnum::NullVotes->value),
                    'TotalVotes' => $group->sum('total_votes'),
                    'CountryId' => $countryId,
                    'Division' => DivisionEnum::DIASPORA_COUNTRY->value,
                    'BallotId' => 118,
                    'EligibleVoters' => 0,
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
                    'SuplimentaryVotes' => 0,

                ];
                $id = Turnout::where('CountryId', $countryId)
                    ->where('Division', DivisionEnum::DIASPORA_COUNTRY->value)
                    ->where('BallotId', 118)
                    ->first()?->Id;
                $data['Id'] = $id ?? null;

                return $data;
            })->tap(function (Collection $chunk) {
                Turnout::upsert($chunk->all(), ['Id']);
            });
        $data = [
            'ValidVotes' => $tmp->sum('ValidVotes'),
            'NullVotes' => $tmp->sum('NullVotes'),
            'TotalVotes' => $tmp->sum('TotalVotes'),
            'CountryId' => null,
            'Division' => DivisionEnum::DIASPORA,
            'BallotId' => 118,
            'EligibleVoters' => 0,
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
            'SuplimentaryVotes' => 0,
        ];
        $id = Turnout::where('Division', DivisionEnum::DIASPORA->value)->where('BallotId', 118)->first()?->Id;
        $data['Id'] = $id ?? null;
        Turnout::upsert([$data], ['Id']);
        // Country level
        $collection
            ->groupBy('uat_name')
            ->map(
                fn (Collection $group, $uat_name) => $this->candidates
                    ->map(fn (array $candidate) => $this->makeCandidateResult(
                        $candidate,
                        $group,
                        countryId: $this->getCountryId($uat_name),
                        division: DivisionEnum::DIASPORA_COUNTRY,
                    ))
            )
            ->flatten(1)
            ->chunk(1000)
            ->map(fn (Collection $chunk) => CandidateResult::upsert($chunk->all(), ['Id']));

        // Global level
        $this->candidates
            ->map(fn (array $candidate) => $this->makeCandidateResult(
                $candidate,
                $collection,
                division: DivisionEnum::DIASPORA,
            ))
            ->tap(function (Collection $chunk) {
                CandidateResult::upsert($chunk->all(), ['Id']);
            });
    }

    protected function getCountryId(?string $name): ?int
    {
        $country = $this->countries
            ->firstWhere('name', Str::slug($name));

        return data_get($country, 'id') ?? throw new Exception("Country not found for: {$name}");
    }

    protected function loadCountries(): void
    {
        $this->countries = Country::query()
            ->get()
            ->map(fn (Country $country) => [
                'id' => $country->getKey(),
                'name' => Str::slug($country->Name),
            ]);
    }
}
