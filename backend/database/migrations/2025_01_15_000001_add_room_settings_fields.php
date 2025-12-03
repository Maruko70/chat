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
        Schema::table('rooms', function (Blueprint $table) {
            $table->text('welcome_message')->nullable()->after('description');
            $table->integer('required_likes')->default(0)->after('welcome_message');
            $table->integer('room_hashtag')->nullable()->after('required_likes');
            $table->boolean('enable_mic')->default(false)->after('room_hashtag');
            $table->boolean('disable_incognito')->default(false)->after('enable_mic');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn([
                'welcome_message',
                'required_likes',
                'room_hashtag',
                'enable_mic',
                'disable_incognito',
            ]);
        });
    }
};

