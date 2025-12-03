<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Room extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'welcome_message',
        'required_likes',
        'room_hashtag',
        'max_count',
        'password',
        'room_image',
        'room_cover',
        'is_public',
        'is_staff_only',
        'enable_mic',
        'disable_incognito',
        'created_by',
        'settings',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'is_staff_only' => 'boolean',
        'max_count' => 'integer',
        'required_likes' => 'integer',
        'room_hashtag' => 'integer',
        'enable_mic' => 'boolean',
        'disable_incognito' => 'boolean',
        'settings' => 'array',
    ];

    /**
     * Get the users that belong to this room.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'room_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get the messages for this room.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get the wall posts for this room.
     */
    public function wallPosts(): HasMany
    {
        return $this->hasMany(WallPost::class)->orderBy('created_at', 'desc');
    }

    /**
     * Get the user who created this room.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Register media collections for the room.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('image')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
        
        $this->addMediaCollection('cover')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
    }

    /**
     * Get the room image URL attribute.
     */
    public function getRoomImageUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('image');
        if (!$media) {
            return null;
        }
        
        return url($media->getUrl());
    }

    /**
     * Get the room cover URL attribute.
     */
    public function getRoomCoverUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('cover');
        if (!$media) {
            return null;
        }
        
        return url($media->getUrl());
    }
}

