import { defineStore } from 'pinia'
import type { Room, Message, User } from '~/types'

export const useChatStore = defineStore('chat', {
  state: () => ({
    rooms: [] as Room[],
    currentRoom: null as Room | null,
    messages: [] as Message[],
    activeUsers: [] as User[],
    loading: false,
    connected: false,
  }),

  getters: {
    currentRoomMessages: (state) => {
      if (!state.currentRoom) return []
      const roomId = state.currentRoom.id
      const filtered = state.messages.filter((m: Message) => String(m.room_id) === String(roomId))
      // Sort by created_at ascending (oldest first)
      return filtered.sort((a: Message, b: Message) => {
        const dateA = new Date(a.created_at).getTime()
        const dateB = new Date(b.created_at).getTime()
        return dateA - dateB
      })
    },
    
    displayRooms: (state) => {
      // Always return actual rooms from state, even if empty
      return state.rooms || []
    },
    
    displayActiveUsers: (state) => {
      // Always return actual activeUsers from API, even if empty
      return state.activeUsers || []
    },
  },

  actions: {
    async fetchRooms(force = false) {
      // Try to get from bootstrap cache first
      if (!force) {
        try {
          const { getBootstrap } = await import('~~/app/composables/useBootstrap')
          const bootstrap = getBootstrap()
          if (bootstrap?.rooms && bootstrap.rooms.length > 0) {
            this.rooms = bootstrap.rooms
            return bootstrap.rooms
          }
        } catch (e) {
          // Bootstrap not available, continue with normal fetch
        }
      }

      this.loading = true
      try {
        const { $api } = useNuxtApp()
        const rooms = await $api('/chat')
        this.rooms = rooms
        return rooms
      } finally {
        this.loading = false
      }
    },

    /**
     * Load rooms from bootstrap data (called after bootstrap fetch)
     */
    loadRoomsFromBootstrap(rooms: Room[]) {
      this.rooms = rooms
    },

    async createRoom(roomData: {
      name: string
      description?: string | null
      is_public?: boolean
      max_count?: number
      password?: string | null
      room_image?: string | null
      room_cover?: string | null
      settings?: any
    }) {
      try {
        const { $api } = useNuxtApp()
        const room = await $api('/chat', {
          method: 'POST',
          body: roomData,
        })
        // Add the new room to the list
        this.rooms = [room, ...this.rooms]
        return room
      } catch (error) {
        console.error('Error creating room:', error)
        throw error
      }
    },

    async fetchRoom(id: string | number, password?: string) {
      this.loading = true
      try {
        const { $api } = useNuxtApp()
        const url = password ? `/chat/${id}?password=${encodeURIComponent(password)}` : `/chat/${id}`
        const room = await $api(url)
        this.currentRoom = room
        return room
      } catch (error: any) {
        // Re-throw error so caller can handle password requirement
        throw error
      } finally {
        this.loading = false
      }
    },

    async fetchGeneralRoom() {
      this.loading = true
      try {
        const { $api } = useNuxtApp()
        const room = await $api('/chat/general')
        this.currentRoom = room
        return room
      } finally {
        this.loading = false
      }
    },

    async fetchMessages(roomId: string | number, page: number = 1) {
      try {
        const { $api } = useNuxtApp()
        const response = await $api(`/chat/${roomId}/messages?page=${page}`)
        
        // Handle paginated response structure (Laravel returns { data: [...], current_page, etc })
        const messages = response.data || (Array.isArray(response) ? response : [])
        
        // Ensure messages are for the correct room and have required fields
        const roomMessages = Array.isArray(messages) 
          ? messages
              .filter((m: Message) => String(m.room_id) === String(roomId))
              .map((m: Message) => ({
                ...m,
                room_id: Number(roomId), // Ensure room_id matches
              }))
          : []
        
        if (page === 1) {
          // Remove existing messages for this room to avoid duplicates
          this.messages = this.messages.filter(m => String(m.room_id) !== String(roomId))
          // Add new messages (they come in desc order, so reverse to show oldest first)
          this.messages = [...this.messages, ...roomMessages.reverse()]
        } else {
          // Prepend older messages for pagination
          this.messages = [...roomMessages.reverse(), ...this.messages]
        }
        
        return response
      } catch (error) {
        console.error('Error fetching messages:', error)
        throw error
      }
    },

    async sendMessage(roomId: string | number, content: string, meta?: any) {
      try {
        const { $api } = useNuxtApp()
        const message = await $api(`/chat/${roomId}/messages`, {
          method: 'POST',
          body: { content, meta },
        })
        // Message will be added via Echo broadcast
        return message
      } catch (error) {
        console.error('Error sending message:', error)
        throw error
      }
    },

    addMessage(message: Message | any) {
      // Ensure message has required fields
      if (!message || !message.id) {
        console.warn('Invalid message data:', message)
        return
      }
      
      // Check if message already exists
      const existingMessage = this.messages.find(m => m.id === message.id)
      if (existingMessage) {
        console.log('Message already exists:', message.id)
        return
      }
      
      const currentRoomId = this.currentRoom?.id
      let messageRoomId = message.room_id ?? message.meta?.room_id ?? null

      if (!messageRoomId && currentRoomId) {
        messageRoomId = currentRoomId
      }

      if (!message.room_id && messageRoomId) {
        message.room_id = messageRoomId
      }

      // System messages and welcome messages should always be added regardless of room
      const isSystemMessage = message.meta?.is_system || message.meta?.is_welcome_message || message.user_id === 0
      
      const belongsToCurrentRoom = currentRoomId
        ? String(messageRoomId) === String(currentRoomId)
        : true

      // Allow system messages even if room doesn't match (they might be from room transitions)
      if (!belongsToCurrentRoom && !isSystemMessage) {
        console.warn('Message not added - room mismatch:', {
          message_room_id: messageRoomId,
          current_room_id: currentRoomId,
        })
        return
      }
      
      // For system messages, ensure room_id is set to current room if not set
      if (isSystemMessage && !message.room_id && currentRoomId) {
        message.room_id = Number(currentRoomId)
      }
      
      // Ensure message has user data
      if (!message.user && message.user_id && this.currentRoom?.users) {
        const user = this.currentRoom.users.find((u: any) => u.id === message.user_id)
        if (user) {
          message.user = user
        }
      }
      
      this.messages.push(message)
      console.log('Message added to store:', message.id, 'Total messages:', this.messages.length)
    },

    setCurrentRoom(room: Room | null) {
      // Don't clear messages when switching rooms - keep all messages in store
      this.currentRoom = room
    },

    clearMessages() {
      this.messages = []
    },
    
    async fetchActiveUsers() {
      try {
        const { $api } = useNuxtApp()
        const users = await $api('/users/active')
        console.log('Fetched active users from API:', users)
        this.activeUsers = Array.isArray(users) ? users : []
        console.log('Set activeUsers to:', this.activeUsers.length, 'users')
        return this.activeUsers
      } catch (error: any) {
        console.error('Error fetching active users:', error)
        console.error('Error details:', error.message, error.response)
        // If API fails, set to empty array (no mock data)
        this.activeUsers = []
        return []
      }
    },
    
    // Update a user in the active users list
    updateActiveUser(userData: any) {
      const userIndex = this.activeUsers.findIndex((u: User) => u.id === userData.id)
      if (userIndex !== -1) {
        // Update existing user with new data
        Object.assign(this.activeUsers[userIndex], userData)
        console.log('Updated user in active users list:', userData.id)
      } else {
        // User not in list, but that's okay - they might not be active
        console.log('User not found in active users list:', userData.id)
      }
    },
    
    setConnected(connected: boolean) {
      this.connected = connected
    },
  },
})

