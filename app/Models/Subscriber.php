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
}
