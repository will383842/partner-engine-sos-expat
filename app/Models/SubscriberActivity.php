<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriberActivity extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'subscriber_id',
        'partner_firebase_id',
        'type',
        'call_session_id',
        'provider_type',
        'call_duration_seconds',
        'amount_paid_cents',
        'discount_applied_cents',
        'commission_earned_cents',
        'metadata',
        'created_at',
    ];

    protected $casts = [
        'call_duration_seconds' => 'integer',
        'amount_paid_cents' => 'integer',
        'discount_applied_cents' => 'integer',
        'commission_earned_cents' => 'integer',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(Subscriber::class);
    }
}
