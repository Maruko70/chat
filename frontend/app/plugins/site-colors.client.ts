export default defineNuxtPlugin({
  name: 'site-colors',
  enforce: 'post', // Run after site-settings plugin
  async setup() {
    const { 
      sitePrimaryColor, 
      siteSecondaryColor, 
      siteButtonColor,
      fetchSettings 
    } = useSiteSettings()
    
    // Set default CSS variables immediately in document head (synchronous, before any rendering)
    if (import.meta.client) {
      const defaultPrimary = '#450924'
      const defaultSecondary = '#ffffff'
      const defaultButton = '#450924'
      
      // Set CSS variables on root element immediately
      const root = document.documentElement
      root.style.setProperty('--site-primary-color', defaultPrimary)
      root.style.setProperty('--site-secondary-color', defaultSecondary)
      root.style.setProperty('--site-button-color', defaultButton)
      
      // Also inject into head for early application
      const styleId = 'site-colors-inline'
      let styleElement = document.getElementById(styleId) as HTMLStyleElement
      
      if (!styleElement) {
        styleElement = document.createElement('style')
        styleElement.id = styleId
        document.head.appendChild(styleElement)
      }
      
      styleElement.textContent = `
        :root {
          --site-primary-color: ${defaultPrimary};
          --site-secondary-color: ${defaultSecondary};
          --site-button-color: ${defaultButton};
        }
      `
    }
    
    // Function to apply colors as CSS variables
    const applyColors = () => {
      if (import.meta.client) {
        const root = document.documentElement
        const primary = sitePrimaryColor.value || '#450924'
        const secondary = siteSecondaryColor.value || '#ffffff'
        const button = siteButtonColor.value || '#450924'
        
        root.style.setProperty('--site-primary-color', primary)
        root.style.setProperty('--site-secondary-color', secondary)
        root.style.setProperty('--site-button-color', button)
        
        // Update inline style in head
        const styleElement = document.getElementById('site-colors-inline') as HTMLStyleElement
        if (styleElement) {
          styleElement.textContent = `
            :root {
              --site-primary-color: ${primary};
              --site-secondary-color: ${secondary};
              --site-button-color: ${button};
            }
          `
        }
      }
    }
    
    // Apply colors from composable (may still be defaults if not loaded yet)
    applyColors()
    
    // Fetch settings with retry logic for new browsers/accounts
    let retries = 3
    let lastError: any = null
    
    while (retries > 0) {
      try {
        await fetchSettings(true)
        // Success - apply colors from fetched settings
        applyColors()
        break
      } catch (error) {
        lastError = error
        retries--
        if (retries > 0) {
          // Wait before retry (exponential backoff)
          await new Promise(resolve => setTimeout(resolve, 1000 * (4 - retries)))
        }
      }
    }
    
    // If all retries failed, log warning but colors are already set to defaults
    if (retries === 0 && lastError) {
      console.warn('Failed to fetch site color settings after retries, using defaults:', lastError)
      // Colors are already applied with defaults above
    }
    
    // Watch for color changes and update CSS variables reactively
    watch([sitePrimaryColor, siteSecondaryColor, siteButtonColor], () => {
      applyColors()
    }, { immediate: false })
  }
})

