// Preload all flag images at build time using import.meta.glob
// This ensures all flag images are available and properly handled by Nuxt
// Using ~/assets/flags/ to match the emoji pattern (which uses ~/assets/emojis/)
const flagImages = import.meta.glob('~/assets/flags/*.png', { 
  eager: true, 
  query: '?url', 
  import: 'default' 
}) as Record<string, string>


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


  // Try to find the flag image in the preloaded images
  // The glob pattern creates paths like: ~/assets/flags/sa.png
  // We need to match against the actual file path
  for (const [path, url] of Object.entries(flagImages)) {
    // Extract filename from path (e.g., "sa.png" from "~/app/assets/flags/sa.png")
    const filename = path.split('/').pop()?.toLowerCase()
    
    
    if (filename === targetFile) {
      return url as string
    }
  }

  // Fallback: try to get SA flag explicitly
  for (const [path, url] of Object.entries(flagImages)) {
    const filename = path.split('/').pop()?.toLowerCase()
    if (filename === 'sa.png') {
      return url as string
    }
  }

  return ''
}

