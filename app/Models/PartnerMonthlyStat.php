<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerMonthlyStat extends Model
{
    protected $fillable = [
        'partner_firebase_id',
        'month',
        'total_subscribers',
        'new_subscribers',
        'active_subscribers',
        'total_calls',
        'total_revenue_cents',
        'total_commissions_cents',
        'total_discounts_cents',
        'conversion_rate',
    ];

    protected $casts = [
        'total_subscribers' => 'integer',
        'new_subscribers' => 'integer',
        'active_subscribers' => 'integer',
        'total_calls' => 'integer',
        'total_revenue_cents' => 'integer',
        'total_commissions_cents' => 'integer',
        'total_discounts_cents' => 'integer',
        'conversion_rate' => 'decimal:2',
    ];
}
