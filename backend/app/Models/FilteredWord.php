<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FilteredWord extends Model
{
    protected $fillable = [
        'word',
        'applies_to',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get all active filtered words for a specific type.
     */
    public static function getActiveWordsForType(string $type): array
    {
        return static::where('is_active', true)
            ->where('applies_to', $type)
            ->pluck('word')
            ->toArray();
    }

    /**
     * Get all active filtered words grouped by type.
     */
    public static function getActiveWordsGrouped(): array
    {
        return static::where('is_active', true)
            ->get()
            ->groupBy('applies_to')
            ->map(function ($words) {
                return $words->pluck('word')->toArray();
            })
            ->toArray();
    }
}

