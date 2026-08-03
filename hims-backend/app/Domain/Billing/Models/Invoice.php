<?php

namespace App\Domain\Billing\Models;

use App\Domain\EMR\Models\Encounter;
use App\Domain\Hospitals\Models\Branch;
use App\Domain\Hospitals\Models\Hospital;
use App\Domain\Patients\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    protected $fillable = [
        'hospital_id',
        'branch_id',
        'patient_id',
        'encounter_id',
        'cashier_daybook_id',
        'invoice_number',
        'invoice_type',
        'payer_type',
        'scheme_type',
        'tpa_name',
        'claim_reference',
        'status',
        'subtotal',
        'discount_total',
        'taxable_total',
        'cgst_total',
        'sgst_total',
        'igst_total',
        'round_off',
        'grand_total',
        'paid_total',
        'balance_total',
        'billed_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'taxable_total' => 'decimal:2',
            'cgst_total' => 'decimal:2',
            'sgst_total' => 'decimal:2',
            'igst_total' => 'decimal:2',
            'round_off' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'balance_total' => 'decimal:2',
            'billed_at' => 'datetime',
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

    public function cashierDaybook(): BelongsTo
    {
        return $this->belongsTo(CashierDaybook::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
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
