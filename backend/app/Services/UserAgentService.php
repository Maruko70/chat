<?php

namespace App\Services;

class UserAgentService
{
    /**
     * Detect browser name from user agent string.
     * 
     * @param string|null $userAgent
     * @return string|null
     */
    public function detectBrowser(?string $userAgent): ?string
    {
        if (!$userAgent) {
            return null;
        }

        $userAgent = strtolower($userAgent);

        // Chrome (must check before Safari)
        if (strpos($userAgent, 'chrome') !== false && strpos($userAgent, 'edg') === false && strpos($userAgent, 'opr') === false) {
            // Check if it's Android WebView
            if (strpos($userAgent, 'wv') !== false) {
                return 'Android WebView';
            }
            return 'Chrome';
        }

        // Edge (must check before Chrome)
        if (strpos($userAgent, 'edg') !== false || strpos($userAgent, 'edge') !== false) {
            return 'Edge';
        }

        // Opera
        if (strpos($userAgent, 'opr') !== false || strpos($userAgent, 'opera') !== false) {
            return 'Opera';
        }

        // Firefox
        if (strpos($userAgent, 'firefox') !== false) {
            return 'Firefox';
        }

        // Safari (must check after Chrome)
        if (strpos($userAgent, 'safari') !== false && strpos($userAgent, 'chrome') === false) {
            return 'Safari';
        }

        // Internet Explorer
        if (strpos($userAgent, 'msie') !== false || strpos($userAgent, 'trident') !== false) {
            return 'Internet Explorer';
        }

        // Samsung Internet
        if (strpos($userAgent, 'samsungbrowser') !== false) {
            return 'Samsung Internet';
        }

        return null;
    }

    /**
     * Detect operating system from user agent string.
     * 
     * @param string|null $userAgent
     * @return string|null
     */
    public function detectOperatingSystem(?string $userAgent): ?string
    {
        if (!$userAgent) {
            return null;
        }

        $userAgent = strtolower($userAgent);

        // Windows
        if (strpos($userAgent, 'windows') !== false) {
            return 'Windows';
        }

        // Android
        if (strpos($userAgent, 'android') !== false) {
            return 'Android';
        }

        // iOS
        if (strpos($userAgent, 'iphone') !== false || strpos($userAgent, 'ipad') !== false || strpos($userAgent, 'ipod') !== false) {
            return 'iOS';
        }

        // Mac OS
        if (strpos($userAgent, 'macintosh') !== false || strpos($userAgent, 'mac os') !== false) {
            return 'Mac OS';
        }

        // Linux
        if (strpos($userAgent, 'linux') !== false) {
            return 'Linux';
        }

        // Windows Phone
        if (strpos($userAgent, 'windows phone') !== false) {
            return 'Windows Phone';
        }

        return null;
    }

    /**
     * Get device info (browser + OS) from user agent.
     * 
     * @param string|null $userAgent
     * @return array{browser: string|null, os: string|null}
     */
    public function getDeviceInfo(?string $userAgent): array
    {
        return [
            'browser' => $this->detectBrowser($userAgent),
            'os' => $this->detectOperatingSystem($userAgent),
        ];
    }
}

