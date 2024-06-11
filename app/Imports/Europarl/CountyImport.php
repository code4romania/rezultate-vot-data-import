<?php

declare(strict_types=1);

namespace App\Imports\Europarl;

use App\Concerns\EuroparlJob;
use App\Enums\DivisionEnum;
use App\Enums\TurnoutEnum;
use App\Models\Locality;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class CountyImport implements ToCollection, WithHeadingRow
{
    use Importable;
    use RegistersEventListeners;
    use EuroparlJob;

    public string $countyCode;

    public ?int $countyId;

    public ?Collection $localities = null;

    public function __construct(string $countyCode, ?int $countyId)
    {
        $this->countyCode = $countyCode;
        $this->countyId = $countyId;
    }

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
        // Locality level
        $collection
            ->groupBy('uat_siruta')
            ->map(
                fn (Collection $group, $uat_siruta) => $this->getCandidates()
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
            ->map(fn (Collection $chunk) => $this->upsertCandidateResults($chunk, ['Votes']));

        // County level
        $this->getCandidates()
            ->map(fn (array $candidate) => $this->makeCandidateResult(
                $candidate,
                $collection,
                division: DivisionEnum::COUNTY,
                countyId: $this->countyId,
            ))
            ->tap(function (Collection $chunk) {
                Cache::driver('file')->rememberForever("europarl_county_results_{$this->countyCode}", fn () => $chunk);
                $this->upsertCandidateResults($chunk, ['Votes']);
            });
    }

    protected function importTurnouts(Collection $collection): void
    {
        // Locality level
        $collection
            ->groupBy('uat_siruta')
            ->map(
                fn (Collection $group, $uat_siruta) => $this->makeTurnout(
                    $group,
                    DivisionEnum::LOCALITY,
                    $this->countyId,
                    $this->getLocalityId($uat_siruta),
                )
            )
            ->chunk(1000)
            ->map(fn (Collection $chunk) => $this->upsertTurnouts($chunk));

        // County level
        collect([
            $this->makeTurnout(
                $collection,
                DivisionEnum::COUNTY,
                $this->countyId,
            ),
        ])
            ->tap(function (Collection $chunk) {
                Cache::driver('file')->rememberForever("europarl_turnouts_{$this->countyCode}", fn () => $chunk);
                $this->upsertTurnouts($chunk);
            });
    }

    protected function getLocalityId(?int $code): ?int
    {
        $locality = $this->getLocalities()
            ->firstWhere('code', $code);

        return data_get($locality, 'id') ?? throw new Exception("Locality not found for: {$code}");
    }

    protected function getLocalities(): Collection
    {
        if (blank($this->localities)) {
            $this->localities = Locality::query()
                ->where('CountyId', $this->countyId)
                ->get(['LocalityId', 'Siruta'])
                ->map(fn (Locality $locality) => [
                    'id' => $locality->LocalityId,
                    'code' => $locality->Siruta,
                ]);
        }

        return $this->localities;
    }
}
