<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds monthly_base_fee to agreements to support 3 billing models:
 *   (a) Per-member only         : monthly_base_fee = NULL/0,  billing_rate > 0
 *   (b) Flat monthly fee only   : monthly_base_fee > 0,        billing_rate = 0
 *   (c) Hybrid (flat + per-mbr) : monthly_base_fee > 0,        billing_rate > 0
 *
 * Total invoice amount = monthly_base_fee + (active_subscribers × billing_rate).
 * Existing agreements default to NULL (model a) — no behavior change.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->decimal('monthly_base_fee', 8, 2)->nullable()->after('billing_rate');
        });
    }

    public function down(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->dropColumn('monthly_base_fee');
        });
    }
};
