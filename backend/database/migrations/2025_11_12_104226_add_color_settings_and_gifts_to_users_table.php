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
            // Color settings (stored as JSON)
            $table->json('name_color')->nullable()->after('is_guest');
            $table->json('message_color')->nullable()->after('name_color');
            $table->json('name_bg_color')->nullable()->after('message_color');
            $table->json('image_border_color')->nullable()->after('name_bg_color');
            $table->json('bio_color')->nullable()->after('image_border_color');
            $table->integer('room_font_size')->default(14)->after('bio_color');
            
            // Gifts and roles
            $table->json('gifts')->nullable()->after('room_font_size'); // Array of gift IDs or names
            $table->string('group_role')->nullable()->after('gifts'); // e.g., 'admin', 'moderator', 'vip'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'name_color',
                'message_color',
                'name_bg_color',
                'image_border_color',
                'bio_color',
                'room_font_size',
                'gifts',
                'group_role',
            ]);
        });
    }
};
