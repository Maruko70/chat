export default defineNuxtRouteMiddleware((to, from) => {
  const authStore = useAuthStore()
  
  // Initialize auth from localStorage
  authStore.initAuth()
  
  if (!authStore.isAuthenticated) {
    // return navigateTo('/login')
  }
})

