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
            // Status: active, inactive_tab, private_disabled, away, incognito
            $table->string('status', 20)->default('away')->after('private_messages_enabled');
            // Last activity timestamp for calculating status
            $table->timestamp('last_activity')->nullable()->after('status');
            // Index for efficient queries
            $table->index('status');
            $table->index('last_activity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropIndex(['last_activity']);
            $table->dropColumn(['status', 'last_activity']);
        });
    }
};

