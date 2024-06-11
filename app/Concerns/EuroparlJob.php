<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Enums\DivisionEnum;
use App\Enums\TurnoutEnum;
use App\Models\CandidateResult;
use App\Models\Party;
use App\Models\Turnout;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use stdClass;

trait EuroparlJob
{
    public int $ballotId = 118;

    public ?Collection $candidates = null;

    public ?Collection $parties = null;

    public ?Collection $candidateResults = null;

    public ?Collection $turnouts = null;

    protected function getCandidates(): Collection
    {
        if (blank($this->candidates)) {
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

        return $this->candidates;
    }

    protected function getParties(): Collection
    {
        if (blank($this->parties)) {
            $this->parties = Party::query()
                ->get(['Id', 'Name', 'ShortName', 'Alias'])
                ->map(fn (Party $party) => [
                    'id' => $party->Id,
                    'name' => Str::slug($party->Name),
                    'shortName' => $party->ShortName,
                    'alias' => $party->Alias,
                ]);
        }

        return $this->parties;
    }

    protected function getCandidateResults(): Collection
    {
        if (blank($this->candidateResults)) {
            $this->candidateResults = CandidateResult::query()
                ->toBase()
                ->where('BallotId', $this->ballotId)
                ->get(['Id', 'Name', 'PartyId', 'CountyId', 'LocalityId', 'CountryId', 'Division'])
                ->map(function (stdClass $candidateResult) {
                    $candidateResult->Name = Str::slug($candidateResult->Name);

                    return $candidateResult;
                });
        }

        return $this->candidateResults;
    }

    protected function getTurnouts(): Collection
    {
        if (blank($this->turnouts)) {
            $this->turnouts = Turnout::query()
                ->toBase()
                ->where('BallotId', $this->ballotId)
                ->get(['Id', 'Division', 'CountyId', 'LocalityId', 'CountryId']);
        }

        return $this->turnouts;
    }

    protected function getPartyId(string $name): ?int
    {
        $name = Str::slug($name);

        $party = $this->getParties()
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
        $votesKey = ($division === DivisionEnum::NATIONAL) ? 'Votes' : $candidate['key'];

        $result = [
            'Votes' => $collection->sum($votesKey),
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

        $candidateResult = $this->getCandidateResults()
            ->firstWhere(function (stdClass $candidateResult) use ($result, $candidate) {
                return $candidateResult->Name === $candidate['normalizedName']
                    && $candidateResult->PartyId === $result['PartyId']
                    && $candidateResult->CountyId === $result['CountyId']
                    && $candidateResult->LocalityId === $result['LocalityId']
                    && $candidateResult->CountryId === $result['CountryId']
                    && $candidateResult->Division === $result['Division'];
            });

        $result['Id'] = $candidateResult?->Id;

        return $result;
    }

    public function upsertCandidateResults(Collection $collection, ?array $update = null): void
    {
        $rowsAffected = CandidateResult::upsert(
            $collection->all(),
            uniqueBy : ['Id'],
            update: $update
        );

        logger()->info('CandidateResult upserted', [
            'rowsAffected' => $rowsAffected,
        ]);
    }

    public function makeTurnout(
        Collection $collection,
        DivisionEnum $division,
        ?int $countyId = null,
        ?int $localityId = null,
        ?int $countryId = null,
    ): array {
        if ($division === DivisionEnum::NATIONAL) {
            $eligibleVotersKey = 'EligibleVoters';
            $totalVotesKey = 'TotalVotes';
            $nullVotesKey = 'NullVotes';
            $validVotesKey = 'ValidVotes';
        } else {
            $eligibleVotersKey = TurnoutEnum::EligibleVoters->value;
            $totalVotesKey = 'total_votes';
            $nullVotesKey = TurnoutEnum::NullVotes->value;
            $validVotesKey = TurnoutEnum::ValidVotes->value;
        }

        $result = [
            'EligibleVoters' => $collection->sum($eligibleVotersKey),
            'TotalVotes' => $collection->sum($totalVotesKey),
            'NullVotes' => $collection->sum($nullVotesKey),
            'ValidVotes' => $collection->sum($validVotesKey),
            'VotesByMail' => 0,
            'TotalSeats' => 0,
            'Coefficient' => 0,
            'Threshold' => 0,
            'Circumscription' => 0,
            'MinVotes' => 0,
            'Mandates' => 0,
            'CorrespondenceVotes' => 0,
            'SpecialListsVotes' => 0,
            'PermanentListsVotes' => 0, //$items->sum(TurnoutFieldEnum::VOTERS_ON_PERMANENT_LIST->value),
            'SuplimentaryVotes' => 0, //$items->sum(TurnoutFieldEnum::VOTERS_ON_COMPLEMENTARY_LIST->value),

            'BallotId' => $this->ballotId,
            'CountyId' => $countyId,
            'LocalityId' => $localityId,
            'CountryId' => $countryId,
            'Division' => $division?->value,
        ];

        $turnout = $this->getTurnouts()
            ->firstWhere(function (stdClass $turnout) use ($result) {
                return $turnout->CountyId === $result['CountyId']
                    && $turnout->LocalityId === $result['LocalityId']
                    && $turnout->CountryId === $result['CountryId']
                    && $turnout->Division === $result['Division'];
            });

        $result['Id'] = $turnout?->Id;

        return $result;
    }

    public function upsertTurnouts(Collection $collection, ?array $update = null): void
    {
        $rowsAffected = Turnout::upsert(
            $collection->all(),
            uniqueBy : ['Id'],
            update: $update ?? [
                'EligibleVoters',
                'TotalVotes',
                'NullVotes',
                'ValidVotes',
            ]
        );

        logger()->info('Turnout upserted', [
            'rowsAffected' => $rowsAffected,
        ]);
    }
}
