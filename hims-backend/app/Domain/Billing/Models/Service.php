<?php

namespace App\Domain\Billing\Models;

use App\Domain\Hospitals\Models\Department;
use App\Domain\Hospitals\Models\Hospital;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $fillable = [
        'hospital_id',
        'department_id',
        'name',
        'code',
        'service_type',
        'category',
        'hsn_sac_code',
        'base_rate',
        'cgst_rate',
        'sgst_rate',
        'igst_rate',
        'is_tax_exempt',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'base_rate' => 'decimal:2',
            'cgst_rate' => 'decimal:2',
            'sgst_rate' => 'decimal:2',
            'igst_rate' => 'decimal:2',
            'is_tax_exempt' => 'boolean',
        ];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function scopeForHospital(Builder $query, int $hospitalId): Builder
    {
        return $query->where('hospital_id', $hospitalId);
    }
}
