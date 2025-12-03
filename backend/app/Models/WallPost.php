<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WallPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'user_id',
        'content',
        'image',
        'youtube_video',
    ];

    protected $casts = [
        'youtube_video' => 'array',
    ];

    /**
     * Get the room that owns the wall post.
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Get the user that created the wall post.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the likes for this wall post.
     */
    public function likes(): HasMany
    {
        return $this->hasMany(WallPostLike::class);
    }

    /**
     * Get the comments for this wall post.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(WallPostComment::class)->orderBy('created_at', 'asc');
    }

    /**
     * Get the likes count attribute.
     */
    public function getLikesCountAttribute(): int
    {
        return $this->likes()->count();
    }

    /**
     * Check if a user has liked this post.
     */
    public function isLikedBy(int $userId): bool
    {
        return $this->likes()->where('user_id', $userId)->exists();
    }
}
