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
        Schema::create('banned_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('username');
            $table->string('name')->nullable();
            $table->foreignId('banned_by')->constrained('users')->onDelete('cascade');
            $table->text('reason')->nullable();
            $table->string('device')->nullable(); // Parsed from user_agent
            $table->string('ip_address', 45)->nullable();
            $table->string('account_name')->nullable(); // username at time of ban
            $table->string('country', 100)->nullable();
            $table->timestamp('banned_at');
            $table->timestamp('ends_at')->nullable(); // null = permanent ban
            $table->boolean('is_permanent')->default(false);
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('ip_address');
            $table->index('ends_at');
            $table->index('is_permanent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banned_users');
    }
};
