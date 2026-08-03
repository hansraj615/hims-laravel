<?php

namespace App\Domain\Appointments\Models;

use App\Domain\Hospitals\Models\Hospital;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorFeeMaster extends Model
{
    protected $fillable = [
        'hospital_id',
        'doctor_user_id',
        'visit_type',
        'fee_amount',
        'effective_from',
        'effective_to',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'fee_amount' => 'decimal:2',
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_user_id');
    }

    public function scopeForHospital(Builder $query, int $hospitalId): Builder
    {
        return $query->where('hospital_id', $hospitalId);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
