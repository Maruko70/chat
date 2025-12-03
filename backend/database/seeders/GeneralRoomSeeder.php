<?php

namespace Database\Seeders;

use App\Models\Room;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GeneralRoomSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if general room already exists
        $generalRoom = Room::where('slug', 'like', 'alghurfah-alammah%')
            ->orWhere('name', 'الغرفة العامة')
            ->first();

        if (!$generalRoom) {
            Room::create([
                'name' => 'الغرفة العامة',
                'slug' => 'alghurfah-alammah-' . Str::random(6),
                'description' => 'الغرفة العامة للدردشة والتواصل',
                'max_count' => 200,
                'password' => null,
                'room_image' => null,
                'room_cover' => null,
                'is_public' => true,
                'is_staff_only' => false,
                'created_by' => null, // System created
                'settings' => null,
            ]);
        }
    }
}
