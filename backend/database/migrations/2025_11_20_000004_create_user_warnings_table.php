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
        Schema::create('user_warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('warned_by')->constrained('users')->onDelete('cascade');
            $table->text('reason'); // Reason for the warning
            $table->foreignId('violation_id')->nullable()->constrained('filtered_word_violations')->onDelete('set null');
            $table->enum('type', ['manual', 'violation'])->default('violation'); // manual = admin warned, violation = from filtered word violation
            $table->boolean('is_read')->default(false); // Whether user has seen the warning
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('warned_by');
            $table->index('violation_id');
            $table->index('is_read');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_warnings');
    }
};



