<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginLog extends Model
{
    protected $fillable = [
        'user_id',
        'is_guest',
        'username',
        'room_id',
        'room_name',
        'ip_address',
        'country',
        'user_agent',
    ];

    protected $casts = [
        'is_guest' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the user that logged in.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the room that the user joined.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
