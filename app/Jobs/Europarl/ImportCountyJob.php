<?php

declare(strict_types=1);

namespace App\Jobs\Europarl;

use App\Concerns\UsesEuroparlUrl;
use App\Imports\Europarl\CountyImport;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class ImportCountyJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use UsesEuroparlUrl;

    public string $countyCode;

    public ?int $countyId;

    /**
     * Create a new job instance.
     */
    public function __construct(string $countyCode, ?int $countyId = null)
    {
        $this->countyCode = $countyCode;
        $this->countyId = $countyId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $filename = "{$this->countyCode}.csv";

        Storage::put(
            $filename,
            Http::withBasicAuth(config('services.import.europarl.username'), config('services.import.europarl.password'))
                ->get($this->getEuroparlUrl($this->countyCode))
                ->throw()
                ->body()
        );

        (new CountyImport($this->countyCode, $this->countyId))->import($filename);
    }
}
