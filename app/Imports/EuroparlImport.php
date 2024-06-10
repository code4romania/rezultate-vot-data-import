<?php

declare(strict_types=1);

namespace App\Imports;

use App\Enums\DivisionEnum;
use App\Models\Locality;
use App\Models\Party;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Events\AfterChunk;

class EuroparlImport implements ToCollection, WithHeadingRow
{
    use Importable;
    use RegistersEventListeners;

    public ?int $countyId;

    public Collection $parties;

    public Collection $candidates;

    public Collection $localities;

    public function __construct(?int $countyId)
    {
        $this->countyId = $countyId;

        $this->loadCandidates();
        $this->loadParties();
        $this->loadLocalities();
    }

    /**
     * @param Collection $collection
     */
    public function collection(Collection $collection)
    {
        $collection
            ->groupBy('uat_siruta')
            ->map(
                fn (Collection $group, $uat_siruta) => $this->candidates
                    ->map(fn (array $candidate) => $this->candidateData(
                        $candidate,
                        $group,
                        countyId: $this->countyId,
                        localityId: $this->getLocalityId($uat_siruta)
                    ))
            )
            ->flatten(1)
            ->dump();

        $this->candidates
            ->mapWithKeys(fn (array $candidate) => $this->candidateData(
                $candidate,
                $collection,
                countyId: $this->countyId,
            ))

            // [
            //     'Name' => $candidate['name'],
            //     'Votes' => $collection->sum($candidate['key']),
            //     'PartyId' => $this->getPartyId($candidate['name']),
            //     'CountyId' => $this->countyId,
            //     'LocalityId' => null,
            //     'Division' => DivisionEnum::COUNTY->value,
            //     'CountryId' => null,
            // ])
            ->dump();
    }

    public function afterChunk(AfterChunk $event)
    {
        logger()->debug("Importing chunk from row {$this->getStartRow()}...");
    }

    protected function candidateData(
        array $candidate,
        Collection $collection,
        ?int $countyId = null,
        ?int $localityId = null,
        ?int $countryId = null,
    ): array {
        return [
            'Id' => null,
            'Votes' => $collection->sum($candidate['key']),
            'BallotId' => null,
            'Name' => $candidate['name'],
            'PartyId' => $this->getPartyId($candidate['name']),
            'YesVotes' => 0,
            'NoVotes' => 0,
            'SeatsGained' => 0,
            'TotalSeats' => 0,
            'Seats1' => 0,
            'Seats2' => 0,
            'OverElectoralThreshold' => 0,
            'CountyId' => $countyId,
            'LocalityId' => $localityId,
            'CountryId' => $countryId,

            'Division' => match (true) {
                filled($localityId) => DivisionEnum::CITY->value,
                filled($countyId) => DivisionEnum::COUNTY->value,
                default => throw new Exception('Invalid division'),
            },
            'BallotPosition' => $candidate['position'],
        ];
    }

    protected function normalize(string $source): string
    {
        return Str::of($source)
            ->slug()
            ->remove(['judetul-', 'municipiul-', 'oras-', 'bucuresti-'])
            ->value();
    }

    protected function getPartyId(string $name): ?int
    {
        $name = $this->normalize($name);

        $party = $this->parties
            ->firstWhere(
                fn (array $party) => $party['name'] === $name
                    || $party['shortName'] === $name
                    || $party['alias'] === $name
            );

        return data_get($party, 'id');
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

    protected function loadParties(): void
    {
        $this->parties = Party::all()
            ->map(fn (Party $party) => [
                'id' => $party->Id,
                'name' => $this->normalize($party->Name),
                'shortName' => $party->ShortName,
                'alias' => $party->Alias,
            ]);
    }

    protected function loadCandidates(): void
    {
        $this->candidates = collect([
            [
                'position' => 1,
                'key' => 'uniunea_democrata_maghiara_din_romania_voturi',
                'name' => 'UNIUNEA DEMOCRATĂ MAGHIARĂ DIN ROMÂNIA',
            ],
            [
                'position' => 2,
                'key' => 'alianta_electorala_psd_pnl_voturi',
                'name' => 'ALIANȚA ELECTORALĂ PSD PNL',
            ],
            [
                'position' => 3,
                'key' => 'partidul_reinnoim_proiectul_european_al_romaniei_voturi',
                'name' => 'PARTIDUL REÎNNOIM PROIECTUL EUROPEAN AL ROMÂNIEI',
            ],
            [
                'position' => 4,
                'key' => 'alianta_aur_voturi',
                'name' => 'ALIANȚA AUR',
            ],
            [
                'position' => 5,
                'key' => 'partidul_umanist_social_liberal_voturi',
                'name' => 'PARTIDUL UMANIST SOCIAL LIBERAL',
            ],
            [
                'position' => 6,
                'key' => 'alianta_dreapta_unita_usr_pmp_forta_dreptei_voturi',
                'name' => 'ALIANȚA DREAPTA UNITĂ USR - PMP - FORȚA DREPTEI',
            ],
            [
                'position' => 7,
                'key' => 'romania_socialista_voturi',
                'name' => 'ROMÂNIA SOCIALISTĂ',
            ],
            [
                'position' => 8,
                'key' => 'partidul_romania_mare_voturi',
                'name' => 'PARTIDUL ROMÂNIA MARE',
            ],
            [
                'position' => 9,
                'key' => 'partidul_patriotilor_voturi',
                'name' => 'PARTIDUL PATRIOȚILOR',
            ],
            [
                'position' => 10,
                'key' => 'partidul_alternativa_dreapta_voturi',
                'name' => 'PARTIDUL ALTERNATIVA DREAPTĂ',
            ],
            [
                'position' => 11,
                'key' => 'partidul_sos_romania_voturi',
                'name' => 'PARTIDUL S.O.S. ROMÂNIA',
            ],
            [
                'position' => 12,
                'key' => 'partidul_diaspora_unita_voturi',
                'name' => 'PARTIDUL DIASPORA UNITĂ',
            ],
            [
                'position' => 13,
                'key' => 'pirvanescu_paula_marinela_voturi',
                'name' => 'PÎRVĂNESCU PAULA-MARINELA',
            ],
            [
                'position' => 14,
                'key' => 'gheorghe_vlad_dan_voturi',
                'name' => 'GHEORGHE VLAD-DAN',
            ],
            [
                'position' => 15,
                'key' => 'stefanuta_nicolae_bogdanel_voturi',
                'name' => 'ȘTEFĂNUȚĂ NICOLAE-BOGDĂNEL',
            ],
            [
                'position' => 16,
                'key' => 'sosoaca_dumitru_silvestru_voturi',
                'name' => 'ȘOȘOACĂ DUMITRU-SILVESTRU',
            ],
        ]);
    }
}
