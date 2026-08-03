<?php

namespace App\Domain\Patients\Models;

use App\Domain\Hospitals\Models\Hospital;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'hospital_id',
        'patient_id',
        'document_type',
        'title',
        'file_path',
        'mime_type',
        'file_size',
        'metadata',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'file_size' => 'integer',
        ];
    }

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
