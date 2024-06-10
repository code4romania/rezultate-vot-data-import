<?php

declare(strict_types=1);

namespace App\Imports;

use App\Concerns\EuroparlJob;
use App\Enums\DivisionEnum;
use App\Models\CandidateResult;
use App\Models\Country;
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
