<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Admin user for Filament console (admin.sos-expat.com).
 *
 * NOTE: This model will be extended to implement Filament\Models\Contracts\FilamentUser
 * during Sprint 5 when `composer require filament/filament` is executed.
 *
 * For now (Sprint 1), this is a plain Authenticatable that can be created,
 * but won't be used until Sprint 5 wires it into Filament.
 *
 * This is SEPARATE from Firebase Auth used by API endpoints.
 * Only admins of SOS-Expat have accounts here (not partners, not subscribers).
 *
 * Roles: super_admin | admin | accountant | support
 * - super_admin: full access, impersonate, delete, 2FA reset
 * - admin: CRUD complete, no user management, can impersonate
 * - accountant: invoices & financial reports (read + mark paid)
 * - support: subscribers & partners (read + limited edit)
 */
class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, SoftDeletes;

    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_ACCOUNTANT = 'accountant';
    public const ROLE_SUPPORT = 'support';

    public const FILAMENT_ROLES = [
        self::ROLE_SUPER_ADMIN,
        self::ROLE_ADMIN,
        self::ROLE_ACCOUNTANT,
        self::ROLE_SUPPORT,
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_active' => 'boolean',
        'two_factor_confirmed_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && in_array($this->role, self::FILAMENT_ROLES, true);
    }

    public function canAccessFilament(): bool
    {
        return $this->is_active && in_array($this->role, self::FILAMENT_ROLES, true);
    }

    // --- Role checks ---

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_SUPER_ADMIN, self::ROLE_ADMIN], true);
    }

    public function isAccountant(): bool
    {
        return $this->role === self::ROLE_ACCOUNTANT;
    }

    public function isSupport(): bool
    {
        return $this->role === self::ROLE_SUPPORT;
    }

    public function canManageUsers(): bool
    {
        return $this->isSuperAdmin();
    }

    public function canImpersonate(): bool
    {
        return $this->isAdmin();
    }

    public function canMarkInvoicesPaid(): bool
    {
        return $this->isAdmin() || $this->isAccountant();
    }
}
