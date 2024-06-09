<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\ImportCountyPresenceForCountyJob;
use App\Jobs\ImportLocalPresenceForCountyJob;
use App\Models\County;
use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class DispatchImportJobsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import-jobs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $counties = County::all();

        Bus::batch([
            new ImportCountyPresenceForCountyJob($counties->first()->code),
            ...$counties
                ->map(fn ($county) => new ImportLocalPresenceForCountyJob($county))
                ->all(),
        ])->before(function (Batch $batch) {
            logger()->info('ImportLocalPresenceForCountyJob batch created', ['batch' => $batch->id]);
        })->progress(function (Batch $batch) {
            logger()->info('ImportLocalPresenceForCountyJob completed successfully', [
                'batch' => $batch->id,
                'totalJobs' => $batch->totalJobs,
                'pendingJobs' => $batch->pendingJobs,
                'failedJobs' => $batch->failedJobs,
            ]);
        })->then(function (Batch $batch) {
            logger()->info('ImportLocalPresenceForCountyJob batch completed successfully', ['batch' => $batch->id]);
        })->dispatch();

        return self::SUCCESS;
    }
}
