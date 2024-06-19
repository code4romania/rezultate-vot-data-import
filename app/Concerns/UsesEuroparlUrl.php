<?php

declare(strict_types=1);

namespace App\Concerns;

use Illuminate\Support\Str;

trait UsesEuroparlUrl
{
    public function getEuroparlUrl(string $code): string
    {
        return Str::replace(
            ['{stage}', '{code}'],
            [config('services.import.europarl.stage'), $code],
            config('services.import.europarl.url')
        );
    }
}
