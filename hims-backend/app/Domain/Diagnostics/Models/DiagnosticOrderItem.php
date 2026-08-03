<?php

namespace App\Domain\Diagnostics\Models;

use App\Domain\Billing\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiagnosticOrderItem extends Model
{
    protected $fillable = [
        'diagnostic_order_id',
        'service_id',
        'service_code',
        'service_name',
        'category',
        'quantity',
        'unit_rate',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_rate' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(DiagnosticOrder::class, 'diagnostic_order_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
