<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class County extends Model
{
    use HasFactory;

    protected $connection = 'import';

    protected $primaryKey = 'CountyId';

    public $timestamps = false;

    public function getCodeAttribute(): string
    {
        return Str::lower($this->ShortName);
    }
}
