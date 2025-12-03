export default defineNuxtPlugin(() => {
  const { initEcho } = useEcho()
  
  if (process.client) {
    const authStore = useAuthStore()
    
    // Initialize auth from localStorage
    authStore.initAuth()
    
    // Initialize Echo when authenticated
    if (authStore.isAuthenticated) {
      initEcho()
    }
    
    // Watch for auth changes
    watch(() => authStore.isAuthenticated, (isAuth) => {
      if (isAuth) {
        initEcho()
      } else {
        const { disconnect } = useEcho()
        disconnect()
      }
    })
  }
})

