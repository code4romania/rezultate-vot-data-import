<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Concerns\EuroparlJob;
use App\Models\CandidateResult;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class EuroparlComputeNationalResultsJob implements ShouldQueue
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
        $collection = $this->counties
            ->map(fn (string $code) => Cache::driver('file')->get("europarl_county_results_{$code}"));

        if (! $collection->every(fn ($countyResult) => $countyResult !== null)) {
            throw new Exception('Not all county results are available');
        }

        $this->candidates
            ->map(fn (array $candidate) => $this->makeCandidateResult(
                $candidate,
                $collection,
            ))
            ->dump()
            ->tap(function (Collection $chunk) {
                CandidateResult::upsert($chunk->all(), ['Id']);
            });

        // dd($this->counties);
    }
}
