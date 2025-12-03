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
        Schema::table('wall_posts', function (Blueprint $table) {
            $table->string('image')->nullable()->after('content');
            $table->json('youtube_video')->nullable()->after('image'); // Store YouTube video data (id, title, thumbnail, etc.)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wall_posts', function (Blueprint $table) {
            $table->dropColumn(['image', 'youtube_video']);
        });
    }
};
