<?php

namespace App\Domain\IPD\Models;

use App\Domain\Hospitals\Models\Branch;
use App\Domain\Hospitals\Models\Hospital;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bed extends Model
{
    protected $fillable = [
        'hospital_id',
        'branch_id',
        'ward_id',
        'bed_number',
        'bed_type',
        'status',
        'current_admission_id',
    ];

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function currentAdmission(): BelongsTo
    {
        return $this->belongsTo(Admission::class, 'current_admission_id');
    }

    public function scopeForHospital(Builder $query, int $hospitalId): Builder
    {
        return $query->where('hospital_id', $hospitalId);
    }
}
