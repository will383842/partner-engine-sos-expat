<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds tiered (stepped) pricing support on top of the existing flat fee.
 *
 * `agreements.pricing_tiers` is a JSON array of brackets like:
 *   [
 *     { "min": 0,    "max": 500,   "amount": 500.00 },
 *     { "min": 501,  "max": 1000,  "amount": 650.00 },
 *     { "min": 1001, "max": null,  "amount": 800.00 }   // null = unlimited
 *   ]
 *
 * When set and non-empty, the bracket whose [min, max] contains the active
 * subscribers count replaces `monthly_base_fee` as the base component of
 * the invoice. `billing_rate` (per-member) keeps applying on top, so the
 * existing 3 billing models still work and a 4th tiered model is unlocked:
 *
 *   (a) per-member only       — pricing_tiers NULL, base 0, rate > 0
 *   (b) flat monthly fee      — pricing_tiers NULL, base > 0, rate = 0
 *   (c) hybrid                — pricing_tiers NULL, base > 0, rate > 0
 *   (d) tiered flat           — pricing_tiers set,  rate = 0
 *   (e) tiered + per-member   — pricing_tiers set,  rate > 0  (rare)
 *
 * `partner_invoices.pricing_tier` snapshots the matched bracket at invoice
 * generation time so the historical breakdown stays accurate even if the
 * agreement's tiers are edited later.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->json('pricing_tiers')->nullable()->after('monthly_base_fee');
        });

        Schema::table('partner_invoices', function (Blueprint $table) {
            $table->json('pricing_tier')->nullable()->after('monthly_base_fee');
        });
    }

    public function down(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->dropColumn('pricing_tiers');
        });
        Schema::table('partner_invoices', function (Blueprint $table) {
            $table->dropColumn('pricing_tier');
        });
    }
};
