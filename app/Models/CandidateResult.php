<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CandidateResult extends Model
{
    use HasFactory;

    public $table = 'candidateresults';

    protected $connection = 'import';

    protected $primaryKey = 'Id';

    public $timestamps = false;

    protected $fillable = ['*'];
}
