<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * User for Filament consoles. This is SEPARATE from Firebase Auth used by API
 * endpoints — only admin/partner staff have accounts here, NOT subscribers.
 *
 * Two panels with different role sets:
 *
 *   admin.sos-expat.com — SOS-Expat staff
 *     - super_admin: full access, impersonate, delete, 2FA reset
 *     - admin:       CRUD complete, no user management, can impersonate
 *     - accountant:  invoices & financial reports (read + mark paid)
 *     - support:     subscribers & partners (read + limited edit)
 *
 *   partner-engine.sos-expat.com — partner-company staff
 *     - partner:        group admin, sees ALL cabinets of the partner
 *     - branch_manager: scoped to managed_group_labels (Phase 1 multi-cabinet)
 *
 * A partner-panel user MUST have partner_firebase_id set so PartnerScopedQuery
 * can filter every resource by partner. Branch managers additionally narrow
 * the scope to specific group_labels (cabinets) listed in managed_group_labels.
 */
class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable, SoftDeletes;

    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_ACCOUNTANT = 'accountant';
    public const ROLE_SUPPORT = 'support';
    // Partner company admin (logs into partner-engine.sos-expat.com).
    // Sees ALL cabinets/branches of the partner — equivalent of "group admin".
    public const ROLE_PARTNER = 'partner';
    // Branch manager: scoped to a subset of cabinets via managed_group_labels.
    // Phase 1 of multi-cabinet support — see migration 2026_04_25_000003.
    public const ROLE_BRANCH_MANAGER = 'branch_manager';

    public const FILAMENT_ROLES = [
        self::ROLE_SUPER_ADMIN,
        self::ROLE_ADMIN,
        self::ROLE_ACCOUNTANT,
        self::ROLE_SUPPORT,
    ];

    public const PARTNER_ROLES = [
        self::ROLE_PARTNER,
        self::ROLE_BRANCH_MANAGER,
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'partner_firebase_id',
        'managed_group_labels',
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
        'managed_group_labels' => 'array',
    ];

    /**
     * Route users to panels by panel id:
     *   - 'admin'   panel: only SOS-Expat staff roles (super_admin, admin, accountant, support)
     *   - 'partner' panel: only partner-company users (role=partner)
     *
     * A partner account MUST also have a partner_firebase_id so the Eloquent
     * global scope can filter every resource query by it.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if (!$this->is_active) {
            return false;
        }

        return match ($panel->getId()) {
            'admin'   => in_array($this->role, self::FILAMENT_ROLES, true),
            'partner' => in_array($this->role, self::PARTNER_ROLES, true) && !empty($this->partner_firebase_id),
            default   => false,
        };
    }

    public function canAccessFilament(): bool
    {
        return $this->is_active
            && (in_array($this->role, self::FILAMENT_ROLES, true)
                || in_array($this->role, self::PARTNER_ROLES, true));
    }

    /**
     * Partner users: the Agreement they belong to (scoped to their partner_firebase_id).
     * Returns null for SOS-Expat admins.
     */
    public function agreement()
    {
        return $this->hasOne(Agreement::class, 'partner_firebase_id', 'partner_firebase_id');
    }

    public function isPartner(): bool
    {
        // Includes both ROLE_PARTNER (group admin) and ROLE_BRANCH_MANAGER.
        return in_array($this->role, self::PARTNER_ROLES, true);
    }

    public function isBranchManager(): bool
    {
        return $this->role === self::ROLE_BRANCH_MANAGER;
    }

    /**
     * True for users that see the full partner scope (all cabinets).
     * False only for branch managers explicitly restricted to a subset.
     */
    public function hasFullPartnerAccess(): bool
    {
        return $this->role === self::ROLE_PARTNER;
    }

    /**
     * Returns the array of group_labels (cabinet/branch names) this user is
     * restricted to. For full-access users this is irrelevant — callers should
     * gate on hasFullPartnerAccess() first.
     *
     * Always returns an array (never null) so callers can safely use whereIn.
     * Empty array for a branch_manager means "no cabinet assigned yet" and the
     * scoping layer treats this as fail-closed (sees nothing).
     */
    public function getManagedGroupLabels(): array
    {
        $value = $this->managed_group_labels;
        if (!is_array($value)) {
            return [];
        }
        return array_values(array_filter($value, fn($v) => is_string($v) && $v !== ''));
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
