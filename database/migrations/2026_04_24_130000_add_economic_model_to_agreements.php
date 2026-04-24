<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add economic_model enum to agreements and backfill existing data.
 *
 * Possible values:
 *   - 'commission': legacy per-call commission (System A). Default for new partners.
 *   - 'sos_call':   monthly B2B flat fee per active client (System B).
 *   - 'hybrid':     both at once — rare, admin must explicitly opt in.
 *
 * This column is the single source of truth going forward. The existing
 * sos_call_active boolean + commission_per_call_* integers stay as storage
 * but the Filament form now enforces mutual exclusion via economic_model.
 *
 * Backfill logic (runs once at migrate time):
 *   - sos_call_active = true AND any commission_per_call_* > 0  → 'hybrid'
 *   - sos_call_active = true (no commissions)                   → 'sos_call'
 *   - everything else (existing partners, default case)         → 'commission'
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->string('economic_model', 20)
                ->default('commission')
                ->after('sos_call_active')
                ->comment('commission | sos_call | hybrid');
            $table->index('economic_model');
        });

        // Backfill order matters (most specific first).
        // 1. hybrid: sos_call_active=true AND any commission > 0
        DB::table('agreements')
            ->where('sos_call_active', true)
            ->where(function ($q) {
                $q->where('commission_per_call_lawyer', '>', 0)
                    ->orWhere('commission_per_call_expat', '>', 0)
                    ->orWhere('commission_percent', '>', 0);
            })
            ->update(['economic_model' => 'hybrid']);

        // 2. sos_call: sos_call_active=true, all commissions at 0
        DB::table('agreements')
            ->where('sos_call_active', true)
            ->where('economic_model', '!=', 'hybrid')
            ->update(['economic_model' => 'sos_call']);

        // 3. commission: everyone else (already default, explicit here for clarity)
        DB::table('agreements')
            ->where('sos_call_active', false)
            ->update(['economic_model' => 'commission']);
    }

    public function down(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->dropIndex(['economic_model']);
            $table->dropColumn('economic_model');
        });
    }
};
