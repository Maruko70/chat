/**
 * Bootstrap Data Composable
 * Fetches and caches frequently accessed data (site settings, rooms)
 * Uses localStorage to persist data across page loads
 * Shows cached data immediately, then fetches fresh data in background
 */

interface BootstrapData {
  site_settings: Record<string, any>
  rooms: any[]
  timestamp: string
  profile?: any | null
  stories?: any[]
  unread_count?: number
  active_users?: any[]
  wall_posts?: any[]
  wall_creator?: {
    wall_creator: any | null
    total_likes: number
    top_creators: any[]
  }
}

// Cache bootstrap data in memory
let bootstrapCache: BootstrapData | null = null
let bootstrapLoading = false
let bootstrapTimestamp: number | null = null
const CACHE_DURATION = 5 * 60 * 1000 // 5 minutes in milliseconds
const STORAGE_KEY_PREFIX = 'bootstrap_data_'

/**
 * Get cache key based on user authentication status
 */
const getCacheKey = (): string => {
  if (!import.meta.client) return 'bootstrap_data_guest'
  
  try {
    // Try to get user ID from auth store
    const authStore = useAuthStore()
    if (authStore?.user?.id) {
      return `${STORAGE_KEY_PREFIX}user_${authStore.user.id}`
    }
  } catch (e) {
    // Auth store not available yet
  }
  
  return `${STORAGE_KEY_PREFIX}guest`
}

/**
 * Load bootstrap data from localStorage
 */
const loadFromStorage = (): BootstrapData | null => {
  if (!import.meta.client) return null
  
  try {
    const cacheKey = getCacheKey()
    const cached = localStorage.getItem(cacheKey)
    if (!cached) return null
    
    const parsed = JSON.parse(cached)
    const { data, timestamp } = parsed
    
    // Check if cache is expired
    const age = Date.now() - timestamp
    if (age > CACHE_DURATION) {
      localStorage.removeItem(cacheKey)
      return null
    }
    
    return data as BootstrapData
  } catch (error) {
    console.error('Error loading bootstrap from storage:', error)
    return null
  }
}

/**
 * Save bootstrap data to localStorage
 */
const saveToStorage = (data: BootstrapData): void => {
  if (!import.meta.client) return
  
  try {
    const cacheKey = getCacheKey()
    const cacheData = {
      data,
      timestamp: Date.now(),
    }
    localStorage.setItem(cacheKey, JSON.stringify(cacheData))
  } catch (error) {
    console.error('Error saving bootstrap to storage:', error)
    // If quota exceeded, try to clear old caches
    try {
      const keys = Object.keys(localStorage)
      keys.forEach(key => {
        if (key.startsWith(STORAGE_KEY_PREFIX) && key !== getCacheKey()) {
          localStorage.removeItem(key)
        }
      })
      // Try again
      const cacheData = {
        data,
        timestamp: Date.now(),
      }
      localStorage.setItem(getCacheKey(), JSON.stringify(cacheData))
    } catch (e) {
      console.error('Failed to save bootstrap cache after cleanup:', e)
    }
  }
}

export const useBootstrap = () => {
  const { api } = useApi()

  /**
   * Fetch bootstrap data (site settings, rooms)
   * Loads from localStorage first, then fetches fresh data in background
   */
  const fetchBootstrap = async (force = false): Promise<BootstrapData | null> => {
    // If force refresh, skip cache
    if (force) {
      bootstrapLoading = true
      try {
        const data = await api('/bootstrap')
        bootstrapCache = data
        bootstrapTimestamp = Date.now()
        saveToStorage(data)
        return data
      } catch (error) {
        console.error('Error fetching bootstrap data:', error)
        return null
      } finally {
        bootstrapLoading = false
      }
    }

    // Check memory cache first
    if (bootstrapCache && bootstrapTimestamp) {
      const age = Date.now() - bootstrapTimestamp
      if (age < CACHE_DURATION) {
        // Still valid, but fetch fresh data in background
        fetchBootstrapInBackground()
        return bootstrapCache
      }
    }

    // Try localStorage cache
    const cachedData = loadFromStorage()
    if (cachedData) {
      // Show cached data immediately
      bootstrapCache = cachedData
      // Set timestamp based on when it was cached
      try {
        const cacheKey = getCacheKey()
        const cached = localStorage.getItem(cacheKey)
        if (cached) {
          const parsed = JSON.parse(cached)
          bootstrapTimestamp = parsed.timestamp || Date.now()
        } else {
          bootstrapTimestamp = Date.now()
        }
      } catch (e) {
        bootstrapTimestamp = Date.now()
      }
      
      // Fetch fresh data in background
      fetchBootstrapInBackground()
      return cachedData
    }

    // If already loading, wait for current request
    if (bootstrapLoading) {
      while (bootstrapLoading) {
        await new Promise(resolve => setTimeout(resolve, 50))
      }
      return bootstrapCache
    }

    // No cache, fetch directly
    bootstrapLoading = true
    try {
      const data = await api('/bootstrap')
      bootstrapCache = data
      bootstrapTimestamp = Date.now()
      saveToStorage(data)
      return data
    } catch (error) {
      console.error('Error fetching bootstrap data:', error)
      return null
    } finally {
      bootstrapLoading = false
    }
  }

  /**
   * Fetch bootstrap data in background (non-blocking)
   */
  const fetchBootstrapInBackground = async (): Promise<void> => {
    if (bootstrapLoading) return
    
    try {
      bootstrapLoading = true
      const data = await api('/bootstrap')
      bootstrapCache = data
      bootstrapTimestamp = Date.now()
      saveToStorage(data)
    } catch (error) {
      console.error('Error fetching bootstrap data in background:', error)
      // Keep showing cached data on error
    } finally {
      bootstrapLoading = false
    }
  }

  /**
   * Get cached bootstrap data (returns null if not loaded)
   * Checks memory cache first, then localStorage
   */
  const getBootstrap = (): BootstrapData | null => {
    // Check memory cache first
    if (bootstrapCache && bootstrapTimestamp) {
      const age = Date.now() - bootstrapTimestamp
      if (age < CACHE_DURATION) {
        return bootstrapCache
      }
    }

    // Try localStorage
    const cachedData = loadFromStorage()
    if (cachedData) {
      bootstrapCache = cachedData
      bootstrapTimestamp = Date.now()
      return cachedData
    }

    return null
  }

  /**
   * Clear bootstrap cache (both memory and localStorage)
   */
  const clearBootstrap = (): void => {
    bootstrapCache = null
    bootstrapTimestamp = null
    
    if (import.meta.client) {
      try {
        const cacheKey = getCacheKey()
        localStorage.removeItem(cacheKey)
        // Also clear other user's bootstrap caches
        const keys = Object.keys(localStorage)
        keys.forEach(key => {
          if (key.startsWith(STORAGE_KEY_PREFIX)) {
            localStorage.removeItem(key)
          }
        })
      } catch (error) {
        console.error('Error clearing bootstrap cache:', error)
      }
    }
  }

  /**
   * Initialize bootstrap data (call this on app startup)
   * Loads from localStorage first, then fetches fresh data in background
   */
  const initBootstrap = async (): Promise<void> => {
    // Load from localStorage first (synchronous)
    const cachedData = loadFromStorage()
    if (cachedData) {
      bootstrapCache = cachedData
      bootstrapTimestamp = Date.now()
    }
    
    // Then fetch fresh data (async, non-blocking)
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

