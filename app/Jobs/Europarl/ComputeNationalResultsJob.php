<?php

declare(strict_types=1);

namespace App\Jobs\Europarl;

use App\Concerns\EuroparlJob;
use App\Enums\DivisionEnum;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ComputeNationalResultsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use EuroparlJob;

    public Collection $counties;

    /**
     * Create a new job instance.
     */
    public function __construct(Collection $counties)
    {
        $this->counties = $counties;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->importCandidateResults();
        $this->importTurnouts();
    }

    protected function importCandidateResults(): void
    {
        $collection = $this->counties
            ->map(fn (string $code) => Cache::driver('file')->get("europarl_county_results_{$code}"))
            ->tap(function (Collection $collection) {
                if (! $collection->every(fn ($countyResult) => $countyResult !== null)) {
                    throw new Exception('Not all county results are available');
                }
            })
            ->flatten(1)
            ->groupBy('Name');

        $this->getCandidates()
            ->map(fn (array $candidate) => $this->makeCandidateResult(
                $candidate,
                $collection->get($candidate['name']),
                division: DivisionEnum::NATIONAL,
            ))
            ->tap(function (Collection $chunk) {
                $this->upsertCandidateResults($chunk);
            });
    }

    protected function importTurnouts(): void
    {
        $collection = $this->counties
            ->map(fn (string $code) => Cache::driver('file')->get("europarl_turnouts_{$code}"))
            ->tap(function (Collection $collection) {
                if (! $collection->every(fn ($turnout) => $turnout !== null)) {
                    throw new Exception('Not all turnouts are available');
                }
            })
            ->flatten(1);

        // Global level
        $this->upsertTurnouts(collect([
            $this->makeTurnout(
                $collection,
                division: DivisionEnum::NATIONAL,
            ),
        ]));
    }
}
