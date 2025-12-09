import { defineStore } from 'pinia'
import type { User } from '~/types'

// Simple encoding/decoding for localStorage (not super secure but better than plain text)
const encodeCredentials = (username: string, password: string): string => {
  return btoa(JSON.stringify({ username, password }))
}

const decodeCredentials = (encoded: string): { username: string; password: string } | null => {
  try {
    return JSON.parse(atob(encoded))
  } catch {
    return null
  }
}

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as User | null,
    token: null as string | null,
  }),

  getters: {
    isAuthenticated: (state) => !!state.token && !!state.user,
    isGuest: (state) => state.user?.is_guest || false,
  },

  actions: {
    async setAuth(user: User, token: string, lastEditTime?: string, credentials?: { username: string; password: string }) {
      this.user = user
      this.token = token
      if (import.meta.client) {
        localStorage.setItem('auth_token', token)
        localStorage.setItem('auth_user', JSON.stringify(user))
        
        // Store credentials and timestamps for fast login
        if (credentials && !user.is_guest) {
          const encoded = encodeCredentials(credentials.username, credentials.password)
          localStorage.setItem('auth_credentials', encoded)
          localStorage.setItem('last_edit_time', lastEditTime || user.updated_at || new Date().toISOString())
          localStorage.setItem('last_auth_time', new Date().toISOString())
        } else if (user.is_guest) {
          // For guests, store username only
          localStorage.setItem('guest_username', credentials?.username || user.username)
          localStorage.setItem('last_edit_time', lastEditTime || user.updated_at || new Date().toISOString())
          localStorage.setItem('last_auth_time', new Date().toISOString())
        }
      }
      // Sync settings store with user's color settings
      if (import.meta.client) {
        const { useSettingsStore } = await import('./settingsStore')
        const settingsStore = useSettingsStore()
        settingsStore.loadFromUser(user)
      }
    },

    clearAuth() {
      this.user = null
      this.token = null
      if (import.meta.client) {
        localStorage.removeItem('auth_token')
        localStorage.removeItem('auth_user')
        localStorage.removeItem('auth_credentials')
        localStorage.removeItem('guest_username')
        localStorage.removeItem('last_edit_time')
        localStorage.removeItem('last_auth_time')
      }
    },

    updateLastEditTime() {
      if (import.meta.client) {
        localStorage.setItem('last_edit_time', new Date().toISOString())
      }
    },

    // Instant login - set auth from cache immediately without validation (for instant navigation)
    instantLoginFromCache(username: string, password: string): boolean {
      if (!import.meta.client) return false
      
      const cachedCredentials = localStorage.getItem('auth_credentials')
      const cachedUser = localStorage.getItem('auth_user')
      const cachedToken = localStorage.getItem('auth_token')
      
      if (cachedCredentials && cachedUser && cachedToken) {
        const decoded = decodeCredentials(cachedCredentials)
        if (decoded && decoded.username === username && decoded.password === password) {
          try {
            const user = JSON.parse(cachedUser)
            // Set auth immediately from cache
            this.user = user
            this.token = cachedToken
            localStorage.setItem('auth_token', cachedToken)
            localStorage.setItem('auth_user', cachedUser)
            
            // Sync settings store with user's color settings (non-blocking)
            import('./settingsStore').then(({ useSettingsStore }) => {
              const settingsStore = useSettingsStore()
              settingsStore.loadFromUser(user)
            }).catch(() => {
              // Ignore errors
            })
            
            return true
          } catch (e) {
            return false
          }
        }
      }
      
      return false
    },

    // Instant guest login from cache
    instantGuestLoginFromCache(username: string): boolean {
      if (!import.meta.client) return false
      
      const cachedGuestUsername = localStorage.getItem('guest_username')
      const cachedUser = localStorage.getItem('auth_user')
      const cachedToken = localStorage.getItem('auth_token')
      
      if (cachedGuestUsername === username && cachedUser && cachedToken) {
        try {
          const user = JSON.parse(cachedUser)
          // Set auth immediately from cache
          this.user = user
          this.token = cachedToken
          localStorage.setItem('auth_token', cachedToken)
          localStorage.setItem('auth_user', cachedUser)
          
          // Sync settings store with user's color settings (non-blocking)
          import('./settingsStore').then(({ useSettingsStore }) => {
            const settingsStore = useSettingsStore()
            settingsStore.loadFromUser(user)
          }).catch(() => {
            // Ignore errors
          })
          
          return true
        } catch (e) {
          return false
        }
      }
      
      return false
    },

    async login(username: string, password: string, skipFastLogin = false) {
      const { $api } = useNuxtApp()
      
      // Check for fast login if we have cached credentials
      if (!skipFastLogin && import.meta.client) {
        const cachedCredentials = localStorage.getItem('auth_credentials')
        const cachedLastEditTime = localStorage.getItem('last_edit_time')
        
        if (cachedCredentials && cachedLastEditTime) {
          const decoded = decodeCredentials(cachedCredentials)
          if (decoded && decoded.username === username && decoded.password === password) {
            // Validate credentials and check if last_edit_time matches
            try {
              const validation = await $api('/validate-credentials', {
                method: 'POST',
                body: { username, password },
              })
              
              if (validation.valid && validation.last_edit_time === cachedLastEditTime) {
                // Fast login - use cached data
                const cachedUser = localStorage.getItem('auth_user')
                const cachedToken = localStorage.getItem('auth_token')
                
                if (cachedUser && cachedToken) {
                  try {
                    const user = JSON.parse(cachedUser)
                    this.setAuth(user, cachedToken, validation.last_edit_time, { username, password })
                    return { user, token: cachedToken, last_edit_time: validation.last_edit_time }
                  } catch (e) {
                    // Fall through to normal login
                  }
                }
              }
            } catch (e) {
              // Fall through to normal login if validation fails
            }
          }
        }
      }
      
      // Normal login flow
      const response = await $api('/login', {
        method: 'POST',
        body: { username, password },
      })
      
      if (response.user && response.token) {
        this.setAuth(response.user, response.token, response.last_edit_time, { username, password })
        return response
      }
      
      throw new Error('Login failed')
    },

    async register(username: string, password: string) {
      const { $api } = useNuxtApp()
      const response = await $api('/register', {
        method: 'POST',
        body: { username, password },
      })
      
      if (response.user && response.token) {
        this.setAuth(response.user, response.token, response.last_edit_time, { username, password })
        return response
      }
      
      throw new Error('Registration failed')
    },

    async guestLogin(username: string, skipFastLogin = false) {
      const { $api } = useNuxtApp()
      
      // Check for fast login if we have cached guest username
      if (!skipFastLogin && import.meta.client) {
        const cachedGuestUsername = localStorage.getItem('guest_username')
        const cachedLastEditTime = localStorage.getItem('last_edit_time')
        
        if (cachedGuestUsername === username && cachedLastEditTime) {
          // Try to use cached data for fast login
          const cachedUser = localStorage.getItem('auth_user')
          const cachedToken = localStorage.getItem('auth_token')
          
          if (cachedUser && cachedToken) {
            try {
              const user = JSON.parse(cachedUser)
              // For guests, we can use cached data directly since there's no password
              this.setAuth(user, cachedToken, cachedLastEditTime, { username })
              return { user, token: cachedToken, last_edit_time: cachedLastEditTime }
            } catch (e) {
              // Fall through to normal login
            }
          }
        }
      }
      
      // Normal guest login flow
      const response = await $api('/guest-login', {
        method: 'POST',
        body: { username },
      })
      
      if (response.user && response.token) {
        this.setAuth(response.user, response.token, response.last_edit_time, { username })
        return response
      }
      
      throw new Error('Guest login failed')
    },

    async logout() {
      const { $api } = useNuxtApp()
      try {
        await $api('/logout', { method: 'POST' })
      } catch (error) {
        console.error('Logout error:', error)
      } finally {
        this.clearAuth()
      }
    },

    async fetchUser() {
      const { $api } = useNuxtApp()
      try {
        const user = await $api('/user')
        if (user) {
          // Check if user is banned (shouldn't happen if backend is working correctly)
          if (user.banned) {
            this.clearAuth()
            const router = useRouter()
            router.push('/login')
            throw new Error('Your account has been banned')
          }
          
          this.user = user
          // Sync settings store with user's color settings
          const { useSettingsStore } = await import('./settingsStore')
          const settingsStore = useSettingsStore()
          settingsStore.loadFromUser(user)
        }
        return user
      } catch (error: any) {
        // If error is a ban response, handle it
        if (error?.data?.banned || error?.status === 403) {
          this.clearAuth()
          const router = useRouter()
          router.push('/login')
          const { useToast } = await import('primevue/usetoast')
          const toast = useToast()
          toast.add({
            severity: 'error',
            summary: 'تم حظر حسابك',
            detail: error?.data?.message || 'تم حظر حسابك من الموقع',
            life: 10000,
          })
        } else {
          this.clearAuth()
        }
        throw error
      }
    },

    async backgroundAuth() {
      if (!import.meta.client) return null
      
      const cachedCredentials = localStorage.getItem('auth_credentials')
      const guestUsername = localStorage.getItem('guest_username')
      
      if (!cachedCredentials && !guestUsername) {
        return null
      }
      
      const { $api } = useNuxtApp()
      
      try {
        if (cachedCredentials) {
          // Regular user - use credentials for background auth
          const decoded = decodeCredentials(cachedCredentials)
          if (!decoded) return null
          
          const response = await $api('/background-auth', {
            method: 'POST',
            body: { username: decoded.username, password: decoded.password },
          })
          
          if (response.valid && response.user && response.token) {
            // Update auth with fresh token and user data
            this.setAuth(response.user, response.token, response.last_edit_time, decoded)
            return response
          } else if (response.banned) {
            // User is banned - clear auth and show message
            this.clearAuth()
            const router = useRouter()
            router.push('/')
            const { useToast } = await import('primevue/usetoast')
            const toast = useToast()
            toast.add({
              severity: 'error',
              summary: 'تم حظر حسابك',
              detail: response.message || 'تم حظر حسابك من الموقع',
              life: 10000,
            })
            return null
          }
        } else if (guestUsername) {
          // Guest user - re-login as guest
          const response = await $api('/guest-login', {
            method: 'POST',
            body: { username: guestUsername },
          })
          
          if (response.user && response.token) {
            this.setAuth(response.user, response.token, response.last_edit_time, { username: guestUsername })
            return response
          }
        }
      } catch (error: any) {
        // If banned, handle it
        if (error?.data?.banned || error?.status === 403) {
          this.clearAuth()
          const router = useRouter()
          router.push('/')
          const { useToast } = await import('primevue/usetoast')
          const toast = useToast()
          toast.add({
            severity: 'error',
            summary: 'تم حظر حسابك',
            detail: error?.data?.message || 'تم حظر حسابك من الموقع',
            life: 10000,
          })
        }
        // Silently fail for other errors - don't interrupt user experience
        console.error('Background auth error:', error)
      }
      
      return null
    },

    initAuth() {
      if (import.meta.client) {
        const token = localStorage.getItem('auth_token')
        const userStr = localStorage.getItem('auth_user')
        
        if (token && userStr) {
          try {
            this.token = token
            this.user = JSON.parse(userStr)
            // Sync settings store with user's color settings (non-blocking)
            if (this.user) {
              import('./settingsStore').then(({ useSettingsStore }) => {
                const settingsStore = useSettingsStore()
                settingsStore.loadFromUser(this.user)
              }).catch(() => {
                // Ignore errors
              })
            }
          } catch (error) {
            this.clearAuth()
          }
        }
      }
    },
  },
})

