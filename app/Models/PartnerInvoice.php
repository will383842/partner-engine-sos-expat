<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Monthly invoice for partners on the SOS-Call B2B billing model.
 *
 * - Generated automatically by GenerateMonthlyInvoices command (1st of month, 06:00 UTC)
 * - Total amount = monthly_base_fee + (active_subscribers × billing_rate)
 *   Supports 3 billing models, all driven by these two columns:
 *     (a) Per-member only      : monthly_base_fee NULL/0,  billing_rate > 0
 *     (b) Flat monthly fee only: monthly_base_fee > 0,      billing_rate = 0
 *     (c) Hybrid               : monthly_base_fee > 0,      billing_rate > 0
 * - Internal cost (total_cost) is informational, NOT re-billed to partner
 * - Payment options: Stripe Invoicing (hosted page) OR SEPA bank transfer
 */
class PartnerInvoice extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_CANCELLED = 'cancelled';

    public const PAID_VIA_STRIPE = 'stripe';
    public const PAID_VIA_SEPA = 'sepa_transfer';
    public const PAID_VIA_MANUAL = 'manual';

    protected $fillable = [
        'agreement_id',
        'partner_firebase_id',
        'invoice_number',
        'period',
        'active_subscribers',
        'billing_rate',
        'monthly_base_fee',
        'pricing_tier',
        'billing_currency',
        'total_amount',
        'calls_expert',
        'calls_lawyer',
        'total_cost',
        'status',
        'due_date',
        'paid_at',
        'paid_via',
        'payment_note',
        'stripe_customer_id',
        'stripe_invoice_id',
        'stripe_hosted_url',
        'pdf_path',
    ];

    protected $casts = [
        'active_subscribers' => 'integer',
        'billing_rate' => 'decimal:2',
        'monthly_base_fee' => 'decimal:2',
        'pricing_tier' => 'array',
        'total_amount' => 'decimal:2',
        'calls_expert' => 'integer',
        'calls_lawyer' => 'integer',
        'total_cost' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    // --- Scopes ---

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PAID);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_OVERDUE);
    }

    public function scopeForPeriod(Builder $query, string $period): Builder
    {
        return $query->where('period', $period);
    }

    public function scopeForPartner(Builder $query, string $partnerFirebaseId): Builder
    {
        return $query->where('partner_firebase_id', $partnerFirebaseId);
    }

    // --- Helpers ---

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_OVERDUE;
    }

    public function isDueSoon(int $daysThreshold = 3): bool
    {
        if (!$this->isPending()) {
            return false;
        }
        return $this->due_date->diffInDays(now()) <= $daysThreshold && $this->due_date->isFuture();
    }

    public function markPaid(string $via = self::PAID_VIA_MANUAL, ?string $note = null): void
    {
        if ($this->isPaid()) {
            return;
        }
        $this->status = self::STATUS_PAID;
        $this->paid_at = now();
        $this->paid_via = $via;
        if ($note) {
            $this->payment_note = $note;
        }
        $this->save();

        // P1-1 FIX 2026-04-25: removed dispatch of ReleaseProviderPaymentsOnInvoicePaid.
        // Business model is "immediate-credit + 30-day reserve, SOS-Expat absorbs partner-default risk":
        // providers are credited at call completion (TwilioCallManager B2B branch) with a 30-day hold,
        // independent of when the partner pays the monthly invoice. This dispatch was dead code
        // because no Firebase code ever wrote `payment.status = "pending_partner_invoice"`.
    }

    public function markOverdue(): void
    {
        if ($this->isPending()) {
            $this->status = self::STATUS_OVERDUE;
            $this->save();
        }
    }

    public function cancel(?string $note = null): void
    {
        $this->status = self::STATUS_CANCELLED;
        if ($note) {
            $this->payment_note = $note;
        }
        $this->save();
    }
}
