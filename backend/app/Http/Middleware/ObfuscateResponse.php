<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\ResponseObfuscationService;

class ObfuscateResponse
{
    protected $obfuscationService;

    public function __construct(ResponseObfuscationService $obfuscationService)
    {
        $this->obfuscationService = $obfuscationService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Skip obfuscation for broadcasting auth endpoint (Pusher/Echo requires plain JSON)
        if ($request->is('api/broadcasting/auth')) {
            return $response;
        }
        
        // Skip obfuscation for debug routes
        if ($request->is('api/debug/*')) {
            return $response;
        }

        // Only obfuscate JSON responses
        if ($response->headers->get('Content-Type') === 'application/json' || 
            str_contains($response->headers->get('Content-Type', ''), 'application/json')) {
            
            // Get the response content
            $content = $response->getContent();
            
            // Try to decode JSON to verify it's valid JSON
            $jsonData = json_decode($content, true);
            
            if (json_last_error() === JSON_ERROR_NONE && ($jsonData !== null || $content === 'null')) {
                // Obfuscate the response
                $obfuscated = $this->obfuscationService->obfuscate($jsonData);
                
                // Set obfuscated content
                $response->setContent($obfuscated);
                
                // Change content type to text/plain to make it less obvious
                $response->headers->set('Content-Type', 'text/plain; charset=utf-8');
                
                // Add a custom header to indicate obfuscation (for frontend)
                $response->headers->set('X-Response-Encrypted', '1');
            }
        }

        return $response;
    }
}

