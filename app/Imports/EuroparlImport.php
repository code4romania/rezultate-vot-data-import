<?php

declare(strict_types=1);

namespace App\Imports;

use App\Concerns\EuroparlJob;
use App\Models\CandidateResult;
use App\Models\Locality;
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
        // Locality level
        $collection
            ->groupBy('uat_siruta')
            ->map(
                fn (Collection $group, $uat_siruta) => $this->candidates
                    ->map(fn (array $candidate) => $this->makeCandidateResult(
                        $candidate,
                        $group,
                        countyId: $this->countyId,
                        localityId: $this->getLocalityId($uat_siruta)
                    ))
            )
            ->flatten(1)
            ->chunk(1000)
            ->map(fn (Collection $chunk) => CandidateResult::upsert($chunk->all(), ['Id']));

        // County level
        $this->candidates
            ->map(fn (array $candidate) => $this->makeCandidateResult(
                $candidate,
                $collection,
                countyId: $this->countyId,
            ))
            ->tap(function (Collection $chunk) {
                Cache::driver('file')->rememberForever("europarl_county_results_{$this->countyCode}", fn () => $chunk);

                CandidateResult::upsert($chunk->all(), ['Id']);
            });
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
