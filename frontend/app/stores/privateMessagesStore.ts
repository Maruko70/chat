import { defineStore } from 'pinia'
import type { PrivateMessage, Conversation, User } from '~/types'

export const usePrivateMessagesStore = defineStore('privateMessages', {
  state: () => ({
    conversations: [] as Conversation[],
    currentConversation: null as User | null,
    messages: [] as PrivateMessage[],
    unreadCount: 0,
    loading: false,
  }),

  getters: {
    currentConversationMessages: (state) => {
      if (!state.currentConversation) return []
      const userId = state.currentConversation.id
      const filtered = state.messages.filter((m: PrivateMessage) => 
        (m.sender_id === userId || m.recipient_id === userId)
      )
      // Sort by created_at ascending (oldest first)
      return filtered.sort((a: PrivateMessage, b: PrivateMessage) => {
        const dateA = new Date(a.created_at).getTime()
        const dateB = new Date(b.created_at).getTime()
        return dateA - dateB
      })
    },

    getConversationByUserId: (state) => (userId: number) => {
      return state.conversations.find(c => c.user.id === userId)
    },
  },

  actions: {
    async fetchConversations() {
      this.loading = true
      try {
        const { $api } = useNuxtApp()
        const conversations = await $api('/private-messages')
        this.conversations = conversations
        return conversations
      } catch (error) {
        console.error('Error fetching conversations:', error)
        throw error
      } finally {
        this.loading = false
      }
    },

    async fetchMessages(userId: number, page: number = 1) {
      try {
        const { $api } = useNuxtApp()
        const response = await $api(`/private-messages/${userId}?page=${page}`)
        
        // Handle paginated response structure
        const messages = response.data || (Array.isArray(response) ? response : [])
        
        // Ensure messages are for the correct conversation
        const conversationMessages = Array.isArray(messages) 
          ? messages.filter((m: PrivateMessage) => 
              (m.sender_id === userId || m.recipient_id === userId)
            )
          : []
        
        if (page === 1) {
          // Remove existing messages for this conversation to avoid duplicates
          this.messages = this.messages.filter(m => 
            !(m.sender_id === userId || m.recipient_id === userId)
          )
          // Add new messages (they come in desc order, so reverse to show oldest first)
          this.messages = [...this.messages, ...conversationMessages.reverse()]
        } else {
          // Prepend older messages for pagination
          this.messages = [...conversationMessages.reverse(), ...this.messages]
        }
        
        return response
      } catch (error) {
        console.error('Error fetching messages:', error)
        throw error
      }
    },

    async sendMessage(userId: number, content: string, meta?: any) {
      try {
        const { $api } = useNuxtApp()
        const message = await $api(`/private-messages/${userId}`, {
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

    addMessage(message: PrivateMessage | any) {
      // Ensure message has required fields
      if (!message || !message.id) {
        console.warn('Invalid private message data:', message)
        return
      }
      
      // Check if message already exists
      const existingMessage = this.messages.find(m => m.id === message.id)
      if (existingMessage) {
        return
      }
      
      const currentUserId = this.currentConversation?.id
      if (!currentUserId) {
        // If no conversation is open, still add the message but don't filter
        this.messages.push(message)
        return
      }

      // Only add if it's part of the current conversation
      const belongsToCurrentConversation = 
        message.sender_id === currentUserId || message.recipient_id === currentUserId

      if (!belongsToCurrentConversation) {
        console.warn('Message not added - conversation mismatch:', {
          message_sender_id: message.sender_id,
          message_recipient_id: message.recipient_id,
          current_conversation_id: currentUserId,
        })
        return
      }
      
      this.messages.push(message)
    },

    setCurrentConversation(user: User | null) {
      this.currentConversation = user
    },

    clearMessages() {
      this.messages = []
    },

    async fetchUnreadCount() {
      try {
        const { $api } = useNuxtApp()
        const response = await $api('/private-messages/unread-count')
        this.unreadCount = response.count || 0
        return this.unreadCount
      } catch (error) {
        console.error('Error fetching unread count:', error)
        this.unreadCount = 0
        return 0
      }
    },

    async markAsRead(userId: number) {
      try {
        const { $api } = useNuxtApp()
        await $api(`/private-messages/${userId}/read`, {
          method: 'POST',
        })
        
        // Update local state
        this.messages.forEach(m => {
          if (m.sender_id === userId && !m.read_at) {
            m.read_at = new Date().toISOString()
          }
        })
        
        // Update conversation unread count
        const conversation = this.conversations.find(c => c.user.id === userId)
        if (conversation) {
          conversation.unread_count = 0
        }
        
        // Refresh unread count
        await this.fetchUnreadCount()
      } catch (error) {
        console.error('Error marking messages as read:', error)
      }
    },

    updateConversation(conversation: Conversation) {
      const index = this.conversations.findIndex(c => c.user.id === conversation.user.id)
      if (index !== -1) {
        this.conversations[index] = conversation
      } else {
        // Add new conversation at the beginning
        this.conversations.unshift(conversation)
      }
    },
  },
})

