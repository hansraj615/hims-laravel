<?php

namespace App\Domain\Users\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginOtpChallenge extends Model
{
    protected $fillable = [
        'user_id',
        'mobile',
        'otp_hash',
        'purpose',
        'expires_at',
        'consumed_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUsable(Builder $query): Builder
    {
        return $query
            ->whereNull('consumed_at')
            ->where('expires_at', '>=', now());
    }
}
