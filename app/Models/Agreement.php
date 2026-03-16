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
    ];

    public function subscribers(): HasMany
    {
        return $this->hasMany(Subscriber::class);
    }

    public function monthlyStats(): HasMany
    {
        return $this->hasMany(PartnerMonthlyStat::class, 'partner_firebase_id', 'partner_firebase_id');
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
}
