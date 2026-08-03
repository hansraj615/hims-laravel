<?php

namespace App\Domain\IPD\Models;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpdChargeLine extends Model
{
    protected $fillable = [
        'hospital_id',
        'admission_id',
        'service_id',
        'charge_date',
        'description',
        'source',
        'quantity',
        'unit_rate',
        'amount',
        'status',
        'invoice_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'charge_date' => 'date',
            'quantity' => 'decimal:2',
            'unit_rate' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
