<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BannedOperatingSystem extends Model
{
    protected $fillable = [
        'os_name',
        'description',
        'banned_by',
    ];

    /**
     * Get the user who banned this OS.
     */
    public function bannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'banned_by');
    }
}
