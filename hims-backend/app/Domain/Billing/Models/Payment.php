<?php

namespace App\Domain\Billing\Models;

use App\Domain\Hospitals\Models\Branch;
use App\Domain\Hospitals\Models\Hospital;
use App\Domain\Patients\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'hospital_id',
        'branch_id',
        'invoice_id',
        'patient_id',
        'cashier_daybook_id',
        'receipt_number',
        'payment_type',
        'payment_mode',
        'amount',
        'status',
        'reference_number',
        'bank_name',
        'gateway_payload',
        'paid_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'gateway_payload' => 'array',
            'paid_at' => 'datetime',
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

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function cashierDaybook(): BelongsTo
    {
        return $this->belongsTo(CashierDaybook::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForHospital(Builder $query, int $hospitalId): Builder
    {
        return $query->where('hospital_id', $hospitalId);
    }
}
