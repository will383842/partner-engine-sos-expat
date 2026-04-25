<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshots the agreement's monthly_base_fee onto each invoice at generation time,
 * so the historical breakdown (base + per-member) remains accurate even if the
 * agreement's pricing is changed later.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('partner_invoices', function (Blueprint $table) {
            $table->decimal('monthly_base_fee', 8, 2)->nullable()->after('billing_rate');
        });
    }

    public function down(): void
    {
        Schema::table('partner_invoices', function (Blueprint $table) {
            $table->dropColumn('monthly_base_fee');
        });
    }
};
