<?php

namespace App\Domain\Appointments\Models;

use App\Domain\Hospitals\Models\Hospital;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorLeave extends Model
{
    protected $fillable = [
        'hospital_id',
        'doctor_user_id',
        'start_date',
        'end_date',
        'reason',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
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

    public function covers(string $date): bool
    {
        return $this->start_date->toDateString() <= $date
            && $this->end_date->toDateString() >= $date;
    }
}
