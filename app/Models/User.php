<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'email',
    'password',
    'role',
    'avatar',
    'employee_code',
    'phone',
    'date_of_birth',
    'gender',
    'citizen_id',
    'address',
    'staff_position',
    'hourly_rate',
    'account_status',
    'hired_at',
    'display_password',
])]
#[Hidden(['password', 'remember_token', 'expired_booking_holds', 'booking_blocked_until', 'display_password'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

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
            'expired_booking_holds' => 'integer',
            'booking_blocked_until' => 'datetime',
            'date_of_birth' => 'date',
            'hired_at' => 'date',
            'hourly_rate' => 'integer',
            'display_password' => 'encrypted',
        ];
    }

    /**
     * Check if user is admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is staff.
     */
    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    /**
     * Check if user is regular user.
     */
    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    /**
     * Get user's orders.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function shiftAssignments()
    {
        return $this->hasMany(WorkShiftAssignment::class);
    }

    public function isAccountActive(): bool
    {
        return ($this->account_status ?? 'active') === 'active';
    }

    public function positionLabel(): string
    {
        return WorkShiftAssignment::POSITIONS[$this->staff_position ?? ''] ?? 'Nhân viên rạp';
    }
}
