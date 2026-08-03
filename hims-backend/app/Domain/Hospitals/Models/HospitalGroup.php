<?php

namespace App\Domain\Hospitals\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HospitalGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'legal_name',
        'status',
    ];

    public function hospitals(): HasMany
    {
        return $this->hasMany(Hospital::class);
    }
}
