<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('csv_imports', function (Blueprint $table) {
            $table->id();
            $table->string('partner_firebase_id', 128);
            $table->string('uploaded_by', 128); // UID of uploader (partner or admin)

            $table->string('filename', 255)->nullable();
            $table->integer('total_rows')->default(0);
            $table->integer('imported')->default(0);
            $table->integer('duplicates')->default(0);
            $table->integer('errors')->default(0);
            $table->jsonb('error_details')->default('[]');

            $table->string('status', 20)->default('processing'); // processing|completed|failed

            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('csv_imports');
    }
};
