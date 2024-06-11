<?php

declare(strict_types=1);

namespace App\Imports;

use App\Enums\DivisionEnum;
use App\Models\County;
use App\Models\Locality;
use App\Models\Party;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithProgressBar;
use Maatwebsite\Excel\Events\AfterChunk;

abstract class LocalImport implements ToCollection, WithHeadingRow, WithChunkReading, WithProgressBar
{
    use Importable;
    use RegistersEventListeners;

    public Collection $parties;

    public Collection $counties;

    public Collection $candidateResults;

    public ?Collection $localities = null;

    public array $county = [];

    public function __construct()
    {
        $this->parties = Party::all()
            ->map(fn (Party $party) => [
                'id' => $party->Id,
                'name' => $this->normalize($party->Name),
                'shortName' => $party->ShortName,
                'alias' => $party->Alias,
            ]);

        $this->counties = County::all()
            ->map(fn (County $county) => [
                'id' => $county->CountyId,
                'code' => $county->code,
                'name' => $this->normalize($county->Name),
            ]);
    }

    public function chunkSize(): int
    {
        return 1000;
    }

    public function normalize(string $source): string
    {
        return Str::of($source)
            ->slug()
            ->remove(['judetul-', 'municipiul-', 'oras-', 'bucuresti-'])
            ->value();
    }

    public function afterChunk(AfterChunk $event)
    {
        $this->output->title("Importing chunk from row {$this->getStartRow()}...");
    }

    protected function getBallotId(string $source): int
    {
        return match ($this->normalize($source)) {
            'primari' => 114,
            'primar-general', 'presedinte-consiliu-judetean' => 115,

            'consilieri-locali' => 116,
            'consiliu-general', 'consiliu-judetean' => 117,
        };
    }

    protected function findPartyByName(string $name): ?array
    {
        $name = $this->normalize($name);

        return $this->parties
            ->first(
                fn (array $party) => $party['name'] === $name || $party['shortName'] === $name || $party['alias'] === $name
            );
    }

    protected function getLocalityId(?int $code, ?string $name): ?int
    {
        if (blank($code) && blank($name)) {
            return null;
        }

        return $this->localities
            ->firstWhere('name', $this->normalize($name))['id'] ?? throw new Exception("Locality not found: {$name}");
    }

    protected function loadCountyAndLocalities(string $countyName): void
    {
        $normalizedCountyName = $this->normalize($countyName);

        if (data_get($this->county, 'name') !== $normalizedCountyName) {
            $this->county = $this->counties
                ->firstWhere('name', $normalizedCountyName);

            if (\is_null($this->county)) {
                throw new Exception("County not found: {$normalizedCountyName}");
            }

            $this->localities = Locality::query()
                ->where('CountyId', $this->county['id'])
                ->get()
                ->map(fn (Locality $locality) => [
                    'id' => $locality->LocalityId,
                    'name' => $this->normalize($locality->Name),
                ]);
        }
    }

    protected function getDivision(string $source): DivisionEnum
    {
        return match ($this->normalize($source)) {
            'primari' => DivisionEnum::LOCALITY,
            'consilieri-locali' => DivisionEnum::LOCALITY,
            'primar-general' => DivisionEnum::LOCALITY,
            'presedinte-consiliu-judetean' => DivisionEnum::COUNTY,
            'consiliu-general' => DivisionEnum::COUNTY,
            'consiliu-judetean' => DivisionEnum::COUNTY,
        };
    }
}
