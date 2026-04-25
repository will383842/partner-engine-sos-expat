<?php

namespace App\Filament\Partner\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Trait to apply on every Filament partner Resource.
 *
 * Two layers of scoping:
 *
 * 1) Partner scope (always applied) — filters by partner_firebase_id of the
 *    logged-in user, so cross-tenant leaks between different partner companies
 *    are structurally impossible. If no partner user is logged in (shouldn't
 *    happen thanks to canAccessPanel), the query returns an empty set —
 *    NEVER the full table.
 *
 *    Override $partnerScopeColumn on the resource if the model uses a
 *    different column name.
 *
 * 2) Branch-manager scope (Phase 1 of multi-cabinet, applied only when the
 *    resource declares $groupLabelScopeColumn) — for users with role
 *    `branch_manager`, restricts the query to their managed_group_labels.
 *    Branch managers with no labels assigned see nothing (fail-closed).
 *
 *    Resources without a group_label column (e.g. PartnerInvoice,
 *    PartnerApiKey) are NOT additionally filtered for branch managers in
 *    Phase 1; whether they should be hidden entirely or shown read-only is
 *    a Phase 2 product decision.
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

        $partnerColumn = static::$partnerScopeColumn ?? 'partner_firebase_id';
        $query = $query->where($partnerColumn, $partnerId);

        // Branch-manager scoping — applied only if the resource declares a
        // group_label column AND the user is a branch_manager.
        $groupLabelColumn = static::$groupLabelScopeColumn ?? null;
        if ($groupLabelColumn && $user instanceof User && $user->isBranchManager()) {
            $managedLabels = $user->getManagedGroupLabels();
            if (empty($managedLabels)) {
                // Branch manager with no cabinets assigned: fail-closed.
                return $query->whereRaw('1 = 0');
            }
            $query = $query->whereIn($groupLabelColumn, $managedLabels);
        }

        return $query;
    }
}
