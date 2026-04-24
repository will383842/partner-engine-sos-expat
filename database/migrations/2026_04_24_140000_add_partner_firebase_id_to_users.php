<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add partner_firebase_id + role='partner' support to users table.
 *
 * Before: users only held Filament admin accounts (super_admin, admin,
 * accountant, support) for admin.sos-expat.com.
 *
 * Now: users also hold partner-facing accounts — each partner company
 * gets one (later many) user row with role='partner' and
 * partner_firebase_id matching their Agreement. These users log into
 * the new Filament panel at partner-engine.sos-expat.com.
 *
 * partner_firebase_id is nullable: NULL for SOS-Expat admins, set for
 * partner users. Indexed for the Eloquent global scope that filters
 * every query by it.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('partner_firebase_id', 128)->nullable()->after('role');
            $table->index('partner_firebase_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['partner_firebase_id']);
            $table->dropColumn('partner_firebase_id');
        });
    }
};
