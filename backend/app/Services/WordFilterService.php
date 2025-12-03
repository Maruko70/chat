<?php

namespace App\Services;

use App\Models\FilteredWord;
use App\Models\FilteredWordViolation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WordFilterService
{
    /**
     * Check if text contains any filtered words for the given type.
     * Returns true if filtered words are found, false otherwise.
     */
    public static function containsFilteredWords(string $text, string $type): bool
    {
        $foundWords = self::findFilteredWords($text, $type);
        return !empty($foundWords);
    }

    /**
     * Find which filtered words are in the text.
     * Returns array of FilteredWord models that were found.
     */
    public static function findFilteredWords(string $text, string $type): array
    {
        $filteredWords = self::getFilteredWordsForType($type);
        
        if (empty($filteredWords)) {
            return [];
        }

        $textLower = mb_strtolower($text, 'UTF-8');
        $foundWords = [];
        
        // Get the actual FilteredWord models to check which ones match
        $allWords = FilteredWord::where('is_active', true)
            ->where(function ($query) use ($type) {
                $query->where('applies_to', $type)
                      ->orWhere('applies_to', 'all');
            })
            ->get();
        
        foreach ($allWords as $word) {
            $wordLower = mb_strtolower($word->word, 'UTF-8');
            
            // Check if the word appears in the text (case-insensitive)
            if (mb_strpos($textLower, $wordLower) !== false) {
                $foundWords[] = $word;
            }
        }

        return $foundWords;
    }

    /**
     * Log a violation when filtered words are detected.
     */
    public static function logViolation(
        int $userId,
        string $content,
        string $contentType,
        ?int $messageId = null,
        ?string $filteredContent = null
    ): void {
        try {
            $foundWords = self::findFilteredWords($content, $contentType);
            
            foreach ($foundWords as $word) {
                FilteredWordViolation::create([
                    'user_id' => $userId,
                    'filtered_word_id' => $word->id,
                    'content_type' => $contentType,
                    'original_content' => $content,
                    'filtered_content' => $filteredContent,
                    'message_id' => $messageId,
                    'status' => 'pending',
                ]);
            }
        } catch (\Exception $e) {
            // Log error but don't fail the request
            Log::error('Failed to log filtered word violation', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'content_type' => $contentType,
            ]);
        }
    }

    /**
     * Filter text by replacing filtered words with asterisks.
     * Returns the filtered text.
     */
    public static function filterText(string $text, string $type): string
    {
        $filteredWords = self::getFilteredWordsForType($type);
        
        if (empty($filteredWords)) {
            return $text;
        }

        $filtered = $text;
        
        foreach ($filteredWords as $word) {
            // Use case-insensitive replacement
            $pattern = '/' . preg_quote($word, '/') . '/iu';
            $replacement = str_repeat('*', mb_strlen($word, 'UTF-8'));
            $filtered = preg_replace($pattern, $replacement, $filtered);
        }

        return $filtered;
    }

    /**
     * Get filtered words for a specific type from cache.
     */
    private static function getFilteredWordsForType(string $type): array
    {
        return Cache::remember("filtered_words_{$type}", 300, function () use ($type) {
            // Get words specific to this type
            $typeWords = FilteredWord::getActiveWordsForType($type);
            
            // Get words that apply to all types
            $allWords = FilteredWord::getActiveWordsForType('all');
            
            // Merge and return unique words
            return array_unique(array_merge($typeWords, $allWords));
        });
    }

    /**
     * Clear the cache for filtered words.
     */
    public static function clearCache(): void
    {
        $types = ['chats', 'names', 'bios', 'walls', 'statuses', 'all'];
        foreach ($types as $type) {
            Cache::forget("filtered_words_{$type}");
        }
    }
}

