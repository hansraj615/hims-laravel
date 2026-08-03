<?php

namespace App\Domain\Billing\Models;

use App\Domain\Hospitals\Models\Branch;
use App\Domain\Hospitals\Models\Hospital;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashierDaybook extends Model
{
    protected $fillable = [
        'hospital_id',
        'branch_id',
        'cashier_user_id',
        'business_date',
        'status',
        'opening_cash',
        'cash_collected',
        'cash_refunded',
        'closing_cash',
        'opened_at',
        'closed_at',
        'closed_by',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'opening_cash' => 'decimal:2',
            'cash_collected' => 'decimal:2',
            'cash_refunded' => 'decimal:2',
            'closing_cash' => 'decimal:2',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
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

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function scopeForHospital(Builder $query, int $hospitalId): Builder
    {
        return $query->where('hospital_id', $hospitalId);
    }
}
