<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YouTubeController extends Controller
{
    /**
     * Search YouTube videos without API key.
     * Uses YouTube's public search page parsing.
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'required|string|max:100',
        ]);

        $query = $request->input('q');
        
        try {
            // Use YouTube's public search endpoint with proper headers
            $searchUrl = 'https://www.youtube.com/results?search_query=' . urlencode($query);
            
            // Fetch the search page with browser-like headers
            $response = Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.5',
            ])->timeout(15)->get($searchUrl);
            
            if (!$response->successful()) {
                return response()->json([
                    'error' => 'Failed to search YouTube',
                    'videos' => []
                ], 500);
            }

            $html = $response->body();
            $videos = [];
            
            // Method 1: Try to extract from ytInitialData JSON
            if (preg_match('/var ytInitialData = ({.+?});/', $html, $matches)) {
                try {
                    $data = json_decode($matches[1], true);
                    
                    if (isset($data['contents']['twoColumnSearchResultsRenderer']['primaryContents']['sectionListRenderer']['contents'])) {
                        foreach ($data['contents']['twoColumnSearchResultsRenderer']['primaryContents']['sectionListRenderer']['contents'] as $section) {
                            if (isset($section['itemSectionRenderer']['contents'])) {
                                foreach ($section['itemSectionRenderer']['contents'] as $item) {
                                    if (isset($item['videoRenderer'])) {
                                        $video = $item['videoRenderer'];
                                        $videoId = $video['videoId'] ?? null;
                                        
                                        if ($videoId) {
                                            $title = $video['title']['runs'][0]['text'] ?? ($video['title']['simpleText'] ?? '');
                                            $thumbnail = $video['thumbnail']['thumbnails'][count($video['thumbnail']['thumbnails']) - 1]['url'] ?? '';
                                            $channelTitle = $video['ownerText']['runs'][0]['text'] ?? ($video['ownerText']['simpleText'] ?? '');
                                            
                                            $videos[] = [
                                                'id' => $videoId,
                                                'title' => $title,
                                                'thumbnail' => $thumbnail,
                                                'channelTitle' => $channelTitle,
                                            ];
                                            
                                            if (count($videos) >= 10) {
                                                break 2;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Failed to parse ytInitialData', ['error' => $e->getMessage()]);
                }
            }
            
            // Method 2: Fallback - Extract video IDs and use oEmbed
            if (empty($videos)) {
                // Extract video IDs from watch links in the HTML
                preg_match_all('/watch\?v=([a-zA-Z0-9_-]{11})/', $html, $videoIds);
                
                if (!empty($videoIds[1])) {
                    $uniqueIds = array_unique(array_slice($videoIds[1], 0, 10));
                    
                    foreach ($uniqueIds as $videoId) {
                        try {
                            // Use oEmbed to get video details (no API key needed)
                            $oEmbedUrl = "https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v={$videoId}&format=json";
                            $oEmbedResponse = Http::timeout(5)->get($oEmbedUrl);
                            
                            if ($oEmbedResponse->successful()) {
                                $oEmbedData = $oEmbedResponse->json();
                                $videos[] = [
                                    'id' => $videoId,
                                    'title' => $oEmbedData['title'] ?? '',
                                    'thumbnail' => $oEmbedData['thumbnail_url'] ?? '',
                                    'channelTitle' => $oEmbedData['author_name'] ?? '',
                                ];
                            }
                        } catch (\Exception $e) {
                            // Skip this video if oEmbed fails
                            continue;
                        }
                        
                        if (count($videos) >= 10) {
                            break;
                        }
                    }
                }
            }
            
            return response()->json([
                'videos' => $videos,
            ]);
            
        } catch (\Exception $e) {
            Log::error('YouTube search error', [
                'error' => $e->getMessage(),
                'query' => $query,
            ]);
            
            return response()->json([
                'error' => 'Failed to search YouTube',
                'videos' => []
            ], 500);
        }
    }
}
