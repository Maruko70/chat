/**
 * Composable for localStorage caching with background refresh
 * Shows cached data immediately, then fetches fresh data in background
 */

interface CacheOptions<T> {
  key: string
  fetchFn: () => Promise<T>
  onUpdate?: (data: T) => void
  ttl?: number // Time to live in milliseconds (default: 5 minutes)
}

export const useLocalStorageCache = () => {
  /**
   * Get cached data from localStorage
   */
  const getCachedData = <T>(key: string): T | null => {
    if (!import.meta.client) return null
    
    try {
      const cached = localStorage.getItem(key)
      if (!cached) return null
      
      const parsed = JSON.parse(cached)
      const { data, timestamp } = parsed
      
      // Check if cache is expired (default 5 minutes)
      const ttl = parsed.ttl || 5 * 60 * 1000
      const age = Date.now() - timestamp
      
      if (age > ttl) {
        // Cache expired, remove it
        localStorage.removeItem(key)
        return null
      }
      
      return data as T
    } catch (error) {
      console.error(`Error reading cache for ${key}:`, error)
      localStorage.removeItem(key)
      return null
    }
  }

  /**
   * Save data to localStorage cache
   */
  const setCachedData = <T>(key: string, data: T, ttl?: number): void => {
    if (!import.meta.client) return
    
    try {
      const cacheData = {
        data,
        timestamp: Date.now(),
        ttl: ttl || 5 * 60 * 1000, // Default 5 minutes
      }
      localStorage.setItem(key, JSON.stringify(cacheData))
    } catch (error) {
      console.error(`Error saving cache for ${key}:`, error)
      // If quota exceeded, try to clear old caches
      try {
        clearOldCaches()
        localStorage.setItem(key, JSON.stringify({ data, timestamp: Date.now(), ttl: ttl || 5 * 60 * 1000 }))
      } catch (e) {
        console.error('Failed to save cache after cleanup:', e)
      }
    }
  }

  /**
   * Clear specific cache
   */
  const clearCache = (key: string): void => {
    if (!import.meta.client) return
    localStorage.removeItem(key)
  }

  /**
   * Clear old caches to free up space
   */
  const clearOldCaches = (): void => {
    if (!import.meta.client) return
    
    try {
      const keys = Object.keys(localStorage)
      const now = Date.now()
      
      keys.forEach(key => {
        if (key.startsWith('cache_')) {
          try {
            const cached = localStorage.getItem(key)
            if (cached) {
              const parsed = JSON.parse(cached)
              const ttl = parsed.ttl || 5 * 60 * 1000
              const age = now - parsed.timestamp
              
              if (age > ttl) {
                localStorage.removeItem(key)
              }
            }
          } catch (e) {
            // Invalid cache, remove it
            localStorage.removeItem(key)
          }
        }
      })
    } catch (error) {
      console.error('Error clearing old caches:', error)
    }
  }

  /**
   * Fetch data with caching: shows cached data immediately, then fetches fresh data in background
   */
  const fetchWithCache = async <T>(options: CacheOptions<T>): Promise<T> => {
    const { key, fetchFn, onUpdate, ttl } = options
    const cacheKey = `cache_${key}`
    
    // Try to get cached data first
    const cachedData = getCachedData<T>(cacheKey)
    
    if (cachedData) {
      // Show cached data immediately
      if (onUpdate) {
        onUpdate(cachedData)
      }
      
      // Fetch fresh data in background (don't await)
      fetchFn()
        .then((freshData) => {
          // Update cache with fresh data
          setCachedData(cacheKey, freshData, ttl)
          // Update UI with fresh data
          if (onUpdate) {
            onUpdate(freshData)
          }
        })
        .catch((error) => {
          console.error(`Error fetching fresh data for ${key}:`, error)
          // Keep showing cached data on error
        })
      
      // Return cached data immediately
      return cachedData
    }
    
    // No cache, fetch directly
    const data = await fetchFn()
    setCachedData(cacheKey, data, ttl)
    if (onUpdate) {
      onUpdate(data)
    }
    return data
  }

  return {
    getCachedData,
    setCachedData,
    clearCache,
    clearOldCaches,
    fetchWithCache,
  }
}

