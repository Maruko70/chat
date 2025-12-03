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
        Schema::create('filtered_words', function (Blueprint $table) {
            $table->id();
            $table->string('word');
            $table->enum('applies_to', ['chats', 'names', 'bios', 'walls', 'statuses', 'all'])->default('chats');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('word');
            $table->index('applies_to');
            $table->index('is_active');
            $table->unique(['word', 'applies_to']); // Prevent duplicate words for same type
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('filtered_words');
    }
};

