/**
 * Response Deobfuscation Utility
 * Matches the backend ResponseObfuscationService logic
 */

// Cache the key to avoid repeated lookups
let cachedKey: string | null = null

/**
 * Set the obfuscation key (called from composable with runtime config)
 */
export function setObfuscationKey(key: string): void {
  cachedKey = key
}

/**
 * Get the obfuscation key from cache or environment variable
 * Set NUXT_PUBLIC_OBFUSCATION_KEY in .env file
 * 
 * IMPORTANT: In Nuxt 3, you MUST restart the dev server after adding/changing .env variables
 */
function getKeyBase(): string {
  if (cachedKey) {
    return cachedKey
  }
  
  // Fallback: Access environment variable directly
  // In Nuxt 3, NUXT_PUBLIC_* variables are available via import.meta.env
  // But they're only loaded at build/start time, so server restart is required
  const envKey = import.meta.env.NUXT_PUBLIC_OBFUSCATION_KEY
  const key = envKey || 'CHAT_OBFUSCATION_KEY_2025'
  
  cachedKey = key
  return key
}

/**
 * Base64 decode helper that works in both browser and Node.js
 */
function base64Decode(encoded: string): string {
  // Try browser atob first
  if (typeof atob !== 'undefined') {
    return atob(encoded)
  }
  
  // Fallback to Node.js Buffer (SSR)
  if (typeof Buffer !== 'undefined') {
    return Buffer.from(encoded, 'base64').toString('binary')
  }
  
  throw new Error('Base64 decoding not available: atob and Buffer are both undefined')
}

/**
 * Generate encryption key from salt (matching PHP hash('sha256'))
 * Supports both browser (Web Crypto API) and Node.js SSR (crypto module)
 */
async function generateKey(salt: string): Promise<string> {
  // Combine base key with salt
  const keyBase = getKeyBase()
  const combined = keyBase + salt
 
  // Try Web Crypto API first (browser environment)
  if (typeof crypto !== 'undefined' && crypto.subtle) {
    const encoder = new TextEncoder()
    const data = encoder.encode(combined)

    const hashBuffer = await crypto.subtle.digest('SHA-256', data)
    const hashArray = Array.from(new Uint8Array(hashBuffer))
    const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('')
    
    // PHP's hash('sha256') returns 64 hex chars, we take first 32
    const key = hashHex.substring(0, 32)
    
    return key
  }
  
  // Fallback to Node.js crypto module (SSR environment)
  if (typeof process !== 'undefined' && process.versions?.node) {
    try {
      // Dynamic import for Node.js crypto module
      const { createHash } = await import('node:crypto')
      const hash = createHash('sha256')
      hash.update(combined, 'utf8')
      const hashHex = hash.digest('hex')
      
      // PHP's hash('sha256') returns 64 hex chars, we take first 32
      const key = hashHex.substring(0, 32)
      
      return key
    } catch (e) {
      throw new Error(`Failed to use Node.js crypto module: ${(e as Error).message}`)
    }
  }
  
  throw new Error('Web Crypto API not available and Node.js crypto module not accessible')
}

/**
 * XOR encrypt/decrypt (XOR is symmetric)
 * Works with raw bytes to preserve UTF-8 encoding correctly
 * PHP XORs byte-by-byte: $data[$i] ^ $key[$i % $keyLength]
 * The key is a hex string, so each character is treated as a byte (ASCII value)
 */
function xorEncrypt(dataBytes: Uint8Array, keyHex: string): Uint8Array {
  // In PHP, the hex key string is treated as a regular string
  // Each character's ASCII value is used for XOR
  // So '5' (ASCII 53) ^ data_byte, 'd' (ASCII 100) ^ data_byte, etc.
  const keyLength = keyHex.length
  const resultBytes = new Uint8Array(dataBytes.length)
  
  for (let i = 0; i < dataBytes.length; i++) {
    // Get the ASCII value of the hex character at position (i % keyLength)
    const keyByte = keyHex.charCodeAt(i % keyLength)
    resultBytes[i] = dataBytes[i] ^ keyByte
  }
  
  return resultBytes
}

/**
 * Deobfuscate/decrypt response data
 */
export async function deobfuscateResponse(obfuscated: string): Promise<any> {
  try {
    // Validate input
    if (!obfuscated || typeof obfuscated !== 'string' || obfuscated.length < 24) {
      throw new Error('Invalid obfuscated data: too short or not a string')
    }
    
    // Remove padding (first 8 hex chars = 4 bytes)
    const data = obfuscated.substring(8)
    
    // Split salt and encoded data
    const parts = data.split('|')
    if (parts.length !== 2) {
      throw new Error(`Invalid obfuscated data format: expected 2 parts, got ${parts.length}`)
    }
    
    const [salt, encoded] = parts
    
    // Validate salt (should be 16 hex characters)
    if (!salt || salt.length !== 16 || !/^[0-9a-fA-F]+$/.test(salt)) {
      throw new Error(`Invalid salt format: ${salt}`)
    }
    
    // Generate the same key using salt
    const key = await generateKey(salt)
    
    
    if (!key || key.length !== 32) {
      throw new Error(`Invalid key generated: length ${key?.length || 0}`)
    }
    
    // Base64 decode - convert to Uint8Array for proper byte handling
    let encryptedBytes: Uint8Array
    try {
      const binaryString = base64Decode(encoded)
      // Convert binary string to Uint8Array (byte array)
      // Each character in the binary string represents a byte (0-255)
      encryptedBytes = new Uint8Array(binaryString.length)
      for (let i = 0; i < binaryString.length; i++) {
        encryptedBytes[i] = binaryString.charCodeAt(i) & 0xFF // Ensure byte value (0-255)
      }
    } catch (e) {
      throw new Error(`Failed to decode base64: ${(e as Error).message}`)
    }
    
    if (!encryptedBytes || encryptedBytes.length === 0) {
      throw new Error('Decoded encrypted data is empty')
    }
    
    // XOR decrypt (works with raw bytes)
    const decryptedBytes = xorEncrypt(encryptedBytes, key)
    
    // Convert decrypted bytes back to UTF-8 string
    const decoder = new TextDecoder('utf-8', { fatal: false })
    const json = decoder.decode(decryptedBytes)
    
    if (!json || json.length === 0) {
      throw new Error('Decrypted JSON is empty')
    }
    
    // Decode JSON
    try {
      const parsed = JSON.parse(json)
      return parsed
    } catch (e) {
      // Log the first 200 chars of the decrypted JSON for debugging
      const preview = json.substring(0, 200)
      const charCodes = Array.from(json.substring(0, 50)).map(c => c.charCodeAt(0)).join(',')
      
      // Additional debugging: check if this might be a key mismatch
      const errorMsg = `Failed to decode JSON: ${(e as Error).message}. Preview: ${preview}. Char codes: ${charCodes}`
      
      throw new Error(errorMsg)
    }
  } catch (e) {
    throw new Error(`Failed to deobfuscate response: ${(e as Error).message}`)
  }
}

/**
 * Check if a string is obfuscated
 */
export function isObfuscated(data: string): boolean {
  // Obfuscated data should have padding (8 hex chars) + salt (16 hex chars) + separator + base64
  return data.length > 24 && data.includes('|')
}

