<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_firebase_id', 128);
            $table->string('actor_role', 20); // admin|partner
            $table->string('action', 100); // agreement.created|subscriber.imported|subscriber.suspended|...
            $table->string('resource_type', 50)->nullable(); // agreement|subscriber|csv_import
            $table->unsignedBigInteger('resource_id')->nullable();
            $table->jsonb('details')->default('{}');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('actor_firebase_id', 'idx_audit_logs_actor');
            $table->index(['resource_type', 'resource_id'], 'idx_audit_logs_resource');
            $table->index('created_at', 'idx_audit_logs_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
