<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\Europarl\ComputeNationalResultsJob;
use App\Jobs\Europarl\ImportAbroadJob;
use App\Jobs\Europarl\ImportCountyJob;
use App\Jobs\Europarl\PrepareImportJob;
use App\Models\County;
use Illuminate\Bus\Batch;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;

class DispatchEuroparlJobsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:import:europarl';

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
        $counties = $this->getCounties();

        Bus::chain([
            // Prepare for import
            new PrepareImportJob($counties->pluck('code')),

            // Străinătate
            new ImportAbroadJob,

            // Import data for each county
            Bus::batch($counties->map(fn ($county) => new ImportCountyJob($county->code, $county->getKey())))
                ->before(function (Batch $batch) {
                    logger()->info('EuroparlImportForCountyJob batch created', [
                        'batch' => $batch->id,
                    ]);
                })
                ->progress(function (Batch $batch) {
                    logger()->info('EuroparlImportForCountyJob completed successfully', [
                        'batch' => $batch->id,
                        'totalJobs' => $batch->totalJobs,
                        'pendingJobs' => $batch->pendingJobs,
                        'failedJobs' => $batch->failedJobs,
                    ]);
                })
                ->then(function (Batch $batch) {
                    logger()->info('EuroparlImportForCountyJob batch completed successfully', [
                        'batch' => $batch->id,
                    ]);
                }),

            // Compute national totals from data above,
            new ComputeNationalResultsJob($counties->pluck('code')),

        ])->dispatch();

        return self::SUCCESS;
    }

    protected function getCounties(): Collection
    {
        return County::query()
            ->get();
    }
}
