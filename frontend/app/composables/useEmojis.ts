// Composable for managing emojis from the database
// Provides caching for quick loading

const CACHE_KEY = 'emojis_cache'
const CACHE_VERSION = '1.0'
const CACHE_EXPIRY = 1000 * 60 * 60 // 1 hour

interface EmojiCache {
  version: string
  timestamp: number
  emojis: Array<{
    id: number
    name: string
    url: string
    priority: number
  }>
}

// Global state for emojis
const emojisCache = ref<Map<number, { id: number; name: string; url: string; priority: number }>>(new Map())
const loading = ref(false)
const lastFetchTime = ref<number>(0)

export const useEmojis = () => {
  const { api } = useApi()

  // Load emojis from cache
  const loadFromCache = (): boolean => {
    if (!import.meta.client) return false

    try {
      const cached = localStorage.getItem(CACHE_KEY)
      if (!cached) return false

      const cacheData: EmojiCache = JSON.parse(cached)
      
      // Check version and expiry
      if (cacheData.version !== CACHE_VERSION) {
        localStorage.removeItem(CACHE_KEY)
        return false
      }

      if (Date.now() - cacheData.timestamp > CACHE_EXPIRY) {
        // Cache expired, but still use it while fetching fresh data
        return false
      }

      // Load into memory cache
      emojisCache.value.clear()
      cacheData.emojis.forEach(emoji => {
        emojisCache.value.set(emoji.id, emoji)
      })
      lastFetchTime.value = cacheData.timestamp

      return true
    } catch (error) {
      console.error('Error loading emojis from cache:', error)
      localStorage.removeItem(CACHE_KEY)
      return false
    }
  }

  // Save emojis to cache
  const saveToCache = (emojis: Array<{ id: number; name: string; url: string; priority: number }>) => {
    if (!import.meta.client) return

    try {
      const cacheData: EmojiCache = {
        version: CACHE_VERSION,
        timestamp: Date.now(),
        emojis,
      }
      localStorage.setItem(CACHE_KEY, JSON.stringify(cacheData))
    } catch (error) {
      console.error('Error saving emojis to cache:', error)
    }
  }

  // Fetch emojis from API
  const fetchEmojis = async (force = false): Promise<Map<number, { id: number; name: string; url: string; priority: number }>> => {
    // If already loading and not forcing, wait for current request
    if (loading.value && !force) {
      while (loading.value) {
        await new Promise(resolve => setTimeout(resolve, 50))
      }
      return emojisCache.value
    }

    // Check cache first if not forcing
    if (!force && emojisCache.value.size > 0) {
      const cacheAge = Date.now() - lastFetchTime.value
      // Use cache if less than 5 minutes old
      if (cacheAge < 1000 * 60 * 5) {
        return emojisCache.value
      }
    }

    // Try loading from localStorage cache
    if (!force && loadFromCache() && emojisCache.value.size > 0) {
      // Cache loaded, fetch fresh data in background
      fetchEmojis(true).catch(() => {
        // Ignore errors in background fetch
      })
      return emojisCache.value
    }

    loading.value = true
    try {
      const emojis = await api('/symbols?type=emoji&is_active=true') as Array<{
        id: number
        name: string
        url: string
        priority: number
      }>

      // Sort by priority (descending), then by name
      emojis.sort((a, b) => {
        if (b.priority !== a.priority) {
          return b.priority - a.priority
        }
        return (a.name || '').localeCompare(b.name || '')
      })

      // Update memory cache
      emojisCache.value.clear()
      emojis.forEach(emoji => {
        emojisCache.value.set(emoji.id, emoji)
      })
      lastFetchTime.value = Date.now()

      // Save to localStorage cache
      saveToCache(emojis)

      return emojisCache.value
    } catch (error) {
      console.error('Error fetching emojis:', error)
      // If we have cached data, return it even if expired
      if (emojisCache.value.size > 0) {
        return emojisCache.value
      }
      throw error
    } finally {
      loading.value = false
    }
  }

  // Get emoji by ID
  const getEmoji = (id: number): { id: number; name: string; url: string; priority: number } | null => {
    return emojisCache.value.get(id) || null
  }

  // Get emoji URL by ID
  const getEmojiUrl = (id: number): string | null => {
    const emoji = emojisCache.value.get(id)
    return emoji?.url || null
  }

  // Get all emojis as array
  const getAllEmojis = (): Array<{ id: number; name: string; url: string; priority: number }> => {
    return Array.from(emojisCache.value.values())
  }

  // Get emoji list (array of IDs) for the emoji panel
  const getEmojiList = (): number[] => {
    return Array.from(emojisCache.value.keys())
  }

  // Clear cache
  const clearCache = () => {
    emojisCache.value.clear()
    if (import.meta.client) {
      localStorage.removeItem(CACHE_KEY)
    }
    lastFetchTime.value = 0
  }

  return {
    emojis: readonly(emojisCache),
    loading: readonly(loading),
    fetchEmojis,
    getEmoji,
    getEmojiUrl,
    getAllEmojis,
    getEmojiList,
    clearCache,
  }
}

