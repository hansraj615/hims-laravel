<?php

namespace App\Domain\Appointments\Models;

use App\Domain\Hospitals\Models\Branch;
use App\Domain\Hospitals\Models\Department;
use App\Domain\Hospitals\Models\Hospital;
use App\Domain\OPD\Models\OpdQueue;
use App\Domain\Patients\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Appointment extends Model
{
    protected $fillable = [
        'hospital_id',
        'branch_id',
        'patient_id',
        'department_id',
        'doctor_user_id',
        'appointment_number',
        'appointment_date',
        'slot_start',
        'slot_end',
        'visit_type',
        'source',
        'priority',
        'status',
        'fee_amount',
        'payment_status',
        'reason',
        'cancellation_reason',
        'checked_in_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'appointment_date' => 'date',
            'checked_in_at' => 'datetime',
            'fee_amount' => 'decimal:2',
        ];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_user_id');
    }

    public function queueEntry(): HasOne
    {
        return $this->hasOne(OpdQueue::class);
    }

    public function scopeForHospital(Builder $query, int $hospitalId): Builder
    {
        return $query->where('hospital_id', $hospitalId);
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }
}
