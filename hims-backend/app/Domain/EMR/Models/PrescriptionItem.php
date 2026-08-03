<?php

namespace App\Domain\EMR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionItem extends Model
{
    protected $fillable = [
        'prescription_id',
        'medicine_name',
        'generic_name',
        'formulation',
        'strength',
        'route',
        'frequency',
        'duration',
        'quantity',
        'instructions',
        'is_schedule_h',
        'is_schedule_h1',
        'cdsco_metadata',
        'fhir_payload',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'is_schedule_h' => 'boolean',
            'is_schedule_h1' => 'boolean',
            'cdsco_metadata' => 'array',
            'fhir_payload' => 'array',
        ];
    }

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }
}
