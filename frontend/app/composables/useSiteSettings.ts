// Singleton state - shared across all instances
const globalSettings = ref<Record<string, any>>({})
const globalLoading = ref(false)

// localStorage keys
const STORAGE_KEY = 'site_settings_cache'
const STORAGE_TIMESTAMP_KEY = 'site_settings_timestamp'

export const useSiteSettings = () => {
  const { api } = useApi()

  /**
   * Load settings from localStorage
   */
  const loadFromStorage = (): { settings: Record<string, any> | null, timestamp: number | null } => {
    if (!import.meta.client) {
      return { settings: null, timestamp: null }
    }
    
    try {
      const cached = localStorage.getItem(STORAGE_KEY)
      const cachedTimestamp = localStorage.getItem(STORAGE_TIMESTAMP_KEY)
      
      if (cached && cachedTimestamp) {
        return {
          settings: JSON.parse(cached),
          timestamp: parseInt(cachedTimestamp, 10)
        }
      }
    } catch (e) {
      console.error('Error loading site settings from localStorage:', e)
    }
    
    return { settings: null, timestamp: null }
  }

  /**
   * Save settings to localStorage
   */
  const saveToStorage = (settings: Record<string, any>, timestamp: number) => {
    if (!import.meta.client) {
      return
    }
    
    try {
      // Remove _meta before storing
      const { _meta, ...settingsToStore } = settings
      localStorage.setItem(STORAGE_KEY, JSON.stringify(settingsToStore))
      localStorage.setItem(STORAGE_TIMESTAMP_KEY, timestamp.toString())
    } catch (e) {
      console.error('Error saving site settings to localStorage:', e)
    }
  }

  /**
   * Check if settings need to be fetched by comparing timestamps
   */
  const checkSettingsTimestamp = async (): Promise<{ needsFetch: boolean, serverTimestamp: number | null }> => {
    const { timestamp: cachedTimestamp } = loadFromStorage()
    
    try {
      const response = await api('/site-settings/timestamp') as { timestamp: number, updated_at: string | null }
      const serverTimestamp = response.timestamp || 0
      
      // If no cached timestamp or server timestamp is newer, need to fetch
      const needsFetch = !cachedTimestamp || serverTimestamp > cachedTimestamp
      
      return { needsFetch, serverTimestamp }
    } catch (error) {
      console.error('Error checking site settings timestamp:', error)
      // On error, assume we need to fetch (fail-safe)
      return { needsFetch: true, serverTimestamp: null }
    }
  }

  const fetchSettings = async (force = false) => {
    // Try to get from bootstrap cache first
    if (!force) {
      try {
        const { getBootstrap } = await import('./useBootstrap')
        const bootstrap = getBootstrap()
        if (bootstrap?.site_settings) {
          globalSettings.value = bootstrap.site_settings
          // Save to localStorage if we have timestamp
          if (bootstrap.site_settings?._meta?.timestamp) {
            saveToStorage(bootstrap.site_settings, bootstrap.site_settings._meta.timestamp)
          }
          return globalSettings.value
        }
      } catch (e) {
        // Bootstrap not available, continue with normal fetch
      }
    }

    // If already loading and not forcing, wait for current request
    if (globalLoading.value && !force) {
      // Wait for current request to complete
      while (globalLoading.value) {
        await new Promise(resolve => setTimeout(resolve, 50))
      }
      return globalSettings.value
    }

    // Check localStorage first (if not forcing)
    if (!force && import.meta.client) {
      const { settings: cachedSettings, timestamp: cachedTimestamp } = loadFromStorage()
      
      if (cachedSettings && cachedTimestamp) {
        // Load cached settings immediately (no delay)
        globalSettings.value = cachedSettings
        
        // Check server timestamp in background to see if we need to fetch
        checkSettingsTimestamp().then(({ needsFetch, serverTimestamp }) => {
          if (needsFetch && serverTimestamp) {
            // Settings have changed, fetch fresh data
            globalLoading.value = true
            api('/site-settings').then((settings: Record<string, any>) => {
              const meta = settings._meta || {}
              const timestamp = meta.timestamp || Date.now()
              const { _meta, ...settingsWithoutMeta } = settings
              globalSettings.value = settingsWithoutMeta
              saveToStorage(settingsWithoutMeta, timestamp)
            }).catch((error) => {
              console.error('Error fetching updated site settings:', error)
            }).finally(() => {
              globalLoading.value = false
            })
          }
        }).catch((error) => {
          console.error('Error checking settings timestamp:', error)
        })
        
        // Return cached settings immediately
        return globalSettings.value
      }
    }
    
    globalLoading.value = true
    try {
      // Fetch full settings
      const settings = await api('/site-settings') as Record<string, any>
      
      // Extract and save timestamp
      const meta = settings._meta || {}
      const timestamp = meta.timestamp || Date.now()
      
      // Remove _meta from settings before storing
      const { _meta, ...settingsWithoutMeta } = settings
      globalSettings.value = settingsWithoutMeta
      
      // Save to localStorage
      saveToStorage(settingsWithoutMeta, timestamp)
    } catch (error) {
      console.error('Error fetching site settings:', error)
      
      // On error, try to use cached settings as fallback
      if (!force && import.meta.client) {
        const { settings: cachedSettings } = loadFromStorage()
        if (cachedSettings) {
          globalSettings.value = cachedSettings
          return globalSettings.value
        }
      }
      
      globalSettings.value = {}
    } finally {
      globalLoading.value = false
    }
    return globalSettings.value
  }

  /**
   * Load settings from bootstrap data (called after bootstrap fetch)
   */
  const loadFromBootstrap = (settings: Record<string, any>) => {
    globalSettings.value = settings
  }

  const getSetting = (key: string, defaultValue: any = null) => {
    return globalSettings.value[key]?.value || defaultValue
  }

  // Computed values for reactive access
  const defaultUserImage = computed(() => {
    return getSetting('default_user_image', 'https://media.istockphoto.com/id/1390994387/photo/headshot-of-bearded-saudi-man-in-traditional-attire.jpg?s=612x612&w=0&k=20&c=4fp7roSaxOP1oSq3ighOiyyYbhwSoy1tDvyZRjDhUeo=')
  })

  const defaultRoomImage = computed(() => {
    return getSetting('default_room_image', null)
  })

  const systemMessagesImage = computed(() => {
    return getSetting('system_messages_image', null)
  })

  const favicon = computed(() => {
    return getSetting('favicon', null)
  })

  const seoTitle = computed(() => {
    return getSetting('seo_title', 'Chat Application')
  })

  const seoDescription = computed(() => {
    return getSetting('seo_description', '')
  })

  const seoKeywords = computed(() => {
    return getSetting('seo_keywords', '')
  })

  const ogImage = computed(() => {
    return getSetting('og_image', null)
  })

  const ogUrl = computed(() => {
    return getSetting('og_url', '')
  })

  const sitePrimaryColor = computed(() => {
    return getSetting('site_primary_color', '#450924')
  })

  const siteSecondaryColor = computed(() => {
    return getSetting('site_secondary_color', '#ffffff')
  })

  const siteButtonColor = computed(() => {
    return getSetting('site_button_color', '#3D0821')
  })

  const siteName = computed(() => {
    return getSetting('site_name', '')
  })

  // Helper functions for backward compatibility
  const getDefaultUserImage = () => defaultUserImage.value
  const getDefaultRoomImage = () => defaultRoomImage.value
  const getSystemMessagesImage = () => systemMessagesImage.value
  const getFavicon = () => favicon.value
  const getSeoTitle = () => seoTitle.value
  const getSeoDescription = () => seoDescription.value
  const getSeoKeywords = () => seoKeywords.value
  const getOgImage = () => ogImage.value
  const getOgUrl = () => ogUrl.value

  return {
    settings: readonly(globalSettings),
    loading: readonly(globalLoading),
    fetchSettings,
    loadFromBootstrap,
    getSetting,
    // Computed properties for reactive access
    defaultUserImage,
    defaultRoomImage,
    systemMessagesImage,
    favicon,
    seoTitle,
    seoDescription,
    seoKeywords,
    ogImage,
    ogUrl,
    sitePrimaryColor,
    siteSecondaryColor,
    siteButtonColor,
    siteName,
    // Helper functions
    getDefaultUserImage,
    getDefaultRoomImage,
    getSystemMessagesImage,
    getFavicon,
    getSeoTitle,
    getSeoDescription,
    getSeoKeywords,
    getOgImage,
    getOgUrl,
  }
}

