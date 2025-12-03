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
            $table->foreignId('membership_background_id')->nullable()->after('designed_membership')
                ->constrained('membership_designs')->nullOnDelete();
            $table->foreignId('membership_frame_id')->nullable()->after('membership_background_id')
                ->constrained('membership_designs')->nullOnDelete();
            $table->string('premium_entry_background')->nullable()->after('premium_entry'); // URL to premium entry GIF/PNG
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['membership_background_id']);
            $table->dropForeign(['membership_frame_id']);
            $table->dropColumn(['membership_background_id', 'membership_frame_id', 'premium_entry_background']);
        });
    }
};






