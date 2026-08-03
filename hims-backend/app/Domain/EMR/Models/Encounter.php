<?php

namespace App\Domain\EMR\Models;

use App\Domain\Appointments\Models\Appointment;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Hospitals\Models\Branch;
use App\Domain\Hospitals\Models\Department;
use App\Domain\Hospitals\Models\Hospital;
use App\Domain\OPD\Models\OpdQueue;
use App\Domain\Patients\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Encounter extends Model
{
    protected $fillable = [
        'hospital_id',
        'branch_id',
        'patient_id',
        'appointment_id',
        'opd_queue_id',
        'department_id',
        'doctor_user_id',
        'encounter_number',
        'encounter_type',
        'status',
        'vitals',
        'chief_complaints',
        'clinical_history',
        'examination',
        'diagnoses',
        'care_plan',
        'follow_up',
        'fhir_payload',
        'fhir_version',
        'started_at',
        'completed_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'vitals' => 'array',
            'chief_complaints' => 'array',
            'clinical_history' => 'array',
            'examination' => 'array',
            'diagnoses' => 'array',
            'care_plan' => 'array',
            'follow_up' => 'array',
            'fhir_payload' => 'array',
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

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function queueEntry(): BelongsTo
    {
        return $this->belongsTo(OpdQueue::class, 'opd_queue_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_user_id');
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function scopeForHospital(Builder $query, int $hospitalId): Builder
    {
        return $query->where('hospital_id', $hospitalId);
    }
}
