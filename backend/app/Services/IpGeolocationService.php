<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IpGeolocationService
{
    /**
     * Get country from IP address using ip-api.com (free service)
     * 
     * @param string|null $ipAddress
     * @return string|null
     */
    public function getCountryFromIp(?string $ipAddress): ?string
    {
        // Skip private/local IPs or null
        if (!$ipAddress || $this->isPrivateIp($ipAddress)) {
            return null;
        }

        try {
            // Using ip-api.com free service (no API key required, rate limit: 45 requests/minute)
            $response = Http::timeout(3)->get("http://ip-api.com/json/{$ipAddress}", [
                'fields' => 'status,country,countryCode'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['status']) && $data['status'] === 'success') {
                    return $data['country'] ?? null;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get country from IP', [
                'ip' => $ipAddress,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * Get country code from IP address using ip-api.com (free service)
     * 
     * @param string|null $ipAddress
     * @return string|null
     */
    public function getCountryCodeFromIp(?string $ipAddress): ?string
    {
        // Skip private/local IPs or null
        if (!$ipAddress || $this->isPrivateIp($ipAddress)) {
            return null;
        }

        try {
            // Using ip-api.com free service (no API key required, rate limit: 45 requests/minute)
            $response = Http::timeout(3)->get("http://ip-api.com/json/{$ipAddress}", [
                'fields' => 'status,country,countryCode'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['status']) && $data['status'] === 'success') {
                    return $data['countryCode'] ?? null;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get country code from IP', [
                'ip' => $ipAddress,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    /**
     * Get both country name and country code from IP address
     * Defaults to Saudi Arabia (SA) if unable to determine or on error
     * 
     * @param string|null $ipAddress
     * @return array{country: string|null, countryCode: string}
     */
    public function getCountryDataFromIp(?string $ipAddress): array
    {
        // Skip private/local IPs or null - return default SA
        if (!$ipAddress || $this->isPrivateIp($ipAddress)) {
            return ['country' => 'Saudi Arabia', 'countryCode' => 'SA'];
        }

        try {
            // Using ip-api.com free service (no API key required, rate limit: 45 requests/minute)
            $response = Http::timeout(3)->get("http://ip-api.com/json/{$ipAddress}", [
                'fields' => 'status,country,countryCode'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['status']) && $data['status'] === 'success') {
                    $country = $data['country'] ?? 'Saudi Arabia';
                    $countryCode = $data['countryCode'] ?? 'SA';
                    return [
                        'country' => $country,
                        'countryCode' => $countryCode,
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning('Failed to get country data from IP', [
                'ip' => $ipAddress,
                'error' => $e->getMessage()
            ]);
        }

        // Default to Saudi Arabia on error
        return ['country' => 'Saudi Arabia', 'countryCode' => 'SA'];
    }

    /**
     * Convert country code (ISO 3166-1 alpha-2) to flag emoji
     * Defaults to Saudi Arabia (SA) flag if invalid or null
     * 
     * @param string|null $countryCode
     * @return string
     */
    public function countryCodeToFlag(?string $countryCode): string
    {
        // Default to Saudi Arabia if invalid or null
        if (!$countryCode || strlen($countryCode) !== 2) {
            $countryCode = 'SA';
        }

        // Convert country code to uppercase
        $countryCode = strtoupper($countryCode);

        // Convert each letter to its regional indicator symbol
        // Regional Indicator Symbol Letter A (U+1F1E6) is the base
        $flag = '';
        for ($i = 0; $i < 2; $i++) {
            $char = $countryCode[$i];
            $flag .= mb_chr(0x1F1E6 + ord($char) - ord('A'), 'UTF-8');
        }

        return $flag;
    }

    /**
     * Check if IP address is private/local
     * 
     * @param string|null $ipAddress
     * @return bool
     */
    private function isPrivateIp(?string $ipAddress): bool
    {
        if (!$ipAddress) {
            return true; // Treat null as private/local
        }
        return !filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    /**
     * Get client IP address from request
     * 
     * @param \Illuminate\Http\Request $request
     * @return string|null
     */
    public function getClientIp(\Illuminate\Http\Request $request): ?string
    {
        // List of headers to check (in order of priority)
        $headers = [
            'CF-Connecting-IP',     // Cloudflare
            'True-Client-IP',       // Akamai/Cloudflare Enterprise
            'X-Real-IP',            // Nginx proxy
            'X-Forwarded-For',      // Standard proxy header
            'X-Client-IP',          // Some proxies
            'X-Forwarded',          // Some proxies
            'X-Cluster-Client-IP',  // Cluster
            'Forwarded-For',        // RFC 7239
            'Forwarded',            // RFC 7239
        ];

        $foundHeaders = [];
        foreach ($headers as $header) {
            $ip = $request->header($header);
            if ($ip) {
                $foundHeaders[$header] = $ip;
                // Handle comma-separated list (X-Forwarded-For can have multiple IPs)
                // Format: client, proxy1, proxy2 - we want the rightmost public IP
                $ips = array_map('trim', explode(',', $ip));
                
                // Reverse to check from rightmost (last proxy) to leftmost (original client)
                // This helps when proxies add their IPs to the chain
                $ips = array_reverse($ips);
                
                // Find the first valid, non-private IP (rightmost public IP)
                foreach ($ips as $candidateIp) {
                    if ($this->isValidPublicIp($candidateIp)) {
                        Log::debug('Found public IP from header', [
                            'header' => $header,
                            'ip' => $candidateIp,
                            'all_ips' => $ips
                        ]);
                        return $candidateIp;
                    }
                }
                
                // If no public IP found, return the rightmost valid IP
                foreach ($ips as $candidateIp) {
                    if (filter_var($candidateIp, FILTER_VALIDATE_IP)) {
                        Log::debug('Found IP from header (may be private)', [
                            'header' => $header,
                            'ip' => $candidateIp,
                            'all_ips' => $ips
                        ]);
                        return $candidateIp;
                    }
                }
            }
        }

        // Fallback to Laravel's IP detection
        $ip = $request->ip();
        
        // Log what we found for debugging
        if (!empty($foundHeaders)) {
            Log::debug('IP detection - found headers but no public IP', [
                'headers' => $foundHeaders,
                'laravel_ip' => $ip,
                'is_private' => $this->isPrivateIp($ip)
            ]);
        }
        
        // If we got a private IP (127.0.0.1, etc.), try to get from server variables
        if ($this->isPrivateIp($ip)) {
            // Check $_SERVER directly as last resort
            $serverIp = $_SERVER['REMOTE_ADDR'] ?? null;
            if ($serverIp && filter_var($serverIp, FILTER_VALIDATE_IP) && !$this->isPrivateIp($serverIp)) {
                Log::debug('Found public IP from REMOTE_ADDR', ['ip' => $serverIp]);
                return $serverIp;
            }
            
            // Log warning for local development
            Log::warning('Detected private IP address - this is normal for local development', [
                'ip' => $ip,
                'headers_found' => array_keys($foundHeaders),
                'note' => 'In production behind a proxy, ensure X-Forwarded-For or X-Real-IP headers are set'
            ]);
        }

        return $ip ?: '0.0.0.0';
    }

    /**
     * Check if IP is a valid public IP (not private/local)
     * 
     * @param string $ip
     * @return bool
     */
    private function isValidPublicIp(string $ip): bool
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        
        return !$this->isPrivateIp($ip);
    }
}

