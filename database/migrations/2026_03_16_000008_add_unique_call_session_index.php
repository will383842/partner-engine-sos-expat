<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // Partial unique index to prevent race condition on webhook idempotency
            DB::statement('CREATE UNIQUE INDEX uq_activities_call_session ON subscriber_activities(call_session_id) WHERE call_session_id IS NOT NULL AND type = \'call_completed\'');
        } else {
            // SQLite fallback: simple unique index (close enough for testing)
            DB::statement('CREATE UNIQUE INDEX uq_activities_call_session ON subscriber_activities(call_session_id)');
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_activities_call_session');
    }
};
