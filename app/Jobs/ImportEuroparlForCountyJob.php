<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Imports\EuroparlImport;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportEuroparlForCountyJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public string $countyCode;

    public ?int $countyId;

    public string $url;

    /**
     * Create a new job instance.
     */
    public function __construct(string $countyCode, ?int $countyId = null)
    {
        $this->countyCode = $countyCode;
        $this->countyId = $countyId;
        $this->url = Str::replace('{code}', $countyCode, config('services.import.europarl.url'));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $filename = "{$this->countyCode}.csv";

        if (! Storage::exists($filename)) {
            Storage::put(
                $filename,
                Http::withBasicAuth(config('services.import.europarl.username'), config('services.import.europarl.password'))
                    ->get($this->url)
                    ->throw()
                    ->body()
            );
        }

        (new EuroparlImport($this->countyId))->import($filename);

        // Storage::delete($filename);
    }
}
