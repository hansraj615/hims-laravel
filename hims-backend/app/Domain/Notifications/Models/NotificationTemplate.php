<?php

namespace App\Domain\Notifications\Models;

use App\Domain\Hospitals\Models\Hospital;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationTemplate extends Model
{
    protected $fillable = [
        'hospital_id',
        'code',
        'channel',
        'subject',
        'body',
        'status',
    ];

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }
}
