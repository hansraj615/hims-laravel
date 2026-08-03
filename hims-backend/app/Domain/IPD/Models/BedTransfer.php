<?php

namespace App\Domain\IPD\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BedTransfer extends Model
{
    protected $fillable = [
        'hospital_id',
        'admission_id',
        'from_bed_id',
        'to_bed_id',
        'from_ward_id',
        'to_ward_id',
        'reason',
        'transferred_by',
        'transferred_at',
    ];

    protected function casts(): array
    {
        return [
            'transferred_at' => 'datetime',
        ];
    }

    public function admission(): BelongsTo
    {
        return $this->belongsTo(Admission::class);
    }

    public function fromBed(): BelongsTo
    {
        return $this->belongsTo(Bed::class, 'from_bed_id');
    }

    public function toBed(): BelongsTo
    {
        return $this->belongsTo(Bed::class, 'to_bed_id');
    }

    public function transferredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }
}
