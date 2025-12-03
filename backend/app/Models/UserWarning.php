<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserWarning extends Model
{
    protected $fillable = [
        'user_id',
        'warned_by',
        'reason',
        'violation_id',
        'type',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    /**
     * Get the user who was warned.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the admin who issued the warning.
     */
    public function warnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'warned_by');
    }

    /**
     * Get the violation that triggered this warning (if applicable).
     */
    public function violation(): BelongsTo
    {
        return $this->belongsTo(FilteredWordViolation::class, 'violation_id');
    }

    /**
     * Mark warning as read.
     */
    public function markAsRead(): void
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    /**
     * Get total warning count for a user.
     */
    public static function getWarningCount(int $userId): int
    {
        return static::where('user_id', $userId)->count();
    }
}



