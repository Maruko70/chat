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
        Schema::table('rooms', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->integer('max_count')->default(200)->after('description');
            $table->string('password')->nullable()->after('max_count');
            $table->string('room_image')->nullable()->after('password');
            $table->string('room_cover')->nullable()->after('room_image');
            $table->boolean('is_staff_only')->default(false)->after('room_cover');
            $table->foreignId('created_by')->nullable()->after('is_staff_only')->constrained('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn([
                'description',
                'max_count',
                'password',
                'room_image',
                'room_cover',
                'is_staff_only',
                'created_by',
            ]);
        });
    }
};
