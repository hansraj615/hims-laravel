<?php

namespace App\Domain\Users\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSessionHistory extends Model
{
    protected $fillable = [
        'user_id',
        'session_id',
        'login_method',
        'ip_address',
        'user_agent',
        'logged_in_at',
        'logged_out_at',
    ];

    protected function casts(): array
    {
        return [
            'logged_in_at' => 'datetime',
            'logged_out_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
