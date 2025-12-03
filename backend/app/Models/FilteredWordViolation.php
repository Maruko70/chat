<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FilteredWordViolation extends Model
{
    protected $fillable = [
        'user_id',
        'filtered_word_id',
        'content_type',
        'original_content',
        'filtered_content',
        'message_id',
        'status',
        'reviewed_by',
        'reviewed_at',
        'action_taken',
        'notes',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    /**
     * Get the user who violated the filter.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the filtered word that was violated.
     */
    public function filteredWord(): BelongsTo
    {
        return $this->belongsTo(FilteredWord::class);
    }

    /**
     * Get the message (if applicable).
     */
    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    /**
     * Get the admin who reviewed this violation.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}



