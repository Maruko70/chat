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
        Schema::create('scheduled_messages', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['daily', 'welcoming'])->default('daily');
            $table->integer('time_span')->comment('Time span in minutes (e.g., 1, 2, 60, 1440 for daily)');
            $table->time('daily_time')->nullable()->comment('Specific time for daily messages (HH:MM:SS)');
            $table->string('title')->comment('Title to show');
            $table->text('message')->comment('Message content');
            $table->foreignId('room_id')->nullable()->constrained('rooms')->onDelete('cascade')->comment('null = all rooms');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();
            
            $table->index(['type', 'is_active']);
            $table->index(['room_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scheduled_messages');
    }
};
