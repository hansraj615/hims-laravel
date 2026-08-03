<?php

namespace App\Domain\IPD\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdmissionClearance extends Model
{
    public const TYPES = ['nursing', 'diagnostics', 'billing', 'ward'];

    protected $fillable = [
        'hospital_id',
        'admission_id',
        'clearance_type',
        'status',
        'notes',
        'cleared_by',
        'cleared_at',
    ];

    protected function casts(): array
    {
        return [
            'cleared_at' => 'datetime',
        ];
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function clearedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cleared_by');
    }

    public function isCleared(): bool
    {
        return in_array($this->status, ['cleared', 'waived'], true);
    }
}
