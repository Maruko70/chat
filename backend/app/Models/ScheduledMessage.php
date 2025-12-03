<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'time_span',
        'title',
        'message',
        'room_id',
        'is_active',
        'last_sent_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_sent_at' => 'datetime',
    ];

    /**
     * Get the room that owns the scheduled message.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Check if this message should be sent now (for daily messages).
     */
    public function shouldSendNow(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->type === 'daily') {
            // For time_span based daily messages (recurring every time_span minutes)
            if ($this->last_sent_at) {
                $nextSendTime = $this->last_sent_at->copy()->addMinutes($this->time_span);
                return now()->gte($nextSendTime);
            }
            
            return true; // Never sent, send now
        }

        // Welcoming messages are handled separately when users join
        return false;
    }

    /**
     * Scope for active messages.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for daily messages.
     */
    public function scopeDaily($query)
    {
        return $query->where('type', 'daily');
    }

    /**
     * Scope for welcoming messages.
     */
    public function scopeWelcoming($query)
    {
        return $query->where('type', 'welcoming');
    }

    /**
     * Scope for messages for a specific room or all rooms.
     */
    public function scopeForRoom($query, $roomId)
    {
        return $query->where(function ($q) use ($roomId) {
            $q->whereNull('room_id')
              ->orWhere('room_id', $roomId);
        });
    }
}
