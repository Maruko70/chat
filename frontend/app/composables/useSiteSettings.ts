// Singleton state - shared across all instances
const globalSettings = ref<Record<string, any>>({})
const globalLoading = ref(false)

export const useSiteSettings = () => {
  const { api } = useApi()

  const fetchSettings = async (force = false) => {
    // Try to get from bootstrap cache first
    if (!force) {
      try {
        const { getBootstrap } = await import('./useBootstrap')
        const bootstrap = getBootstrap()
        if (bootstrap?.site_settings) {
          globalSettings.value = bootstrap.site_settings
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
    
    globalLoading.value = true
    try {
      // Add cache-busting parameter to ensure fresh data
      const timestamp = force ? `?t=${Date.now()}` : ''
      globalSettings.value = await api(`/site-settings${timestamp}`)
    } catch (error) {
      console.error('Error fetching site settings:', error)
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
    return getSetting('site_name', getSetting('seo_title', 'شات فورجي الكتابي'))
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

