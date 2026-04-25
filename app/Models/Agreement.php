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
        'pricing_tiers',
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
        // Legal gating
        'legal_status',
        'legal_signed_at',
        'legal_override',
        'legal_override_reason',
        'legal_override_by',
        'partner_legal_language',
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
        'pricing_tiers' => 'array',
        'payment_terms_days' => 'integer',
        'sos_call_active' => 'boolean',
        'default_subscriber_duration_days' => 'integer',
        'max_subscriber_duration_days' => 'integer',
        'legal_signed_at' => 'datetime',
        'legal_override' => 'boolean',
    ];

    // --- Legal status constants (mirror of partner_legal_documents.status, plus 'override') ---
    public const LEGAL_NOT_GENERATED = 'not_generated';
    public const LEGAL_DRAFT = 'draft';
    public const LEGAL_PENDING_VALIDATION = 'pending_admin_validation';
    public const LEGAL_READY_FOR_SIGNATURE = 'ready_for_signature';
    public const LEGAL_PARTIALLY_SIGNED = 'partially_signed';
    public const LEGAL_SIGNED = 'signed';
    public const LEGAL_SUPERSEDED = 'superseded';
    public const LEGAL_OVERRIDE = 'override';

    public function subscribers(): HasMany
    {
        return $this->hasMany(Subscriber::class);
    }

    public function legalDocuments(): HasMany
    {
        return $this->hasMany(PartnerLegalDocument::class);
    }

    public function legalAcceptances(): HasMany
    {
        return $this->hasMany(PartnerLegalAcceptance::class);
    }

    public function activeLegalDocuments(): HasMany
    {
        return $this->hasMany(PartnerLegalDocument::class)
            ->where('status', '!=', PartnerLegalDocument::STATUS_SUPERSEDED);
    }

    /**
     * Is the partner legally cleared to operate SOS-Call?
     * True if all 3 docs are signed, OR admin set legal_override=true with a reason.
     */
    public function isLegallyCleared(): bool
    {
        return $this->legal_override
            || $this->legal_status === self::LEGAL_SIGNED
            || $this->legal_status === self::LEGAL_OVERRIDE;
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

    /**
     * Resolve the base fee component for a given subscriber count.
     *
     * If pricing_tiers is set and contains a matching bracket, the tier's
     * amount becomes the base. Otherwise we fall back to monthly_base_fee
     * (or 0 if neither is set).
     *
     * Returns:
     *   [
     *     'amount' => float,                  // base fee in agreement currency
     *     'source' => 'tier'|'flat',          // which path supplied the amount
     *     'tier'   => array|null,             // snapshot of matched bracket (or null)
     *   ]
     */
    public function resolveBaseFee(int $subscriberCount): array
    {
        $tiers = $this->pricing_tiers;

        if (is_array($tiers) && !empty($tiers)) {
            foreach ($tiers as $tier) {
                $min = (int) ($tier['min'] ?? 0);
                $maxRaw = $tier['max'] ?? null;
                $max = ($maxRaw === null || $maxRaw === '') ? PHP_INT_MAX : (int) $maxRaw;

                if ($subscriberCount >= $min && $subscriberCount <= $max) {
                    return [
                        'amount' => (float) ($tier['amount'] ?? 0),
                        'source' => 'tier',
                        'tier' => [
                            'min' => $min,
                            'max' => ($maxRaw === null || $maxRaw === '') ? null : (int) $maxRaw,
                            'amount' => (float) ($tier['amount'] ?? 0),
                        ],
                    ];
                }
            }
            // No matching bracket: log a warning so admin notices the gap,
            // then fall back to monthly_base_fee (better than crashing the cron).
            \Illuminate\Support\Facades\Log::warning(
                '[Agreement] No matching pricing tier for subscriber count',
                [
                    'agreement_id' => $this->id,
                    'partner_firebase_id' => $this->partner_firebase_id,
                    'count' => $subscriberCount,
                    'tiers' => $tiers,
                ]
            );
        }

        return [
            'amount' => (float) ($this->monthly_base_fee ?? 0),
            'source' => 'flat',
            'tier' => null,
        ];
    }
}
