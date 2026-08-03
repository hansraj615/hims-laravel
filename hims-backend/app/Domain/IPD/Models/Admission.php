<?php

namespace App\Domain\IPD\Models;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Hospitals\Models\Branch;
use App\Domain\Hospitals\Models\Department;
use App\Domain\Hospitals\Models\Hospital;
use App\Domain\Patients\Models\Patient;
use App\Domain\Patients\Models\PatientDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Admission extends Model
{
    public const ACTIVE_STATUSES = ['admitted'];

    public const EXIT_OUTCOMES = ['discharge', 'lama', 'dopr', 'death'];

    protected $fillable = [
        'hospital_id',
        'branch_id',
        'admission_number',
        'patient_id',
        'admitting_doctor_user_id',
        'department_id',
        'ward_id',
        'bed_id',
        'admitted_at',
        'provisional_diagnosis',
        'attendant_name',
        'attendant_mobile',
        'attendant_relation',
        'status',
        'discharge_outcome',
        'discharged_at',
        'discharge_summary',
        'discharge_package',
        'death_at',
        'invoice_id',
        'discharge_document_id',
        'created_by',
        'discharged_by',
    ];

    protected function casts(): array
    {
        return [
            'admitted_at' => 'datetime',
            'discharged_at' => 'datetime',
            'death_at' => 'datetime',
            'discharge_package' => 'array',
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

    public function admittingDoctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admitting_doctor_user_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function ward(): BelongsTo
    {
        return $this->belongsTo(Ward::class);
    }

    public function bed(): BelongsTo
    {
        return $this->belongsTo(Bed::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function dischargeDocument(): BelongsTo
    {
        return $this->belongsTo(PatientDocument::class, 'discharge_document_id');
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(BedTransfer::class);
    }

    public function nursingNotes(): HasMany
    {
        return $this->hasMany(IpdNursingNote::class);
    }

    public function chargeLines(): HasMany
    {
        return $this->hasMany(IpdChargeLine::class);
    }

    public function clearances(): HasMany
    {
        return $this->hasMany(AdmissionClearance::class);
    }

    public function scopeForHospital(Builder $query, int $hospitalId): Builder
    {
        return $query->where('hospital_id', $hospitalId);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }
}
