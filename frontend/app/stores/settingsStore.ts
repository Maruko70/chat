import { defineStore } from 'pinia'
import { useAuthStore } from './authStore'
import { useRateLimit } from '~/composables/useRateLimit'

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
    // Flag to prevent recursive calls from watchers
    isUpdatingPrivateMessages: false,
    isUpdatingNotifications: false,
  }),

  actions: {
    // Load settings from user object
    loadFromUser(user: any) {
      if (user) {
        // Set flags to prevent watchers from triggering auto-save
        this.isLoadingFromUser = true
        this.isUpdatingPrivateMessages = true
        this.isUpdatingNotifications = true
        
        this.roomFontSize = user.room_font_size || 14
        this.nameColor = user.name_color || { r: 69, g: 9, b: 36 }
        this.messageColor = user.message_color || { r: 69, g: 9, b: 36 }
        this.nameBgColor = user.name_bg_color === null || user.name_bg_color === undefined ? 'transparent' : user.name_bg_color
        this.imageBorderColor = user.image_border_color || { r: 69, g: 9, b: 36 }
        this.bioColor = user.bio_color || { r: 107, g: 114, b: 128 }
        // Load privacy settings from user object
        this.privateMessagesEnabled = user.private_messages_enabled !== undefined ? user.private_messages_enabled : true
        this.notificationsEnabled = user.notifications_enabled !== undefined ? user.notifications_enabled : true
        
        // Reset flags after a short delay to allow watchers to settle
        // Use nextTick to ensure all reactive updates are complete
        if (import.meta.client) {
          setTimeout(() => {
            this.isLoadingFromUser = false
            this.isUpdatingPrivateMessages = false
            this.isUpdatingNotifications = false
          }, 100)
        } else {
          this.isLoadingFromUser = false
          this.isUpdatingPrivateMessages = false
          this.isUpdatingNotifications = false
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

    async setPrivateMessagesEnabled(enabled: boolean) {
      // Prevent recursive calls
      if (this.isUpdatingPrivateMessages) {
        return
      }
      
      // Check if value is already set (prevents unnecessary updates)
      if (this.privateMessagesEnabled === enabled) {
        return
      }
      
      // Check rate limit BEFORE changing the value
      const { checkRateLimit } = useRateLimit()
      if (!checkRateLimit('toggle_private_messages')) {
        // Don't change the value if rate limited - InputSwitch will revert via :modelValue binding
        return
      }
      
      // Store the old value in case we need to revert
      const oldValue = this.privateMessagesEnabled
      
      // Set flag to prevent watcher from triggering
      this.isUpdatingPrivateMessages = true
      this.privateMessagesEnabled = enabled
      
      // Save to API immediately
      try {
        const { $api } = useNuxtApp()
        const authStore = useAuthStore()
        const updatedUser = await ($api as any)('/profile', {
          method: 'PUT',
          body: {
            private_messages_enabled: enabled,
          },
        })
        
        // Update user in auth store
        if (updatedUser && authStore.user) {
          authStore.user.private_messages_enabled = updatedUser.private_messages_enabled
          if (import.meta.client) {
            localStorage.setItem('auth_user', JSON.stringify(authStore.user))
          }
        }
      } catch (error) {
        console.error('Error saving private messages setting:', error)
        // Revert the value on error
        this.privateMessagesEnabled = oldValue
      } finally {
        // Reset flag after a short delay to allow watchers to settle
        setTimeout(() => {
          this.isUpdatingPrivateMessages = false
        }, 100)
      }
    },

    async setNotificationsEnabled(enabled: boolean) {
      // Prevent recursive calls
      if (this.isUpdatingNotifications) {
        return
      }
      
      // Check if value is already set (prevents unnecessary updates)
      if (this.notificationsEnabled === enabled) {
        return
      }
      
      // Check rate limit BEFORE changing the value
      const { checkRateLimit } = useRateLimit()
      if (!checkRateLimit('toggle_notifications')) {
        // Don't change the value if rate limited
        return
      }
      
      // Set flag to prevent watcher from triggering
      this.isUpdatingNotifications = true
      this.notificationsEnabled = enabled
      
      // Reset flag after a short delay to allow watchers to settle
      setTimeout(() => {
        this.isUpdatingNotifications = false
      }, 100)
      
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
