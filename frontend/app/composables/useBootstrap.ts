/**
 * Bootstrap Data Composable
 * Fetches and caches frequently accessed data (site settings, rooms, shortcuts)
 * This reduces the number of API calls by getting all data in one request
 */

interface BootstrapData {
  site_settings: Record<string, any>
  rooms: any[]
  shortcuts: any[]
  timestamp: string
}

// Cache bootstrap data in memory
let bootstrapCache: BootstrapData | null = null
let bootstrapLoading = false
let bootstrapTimestamp: number | null = null
const CACHE_DURATION = 5 * 60 * 1000 // 5 minutes in milliseconds

export const useBootstrap = () => {
  const { api } = useApi()

  /**
   * Fetch bootstrap data (site settings, rooms, shortcuts)
   * Uses cache to avoid repeated requests
   */
  const fetchBootstrap = async (force = false): Promise<BootstrapData | null> => {
    // Check if we have valid cached data
    if (!force && bootstrapCache && bootstrapTimestamp) {
      const age = Date.now() - bootstrapTimestamp
      if (age < CACHE_DURATION) {
        return bootstrapCache
      }
    }

    // If already loading, wait for current request
    if (bootstrapLoading && !force) {
      while (bootstrapLoading) {
        await new Promise(resolve => setTimeout(resolve, 50))
      }
      return bootstrapCache
    }

    bootstrapLoading = true
    try {
      const data = await api('/bootstrap')
      bootstrapCache = data
      bootstrapTimestamp = Date.now()
      return data
    } catch (error) {
      console.error('Error fetching bootstrap data:', error)
      return null
    } finally {
      bootstrapLoading = false
    }
  }

  /**
   * Get cached bootstrap data (returns null if not loaded)
   */
  const getBootstrap = (): BootstrapData | null => {
    if (bootstrapCache && bootstrapTimestamp) {
      const age = Date.now() - bootstrapTimestamp
      if (age < CACHE_DURATION) {
        return bootstrapCache
      }
    }
    return null
  }

  /**
   * Clear bootstrap cache
   */
  const clearBootstrap = (): void => {
    bootstrapCache = null
    bootstrapTimestamp = null
  }

  /**
   * Initialize bootstrap data (call this on app startup)
   */
  const initBootstrap = async (): Promise<void> => {
    await fetchBootstrap()
  }

  return {
    fetchBootstrap,
    getBootstrap,
    clearBootstrap,
    initBootstrap,
    isLoading: computed(() => bootstrapLoading),
  }
}

