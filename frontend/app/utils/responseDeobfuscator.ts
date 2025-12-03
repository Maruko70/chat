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
  if (import.meta.dev) {
    console.log('[Obfuscation] Key set from runtime config:', key)
  }
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
  
  // Debug log
  if (import.meta.dev) {
    console.log('[Obfuscation] Key not set from config, using env/default')
    console.log('  - NUXT_PUBLIC_OBFUSCATION_KEY value:', envKey || '(undefined, using default)')
    console.log('  - Using key:', key)
    console.log('  - Expected: H4YB0XdaflQ3AKm7Lc5xku2TbpRj9Gsy')
    console.log('  - Match:', key === 'H4YB0XdaflQ3AKm7Lc5xku2TbpRj9Gsy')
    
    if (key === 'CHAT_OBFUSCATION_KEY_2025') {
      console.warn('[Obfuscation] ⚠️ Using default key!')
    }
  }
  
  cachedKey = key
  return key
}

/**
 * Generate encryption key from salt (matching PHP hash('sha256'))
 * Using Web Crypto API for SHA-256 hashing
 */
async function generateKey(salt: string): Promise<string> {
  // Combine base key with salt
  const keyBase = getKeyBase()
  const combined = keyBase + salt
  
  if (import.meta.dev) {
    console.log('[KeyGen] Key base:', keyBase)
    console.log('[KeyGen] Salt:', salt)
    console.log('[KeyGen] Combined input:', combined)
    console.log('[KeyGen] Combined length:', combined.length)
  }
  
  // Use Web Crypto API for SHA-256 (matches PHP's hash('sha256'))
  if (typeof crypto !== 'undefined' && crypto.subtle) {
    const encoder = new TextEncoder()
    const data = encoder.encode(combined)
    
    if (import.meta.dev) {
      console.log('[KeyGen] Encoded bytes length:', data.length)
      console.log('[KeyGen] First 10 bytes:', Array.from(data.slice(0, 10)).join(', '))
    }
    
    const hashBuffer = await crypto.subtle.digest('SHA-256', data)
    const hashArray = Array.from(new Uint8Array(hashBuffer))
    const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('')
    
    // PHP's hash('sha256') returns 64 hex chars, we take first 32
    const key = hashHex.substring(0, 32)
    
    if (import.meta.dev) {
      console.log('[KeyGen] Full hash (64 chars):', hashHex)
      console.log('[KeyGen] Generated key (first 32):', key)
      
      // Test with known salt to verify key generation
      if (salt === '22aacf270ecb603b') {
        console.log('[KeyGen] ⚠️ Testing with salt 22aacf270ecb603b')
        console.log('[KeyGen] Expected key for this salt (if key base is H4YB0XdaflQ3AKm7Lc5xku2TbpRj9Gsy): Check backend')
      }
    }
    
    return key
  }
  
  throw new Error('Web Crypto API not available')
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
    
    if (import.meta.dev) {
      console.log('[Deobfuscate] Salt:', salt)
      console.log('[Deobfuscate] Key length:', key?.length)
      console.log('[Deobfuscate] Key preview:', key?.substring(0, 10))
      console.log('[Deobfuscate] Encoded length:', encoded.length)
    }
    
    if (!key || key.length !== 32) {
      throw new Error(`Invalid key generated: length ${key?.length || 0}`)
    }
    
    // Base64 decode - convert to Uint8Array for proper byte handling
    let encryptedBytes: Uint8Array
    try {
      const binaryString = atob(encoded)
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
    
    if (import.meta.dev) {
      console.log('[Deobfuscate] Decrypted JSON length:', json.length)
      console.log('[Deobfuscate] Decrypted JSON preview (first 200 chars):', json.substring(0, 200))
      // Check if it looks like valid JSON
      const trimmed = json.trim()
      const firstChar = trimmed[0]
      console.log('[Deobfuscate] First character:', firstChar, 'Expected: { or [')
      
      // Check if key might be wrong by looking at the first few bytes
      if (firstChar !== '{' && firstChar !== '[') {
        console.error('[Deobfuscate] ⚠️ Decrypted data does not start with { or [')
        console.error('[Deobfuscate] Key used:', key)
        console.error('[Deobfuscate] Key base:', getKeyBase())
        console.error('[Deobfuscate] First 10 bytes (hex):', Array.from(decryptedBytes.slice(0, 10)).map(b => b.toString(16).padStart(2, '0')).join(' '))
        console.error('[Deobfuscate] First 10 bytes (decimal):', Array.from(decryptedBytes.slice(0, 10)).join(', '))
      }
    }
    
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
      
      if (import.meta.dev) {
        console.error('[Deobfuscate] JSON parse error details:')
        console.error('  - Error:', (e as Error).message)
        console.error('  - Key base:', getKeyBase())
        console.error('  - Key (first 10):', key.substring(0, 10))
        console.error('  - Salt:', salt)
        console.error('  - Encrypted length:', encryptedBytes.length)
        console.error('  - Decrypted length:', decryptedBytes.length)
      }
      
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

