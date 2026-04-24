<?php

namespace App\Filament\Partner\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Trait to apply on every Filament partner Resource.
 *
 * Overrides getEloquentQuery() to filter by partner_firebase_id of the
 * logged-in partner user, so cross-tenant leaks are structurally impossible.
 *
 * If for any reason no partner user is logged in (shouldn't happen thanks
 * to canAccessPanel), the query returns an empty set — NEVER the full table.
 *
 * The target column is `partner_firebase_id` by default. Override
 * $partnerScopeColumn on the resource if the model uses a different column
 * name (e.g. a relationship path like 'subscriber.partner_firebase_id').
 */
trait PartnerScopedQuery
{
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();
        $partnerId = $user?->partner_firebase_id;

        if (!$partnerId) {
            // Fail-closed: no partner id = zero rows, never expose other partners' data
            return $query->whereRaw('1 = 0');
        }

        $column = static::$partnerScopeColumn ?? 'partner_firebase_id';
        return $query->where($column, $partnerId);
    }
}
