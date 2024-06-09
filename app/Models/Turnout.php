<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Turnout extends Model
{
    use HasFactory;

    protected $connection = 'import';

    protected $primaryKey = 'Id';

    public $timestamps = false;
}
