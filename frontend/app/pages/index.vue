<template>
  <div class="h-screen bg-primary flex justify-center overflow-hidden">
      <div class="w-full max-w-md h-screen flex flex-col overflow-hidden" :style="{ backgroundColor: 'var(--site-secondary-color, #ffffff)' }">
        <!-- Header -->
        <header class="border-b shadow-sm w-full" :style="{ backgroundColor: 'var(--site-secondary-color, #ffffff)' }">
        <div class="py-1">
          <div class="flex justify-between items-center mb-1">
            <div class="flex items-center ">
              <div class="w-10 h-10 flex items-center justify-center shadow-sm overflow-hidden">
                <img 
                  v-if="favicon && !faviconError" 
                  :src="favicon" 
                  alt="Site Icon" 
                  class="w-full h-full object-contain p-1"
                  @error="faviconError = true"
                />
                <span v-else class="text-white text-xs font-bold">شات</span>
              </div>
              <h1 class="font-bold text-primary">{{ siteName }}</h1> 
            </div>
            <Button icon="pi pi-refresh" class="shadow-sm mx-1 rounded text-xs" @click="refreshPage" />
          </div>

          <!-- Banner -->
          <div class="w-full overflow-hidden">
            <img src="assets/images/banner.gif" alt="Chat Banner" class="w-full h-auto object-cover" />
          </div>

          <div class="grid grid-cols-4 gap-x-2 gap-y-1 mt-2 px-1">
            <div class="btn-styled bg-primary text-white text-center" v-for="i in 4">زر {{ i }}</div>
          </div>
        </div>
      </header>

      <!-- Main Content -->
      <div class="w-full flex-1 flex flex-col min-h-0">
        <!-- Auth Tabs Section (Compact, under header) -->
        <div class="flex-shrink-0">
          <Card class="shadow-md !rounded-none border-0">
            <template #content>
              <div class="custom-tabs">
                <!-- Tab Headers -->
                <div class="tab-headers">
                  <button
                    @click="activeTab = 0"
                    :class="['tab-header', { active: activeTab === 0, 'first-tab': true }]"
                  >
                    <div class="flex items-center gap-2">
                      <i class="pi pi-user text-sm" :style="{ color: activeTab === 0 ? 'var(--site-primary-color, #450924)' : '#6b7280' }"></i>
                      <span class="text-xs">دخول الزوار</span>
                    </div>
                  </button>
                  <button
                    @click="activeTab = 1"
                    :class="['tab-header', { active: activeTab === 1, 'middle-tab': true }]"
                  >
                    <div class="flex items-center gap-2">
                      <i class="pi pi-user text-sm" :style="{ color: activeTab === 1 ? 'var(--site-primary-color, #450924)' : '#6b7280' }"></i>
                      <span class="text-xs">دخول الاعضاء</span>
                    </div>
                  </button>
                  <button
                    @click="activeTab = 2"
                    :class="['tab-header', { active: activeTab === 2, 'last-tab': true }]"
                  >
                    <div class="flex items-center gap-2">
                      <i class="pi pi-user-plus text-sm" :style="{ color: activeTab === 2 ? 'var(--site-primary-color, #450924)' : '#6b7280' }"></i>
                      <span class="text-xs">تسجيل عضوية</span>
                    </div>
                  </button>
                </div>

                <!-- Tab Content -->
                <div class="tab-content">
                  <Transition name="tab-fade" mode="out-in">
                    <!-- Guest Tab -->
                    <div v-if="activeTab === 0" key="guest" class="tab-panel">
                      <form @submit.prevent="handleGuestLogin" class="space-y-2">
                        <div class="flex gap-2">
                          <InputText v-model="guestForm.username" type="text" placeholder="اسم المستخدم"
                            class="flex-1 text-xs " size="small" />
                          <Button type="submit" label="دخول كزائر"  size="small"
                            class="w-28 md:w-36 text-xs btn-styled border-0 flex-shrink-0" :style="{ backgroundColor: 'var(--site-primary-color, #450924) !important', border: 'none !important' }" />
                        </div>
                      </form>
                    </div>

                    <!-- Login Tab -->
                    <div v-else-if="activeTab === 1" key="login" class="tab-panel">
                      <form @submit.prevent="handleLogin" class="space-y-2">
                        <div class="flex gap-2">
                          <InputText v-model="loginForm.username" type="text" placeholder="اسم المستخدم"
                            class="w-32 md:w-36 text-xs " size="small" />
                          <div class="relative ">
                            <InputText v-model="loginForm.password" :type="showPassword ? 'text' : 'password'"
                              placeholder="كلمة المرور" class="w-32 md:w-36 pr-8 text-xs " size="small" />
                            <button type="button" @click="showPassword = !showPassword"
                              class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-500 hover:text-gray-700 text-xs">
                              <i :class="showPassword ? 'pi pi-eye-slash' : 'pi pi-eye'"></i>
                            </button>
                          </div>
                          <Button type="submit" label="دخول" size="small"
                            class="w-28 text-xs btn-styled border-0 flex-shrink-0" :style="{ backgroundColor: 'var(--site-primary-color, #450924) !important', border: 'none !important' }" />
                        </div>
                      </form>
                    </div>

                    <!-- Register Tab -->
                    <div v-else-if="activeTab === 2" key="register" class="tab-panel">
                      <form @submit.prevent="handleRegister" class="space-y-2">
                        <div class="flex gap-2">
                          <InputText v-model="registerForm.username" type="text" placeholder="اسم المستخدم"
                            class="w-32 md:w-36 text-xs " size="small" />
                          <InputText v-model="registerForm.password" type="password" placeholder="كلمة المرور"
                            class="w-32 md:w-36 text-xs " size="small" />
                          <Button type="submit" label="تسجيل" size="small"
                            class="w-28 text-xs btn-styled border-0 flex-shrink-0" :style="{ backgroundColor: 'var(--site-primary-color, #450924) !important', border: 'none !important' }" />
                        </div>
                      </form>
                    </div>
                  </Transition>
                </div>
              </div>
            </template>
          </Card>
        </div>

        <!-- Active Users List -->
        <div class="flex-1 flex flex-col min-h-0">
          <!-- Status Bar -->
          <div class="flex justify-between items-center shadow-sm flex-shrink-0">
            <div class="bg-primary text-white px-3 py-2 -xl -xl flex flex-1">
              <span class="text-sm font-medium">{{ onlineCount }} مستخدم</span>
              <i class="pi pi-users text-sm"></i>
            </div>
            <div
              :class="[
                'px-3 py-2 -xl -xl flex items-center gap-1.5',
                chatStore.connected ? 'bg-green-600 text-white' : 'bg-yellow-600 text-white'
              ]"
            >
              <div
                class="w-2 h-2 rounded-full shadow-sm"
                :class="chatStore.connected ? 'bg-white' : 'bg-yellow-300 animate-pulse'"
              ></div>
              <span class="font-medium text-sm">
                {{ chatStore.connected ? 'متصل' : 'غير متصل' }}
              </span>
            </div>
          </div>

          <!-- Active Users List -->
          <Card class="shadow-md border-0 flex-1 flex flex-col min-h-0 overflow-hidden" :style="{ backgroundColor: 'var(--site-secondary-color, #ffffff)' }">
            <template #content>
              <div class="flex flex-col h-full min-h-0">
                <div v-if="chatStore.loading" class="text-center py-8 flex-shrink-0">
                  <p class="text-gray-600">جاري التحميل...</p>
                </div>

                <div v-else-if="displayActiveUsers.length === 0" class="text-center py-8 flex-shrink-0">
                  <p class="text-gray-600">لا يوجد مستخدمين متصلين حالياً</p>
                </div>

                <div v-else class="flex-1 overflow-y-auto min-h-0" style="max-height: 100%;">
                  <div v-for="user in displayActiveUsers" :key="user.id"
                    class="flex items-start hover:bg-gray-100 transition border border-gray-200 hover:border-gray-300 hover:shadow-sm">
                    <div>
                      <img :src="user.avatar_url || getDefaultUserImage()" alt="Avatar" width="14" height="14" class="w-14 h-14">
                    </div>
                    <div class="flex-1 min-w-0 items-center justify-center">
                      <div class="flex items-center justify-between">
                        <div class="font-medium text-md mx-2 flex items-center gap-1" :style="{
                          color: rgbToCss(user.name_color || { r: 69, g: 9, b: 36 }),
                          backgroundColor: user.name_bg_color === null || user.name_bg_color === undefined || user.name_bg_color === 'transparent'
                            ? 'transparent'
                            : rgbToCss(user.name_bg_color)
                        }">
                          <!-- Role Group Banner -->
                          <img
                            v-if="getRoleGroupBanner(user)"
                            :src="getRoleGroupBanner(user)"
                            :alt="getRoleGroupName(user)"
                            class="h-3 w-auto object-contain"
                            :title="getRoleGroupName(user)"
                          />
                          {{ user.name || user.username }}
                        </div>
                        <div class="flex items-center mx-2">
                          <span class="text-xs text-gray-400">#{{ user.id }}</span>
                          <img
                            v-if="user.country_code"
                            :src="`/flags/${user.country_code.toLowerCase()}.png`"
                            :alt="user.country_code"
                            class="w-5 h-4 object-contain"
                            @error="(e: Event) => { const target = e.target as HTMLImageElement; if (target) target.style.display = 'none' }"
                          />
                        </div>
                      </div>
                      <div class="flex-1 min-w-0 flex items-center justify-center mx-2">
                        <p class="text-sm inline"
                          :style="{ color: rgbToCss(user.bio_color || { r: 107, g: 114, b: 128 }) }">
                          {{ user.bio || '(عضو جديد)' }}
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </template>
          </Card>
        </div>
      </div>

      <!-- Footer -->
      <footer class="border-t w-full flex-shrink-0" :style="{ backgroundColor: 'var(--site-secondary-color, #ffffff)' }">
        <div class="px-4 py-4 text-center text-sm text-gray-600">
          حقوق النشر © 2025 {{ siteName }}. جميع الحقوق محفوظة
        </div>
      </footer>
    </div>

    <!-- Message Dialog -->
    <MessageDialog />
  </div>
</template>

<script setup lang="ts">
import { getFlagImageUrl } from '../utils/flagImage'

const authStore = useAuthStore()
const chatStore = useChatStore()
const router = useRouter()
const { getEcho, initEcho, disconnect } = useEcho()
const { getDefaultUserImage, favicon, siteName } = useSiteSettings()
const faviconError = ref(false)

// Message dialog
const { showMessage } = useMessageDialog()

const activeTab = ref(0)

const getRoleIcon = (role: string): string => {
  const roleIcons: Record<string, string> = {
    'admin': 'pi pi-shield',
    'moderator': 'pi pi-user-edit',
    'vip': 'pi pi-star',
    'member': 'pi pi-user',
  }
  return roleIcons[role.toLowerCase()] || 'pi pi-user'
}

// Helper function to get role group banner
const getRoleGroupBanner = (user: any): string | undefined => {
  // First check if role_group_banner is directly available (from backend accessor)
  if (user?.role_group_banner) {
    return user.role_group_banner
  }
  // Fallback to checking role_groups array
  if (user?.role_groups && user.role_groups.length > 0) {
    // Get the highest priority role group (first one, already sorted by backend)
    const primaryRoleGroup = user.role_groups[0]
    return primaryRoleGroup?.banner || undefined
  }
  return undefined
}

// Helper function to get role group name
const getRoleGroupName = (user: any): string => {
  if (!user?.role_groups || user.role_groups.length === 0) {
    return ''
  }
  const primaryRoleGroup = user.role_groups[0]
  return primaryRoleGroup?.name || ''
}

// Helper function to convert RGB object to CSS string
const rgbToCss = (color: any): string => {
  if (!color) return ''
  if (typeof color === 'string') {
    // If it's already a string (hex or rgb), return as is
    if (color.startsWith('rgb') || color.startsWith('#') || color === 'transparent') {
      return color
    }
    // Try to parse as stored JSON RGB object
    try {
      const parsed = JSON.parse(color)
      if (parsed && typeof parsed === 'object' && 'r' in parsed && 'g' in parsed && 'b' in parsed) {
        return `rgb(${parsed.r}, ${parsed.g}, ${parsed.b})`
      }
    } catch {
      return color
    }
    return color
  }
  if (typeof color === 'object' && 'r' in color && 'g' in color && 'b' in color) {
    return `rgb(${color.r}, ${color.g}, ${color.b})`
  }
  return ''
}

// Login form
const loginForm = ref({
  username: '',
  password: '',
})
// Register form
const registerForm = ref({
  username: '',
  password: '',
})

// Guest form
const guestForm = ref({
  username: '',
})

const showPassword = ref(false)

const displayActiveUsers = computed(() => chatStore.displayActiveUsers)

const onlineCount = computed(() => {
  return displayActiveUsers.value.length
})

const handleLogin = () => {
  const username = loginForm.value.username
  const password = loginForm.value.password
  
  // Try instant login from cache - navigate immediately if successful
  const instantLogin = authStore.instantLoginFromCache(username, password)
  
  // Navigate IMMEDIATELY without waiting (no loading indicator)
  router.push('/chat')
  chatStore.setCurrentRoomId(1)
  
  if (instantLogin) {
    // Cache exists - authenticate in background to refresh token and validate
    authStore.login(username, password, true).catch(async (err: any) => {
      // Authentication failed - clear auth and redirect back
      authStore.clearAuth()
      router.push('/')
      await nextTick()
      const errorMessage = err?.data?.message || err?.message || 'فشل تسجيل الدخول. يرجى التحقق من بيانات الاعتماد.'
      showMessage('error', 'خطأ في تسجيل الدخول', errorMessage)
    })
  } else {
    // No cache - authenticate immediately in background
    authStore.login(username, password, true).catch(async (err: any) => {
      // Authentication failed - clear auth and redirect back
      authStore.clearAuth()
      router.push('/')
      await nextTick()
      const errorMessage = err?.data?.message || err?.message || 'فشل تسجيل الدخول. يرجى التحقق من بيانات الاعتماد.'
      showMessage('error', 'خطأ في تسجيل الدخول', errorMessage)
    })
  }
}

const handleRegister = () => {
  const username = registerForm.value.username
  const password = registerForm.value.password
  
  // Navigate IMMEDIATELY without waiting (no loading indicator)
  router.push('/chat')
  chatStore.setCurrentRoomId(1)
  
  // Register in background
  authStore.register(username, password).catch(async (err: any) => {
    // Registration failed - clear auth and redirect back
    authStore.clearAuth()
    router.push('/')
    await nextTick()
    const errorMessage = err?.data?.message || err?.message || 'فشل التسجيل. يرجى المحاولة مرة أخرى.'
    showMessage('error', 'خطأ في التسجيل', errorMessage)
  })
}

const handleGuestLogin = () => {
  const username = guestForm.value.username
  
  // Try instant guest login from cache
  const instantLogin = authStore.instantGuestLoginFromCache(username)
  
  // Navigate IMMEDIATELY without waiting (no loading indicator)
  router.push('/chat')
  chatStore.setCurrentRoomId(1)
  
  if (instantLogin) {
    // Cache exists - authenticate in background to refresh token and validate
    authStore.guestLogin(username, true).catch(async (err: any) => {
      // Authentication failed - clear auth and redirect back
      authStore.clearAuth()
      router.push('/')
      await nextTick()
      const errorMessage = err?.data?.message || err?.message || 'فشل دخول الزوار. يرجى المحاولة مرة أخرى.'
      showMessage('error', 'خطأ في دخول الزوار', errorMessage)
    })
  } else {
    // No cache - authenticate immediately in background
    authStore.guestLogin(username, true).catch(async (err: any) => {
      // Authentication failed - clear auth and redirect back
      authStore.clearAuth()
      router.push('/')
      await nextTick()
      const errorMessage = err?.data?.message || err?.message || 'فشل دخول الزوار. يرجى المحاولة مرة أخرى.'
      showMessage('error', 'خطأ في دخول الزوار', errorMessage)
    })
  }
}

let refreshInterval: ReturnType<typeof setInterval> | null = null

const refreshPage = () => {
  chatStore.fetchActiveUsers().catch(() => {
    // Ignore errors if not authenticated
  })
}

onMounted(async () => {
  authStore.initAuth()

  // Initialize connection state based on auth status
  if (!authStore.isAuthenticated || !authStore.token) {
    chatStore.setConnected(false)
  }

  // Only load minimal data for landing page (not bootstrap - that loads after joining room)
  // Load site settings for display on landing page (non-blocking)
  if (!authStore.isAuthenticated) {
    try {
      await useSiteSettings().fetchSettings()
    } catch (error) {
      console.error('Error loading site settings:', error)
    }
  }

  // Load active users for display (non-blocking)
  try {
    await chatStore.fetchActiveUsers()
  } catch (error) {
    console.error('Error loading active users:', error)
  }

  // Set up Echo to listen for profile updates on the global presence channel
  if (authStore.isAuthenticated && authStore.token) {
    // Initialize Echo if not already initialized
    initEcho()
    const echo = getEcho()
    
    if (echo) {
      
      // Track connection state - access Pusher through Echo's connector
      try {
        // @ts-ignore - accessing internal connector property
        const pusher = echo.connector?.pusher || echo.pusher
        if (pusher && pusher.connection) {
          // Set initial connection state
          const initialState = pusher.connection.state === 'connected' || pusher.connection.state === 'connecting'
          chatStore.setConnected(initialState)
          
          // Listen for connection events
          pusher.connection.bind('connected', () => {
            chatStore.setConnected(true)
          })
          
          pusher.connection.bind('disconnected', () => {
            chatStore.setConnected(false)
          })
          
          pusher.connection.bind('error', () => {
            chatStore.setConnected(false)
          })
          
          pusher.connection.bind('state_change', (states: any) => {
            chatStore.setConnected(states.current === 'connected')
          })
        } else {
          // If pusher connection not available, assume disconnected
          chatStore.setConnected(false)
        }
      } catch (error) {
        console.error('Error accessing Pusher connection:', error)
        chatStore.setConnected(false)
      }
      
      // Join global presence channel to listen for profile updates
      const presenceChannel = echo.join('presence')
      
      presenceChannel.subscribed(() => {
        
        // Set up listener after subscription is confirmed
        presenceChannel.listen('.profile.updated', (data: any) => {
          if (data.user && data.user.id) {
            // Update user in active users list
            chatStore.updateActiveUser(data.user)
          }
        })
      })
      
      presenceChannel.error((error: any) => {
        console.error('❌ Error subscribing to presence channel:', error)
      })
      
      // Also set up listener immediately (in case subscribed() doesn't fire)
      presenceChannel.listen('.profile.updated', (data: any) => {
        if (data.user && data.user.id) {
          // Update user in active users list
          chatStore.updateActiveUser(data.user)
        }
      })

      // Subscribe to user's private channel for ban events
      if (authStore.user) {
        const userPrivateChannel = echo.private(`user.${authStore.user.id}`)
        
        userPrivateChannel.subscribed(() => {
        })

        // Listen for ban event (inside subscribed callback)
        userPrivateChannel.subscribed(() => {
          
          userPrivateChannel.listen('.user.banned', async (data: any) => {
            
            try {
              // Clear auth and disconnect Echo
              authStore.clearAuth()
              disconnect()
              
              // Show ban message
              const { useToast } = await import('primevue/usetoast')
              const toast = useToast()
              toast.add({
                severity: 'error',
                summary: 'تم حظر حسابك',
                detail: data.message || 'تم حظر حسابك من الموقع',
                life: 10000,
              })
              
              // Already on home page, but refresh to clear any cached data
              window.location.href = '/'
            } catch (error) {
              console.error('❌ Error handling ban event:', error)
              // Fallback: force redirect
              window.location.href = '/'
            }
          })
        })

        // Also set up listener immediately (in case subscribed() doesn't fire)
        userPrivateChannel.listen('.user.banned', async (data: any) => {
          
          try {
            // Clear auth and disconnect Echo
            authStore.clearAuth()
            disconnect()
            
            // Show ban message
            const { useToast } = await import('primevue/usetoast')
            const toast = useToast()
            toast.add({
              severity: 'error',
              summary: 'تم حظر حسابك',
              detail: data.message || 'تم حظر حسابك من الموقع',
              life: 10000,
            })
            
            // Already on home page, but refresh to clear any cached data
            window.location.href = '/'
          } catch (error) {
            console.error('❌ Error handling ban event:', error)
            // Fallback: force redirect
            window.location.href = '/'
          }
        })
      }
    } else {
      console.warn('⚠️ Echo not available for profile updates - token might be missing')
      chatStore.setConnected(false)
    }
  } else {
    chatStore.setConnected(false)
  }

  // Refresh active users periodically
  refreshInterval = setInterval(async () => {
    try {
      await chatStore.fetchActiveUsers()
    } catch (error) {
      console.error('Error refreshing active users:', error)
    }
  }, 30000) // Refresh every 30 seconds
})

onUnmounted(() => {
  if (refreshInterval) {
    clearInterval(refreshInterval)
    refreshInterval = null
  }
  
  // Leave presence channel when component unmounts
  if (authStore.isAuthenticated) {
    const echo = getEcho()
    if (echo) {
      echo.leave('presence')
    }
  } else {
    // If not authenticated, ensure connection state is false
    chatStore.setConnected(false)
  }
})

// Watch for auth changes to redirect if needed
watch(() => authStore.isAuthenticated, async (isAuth) => {
  if (isAuth) {
    // Initialize Echo and track connection when authenticated
    initEcho()
    const echo = getEcho()
    if (echo) {
      try {
        // @ts-ignore - accessing internal connector property
        const pusher = echo.connector?.pusher || echo.pusher
        if (pusher && pusher.connection) {
          // Set initial connection state
          const initialState = pusher.connection.state === 'connected' || pusher.connection.state === 'connecting'
          chatStore.setConnected(initialState)
          
          // Listen for connection events if not already listening
          pusher.connection.bind('connected', () => {
            chatStore.setConnected(true)
          })
          
          pusher.connection.bind('disconnected', () => {
            chatStore.setConnected(false)
          })
          
          pusher.connection.bind('state_change', (states: any) => {
            chatStore.setConnected(states.current === 'connected')
          })
        }
      } catch (error) {
        console.error('Error tracking connection state:', error)
      }
    }
    
    // Navigate IMMEDIATELY to general room (ID is always 1)
    router.push('/chat/1')
  } else {
    // User logged out, set connection to false
    chatStore.setConnected(false)
  }
})
</script>

<style scoped>
.custom-tabs {
  width: 100%;
}

.tab-headers {
  display: flex;
  margin: 0;
  padding: 0;
  background: transparent;
  border-top: 1px solid var(--site-secondary-color, #ffffff);
  border-bottom: 1px solid var(--site-primary-color, #450924);
  position: relative;
}

.tab-header {
  flex: 0 0 auto;
  padding: 0.75rem 1rem;
  font-size: 0.75rem;
  border: none;
  background: transparent;
  color: #6b7280;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  cursor: pointer;
  margin-top: -1px;
  position: relative;
  outline: none;
}

.tab-header:hover {
  background: var(--site-secondary-color, #ffffff);
  color: var(--site-primary-color, #450924);
}

.tab-header.active {
  background: var(--site-secondary-color, #ffffff);
  color: var(--site-primary-color, #450924);
  font-weight: 600;
  border-bottom: 1px solid var(--site-secondary-color, #ffffff);
  z-index: 1;
  margin-bottom: -1px;
}

/* First tab (guest) - top and right border */
.tab-header.first-tab.active {
  border-top: 1px solid var(--site-primary-color, #450924);
  border-right: 1px solid var(--site-primary-color, #450924);
  border-left: none;
  border-bottom: 1px solid transparent;
  border-top-left-radius: 4px;
  border-top-right-radius: 4px;
}

/* Middle tab (login) - top, left, and right border */
.tab-header.middle-tab.active {
  border-top: 1px solid var(--site-primary-color, #450924);
  border-left: 1px solid var(--site-primary-color, #450924);
  border-right: 1px solid var(--site-primary-color, #450924);
  border-bottom: 1px solid transparent;
  border-top-left-radius: 4px;
  border-top-right-radius: 4px;
}

/* Last tab (register) - top, left, and right border */
.tab-header.last-tab.active {
  border-top: 1px solid var(--site-primary-color, #450924);
  border-left: 1px solid var(--site-primary-color, #450924);
  border-right: 1px solid var(--site-primary-color, #450924);
  border-bottom: 1px solid transparent;
  border-top-left-radius: 4px;
  border-top-right-radius: 4px;
}

.tab-content {
  padding: 1rem;
  background: transparent;
}

.tab-panel {
  padding: 0;
}

/* Tab transition animations */
.tab-fade-enter-active,
.tab-fade-leave-active {
  transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1), transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.tab-fade-enter-from {
  opacity: 0;
  transform: translateY(-5px);
}

.tab-fade-leave-to {
  opacity: 0;
  transform: translateY(5px);
}

.tab-fade-enter-to,
.tab-fade-leave-from {
  opacity: 1;
  transform: translateY(0);
}

:deep(.p-card) {
  border-radius: 0;
  box-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
  display: flex;
  flex-direction: column;
  height: 100%;
}

:deep(.p-card-body) {
  padding: 0;
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

:deep(.p-card-content) {
  padding: 0;
  display: flex;
  flex-direction: column;
  flex: 1;
  min-height: 0;
  height: 100%;
}
</style>
