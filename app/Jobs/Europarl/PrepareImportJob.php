<?php

declare(strict_types=1);

namespace App\Jobs\Europarl;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PrepareImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

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
        logger()->info('EuroparlJobs init');

        logger()->info('Clearing cache and storage');
        Cache::driver('file')->flush();

        collect(Storage::files())
            ->filter(fn (string $file) => Str::endsWith($file, '.csv'))
            ->each(fn (string $file) => Storage::delete($file));
    }
}
