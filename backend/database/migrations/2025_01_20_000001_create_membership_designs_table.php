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
        Schema::create('membership_designs', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Design name
            $table->enum('type', ['background', 'frame']); // Type of design
            $table->string('image_url'); // URL to the design image
            $table->text('description')->nullable(); // Optional description
            $table->boolean('is_active')->default(true); // Whether design is available
            $table->integer('priority')->default(0); // Display order priority
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('membership_designs');
    }
};






