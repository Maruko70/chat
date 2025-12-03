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
        Schema::create('filtered_word_violations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('filtered_word_id')->constrained('filtered_words')->onDelete('cascade');
            $table->enum('content_type', ['chats', 'names', 'bios', 'walls', 'statuses']);
            $table->text('original_content'); // The content that contained the bad word
            $table->text('filtered_content')->nullable(); // The content after filtering (if saved)
            $table->foreignId('message_id')->nullable()->constrained('messages')->onDelete('cascade'); // If it's a chat message
            $table->enum('status', ['pending', 'reviewed', 'action_taken', 'dismissed'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->text('action_taken')->nullable(); // What action was taken (ban, warn, delete, etc.)
            $table->text('notes')->nullable(); // Admin notes
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('filtered_word_id');
            $table->index('content_type');
            $table->index('status');
            $table->index('created_at');
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('filtered_word_violations');
    }
};



