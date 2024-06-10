<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Enums\DivisionEnum;
use App\Models\CandidateResult;
use App\Models\Party;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use stdClass;

trait EuroparlJob
{
    public int $ballotId = 118;

    public Collection $candidates;

    public Collection $parties;

    public Collection $candidateResults;

    public function loadData(): void
    {
        $this->loadParties();

        $this->loadCandidateResults();

        $this->loadCandidates();
    }

    protected function loadCandidates(): void
    {
        $this->candidates = collect([
            'UNIUNEA DEMOCRATĂ MAGHIARĂ DIN ROMÂNIA',
            'ALIANȚA ELECTORALĂ PSD PNL',
            'PARTIDUL REÎNNOIM PROIECTUL EUROPEAN AL ROMÂNIEI',
            'ALIANȚA AUR',
            'PARTIDUL UMANIST SOCIAL LIBERAL',
            'ALIANȚA DREAPTA UNITĂ USR - PMP - FORȚA DREPTEI',
            'ROMÂNIA SOCIALISTĂ',
            'PARTIDUL ROMÂNIA MARE',
            'PARTIDUL PATRIOȚILOR',
            'PARTIDUL ALTERNATIVA DREAPTĂ',
            'PARTIDUL S.O.S. ROMÂNIA',
            'PARTIDUL DIASPORA UNITĂ',
            'PÎRVĂNESCU PAULA-MARINELA',
            'GHEORGHE VLAD-DAN',
            'ȘTEFĂNUȚĂ NICOLAE-BOGDĂNEL',
            'ȘOȘOACĂ DUMITRU-SILVESTRU',
        ])
            ->map(fn (string $name, int $index) => [
                'position' => $index + 1,
                'name' => $name,
                'normalizedName' => Str::slug($name),
                'key' => Str::slug($name . '_voturi', '_'),

            ]);
    }

    protected function loadParties(): void
    {
        $this->parties = Party::query()
            ->get(['Id', 'Name', 'ShortName', 'Alias'])
            ->map(fn (Party $party) => [
                'id' => $party->Id,
                'name' => Str::slug($party->Name),
                'shortName' => $party->ShortName,
                'alias' => $party->Alias,
            ]);
    }

    protected function loadCandidateResults(): void
    {

        $this->candidateResults = CandidateResult::query()
            ->toBase()
            ->where('BallotId', $this->ballotId)
            ->get(['Id', 'Name', 'PartyId', 'CountyId', 'LocalityId', 'CountryId'])
            ->map(function (stdClass $candidateResult) {
                $candidateResult->Name = Str::slug($candidateResult->Name);

                return $candidateResult;
            });
    }

    protected function getPartyId(string $name): ?int
    {
        $name = Str::slug($name);

        $party = $this->parties
            ->firstWhere(
                fn (array $party) => $party['name'] === $name
                    || $party['shortName'] === $name
                    || $party['alias'] === $name
            );

        return data_get($party, 'id');
    }

    public function makeCandidateResult(
        array $candidate,
        Collection $collection,
        DivisionEnum $division,
        ?int $countyId = null,
        ?int $localityId = null,
        ?int $countryId = null,
    ): array {
        $key = ($division == DivisionEnum::NATIONAL) ? 'Votes' : $candidate['key'];

        $result = [
            'Votes' => $collection->sum($key),
            'BallotId' => $this->ballotId,
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
            'Division' => $division?->value,
            'BallotPosition' => $candidate['position'],
        ];

        $candidateResult = $this->candidateResults
            ->firstWhere(function (stdClass $candidateResult) use ($result, $candidate) {
                return $candidateResult->Name === $candidate['normalizedName']
                    && $candidateResult->PartyId === $result['PartyId']
                    && $candidateResult->CountyId === $result['CountyId']
                    && $candidateResult->LocalityId === $result['LocalityId']
                    && $candidateResult->CountryId === $result['CountryId'];
            });

        $result['Id'] = $candidateResult?->Id;

        return $result;
    }
}
