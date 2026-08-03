<?php

namespace App\Domain\IPD\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpdNursingNote extends Model
{
    protected $fillable = [
        'hospital_id',
        'admission_id',
        'recorded_at',
        'vitals',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'vitals' => 'array',
        ];
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
