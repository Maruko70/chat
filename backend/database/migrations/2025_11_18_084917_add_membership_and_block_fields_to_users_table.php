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
            $table->boolean('premium_entry')->default(false)->after('is_guest');
            $table->boolean('designed_membership')->default(false)->after('premium_entry');
            $table->boolean('verify_membership')->default(false)->after('designed_membership');
            $table->boolean('is_blocked')->default(false)->after('verify_membership');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['premium_entry', 'designed_membership', 'verify_membership', 'is_blocked']);
        });
    }
};
