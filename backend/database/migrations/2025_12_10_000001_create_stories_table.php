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
        Schema::create('stories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('media_url'); // Image or video URL
            $table->string('media_type')->default('image'); // 'image' or 'video'
            $table->text('caption')->nullable();
            $table->timestamp('expires_at'); // Stories expire after 24 hours
            $table->timestamps();
            
            $table->index(['user_id', 'expires_at']);
            $table->index('expires_at'); // For cleanup queries
        });

        Schema::create('story_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();
            
            $table->unique(['story_id', 'user_id']); // Prevent duplicate views
            $table->index('story_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('story_views');
        Schema::dropIfExists('stories');
    }
};

