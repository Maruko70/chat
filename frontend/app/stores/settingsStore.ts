import { defineStore } from 'pinia'
import { useAuthStore } from './authStore'

export const useSettingsStore = defineStore('settings', {
  state: () => ({
    // User-specific settings (loaded from API)
    roomFontSize: 14, // in pixels
    nameColor: { r: 69, g: 9, b: 36 } as { r: number; g: number; b: number } | string,
    messageColor: { r: 69, g: 9, b: 36 } as { r: number; g: number; b: number } | string,
    nameBgColor: 'transparent' as { r: number; g: number; b: number } | string,
    imageBorderColor: { r: 69, g: 9, b: 36 } as { r: number; g: number; b: number } | string,
    bioColor: { r: 107, g: 114, b: 128 } as { r: number; g: number; b: number } | string,
    privateMessagesEnabled: true,
    notificationsEnabled: true,
    // Flag to prevent auto-save when loading from user data
    isLoadingFromUser: false,
  }),

  actions: {
    // Load settings from user object
    loadFromUser(user: any) {
      if (user) {
        // Set flag to prevent watchers from triggering auto-save
        this.isLoadingFromUser = true
        
        this.roomFontSize = user.room_font_size || 14
        this.nameColor = user.name_color || { r: 69, g: 9, b: 36 }
        this.messageColor = user.message_color || { r: 69, g: 9, b: 36 }
        this.nameBgColor = user.name_bg_color === null || user.name_bg_color === undefined ? 'transparent' : user.name_bg_color
        this.imageBorderColor = user.image_border_color || { r: 69, g: 9, b: 36 }
        this.bioColor = user.bio_color || { r: 107, g: 114, b: 128 }
        
        // Reset flag after a short delay to allow watchers to settle
        // Use nextTick to ensure all reactive updates are complete
        if (import.meta.client) {
          setTimeout(() => {
            this.isLoadingFromUser = false
          }, 100)
        } else {
          this.isLoadingFromUser = false
        }
      }
    },

    // Setter methods now only update the state, no auto-save
    setRoomFontSize(size: number) {
      this.roomFontSize = size
    },

    setNameColor(color: any) {
      this.nameColor = color
    },

    setMessageColor(color: any) {
      this.messageColor = color
    },

    setNameBgColor(color: any) {
      this.nameBgColor = color
    },

    setImageBorderColor(color: any) {
      this.imageBorderColor = color
    },

    setBioColor(color: any) {
      this.bioColor = color
    },

    setPrivateMessagesEnabled(enabled: boolean) {
      this.privateMessagesEnabled = enabled
      // This might not need API save, depending on requirements
    },

    setNotificationsEnabled(enabled: boolean) {
      this.notificationsEnabled = enabled
      // This might not need API save, depending on requirements
    },

    // Save color settings to API
    async saveToAPI() {
      const authStore = useAuthStore()
      if (!authStore.isAuthenticated) {
        return // Don't save if not authenticated
      }

      // Don't save if we're currently loading from user data
      if (this.isLoadingFromUser) {
        return
      }

      try {
        const { $api } = useNuxtApp()
        const updateData: any = {
          room_font_size: this.roomFontSize,
          name_color: this.nameColor,
          message_color: this.messageColor,
          name_bg_color: this.nameBgColor === 'transparent' ? null : this.nameBgColor,
          image_border_color: this.imageBorderColor,
          bio_color: this.bioColor,
        }

        const updatedUser = await ($api as any)('/profile', {
          method: 'PUT',
          body: updateData,
        })

        // Update auth store with new user data
        if (updatedUser && authStore.user) {
          authStore.user = updatedUser
          if (import.meta.client) {
            localStorage.setItem('auth_user', JSON.stringify(updatedUser))
          }
        }
      } catch (error) {
        console.error('Error saving color settings:', error)
        // Don't throw - allow user to continue using app
      }
    },

    // Fetch settings from API
    async fetchFromAPI() {
      const authStore = useAuthStore()
      if (!authStore.isAuthenticated) {
        return
      }

      try {
        const { $api } = useNuxtApp()
        const user = await ($api as any)('/profile')
        if (user) {
          this.loadFromUser(user)
          // Also update auth store
          if (authStore.user) {
            authStore.user = user
            if (import.meta.client) {
              localStorage.setItem('auth_user', JSON.stringify(user))
            }
          }
        }
      } catch (error) {
        console.error('Error fetching color settings:', error)
      }
    },

    resetSettings() {
      this.roomFontSize = 14
      this.nameColor = { r: 69, g: 9, b: 36 }
      this.messageColor = { r: 69, g: 9, b: 36 }
      this.nameBgColor = 'transparent'
      this.imageBorderColor = { r: 69, g: 9, b: 36 }
      this.bioColor = { r: 107, g: 114, b: 128 }
      this.privateMessagesEnabled = true
      this.notificationsEnabled = true
    },
  },
})
