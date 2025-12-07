import { ref, readonly } from 'vue'

export interface Message {
  id: string
  type: 'success' | 'error' | 'info'
  title: string
  message: string
  duration: number
  x: number
  y: number
}

const messages = ref<Message[]>([])

let messageIdCounter = 0

export const useMessageDialog = () => {
  const showMessage = (
    type: 'success' | 'error' | 'info',
    title: string,
    message: string,
    duration: number = type === 'error' ? 5000 : 3000
  ) => {
    const id = `msg-${Date.now()}-${messageIdCounter++}`
    
    // Calculate initial position (center-left, slightly offset to the left)
    if (typeof window !== 'undefined') {
      const dialogWidth = 400
      const dialogHeight = 120 // estimate or tweak
      const centerX = window.innerWidth / 2
      const centerY = window.innerHeight / 2
      
      // Offset to the left (about 200px from center)
      const offsetX = 200
      const baseX = centerX - offsetX - dialogWidth / 2
      
      // Add random horizontal offset (2-4px, randomly left or right) for visual stacking effect
      const direction = Math.random() < 0.5 ? -1 : 1 // Randomly left (-) or right (+)
      const magnitude = 2 + Math.random() * 2 // Random between 2-4px
      const randomOffset = direction * magnitude
      const initialX = baseX + randomOffset
      
      // Stack on top of each other (same Y position for all - no vertical spacing)
      const initialY = centerY - dialogHeight / 2 - 100

      const newMessage: Message = {
        id,
        type,
        title,
        message,
        duration,
        x: initialX,
        y: initialY,
      }

      messages.value.push(newMessage)

      // Auto-remove after duration
      if (duration > 0) {
        setTimeout(() => {
          removeMessage(id)
        }, duration)
      }

      return id
    }
    
    // Fallback for SSR
    const newMessage: Message = {
      id,
      type,
      title,
      message,
      duration,
      x: 0,
      y: 0,
    }

    messages.value.push(newMessage)

    if (duration > 0) {
      setTimeout(() => {
        removeMessage(id)
      }, duration)
    }

    return id
  }

  const removeMessage = (id: string) => {
    const index = messages.value.findIndex((msg) => msg.id === id)
    if (index !== -1) {
      messages.value.splice(index, 1)
      // Recalculate positions for remaining messages
    }
  }


  return {
    messages: readonly(messages),
    showMessage,
    removeMessage
  }
}

