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
        Schema::create('banned_operating_systems', function (Blueprint $table) {
            $table->id();
            $table->string('os_name')->unique(); // Windows, Linux, Android, iOS, etc.
            $table->text('description')->nullable();
            $table->foreignId('banned_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index('os_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('banned_operating_systems');
    }
};
