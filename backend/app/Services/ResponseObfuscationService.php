<?php

namespace App\Services;

class ResponseObfuscationService
{
    /**
     * Get the encryption key base from environment
     * Must be set in .env file: RESPONSE_OBFUSCATION_KEY
     */
    private function getKeyBase(): string
    {
        $key = env('RESPONSE_OBFUSCATION_KEY', 'CHAT_OBFUSCATION_KEY_2025');
        
        // Debug logging in development
        if (config('app.debug')) {
            \Log::debug('ResponseObfuscationService: Using key base', [
                'key_length' => strlen($key),
                'key_preview' => substr($key, 0, 10) . '...',
                'key_full' => $key, // Log full key in dev for debugging
                'is_default' => $key === 'CHAT_OBFUSCATION_KEY_2025',
                'expected' => 'H4YB0XdaflQ3AKm7Lc5xku2TbpRj9Gsy',
                'matches' => $key === 'H4YB0XdaflQ3AKm7Lc5xku2TbpRj9Gsy',
            ]);
        }
        
        return $key;
    }

    /**
     * Obfuscate/encrypt response data
     * 
     * @param mixed $data
     * @return string
     */
    public function obfuscate($data): string
    {
        // Convert to JSON
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        // Generate a random salt for this response
        $salt = bin2hex(random_bytes(8));
        
        // Create a unique key for this response based on salt
        $key = $this->generateKey($salt);
        
        // XOR encrypt the JSON
        $encrypted = $this->xorEncrypt($json, $key);
        
        // Base64 encode
        $encoded = base64_encode($encrypted);
        
        // Combine salt + encoded data with separator
        $obfuscated = $salt . '|' . $encoded;
        
        // Add some random padding to make responses look different
        $padding = bin2hex(random_bytes(4));
        
        return $padding . $obfuscated;
    }

    /**
     * Deobfuscate/decrypt response data
     * 
     * @param string $obfuscated
     * @return mixed
     */
    public function deobfuscate(string $obfuscated)
    {
        try {
            // Remove padding (first 8 hex chars = 4 bytes)
            $data = substr($obfuscated, 8);
            
            // Split salt and encoded data
            $parts = explode('|', $data, 2);
            if (count($parts) !== 2) {
                throw new \Exception('Invalid obfuscated data format');
            }
            
            [$salt, $encoded] = $parts;
            
            // Generate the same key using salt
            $key = $this->generateKey($salt);
            
            // Base64 decode
            $encrypted = base64_decode($encoded, true);
            if ($encrypted === false) {
                throw new \Exception('Failed to decode base64');
            }
            
            // XOR decrypt
            $json = $this->xorEncrypt($encrypted, $key);
            
            // Decode JSON
            $data = json_decode($json, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Failed to decode JSON: ' . json_last_error_msg());
            }
            
            return $data;
        } catch (\Exception $e) {
            // If deobfuscation fails, return null or throw
            throw new \Exception('Failed to deobfuscate response: ' . $e->getMessage());
        }
    }

    /**
     * Generate encryption key from salt
     * 
     * @param string $salt
     * @return string
     */
    private function generateKey(string $salt): string
    {
        // Combine base key with salt
        $combined = $this->getKeyBase() . $salt;
        
        // Use hash to create a consistent key
        $hash = hash('sha256', $combined);
        
        // Return first 32 bytes (XOR key length)
        return substr($hash, 0, 32);
    }

    /**
     * XOR encrypt/decrypt (XOR is symmetric)
     * 
     * @param string $data
     * @param string $key
     * @return string
     */
    private function xorEncrypt(string $data, string $key): string
    {
        $keyLength = strlen($key);
        $dataLength = strlen($data);
        $result = '';
        
        for ($i = 0; $i < $dataLength; $i++) {
            $result .= $data[$i] ^ $key[$i % $keyLength];
        }
        
        return $result;
    }

    /**
     * Check if a string is obfuscated
     * 
     * @param string $data
     * @return bool
     */
    public function isObfuscated(string $data): bool
    {
        // Obfuscated data should have padding (8 hex chars) + salt (16 hex chars) + separator + base64
        return strlen($data) > 24 && strpos($data, '|') !== false;
    }
}

