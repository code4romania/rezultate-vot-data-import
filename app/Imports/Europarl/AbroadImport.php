<?php

declare(strict_types=1);

namespace App\Imports\Europarl;

use App\Concerns\EuroparlJob;
use App\Enums\DivisionEnum;
use App\Enums\TurnoutEnum;
use App\Models\Country;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AbroadImport implements ToCollection, WithHeadingRow
{
    use Importable;
    use RegistersEventListeners;
    use EuroparlJob;

    public ?Collection $countries = null;

    /**
     * @param Collection $collection
     */
    public function collection(Collection $collection)
    {
        $collection
            ->map(fn (Collection $row) => $row->put(
                'total_votes',
                $row->get(TurnoutEnum::ValidVotes->value) + $row->get(TurnoutEnum::NullVotes->value)
            ));

        $this->importTurnouts($collection);
        $this->importCandidateResults($collection);
    }

    protected function importCandidateResults(Collection $collection): void
    {
        // Country level
        $collection
            ->groupBy('uat_name')
            ->map(
                fn (Collection $group, $uat_name) => $this->getCandidates()
                    ->map(fn (array $candidate) => $this->makeCandidateResult(
                        $candidate,
                        $group,
                        countryId: $this->getCountryId($uat_name),
                        division: DivisionEnum::DIASPORA_COUNTRY,
                    ))
            )
            ->flatten(1)
            ->chunk(1000)
            ->map(fn (Collection $chunk) => $this->upsertCandidateResults($chunk, ['Votes']));

        // Global level
        $this->getCandidates()
            ->map(fn (array $candidate) => $this->makeCandidateResult(
                $candidate,
                $collection,
                division: DivisionEnum::DIASPORA,
            ))
            ->tap(function (Collection $chunk) {
                $this->upsertCandidateResults($chunk);
            });
    }

    protected function importTurnouts(Collection $collection): void
    {
        // Country level
        $collection
            ->groupBy('uat_name')
            ->map(
                fn (Collection $group, $uat_name) => $this->makeTurnout(
                    $group,
                    countryId: $this->getCountryId($uat_name),
                    division: DivisionEnum::DIASPORA_COUNTRY,
                )
            )
            ->chunk(1000)
            ->map(fn (Collection $chunk) => $this->upsertTurnouts($chunk));

        // Global level
        $this->upsertTurnouts(collect([
            $this->makeTurnout(
                $collection,
                division: DivisionEnum::DIASPORA,
            ),
        ]));
    }

    protected function getCountryId(?string $name): ?int
    {
        $country = $this->getCountries()
            ->firstWhere('name', Str::slug($name));

        return data_get($country, 'id') ?? throw new Exception("Country not found for: {$name}");
    }

    protected function getCountries(): Collection
    {
        if (blank($this->countries)) {
            $this->countries = Country::query()
                ->get()
                ->map(fn (Country $country) => [
                    'id' => $country->getKey(),
                    'name' => Str::slug($country->Name),
                ]);
        }

        return $this->countries;
    }
}
