<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $perPage = 10;

    protected $fillable = [
        'name', 'username', 'email', 'password', 'phone', 'timezone', 'account_type', 'status',
        'is_admin', 'staff_role', 'is_active', 'avatar_path', 'profile_photo_path',
        'email_verified_at', 'phone_verified_at', 'last_login_at', 'last_active_at',
        'suspended_at', 'suspension_reason', 'emergency_contact_name',
        'emergency_contact_phone', 'email_notifications', 'sms_notifications',
        'whatsapp_notifications', 'marketing_consent',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
            'email_notifications' => 'boolean',
            'sms_notifications' => 'boolean',
            'whatsapp_notifications' => 'boolean',
            'marketing_consent' => 'boolean',
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

    public function directPermissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'permission_user')
            ->withPivot('granted_by')
            ->withTimestamps();
    }

    public function bookings(): HasMany { return $this->hasMany(Booking::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
    public function identityDocuments(): HasMany { return $this->hasMany(UserIdentityDocument::class); }
    public function currentIdentity(): HasOne { return $this->hasOne(UserIdentityDocument::class)->where('is_current', true)->latestOfMany(); }
    public function staffLoginHistories(): HasMany { return $this->hasMany(StaffLoginHistory::class); }
    public function serviceRequests(): HasMany { return $this->hasMany(ServiceRequest::class); }
    public function supportTickets(): HasMany { return $this->hasMany(SupportTicket::class); }
    public function reviews(): HasMany { return $this->hasMany(Review::class); }

    public function isStaff(): bool
    {
        return (bool) $this->is_admin || filled($this->staff_role);
    }

    public function isAdministrator(): bool
    {
        return (bool) $this->is_admin || $this->staff_role === 'administrator';
    }

    public function isSuspended(): bool
    {
        return $this->is_active === false || $this->status === 'suspended' || $this->suspended_at !== null;
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isAdministrator()) {
            return true;
        }

        $direct = $this->relationLoaded('directPermissions')
            ? $this->directPermissions
            : $this->directPermissions()->get();

        if ($direct->contains('slug', $permission)) {
            return true;
        }

        [$module] = array_pad(explode('.', $permission, 2), 2, null);
        if ($module && $direct->contains('slug', $module.'.manage')) {
            return true;
        }

        return $this->roles()->whereHas('permissions', function ($query) use ($permission, $module): void {
            $query->where('slug', $permission)
                ->when($module, fn ($q) => $q->orWhere('slug', $module.'.manage'));
        })->exists();
    }
}
