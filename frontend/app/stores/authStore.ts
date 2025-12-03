import { defineStore } from 'pinia'
import type { User } from '~/types'

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
    async setAuth(user: User, token: string) {
      this.user = user
      this.token = token
      if (import.meta.client) {
        localStorage.setItem('auth_token', token)
        localStorage.setItem('auth_user', JSON.stringify(user))
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
      }
    },

    async login(username: string, password: string) {
      const { $api } = useNuxtApp()
      const response = await $api('/login', {
        method: 'POST',
        body: { username, password },
      })
      
      if (response.user && response.token) {
        this.setAuth(response.user, response.token)
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
        this.setAuth(response.user, response.token)
        return response
      }
      
      throw new Error('Registration failed')
    },

    async guestLogin(username: string) {
      const { $api } = useNuxtApp()
      const response = await $api('/guest-login', {
        method: 'POST',
        body: { username },
      })
      
      if (response.user && response.token) {
        this.setAuth(response.user, response.token)
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

