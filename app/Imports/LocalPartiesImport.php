<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\CandidateResult;
use Illuminate\Support\Collection;

class LocalPartiesImport extends LocalImport
{
    public function __construct()
    {
        parent::__construct();

        $this->candidateResults = CandidateResult::query()
            ->whereIn('BallotId', [116, 117])
            ->get(['Id', 'Name', 'BallotId', 'CountyId', 'LocalityId', 'PartyName', 'PartyId']);
    }

    public function collection(Collection $collection)
    {
        $chunk = $collection
            ->map(function (Collection $row) {
                $ballotId = $this->getBallotId($row['tip_proces_electoral']);

                if (\in_array($ballotId, [114, 115])) {
                    return null;
                }

                $this->loadCountyAndLocalities($row['denumire_judet']);

                $party = $this->findPartyByName($row['aliantanume_partidindependent']);

                $result = [
                    'Votes' => 0,
                    'BallotId' => $ballotId,
                    'Name' => $row['aliantanume_partidindependent'],
                    'ShortName' => data_get($party, 'shortName'),
                    'PartyName' => $row['aliantanume_partidindependent'],
                    'PartyId' => data_get($party, 'id'),
                    'YesVotes' => 0,
                    'NoVotes' => 0,
                    'SeatsGained' => 0,
                    'Division' => $this->getDivision($row['tip_proces_electoral'])->value,
                    'TotalSeats' => 0,
                    'Seats1' => 0,
                    'Seats2' => 0,
                    'OverElectoralThreshold' => 0,
                    'CountryId' => null,
                    'CountyId' => $this->county['id'],
                    'LocalityId' => $this->getLocalityId($row['cod_uat'], $row['denumire_uat']),
                    'BallotPosition' => $row['pozitie_pe_buletin'],
                ];

                $candidateResult = $this->candidateResults
                    ->firstWhere(
                        fn (CandidateResult $candidateResult) => $candidateResult->BallotId === $result['BallotId']
                            && $candidateResult->Name === $result['Name']
                            && $candidateResult->CountyId === $result['CountyId']
                            && $candidateResult->LocalityId === $result['LocalityId']
                            && $candidateResult->PartyName === $result['PartyName']
                            && $candidateResult->PartyId === $result['PartyId']
                    );

                $result['Id'] = $candidateResult?->Id;

                return $result;
            })
            ->filter();

        CandidateResult::upsert(
            $chunk->all(),
            ['Id']
        );
    }
}
