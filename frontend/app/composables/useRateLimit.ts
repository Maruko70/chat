import { ref, computed } from 'vue'
import { useMessageDialog } from './useMessageDialog'
import { useSiteSettings } from './useSiteSettings'

// Initialize message dialog at module level to ensure reactivity
const { showMessage: showRateLimitMessage } = useMessageDialog()

interface ActionRecord {
  timestamp: number
  action: string
}

const actionHistory = ref<ActionRecord[]>([])
const ignoreCount = ref(5) // Default values
const ignoreTimeTillBan = ref(3)
const ignoreBanTimeMinutes = ref(5)
const banUntil = ref<number | null>(null)
const ignoreCountReached = ref(0)
const settingsLoaded = ref(false)
const settingsLoading = ref(false)

// Load settings from site settings
const loadSettings = async (forceRefresh = false) => {
  // If forcing refresh, reset the loaded flag
  if (forceRefresh) {
    settingsLoaded.value = false
  }
  
  // If already loading and not forcing refresh, wait for it
  if (settingsLoading.value && !forceRefresh) {
    // Wait a bit for current load to finish
    let attempts = 0
    while (settingsLoading.value && attempts < 10) {
      await new Promise(resolve => setTimeout(resolve, 100))
      attempts++
    }
    if (settingsLoaded.value) return
  }
  
  // If already loaded and not forcing refresh, skip
  if (settingsLoaded.value && !forceRefresh) return
  
  settingsLoading.value = true
  try {
    const { getSetting, fetchSettings } = useSiteSettings()
    // Fetch settings - force refresh if requested
    await fetchSettings(forceRefresh)
    const ignoreCountVal = getSetting('ignore_count', '5')
    const ignoreTimeTillBanVal = getSetting('ignore_time_till_ban', '3')
    const ignoreBanTimeMinutesVal = getSetting('ignore_ban_time_minutes', '5')
    
    console.log('[RateLimit] Loading settings:', {
      ignore_count: ignoreCountVal,
      ignore_time_till_ban: ignoreTimeTillBanVal,
      ignore_ban_time_minutes: ignoreBanTimeMinutesVal,
      'ignore_ban_time_minutes type': typeof ignoreBanTimeMinutesVal
    })
    
    ignoreCount.value = ignoreCountVal ? parseInt(ignoreCountVal) || 5 : 5
    ignoreTimeTillBan.value = ignoreTimeTillBanVal ? parseInt(ignoreTimeTillBanVal) || 3 : 3
    // Allow 0 for ban minutes (0 means kick instead of ban)
    if (ignoreBanTimeMinutesVal !== null && ignoreBanTimeMinutesVal !== undefined && ignoreBanTimeMinutesVal !== '') {
      const parsed = parseInt(ignoreBanTimeMinutesVal)
      ignoreBanTimeMinutes.value = isNaN(parsed) ? 5 : parsed
      console.log('[RateLimit] Parsed ban minutes:', ignoreBanTimeMinutesVal, '->', parsed, '->', ignoreBanTimeMinutes.value)
    } else {
      ignoreBanTimeMinutes.value = 5
      console.log('[RateLimit] Using default ban minutes: 5')
    }
    settingsLoaded.value = true
  } catch (error) {
    console.error('Error loading rate limit settings:', error)
    // Keep default values
  } finally {
    settingsLoading.value = false
  }
}

// Check if user is banned
const isBanned = computed(() => {
  if (!banUntil.value) return false
  return Date.now() < banUntil.value
})

// Get remaining ban time in minutes
const getRemainingBanTime = computed(() => {
  if (!banUntil.value || !isBanned.value) return 0
  const remaining = banUntil.value - Date.now()
  return Math.ceil(remaining / 60000) // Convert to minutes
})

// Separate action history for messages (allows bursts)
const messageActionHistory = ref<ActionRecord[]>([])

// Clean old actions (older than 1 minute)
const cleanOldActions = () => {
  const oneMinuteAgo = Date.now() - 60000
  actionHistory.value = actionHistory.value.filter(
    (record) => record.timestamp > oneMinuteAgo
  )
}

// Clean old message actions (older than 1 minute)
const cleanOldMessageActions = () => {
  const oneMinuteAgo = Date.now() - 60000
  messageActionHistory.value = messageActionHistory.value.filter(
    (record) => record.timestamp > oneMinuteAgo
  )
}

// Check if action should be allowed (synchronous for performance)
const checkRateLimit = (action: string): boolean => {
  // Load settings in background if not loaded (non-blocking)
  if (!settingsLoaded.value && !settingsLoading.value) {
    loadSettings() // Fire and forget, will use defaults until loaded
  }
  
  // Check if banned
  if (isBanned.value) {
    // Use setTimeout to prevent blocking UI
    setTimeout(() => {
      showRateLimitMessage(
        'error',
        'تم حظرك مؤقتاً',
        `تم حظرك لمدة ${ignoreBanTimeMinutes.value} دقائق بسبب الإفراط في الطلبات. الوقت المتبقي: ${getRemainingBanTime.value} دقيقة`,
        10000
      )
    }, 0)
    return false
  }

  // Clean old actions (quick operation)
  cleanOldActions()

  // Count actions in the last minute
  const recentActions = actionHistory.value.filter(
    (record) => record.timestamp > Date.now() - 60000
  )

  // Check if limit exceeded
  if (recentActions.length >= ignoreCount.value) {
    ignoreCountReached.value++
    
    // Use setTimeout to prevent blocking UI
    setTimeout(() => {
      showRateLimitMessage(
        'error',
        'تم تجاهل طلبك',
        'تم تجاهل طلبك بسبب الإفراط في الطلبات. يرجى الانتظار قليلاً قبل المحاولة مرة أخرى.',
        5000
      )
    }, 0)

    // Check if should ban
    if (ignoreCountReached.value >= ignoreTimeTillBan.value) {
      console.log('[RateLimit] Ban threshold reached! ignoreCountReached:', ignoreCountReached.value, 'ignoreTimeTillBan:', ignoreTimeTillBan.value)
      ignoreCountReached.value = 0 // Reset counter
      // Call banUser immediately - ensure settings are loaded first
      // Use setTimeout to ensure it runs after current execution
      setTimeout(async () => {
        console.log('[RateLimit] Calling banUser()...')
        await banUser()
      }, 0)
    }

    return false
  }

  // Record action
  actionHistory.value.push({
    timestamp: Date.now(),
    action,
  })

  // Reset ignore count if action is allowed
  ignoreCountReached.value = 0

  return true
}

// Check rate limit specifically for messages (allows bursts)
// Uses a higher threshold (20 messages per minute) to allow rapid messaging
// but still prevents spam
const checkMessageRateLimit = (): boolean => {
  // Load settings in background if not loaded (non-blocking)
  if (!settingsLoaded.value && !settingsLoading.value) {
    loadSettings() // Fire and forget, will use defaults until loaded
  }
  
  // Check if banned
  if (isBanned.value) {
    // Use setTimeout to prevent blocking UI
    setTimeout(() => {
      showRateLimitMessage(
        'error',
        'تم حظرك مؤقتاً',
        `تم حظرك لمدة ${ignoreBanTimeMinutes.value} دقائق بسبب الإفراط في الطلبات. الوقت المتبقي: ${getRemainingBanTime.value} دقيقة`,
        10000
      )
    }, 0)
    return false
  }

  // Clean old message actions (quick operation)
  cleanOldMessageActions()

  // Count message actions in the last minute
  const recentMessages = messageActionHistory.value.filter(
    (record) => record.timestamp > Date.now() - 60000
  )

  // Use a higher threshold for messages (20 per minute instead of 5)
  // This allows bursts of messages while still preventing spam
  const messageLimit = 20

  // Check if limit exceeded
  if (recentMessages.length >= messageLimit) {
    // Use setTimeout to prevent blocking UI
    setTimeout(() => {
      showRateLimitMessage(
        'error',
        'تم تجاهل طلبك',
        'تم تجاهل طلبك بسبب الإفراط في إرسال الرسائل. يرجى الانتظار قليلاً قبل المحاولة مرة أخرى.',
        5000
      )
    }, 0)

    return false
  }

  // Record message action
  messageActionHistory.value.push({
    timestamp: Date.now(),
    action: 'send_message',
  })

  return true
}

// Kick user (logout and redirect)
const kickUser = async () => {
  console.log('[RateLimit] Kicking user...')
  
  // Clear ban status and action history immediately
  if (typeof window !== 'undefined') {
    localStorage.removeItem('rate_limit_ban_until')
    actionHistory.value = []
  }
  
  // Show message (non-blocking)
  showRateLimitMessage(
    'error',
    'تم طردك',
    'تم طردك من الموقع بسبب الإفراط في الطلبات المتكررة.',
    2000
  )
  
  // Start logout process (non-blocking, don't wait for it)
  ;(async () => {
    try {
      console.log('[RateLimit] Starting logout process...')
      
      // Import auth store and disconnect
      const { useAuthStore } = await import('~/stores/authStore')
      const authStore = useAuthStore()
      
      // Clear auth immediately (don't wait for API call)
      authStore.clearAuth()
      
      // Try to disconnect Echo if available
      try {
        const { disconnect } = await import('~/composables/useEcho')
        disconnect()
        console.log('[RateLimit] Echo disconnected')
      } catch (e) {
        console.warn('[RateLimit] Echo disconnect failed:', e)
      }
      
      // Try to logout via API (but don't wait for it)
      try {
        await authStore.logout()
        console.log('[RateLimit] Logout API call successful')
      } catch (e) {
        console.warn('[RateLimit] Logout API call failed:', e)
        // Continue anyway - we already cleared auth
      }
    } catch (error) {
      console.error('[RateLimit] Error during logout process:', error)
      // Continue to redirect anyway
    }
  })()
  
  // Force redirect immediately - don't wait for anything
  if (typeof window !== 'undefined') {
    console.log('[RateLimit] Force redirecting to home page NOW...')
    
    // Immediate redirect - try replace first
    try {
      window.location.replace('/')
    } catch (e) {
      console.warn('[RateLimit] replace failed, trying href:', e)
      try {
        window.location.href = '/'
      } catch (e2) {
        console.error('[RateLimit] href failed, trying reload:', e2)
        window.location.reload()
      }
    }
    
    // Fallback after 500ms if still on same page
    setTimeout(() => {
      if (window.location.pathname !== '/') {
        console.log('[RateLimit] Fallback redirect triggered')
        window.location.href = '/'
      }
    }, 500)
    
    // Final fallback after 1 second
    setTimeout(() => {
      if (window.location.pathname !== '/') {
        console.log('[RateLimit] Final fallback redirect triggered')
        window.location.reload()
      }
    }, 1000)
  }
}

// Ban user
const banUser = async () => {
  // Force reload settings to ensure we have the latest value
  await loadSettings(true) // Force refresh to get latest settings
  
  // Ensure we have a valid number
  const banMinutes = Number(ignoreBanTimeMinutes.value)
  
  console.log('[RateLimit] banUser called, banMinutes:', banMinutes, 'raw value:', ignoreBanTimeMinutes.value, 'settingsLoaded:', settingsLoaded.value)
  
  // If ban time is exactly 0, kick only (no ban)
  if (banMinutes === 0) {
    console.log('[RateLimit] Ban minutes is 0, kicking user only (no ban)...')
    await kickUser()
    return
  }
  
  // If ban time is more than 0, ban AND kick
  console.log('[RateLimit] Ban minutes > 0, banning and kicking user...')
  
  // Call API to create ban record in database
  try {
    const { $api } = useNuxtApp()
    console.log('[RateLimit] Calling API to ban user...')
    
    await ($api as any)('/bans/users/rate-limit', {
      method: 'POST',
      body: {
        ban_minutes: banMinutes,
      },
    })
    
    console.log('[RateLimit] Ban record created in database')
  } catch (error: any) {
    console.error('[RateLimit] Failed to create ban record:', error)
    // Continue anyway - we'll still kick the user
  }
  
  // Set ban with time limit (for frontend tracking)
  const banEndTime = Date.now() + banMinutes * 60000
  banUntil.value = banEndTime
  
  // Store in localStorage
  if (typeof window !== 'undefined') {
    localStorage.setItem('rate_limit_ban_until', banEndTime.toString())
  }

  // Show ban message
  showRateLimitMessage(
    'error',
    'تم حظرك مؤقتاً',
    `تم حظرك لمدة ${banMinutes} دقائق بسبب الإفراط في الطلبات المتكررة.`,
    3000
  )
  
  // Also kick the user (logout and redirect)
  await kickUser()
}

// Load ban status from localStorage
const loadBanStatus = () => {
  if (typeof window !== 'undefined') {
    const stored = localStorage.getItem('rate_limit_ban_until')
    if (stored) {
      const banTime = parseInt(stored)
      if (banTime > Date.now()) {
        banUntil.value = banTime
      } else {
        localStorage.removeItem('rate_limit_ban_until')
        banUntil.value = null
      }
    }
  }
}

// Initialize ban status on load
if (typeof window !== 'undefined') {
  loadBanStatus()
  // Load settings in background (non-blocking)
  loadSettings()
  
  // Clean old actions periodically
  setInterval(cleanOldActions, 30000) // Every 30 seconds
}

export const useRateLimit = () => {
  return {
    checkRateLimit,
    checkMessageRateLimit,
    isBanned,
    getRemainingBanTime,
    loadSettings,
  }
}

