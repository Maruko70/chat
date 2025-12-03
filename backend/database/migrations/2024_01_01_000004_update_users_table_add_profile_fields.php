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
        Schema::table('users', function (Blueprint $table) {
            // Drop unique constraint on email first if it exists
            $table->dropUnique(['email']);
            
            $table->string('username')->unique()->after('id');
            $table->string('email')->nullable()->change();
            $table->string('phone')->nullable()->after('email');
            $table->string('country_flag')->nullable()->after('phone');
            $table->text('bio')->nullable()->after('country_flag');
            $table->boolean('is_guest')->default(false)->after('bio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['username', 'phone', 'country_flag', 'bio', 'is_guest']);
            $table->string('email')->nullable(false)->change();
            $table->unique('email');
        });
    }
};

