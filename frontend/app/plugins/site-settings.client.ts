export default defineNuxtPlugin({
  name: 'site-settings-seo',
  enforce: 'pre', // Run early to set SEO before pages load
  async setup() {
    const { 
      fetchSettings, 
      seoTitle, 
      seoDescription, 
      seoKeywords, 
      ogImage, 
      ogUrl, 
      favicon 
    } = useSiteSettings()
    
    // Fetch site settings on app initialization (force refresh on page load)
    await fetchSettings(true)
    
    // Function to apply SEO
    const applySEO = () => {
      const title = seoTitle.value || 'Chat Application'
      const description = seoDescription.value || ''
      const keywords = seoKeywords.value || ''
      const ogImg = ogImage.value || ''
      const ogUrlValue = ogUrl.value || ''
      // Add timestamp to favicon URL to force browser refresh
      const faviconUrl = favicon.value 
        ? `${favicon.value}${favicon.value.includes('?') ? '&' : '?'}v=${Date.now()}` 
        : '/favicon.ico'
      
      // Use useSeoMeta for better Nuxt 3 integration (recommended way)
      useSeoMeta({
        title,
        description,
        keywords,
        ogTitle: title,
        ogDescription: description,
        ogImage: ogImg,
        ogUrl: ogUrlValue,
        ogType: 'website',
        twitterCard: 'summary_large_image',
        twitterTitle: title,
        twitterDescription: description,
        twitterImage: ogImg,
      })
      
      // Set favicon via useHead with cache-busting
      useHead({
        link: [
          {
            rel: 'icon',
            type: 'image/x-icon',
            href: faviconUrl,
            id: 'dynamic-favicon', // Add ID to allow replacement
          },
        ],
      })
      
      // Force favicon refresh by updating link element directly
      if (import.meta.client) {
        nextTick(() => {
          let faviconLink = document.querySelector('link#dynamic-favicon') as HTMLLinkElement
          if (!faviconLink) {
            faviconLink = document.createElement('link')
            faviconLink.id = 'dynamic-favicon'
            faviconLink.rel = 'icon'
            faviconLink.type = 'image/x-icon'
            document.head.appendChild(faviconLink)
          }
          faviconLink.href = faviconUrl
        })
      }
    }
    
    // Apply SEO immediately
    applySEO()
    
    // Watch settings and update head meta tags reactively when they change
    watch([seoTitle, seoDescription, seoKeywords, ogImage, ogUrl, favicon], () => {
      applySEO()
    })
  }
})
