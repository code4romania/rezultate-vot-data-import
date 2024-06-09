<?php

declare(strict_types=1);

namespace App\Imports;

use App\Models\CandidateResult;
use Illuminate\Support\Collection;

class LocalCandidatesImport extends LocalImport
{
    public function __construct()
    {
        parent::__construct();

        $this->candidateResults = CandidateResult::query()
            ->whereIn('BallotId', [114, 115])
            ->get(['Id', 'Name', 'BallotId', 'CountyId', 'LocalityId', 'PartyName', 'PartyId']);
    }

    public function collection(Collection $collection)
    {
        $chunk = $collection
            ->map(function (Collection $row) {
                $ballotId = $this->getBallotId($row['tip_proces_electoral']);

                if (\in_array($ballotId, [116, 117])) {
                    return null;
                }

                $this->loadCountyAndLocalities($row['denumire_judet']);

                $party = $this->findPartyByName($row['aliantanume_partidindependent']);

                $result = [
                    'Votes' => 0,
                    'BallotId' => $ballotId,
                    'Name' => $row['nume_si_prenume'],
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
                    'BallotPosition' => $row['pozitie_lista'],
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
        // return new CandidateResult([
        //     // primari - importat individual candidates, LocalityId setat, Divison 3
        //     // cl - importat partide, LocalityId setat, Divison 3
        //     // primar capitala - importat individual candidates, LocalityId setat, Division 3,

        //     // pcj - importat individual candidates, LocalityId = null, Divison 2
        //     // cj - importat partide, LocalityId = null, Divison 2
        // ]);
    }

    // public function uniqueBy(): array
    // {
    //     return ['Name', 'LocalityId', 'CountyId', 'BallotId'];
    // }
}

//Cod Judet,Denumire Judet,Cod UAT,Denumire UAT,Tip proces electoral,Alianta/Nume partid/independent,Denumirea membrului din cadrul alianței care a propus candidatul,Nume si prenume	Pozitie lista
// 1,ALBA,1,MUNICIPIUL ALBA IULIA,CONSILIERI LOCALI,PARTIDUL SOCIAL DEMOCRAT,,VUŞCAN VOICU,1

//Id,Votes,BallotId,Name,ShortName,PartyName,PartyId,YesVotes,NoVotes,SeatsGained,Division,CountyId,LocalityId,TotalSeats,Seats1,Seats2,OverElectoralThreshold,CountryId,BallotPosition
//3494644,78,95,OLAR IOAN,NULL,PARTIDUL SOCIAL DEMOCRAT,13,0,0,0,3,1,2,0,0,0,0,NULL,0
