<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('incognito_mode_enabled')->default(false)->after('is_blocked');
            $table->boolean('private_messages_enabled')->default(true)->after('incognito_mode_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['incognito_mode_enabled', 'private_messages_enabled']);
        });
    }
};
