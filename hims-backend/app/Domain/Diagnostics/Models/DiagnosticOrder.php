<?php

namespace App\Domain\Diagnostics\Models;

use App\Domain\Appointments\Models\Appointment;
use App\Domain\Billing\Models\Invoice;
use App\Domain\EMR\Models\Encounter;
use App\Domain\Hospitals\Models\Branch;
use App\Domain\Hospitals\Models\Hospital;
use App\Domain\Patients\Models\Patient;
use App\Domain\Patients\Models\PatientDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiagnosticOrder extends Model
{
    public const CATEGORIES = ['pathology', 'radiology', 'procedure'];

    public const STATUSES = [
        'ordered',
        'sample_collected',
        'in_progress',
        'result_ready',
        'billed',
        'cancelled',
    ];

    protected $fillable = [
        'hospital_id',
        'branch_id',
        'order_number',
        'patient_id',
        'encounter_id',
        'appointment_id',
        'category',
        'priority',
        'status',
        'clinical_notes',
        'result_summary',
        'result_payload',
        'ordered_by',
        'ordered_at',
        'collected_by',
        'collected_at',
        'resulted_by',
        'resulted_at',
        'invoice_id',
        'patient_document_id',
    ];

    protected function casts(): array
    {
        return [
            'result_payload' => 'array',
            'ordered_at' => 'datetime',
            'collected_at' => 'datetime',
            'resulted_at' => 'datetime',
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

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function resultedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resulted_by');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function patientDocument(): BelongsTo
    {
        return $this->belongsTo(PatientDocument::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DiagnosticOrderItem::class);
    }

    public function scopeForHospital(Builder $query, int $hospitalId): Builder
    {
        return $query->where('hospital_id', $hospitalId);
    }
}
