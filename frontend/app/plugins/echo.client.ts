export default defineNuxtPlugin(() => {
  const { initEcho } = useEcho()
  
  if (process.client) {
    const authStore = useAuthStore()
    
    // Initialize auth from localStorage IMMEDIATELY
    authStore.initAuth()
    
    // Initialize Echo IMMEDIATELY when authenticated (don't wait for anything)
    if (authStore.isAuthenticated && authStore.token) {
      // Initialize immediately - this starts the WebSocket connection right away
      initEcho()
    }
    
    // Watch for auth changes
    watch(() => authStore.isAuthenticated, (isAuth) => {
      if (isAuth && authStore.token) {
        // Initialize immediately when auth changes
        initEcho()
      } else {
        const { disconnect } = useEcho()
        disconnect()
      }
    }, { immediate: false })
  }
})

