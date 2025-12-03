<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WallPostComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'wall_post_id',
        'user_id',
        'content',
    ];

    /**
     * Get the wall post that owns the comment.
     */
    public function wallPost(): BelongsTo
    {
        return $this->belongsTo(WallPost::class);
    }

    /**
     * Get the user that created the comment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
