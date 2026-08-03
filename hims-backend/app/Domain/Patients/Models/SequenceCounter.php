<?php

namespace App\Domain\Patients\Models;

use Illuminate\Database\Eloquent\Model;

class SequenceCounter extends Model
{
    protected $fillable = [
        'hospital_id',
        'branch_id',
        'name',
        'current_value',
    ];
}
