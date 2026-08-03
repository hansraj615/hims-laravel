<?php

namespace App\Domain\OPD\Models;

use App\Domain\Appointments\Models\Appointment;
use App\Domain\EMR\Models\Encounter;
use App\Domain\Hospitals\Models\Branch;
use App\Domain\Hospitals\Models\Department;
use App\Domain\Hospitals\Models\Hospital;
use App\Domain\Patients\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OpdQueue extends Model
{
    protected $table = 'opd_queues';

    protected $fillable = [
        'hospital_id',
        'branch_id',
        'appointment_id',
        'patient_id',
        'department_id',
        'doctor_user_id',
        'queue_date',
        'token_number',
        'token_prefix',
        'token_code',
        'status',
        'vitals',
        'vitals_recorded_at',
        'vitals_recorded_by',
        'called_at',
        'started_at',
        'completed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'queue_date' => 'date',
            'vitals' => 'array',
            'vitals_recorded_at' => 'datetime',
            'called_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
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

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
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

    public function vitalsRecorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vitals_recorded_by');
    }

    public function encounter(): HasOne
    {
        return $this->hasOne(Encounter::class);
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
