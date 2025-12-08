<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Story extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'media_url',
        'media_type',
        'caption',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user that owns the story.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all views for this story.
     */
    public function views(): HasMany
    {
        return $this->hasMany(StoryView::class);
    }

    /**
     * Check if the story has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if a user has viewed this story.
     */
    public function isViewedBy(int $userId): bool
    {
        return $this->views()->where('user_id', $userId)->exists();
    }

    /**
     * Scope to get only active (non-expired) stories.
     */
    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope to get stories for specific users.
     */
    public function scopeForUsers($query, array $userIds)
    {
        return $query->whereIn('user_id', $userIds);
    }

    /**
     * Scope to order by newest first.
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('created_at', 'desc');
    }
}

