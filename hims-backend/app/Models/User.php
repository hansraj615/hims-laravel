<?php

namespace App\Models;

use App\Domain\Appointments\Models\Appointment;
use App\Domain\Billing\Models\CashierDaybook;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Payment;
use App\Domain\EMR\Models\Encounter;
use App\Domain\EMR\Models\Prescription;
use App\Domain\Hospitals\Models\UserHospitalBranchAssignment;
use App\Domain\OPD\Models\OpdQueue;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Notifications\ResetPasswordNotification;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'mobile',
        'password',
        'status',
        'locked_at',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'locked_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function hospitalAssignments(): HasMany
    {
        return $this->hasMany(UserHospitalBranchAssignment::class);
    }

    public function doctorAppointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'doctor_user_id');
    }

    public function opdQueueAssignments(): HasMany
    {
        return $this->hasMany(OpdQueue::class, 'doctor_user_id');
    }

    public function doctorEncounters(): HasMany
    {
        return $this->hasMany(Encounter::class, 'doctor_user_id');
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class, 'prescribed_by');
    }

    public function cashierDaybooks(): HasMany
    {
        return $this->hasMany(CashierDaybook::class, 'cashier_user_id');
    }

    public function createdInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'created_by');
    }

    public function createdPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'created_by');
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}