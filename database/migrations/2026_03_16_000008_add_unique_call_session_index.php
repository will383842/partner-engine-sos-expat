<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Unique index to prevent race condition on webhook idempotency
        DB::statement('CREATE UNIQUE INDEX uq_activities_call_session ON subscriber_activities(call_session_id) WHERE call_session_id IS NOT NULL AND type = \'call_completed\'');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_activities_call_session');
    }
};
