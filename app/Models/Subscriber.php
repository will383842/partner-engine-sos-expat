<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscriber extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'partner_firebase_id',
        'agreement_id',
        'email',
        'first_name',
        'last_name',
        'phone',
        'country',
        'language',
        'firebase_uid',
        'affiliate_code',
        'invite_token',
        'status',
        'invited_at',
        'registered_at',
        'last_activity_at',
        'total_calls',
        'total_spent_cents',
        'total_discount_cents',
        'tags',
        'custom_fields',
        // SOS-Call fields (system B)
        'sos_call_code',
        'sos_call_activated_at',
        'sos_call_expires_at',
        'calls_expert',
        'calls_lawyer',
        // Hierarchy (optional) — for big partners with sub-entities
        'group_label',    // e.g. "AXA Paris", "AXA Lyon"
        'region',         // e.g. "Île-de-France"
        'department',     // e.g. "IT", "Sales"
        'external_id',    // partner's internal CRM id
    ];

    protected $casts = [
        'total_calls' => 'integer',
        'total_spent_cents' => 'integer',
        'total_discount_cents' => 'integer',
        'tags' => 'array',
        'custom_fields' => 'array',
        'invited_at' => 'datetime',
        'registered_at' => 'datetime',
        'last_activity_at' => 'datetime',
        // SOS-Call casts
        'sos_call_activated_at' => 'datetime',
        'sos_call_expires_at' => 'datetime',
        'calls_expert' => 'integer',
        'calls_lawyer' => 'integer',
    ];

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(SubscriberActivity::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }

    /**
     * Check if subscriber has SOS-Call access active and not expired.
     */
    public function hasSosCallAccess(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }
        if (!$this->sos_call_code) {
            return false;
        }
        if ($this->sos_call_expires_at && $this->sos_call_expires_at->isPast()) {
            return false;
        }
        return true;
    }

    /**
     * Total SOS-Call calls used (expert + lawyer).
     */
    public function getTotalSosCallUsedAttribute(): int
    {
        return (int) ($this->calls_expert + $this->calls_lawyer);
    }

    /**
     * Remaining SOS-Call calls based on agreement's max_calls_per_subscriber.
     * Returns null if unlimited.
     */
    public function getSosCallRemainingAttribute(): ?int
    {
        $max = $this->agreement?->max_calls_per_subscriber;
        if ($max === null) {
            return null; // unlimited
        }
        return max(0, $max - $this->total_sos_call_used);
    }

    /**
     * Check if subscriber has reached their quota.
     */
    public function hasReachedSosCallQuota(): bool
    {
        $remaining = $this->sos_call_remaining;
        return $remaining !== null && $remaining <= 0;
    }
}
