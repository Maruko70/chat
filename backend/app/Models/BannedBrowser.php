<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BannedBrowser extends Model
{
    protected $fillable = [
        'browser_name',
        'description',
        'banned_by',
    ];

    /**
     * Get the user who banned this browser.
     */
    public function bannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'banned_by');
    }
}
