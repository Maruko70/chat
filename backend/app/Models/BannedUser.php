<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BannedUser extends Model
{
    protected $fillable = [
        'user_id',
        'username',
        'name',
        'banned_by',
        'reason',
        'device',
        'ip_address',
        'account_name',
        'country',
        'banned_at',
        'ends_at',
        'is_permanent',
    ];

    protected $casts = [
        'banned_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_permanent' => 'boolean',
    ];

    /**
     * Get the banned user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who banned this user.
     */
    public function bannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'banned_by');
    }

    /**
     * Check if ban is still active.
     */
    public function isActive(): bool
    {
        if ($this->is_permanent) {
            return true;
        }

        if (!$this->ends_at) {
            return true;
        }

        return $this->ends_at->isFuture();
    }

    /**
     * Scope to get only active bans.
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->where('is_permanent', true)
              ->orWhereNull('ends_at')
              ->orWhere('ends_at', '>', now());
        });
    }
}
