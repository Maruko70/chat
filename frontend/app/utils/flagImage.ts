// Preload all flag images at build time using import.meta.glob
// This ensures all flag images are available and properly handled by Nuxt
// Using ~/assets/flags/ to match the emoji pattern (which uses ~/assets/emojis/)
const flagImages = import.meta.glob('~/assets/flags/*.png', { 
  eager: true, 
  query: '?url', 
  import: 'default' 
}) as Record<string, string>

// Debug: Log loaded flag images in development
if (import.meta.dev) {
  console.log('[Flag Images] Loaded flag images:', Object.keys(flagImages).length)
  if (Object.keys(flagImages).length === 0) {
    console.warn('[Flag Images] ⚠️ No flag images found! Check the glob pattern: ~/assets/flags/*.png')
  } else {
    console.log('[Flag Images] ✅ Sample paths:', Object.keys(flagImages).slice(0, 5))
  }
}

/**
 * Get flag image URL from country code
 * @param countryCode - ISO 3166-1 alpha-2 country code (e.g., "SA", "US", "SD")
 * @returns URL to flag image or default SA flag
 */
export function getFlagImageUrl(countryCode: string | null | undefined): string {
  // Default to Saudi Arabia if invalid
  if (!countryCode || countryCode.length !== 2) {
    countryCode = 'SA'
  }

  // Convert to lowercase for filename matching
  const code = countryCode.toLowerCase()
  const targetFile = `${code}.png`

  // Debug logging
  if (import.meta.dev) {
    console.log(`[Flag Images] Looking for country code: ${countryCode} (file: ${targetFile})`)
    console.log(`[Flag Images] Available flag images count: ${Object.keys(flagImages).length}`)
  }

  // Try to find the flag image in the preloaded images
  // The glob pattern creates paths like: ~/assets/flags/sa.png
  // We need to match against the actual file path
  for (const [path, url] of Object.entries(flagImages)) {
    // Extract filename from path (e.g., "sa.png" from "~/app/assets/flags/sa.png")
    const filename = path.split('/').pop()?.toLowerCase()
    
    if (import.meta.dev && filename === targetFile) {
      console.log(`[Flag Images] Found match: ${path} -> ${url}`)
    }
    
    if (filename === targetFile) {
      return url as string
    }
  }

  // Fallback: try to get SA flag explicitly
  for (const [path, url] of Object.entries(flagImages)) {
    const filename = path.split('/').pop()?.toLowerCase()
    if (filename === 'sa.png') {
      if (import.meta.dev) {
        console.log(`[Flag Images] Using SA fallback: ${path} -> ${url}`)
      }
      return url as string
    }
  }

  // Final fallback: return empty string (image will fail to load and be hidden)
  console.warn(`[Flag Images] Flag image not found for country code: ${countryCode}`)
  if (import.meta.dev) {
    console.log(`[Flag Images] Available files:`, Object.keys(flagImages).map(p => p.split('/').pop()).slice(0, 10))
  }
  return ''
}

