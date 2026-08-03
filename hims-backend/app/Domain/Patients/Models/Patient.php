<?php

namespace App\Domain\Patients\Models;

use App\Domain\Appointments\Models\Appointment;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Payment;
use App\Domain\EMR\Models\Encounter;
use App\Domain\Hospitals\Models\Branch;
use App\Domain\Hospitals\Models\Hospital;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    use HasFactory;

    protected $fillable = [
        'hospital_id',
        'branch_id',
        'uhid',
        'salutation',
        'patient_category',
        'registration_source',
        'referred_by',
        'first_name',
        'middle_name',
        'last_name',
        'gender',
        'blood_group',
        'marital_status',
        'occupation',
        'nationality',
        'preferred_language',
        'date_of_birth',
        'age_years',
        'age_months',
        'age_days',
        'mobile',
        'alternate_mobile',
        'email',
        'address',
        'city',
        'district',
        'state',
        'pincode',
        'country',
        'identity_type',
        'identity_number',
        'abha_id',
        'abha_number',
        'abha_address',
        'abha_verification_status',
        'abha_verified_at',
        'abdm_last_transaction_id',
        'abdm_consent_reference',
        'abdm_scan_share_payload',
        'abdm_profile_payload',
        'guardian_name',
        'guardian_relation',
        'guardian_mobile',
        'emergency_contact_name',
        'emergency_contact_mobile',
        'emergency_contact_relation',
        'consent_sms',
        'consent_email',
        'consent_whatsapp',
        'remarks',
        'status',
        'registered_at',
        'registered_by',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'abha_verified_at' => 'datetime',
            'abdm_scan_share_payload' => 'array',
            'abdm_profile_payload' => 'array',
            'consent_sms' => 'boolean',
            'consent_email' => 'boolean',
            'consent_whatsapp' => 'boolean',
            'registered_at' => 'datetime',
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

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(PatientDocument::class);
    }

    public function scopeForHospital(Builder $query, int $hospitalId): Builder
    {
        return $query->where('hospital_id', $hospitalId);
    }

    public function getFullNameAttribute(): string
    {
        return collect([$this->first_name, $this->middle_name, $this->last_name])
            ->filter()
            ->implode(' ');
    }
}
