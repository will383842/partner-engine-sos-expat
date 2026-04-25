<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agreement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'partner_firebase_id',
        'partner_name',
        'name',
        'status',
        'discount_type',
        'discount_value',
        'discount_max_cents',
        'discount_label',
        'commission_per_call_lawyer',
        'commission_per_call_expat',
        'commission_type',
        'commission_percent',
        'max_subscribers',
        'max_calls_per_subscriber',
        'starts_at',
        'expires_at',
        'notes',
        // SOS-Call fields (system B — monthly flat-rate billing)
        'billing_rate',
        'monthly_base_fee',
        'billing_currency',
        'payment_terms_days',
        'call_types_allowed',
        'sos_call_active',
        'billing_email',
        'default_subscriber_duration_days',
        'max_subscriber_duration_days',
        // Single source of truth for the economic model chosen by admin.
        // One of: 'commission' | 'sos_call' | 'hybrid'. Drives the Filament
        // UI (mutually exclusive fields) and future billing logic.
        'economic_model',
    ];

    protected $casts = [
        'discount_value' => 'integer',
        'discount_max_cents' => 'integer',
        'commission_per_call_lawyer' => 'integer',
        'commission_per_call_expat' => 'integer',
        'commission_percent' => 'decimal:2',
        'max_subscribers' => 'integer',
        'max_calls_per_subscriber' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        // SOS-Call casts
        'billing_rate' => 'decimal:2',
        'monthly_base_fee' => 'decimal:2',
        'payment_terms_days' => 'integer',
        'sos_call_active' => 'boolean',
        'default_subscriber_duration_days' => 'integer',
        'max_subscriber_duration_days' => 'integer',
    ];

    public function subscribers(): HasMany
    {
        return $this->hasMany(Subscriber::class);
    }

    public function monthlyStats(): HasMany
    {
        return $this->hasMany(PartnerMonthlyStat::class, 'partner_firebase_id', 'partner_firebase_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(PartnerInvoice::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isPaused(): bool
    {
        return $this->status === 'paused';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired';
    }

    /**
     * Check if this agreement uses the SOS-Call (B2B monthly flat-rate) model.
     * If false, the agreement uses the commission-per-call model (system A).
     */
    public function usesSosCall(): bool
    {
        return (bool) $this->sos_call_active;
    }

    /**
     * Compute the effective subscriber expiration date based on agreement policy.
     * Returns null if the subscriber has no defined expiration (permanent).
     */
    public function computeSubscriberExpiresAt(): ?\Carbon\Carbon
    {
        if ($this->default_subscriber_duration_days) {
            return now()->addDays($this->default_subscriber_duration_days);
        }
        return $this->expires_at;
    }
}
