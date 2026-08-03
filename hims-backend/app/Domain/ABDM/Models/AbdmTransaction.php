<?php

namespace App\Domain\ABDM\Models;

use App\Domain\Hospitals\Models\Branch;
use App\Domain\Hospitals\Models\Hospital;
use App\Domain\Patients\Models\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbdmTransaction extends Model
{
    protected $fillable = [
        'hospital_id',
        'branch_id',
        'patient_id',
        'user_id',
        'operation',
        'provider',
        'status',
        'external_txn_id',
        'abha_number',
        'abha_address',
        'mobile',
        'request_payload',
        'response_payload',
        'error_message',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
