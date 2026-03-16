<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('partner_firebase_id', 128);
            $table->string('type', 50)->default('invitation'); // invitation|reminder|expiration
            $table->string('subject', 500);
            $table->text('body_html');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['partner_firebase_id', 'type'], 'uq_email_templates_partner_type');
            $table->index('partner_firebase_id', 'idx_email_templates_partner');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
