<?php

namespace App\Domain\Hospitals\Models;

use App\Domain\Appointments\Models\Appointment;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Payment;
use App\Domain\EMR\Models\Encounter;
use App\Domain\OPD\Models\OpdQueue;
use App\Domain\Patients\Models\Patient;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'hospital_id',
        'name',
        'code',
        'facility_type',
        'address',
        'city',
        'state',
        'pincode',
        'phone',
        'timezone',
        'status',
    ];

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function opdQueues(): HasMany
    {
        return $this->hasMany(OpdQueue::class);
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
}
