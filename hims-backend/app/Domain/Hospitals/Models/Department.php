<?php

namespace App\Domain\Hospitals\Models;

use App\Domain\Appointments\Models\Appointment;
use App\Domain\Billing\Models\Service;
use App\Domain\EMR\Models\Encounter;
use App\Domain\OPD\Models\OpdQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'hospital_id',
        'branch_id',
        'name',
        'code',
        'department_type',
        'status',
    ];

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(Hospital::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function opdQueues(): HasMany
    {
        return $this->hasMany(OpdQueue::class);
    }

    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function scopeForHospital(Builder $query, int $hospitalId): Builder
    {
        return $query->where('hospital_id', $hospitalId);
    }

    public function scopeForBranch(Builder $query, ?int $branchId): Builder
    {
        return $branchId === null
            ? $query
            : $query->where(function (Builder $query) use ($branchId): void {
                $query->whereNull('branch_id')->orWhere('branch_id', $branchId);
            });
    }
}
