<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $perPage = 10;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'account_type', 'status',
        'is_admin', 'staff_role', 'is_active', 'avatar_path', 'profile_photo_path',
        'email_verified_at', 'last_login_at', 'last_active_at', 'suspended_at',
        'suspension_reason',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'last_active_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function isStaff(): bool
    {
        return (bool) $this->is_admin || filled($this->staff_role);
    }

    public function isSuspended(): bool
    {
        return ! $this->is_active || $this->status === 'suspended' || $this->suspended_at !== null;
    }
}
