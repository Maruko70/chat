<template>
  <div class="space-y-4">
    <!-- Creative Warning Vault Card -->
    <div 
      class="relative overflow-hidden rounded-xl border-2 transition-all duration-300 cursor-pointer group"
      :class="[
        !warningsLoaded 
          ? 'border-gray-300 bg-gradient-to-br from-gray-50 to-gray-100 hover:border-gray-400 hover:shadow-lg'
          : warningsLoaded && unreadCount > 0
          ? 'border-orange-400 bg-gradient-to-br from-orange-50 via-orange-100 to-orange-50 shadow-lg shadow-orange-200/50'
          : warningsLoaded && unreadCount === 0 && warnings.length > 0
          ? 'border-gray-300 bg-gradient-to-br from-gray-50 to-gray-100'
          : warningsLoaded && warnings.length === 0
          ? 'border-green-300 bg-gradient-to-br from-green-50 via-green-100 to-green-50 shadow-md'
          : 'border-gray-200 bg-white'
      ]"
      @click="handleVaultClick"
    >
      <!-- Animated Background Pattern -->
      <div 
        v-if="!warningsLoaded"
        class="absolute inset-0 opacity-5"
        style="background-image: repeating-linear-gradient(45deg, transparent, transparent 10px, rgba(0,0,0,0.1) 10px, rgba(0,0,0,0.1) 20px);"
      ></div>
      
      <!-- Shimmer Effect (when not loaded) -->
      <div 
        v-if="!warningsLoaded && !loading"
        class="absolute inset-0 -translate-x-full group-hover:translate-x-full transition-transform duration-1000 bg-gradient-to-r from-transparent via-white/20 to-transparent"
      ></div>

      <div class="relative p-5">
        <div class="flex items-center justify-between gap-4">
          <!-- Left Side: Icon & Info -->
          <div class="flex items-center gap-4 flex-1">
            <!-- Animated Lock/Vault Icon -->
            <div class="relative flex-shrink-0">
              <div 
                class="w-16 h-16 rounded-2xl flex items-center justify-center transition-all duration-500 transform"
                :class="[
                  !warningsLoaded
                    ? 'bg-gradient-to-br from-gray-400 to-gray-500 shadow-lg group-hover:scale-110 group-hover:rotate-3'
                    : warningsLoaded && unreadCount > 0
                    ? 'bg-gradient-to-br from-orange-500 to-orange-600 shadow-xl animate-pulse'
                    : warningsLoaded && warnings.length > 0
                    ? 'bg-gradient-to-br from-gray-400 to-gray-500 shadow-lg'
                    : warningsLoaded && warnings.length === 0
                    ? 'bg-gradient-to-br from-green-500 to-green-600 shadow-lg'
                    : 'bg-gray-300'
                ]"
              >
                <!-- Lock Icon (when not loaded) -->
                <Transition name="icon-flip" mode="out-in">
                  <div v-if="!warningsLoaded" key="lock" class="relative">
                    <i class="pi pi-lock text-white text-2xl"></i>
                    <!-- Lock shine effect -->
                    <div class="absolute inset-0 bg-white/30 rounded-full animate-ping"></div>
                  </div>
                  <!-- Warning Icon (when loaded with warnings) -->
                  <div v-else-if="warningsLoaded && warnings.length > 0" key="warning" class="relative">
                    <i class="pi pi-exclamation-triangle text-white text-2xl"></i>
                    <!-- Pulse ring for unread warnings -->
                    <div 
                      v-if="unreadCount > 0"
                      class="absolute inset-0 rounded-2xl border-2 border-white animate-ping opacity-75"
                    ></div>
                  </div>
                  <!-- Success Icon (when no warnings) -->
                  <div v-else key="success" class="relative">
                    <i class="pi pi-check-circle text-white text-2xl"></i>
                    <div class="absolute inset-0 rounded-2xl bg-white/30 animate-pulse"></div>
                  </div>
                </Transition>
              </div>
              
              <!-- Floating particles for unread warnings -->
              <div 
                v-if="warningsLoaded && unreadCount > 0"
                class="absolute -top-1 -right-1 w-6 h-6 bg-red-500 rounded-full flex items-center justify-center shadow-lg animate-bounce"
              >
                <span class="text-white text-xs font-bold">{{ unreadCount }}</span>
              </div>
            </div>
            
            <!-- Text Content -->
            <div class="text-right flex-1">
              <h3 class="text-xl font-bold mb-1 flex items-center gap-2">
                <span>صندوق التحذيرات</span>
                <Transition name="fade">
                  <i 
                    v-if="warningsLoaded && unreadCount > 0"
                    class="pi pi-exclamation-circle text-orange-600 animate-pulse"
                  ></i>
                </Transition>
              </h3>
              <p class="text-sm text-gray-600">
                <Transition name="fade" mode="out-in">
                  <span v-if="!warningsLoaded && !loading" key="click">
                    🔒 انقر لفتح الصندوق وعرض التحذيرات
                  </span>
                  <span v-else-if="loading" key="loading">
                    ⏳ جاري فتح الصندوق...
                  </span>
                  <span v-else-if="warnings.length === 0" key="empty" class="text-green-700 font-medium">
                    ✅ لا توجد تحذيرات - حسابك نظيف
                  </span>
                  <span v-else key="has-warnings">
                    📋 {{ warnings.length }} تحذير
                    <span v-if="unreadCount > 0" class="text-orange-600 font-bold">
                      ({{ unreadCount }} جديد)
                    </span>
                  </span>
                </Transition>
              </p>
            </div>
          </div>
          
          <!-- Right Side: Action Indicator -->
          <div class="flex items-center gap-2 flex-shrink-0">
            <Badge
              v-if="warningsLoaded && unreadCount > 0"
              :value="unreadCount"
              severity="danger"
              class="text-sm animate-pulse"
            />
            <div 
              class="w-10 h-10 rounded-full flex items-center justify-center transition-all duration-300"
              :class="[
                !warningsLoaded
                  ? 'bg-gray-200 group-hover:bg-gray-300 group-hover:scale-110'
                  : showWarnings
                  ? 'bg-blue-100 text-blue-600'
                  : 'bg-gray-100 text-gray-500'
              ]"
            >
              <Transition name="rotate" mode="out-in">
                <i 
                  :key="showWarnings ? 'up' : 'down'"
                  :class="[
                    'pi transition-transform duration-300',
                    showWarnings ? 'pi-chevron-up' : 'pi-chevron-down',
                    !warningsLoaded && 'group-hover:animate-bounce'
                  ]"
                ></i>
              </Transition>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading Animation with Creative Spinner -->
    <Transition name="vault-open">
      <div v-if="loading" class="relative overflow-hidden rounded-xl bg-gradient-to-br from-gray-50 to-gray-100 border-2 border-gray-200 p-8">
        <div class="flex flex-col items-center justify-center gap-4">
          <!-- Animated Vault Opening -->
          <div class="relative w-24 h-24">
            <div class="absolute inset-0 flex items-center justify-center">
              <div class="w-16 h-16 border-4 border-gray-300 border-t-orange-500 rounded-full animate-spin"></div>
            </div>
            <div class="absolute inset-0 flex items-center justify-center">
              <i class="pi pi-lock text-gray-400 text-3xl animate-pulse"></i>
            </div>
          </div>
          <div class="text-center">
            <p class="text-lg font-semibold text-gray-700 mb-1">جاري فتح الصندوق...</p>
            <p class="text-sm text-gray-500">يرجى الانتظار قليلاً</p>
          </div>
          <!-- Progress dots -->
          <div class="flex gap-2">
            <div 
              v-for="i in 3" 
              :key="i"
              class="w-2 h-2 bg-orange-500 rounded-full animate-bounce"
              :style="{ animationDelay: `${i * 0.15}s` }"
            ></div>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Warnings Content - Revealed with Animation -->
    <Transition name="vault-reveal">
      <div v-if="showWarnings && warningsLoaded && !loading" class="space-y-4">
        <!-- Empty State with Celebration -->
        <div v-if="warnings.length === 0" class="relative overflow-hidden rounded-xl bg-gradient-to-br from-green-50 via-green-100 to-green-50 border-2 border-green-300 p-8 text-center">
          <!-- Confetti effect background -->
          <div class="absolute inset-0 opacity-10">
            <div class="absolute top-4 left-1/4 w-2 h-2 bg-green-500 rounded-full animate-bounce" style="animation-delay: 0s;"></div>
            <div class="absolute top-8 right-1/4 w-2 h-2 bg-green-500 rounded-full animate-bounce" style="animation-delay: 0.2s;"></div>
            <div class="absolute bottom-4 left-1/3 w-2 h-2 bg-green-500 rounded-full animate-bounce" style="animation-delay: 0.4s;"></div>
            <div class="absolute bottom-8 right-1/3 w-2 h-2 bg-green-500 rounded-full animate-bounce" style="animation-delay: 0.6s;"></div>
          </div>
          <div class="relative">
            <div class="text-6xl mb-4 animate-bounce">🎉</div>
            <i class="pi pi-check-circle text-6xl text-green-500 mb-4 block"></i>
            <p class="text-xl font-bold text-green-700 mb-2">ممتاز!</p>
            <p class="text-sm text-green-600">حسابك نظيف ولا يحتوي على أي تحذيرات</p>
            <p class="text-xs text-green-500 mt-2">استمر في الحفاظ على هذا المستوى</p>
          </div>
        </div>

        <!-- Warnings Content -->
        <div v-else class="space-y-4">
          <!-- Warning Level Meter - Creative Design -->
          <div class="relative overflow-hidden rounded-xl bg-gradient-to-br from-gray-100 to-gray-200 border-2 border-gray-300 p-5">
            <div class="flex items-center justify-between mb-3">
              <div class="flex items-center gap-2">
                <i class="pi pi-gauge text-xl text-gray-600"></i>
                <span class="text-sm font-bold text-gray-700">مستوى التحذيرات</span>
              </div>
              <div class="flex items-center gap-2">
                <span 
                  class="text-2xl font-bold px-3 py-1 rounded-lg"
                  :class="warningLevel >= 3 ? 'text-red-600 bg-red-100' : 'text-orange-600 bg-orange-100'"
                >
                  {{ warningLevel }}
                </span>
                <span class="text-sm text-gray-500">/ 3</span>
              </div>
            </div>
            
            <!-- Animated Progress Bar -->
            <div class="relative w-full bg-gray-300 rounded-full h-4 overflow-hidden shadow-inner">
              <div 
                class="h-full transition-all duration-1000 ease-out rounded-full relative overflow-hidden"
                :class="[
                  warningLevel >= 3 
                    ? 'bg-gradient-to-r from-red-500 via-red-600 to-red-500' 
                    : 'bg-gradient-to-r from-orange-400 via-orange-500 to-orange-400'
                ]"
                :style="{ width: `${Math.min((warningLevel / 3) * 100, 100)}%` }"
              >
                <!-- Shimmer effect on progress bar -->
                <div class="absolute inset-0 bg-gradient-to-r from-transparent via-white/30 to-transparent animate-shimmer"></div>
              </div>
              <!-- Progress markers -->
              <div class="absolute inset-0 flex justify-between items-center px-1">
                <div 
                  v-for="i in 3" 
                  :key="i"
                  class="w-2 h-2 rounded-full"
                  :class="i <= warningLevel ? 'bg-white shadow-md' : 'bg-gray-400'"
                ></div>
              </div>
            </div>
            
            <!-- Warning Message -->
            <Transition name="fade">
              <div 
                v-if="warningLevel >= 3" 
                class="mt-3 p-3 bg-red-100 border border-red-300 rounded-lg flex items-center gap-2 animate-pulse"
              >
                <i class="pi pi-exclamation-triangle text-red-600"></i>
                <p class="text-xs font-bold text-red-700">
                  ⚠️ تم الوصول إلى الحد الأقصى - سيتم الحظر تلقائياً
                </p>
              </div>
              <p v-else class="text-xs text-gray-600 mt-3 text-center">
                بعد <span class="font-bold text-orange-600">{{ 3 - warningLevel }}</span> تحذير{{ 3 - warningLevel > 1 ? 'ات' : '' }} سيتم حظرك تلقائياً لمدة 7 أيام
              </p>
            </Transition>
          </div>

          <!-- Warnings Timeline with Creative Design -->
          <div class="relative">
            <!-- Animated Timeline Line -->
            <div class="absolute left-8 top-0 bottom-0 w-1 bg-gradient-to-b from-orange-300 via-orange-400 to-gray-200 rounded-full"></div>
            
            <div class="space-y-6">
              <TransitionGroup name="warning-item">
                <div
                  v-for="(warning, index) in warnings"
                  :key="warning.id"
                  class="relative flex gap-4"
                  :style="{ animationDelay: `${index * 0.1}s` }"
                >
                  <!-- Timeline Dot with Animation -->
                  <div class="relative z-10 flex-shrink-0">
                    <div 
                      class="w-16 h-16 rounded-2xl flex items-center justify-center border-4 transition-all duration-300 transform hover:scale-110"
                      :class="[
                        warning.is_read
                          ? 'bg-gradient-to-br from-gray-100 to-gray-200 border-gray-300 shadow-md'
                          : 'bg-gradient-to-br from-orange-100 to-orange-200 border-orange-400 shadow-xl shadow-orange-200/50 animate-pulse'
                      ]"
                    >
                      <i
                        :class="[
                          'pi text-xl',
                          warning.is_read 
                            ? 'pi-check text-gray-500' 
                            : 'pi-exclamation-triangle text-orange-600'
                        ]"
                      ></i>
                    </div>
                    <!-- Pulse effect for unread -->
                    <div 
                      v-if="!warning.is_read"
                      class="absolute inset-0 rounded-2xl bg-orange-400 animate-ping opacity-40"
                    ></div>
                    <!-- Ripple effect -->
                    <div 
                      v-if="!warning.is_read"
                      class="absolute inset-0 rounded-2xl border-2 border-orange-400 animate-pulse"
                    ></div>
                  </div>
                  
                  <!-- Warning Card with Creative Design -->
                  <div 
                    class="flex-1 mb-4 rounded-xl border-2 transition-all duration-300 hover:shadow-xl transform hover:-translate-y-1"
                    :class="[
                      warning.is_read
                        ? 'bg-white border-gray-200'
                        : 'bg-gradient-to-br from-orange-50 via-orange-100 to-orange-50 border-orange-300 shadow-lg'
                    ]"
                  >
                    <div class="p-5">
                      <div class="flex items-start justify-between gap-3 mb-3">
                        <div class="flex-1">
                          <!-- Tags Row -->
                          <div class="flex items-center gap-2 mb-3 flex-wrap">
                            <Tag
                              :value="warning.type === 'manual' ? 'يدوي' : 'انتهاك تلقائي'"
                              :severity="warning.type === 'manual' ? 'info' : 'warning'"
                              class="text-xs"
                            />
                            <Transition name="scale">
                              <span
                                v-if="!warning.is_read"
                                class="px-3 py-1 bg-gradient-to-r from-orange-500 to-orange-600 text-white text-xs font-bold rounded-full shadow-md animate-pulse"
                              >
                                ✨ جديد
                              </span>
                            </Transition>
                          </div>
                          
                          <!-- Warning Reason -->
                          <p class="text-base font-semibold text-gray-800 mb-3 leading-relaxed">
                            {{ warning.reason }}
                          </p>
                          
                          <!-- Metadata -->
                          <div class="flex items-center gap-4 text-xs text-gray-600 flex-wrap">
                            <span class="flex items-center gap-1.5 px-2 py-1 bg-gray-100 rounded-lg">
                              <i class="pi pi-user text-orange-600"></i>
                              <span class="font-medium">{{ warning.warned_by?.username || 'إدارة' }}</span>
                            </span>
                            <span class="flex items-center gap-1.5 px-2 py-1 bg-gray-100 rounded-lg">
                              <i class="pi pi-calendar text-blue-600"></i>
                              <span>{{ formatDate(warning.created_at) }}</span>
                            </span>
                            <span 
                              v-if="warning.violation" 
                              class="flex items-center gap-1.5 px-2 py-1 bg-gray-100 rounded-lg"
                            >
                              <i class="pi pi-info-circle text-purple-600"></i>
                              <span>{{ getContentTypeLabel(warning.violation.content_type) }}</span>
                            </span>
                          </div>
                        </div>
                        
                        <!-- Mark as Read Button -->
                        <Transition name="scale">
                          <Button
                            v-if="!warning.is_read"
                            icon="pi pi-check"
                            text
                            rounded
                            size="small"
                            @click="markAsRead(warning.id)"
                            v-tooltip.top="'تمت القراءة'"
                            class="flex-shrink-0 text-green-600 hover:bg-green-50 hover:scale-110 transition-transform"
                          />
                        </Transition>
                      </div>
                    </div>
                  </div>
                </div>
              </TransitionGroup>
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

interface UserWarning {
  id: number
  user_id: number
  warned_by: number
  reason: string
  violation_id?: number | null
  type: 'manual' | 'violation'
  is_read: boolean
  read_at?: string | null
  created_at: string
  warned_by?: {
    id: number
    username: string
    name?: string
  }
  violation?: {
    id: number
    content_type: string
  }
}

const { $api } = useNuxtApp()
const toast = useToast()

const warnings = ref<UserWarning[]>([])
const loading = ref(false)
const showWarnings = ref(false)
const warningsLoaded = ref(false)

const unreadCount = computed(() => {
  return warnings.value.filter(w => !w.is_read).length
})

const warningLevel = computed(() => {
  return warnings.value.length
})

const getContentTypeLabel = (type: string): string => {
  const labels: Record<string, string> = {
    chats: 'المحادثات',
    names: 'الأسماء',
    bios: 'السير الذاتية',
    walls: 'الجدران',
    statuses: 'الحالات',
  }
  return labels[type] || type
}

const formatDate = (dateString: string): string => {
  if (!dateString) return '-'
  const date = new Date(dateString)
  return date.toLocaleDateString('ar-EG', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const fetchWarnings = async () => {
  // Only fetch if not already loaded
  if (warningsLoaded.value) {
    return
  }
  
  // Load warnings for the first time
  loading.value = true
  try {
    warnings.value = await $api('/warnings/my-warnings')
    warningsLoaded.value = true
    // Automatically show warnings after loading
    showWarnings.value = true
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.message || 'فشل تحميل التحذيرات',
      life: 3000,
    })
    showWarnings.value = false
  } finally {
    loading.value = false
  }
}

const handleVaultClick = () => {
  if (!warningsLoaded.value && !loading.value) {
    // First time click - load warnings
    fetchWarnings()
  } else if (warningsLoaded.value) {
    // Already loaded - just toggle visibility
    showWarnings.value = !showWarnings.value
  }
}

const markAsRead = async (warningId: number) => {
  try {
    await $api(`/warnings/${warningId}/read`, {
      method: 'PUT',
    })
    
    // Update local state
    const warning = warnings.value.find(w => w.id === warningId)
    if (warning) {
      warning.is_read = true
      warning.read_at = new Date().toISOString()
    }
    
    toast.add({
      severity: 'success',
      summary: 'نجح',
      detail: 'تم تحديد التحذير كمقروء',
      life: 2000,
    })
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.message || 'فشل تحديث حالة التحذير',
      life: 3000,
    })
  }
}

// Method to force show warnings (useful when opening in modal)
const forceShowWarnings = async () => {
  if (!warningsLoaded.value && !loading.value) {
    await fetchWarnings()
  } else if (warningsLoaded.value && !showWarnings.value) {
    showWarnings.value = true
  }
}

// Expose methods and properties so parent can access
defineExpose({
  fetchWarnings,
  handleVaultClick,
  forceShowWarnings,
  unreadCount,
  warningsLoaded,
  showWarnings,
})
</script>

<style scoped>
/* Fade transitions */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

/* Icon flip animation */
.icon-flip-enter-active {
  transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}

.icon-flip-leave-active {
  transition: all 0.3s ease-in;
}

.icon-flip-enter-from {
  opacity: 0;
  transform: rotateY(-90deg) scale(0.5);
}

.icon-flip-leave-to {
  opacity: 0;
  transform: rotateY(90deg) scale(0.5);
}

/* Rotate animation for chevron */
.rotate-enter-active,
.rotate-leave-active {
  transition: transform 0.3s ease;
}

.rotate-enter-from {
  transform: rotate(180deg);
}

.rotate-leave-to {
  transform: rotate(-180deg);
}

/* Vault opening animation */
.vault-open-enter-active {
  animation: vaultOpen 0.5s ease-out;
}

.vault-open-leave-active {
  animation: vaultClose 0.3s ease-in;
}

@keyframes vaultOpen {
  0% {
    opacity: 0;
    transform: scale(0.9) translateY(-10px);
  }
  50% {
    transform: scale(1.02) translateY(0);
  }
  100% {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

@keyframes vaultClose {
  0% {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
  100% {
    opacity: 0;
    transform: scale(0.9) translateY(-10px);
  }
}

/* Vault reveal animation */
.vault-reveal-enter-active {
  animation: reveal 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}

.vault-reveal-leave-active {
  animation: hide 0.3s ease-in;
}

@keyframes reveal {
  0% {
    opacity: 0;
    transform: translateY(-20px) scale(0.95);
    filter: blur(4px);
  }
  50% {
    transform: translateY(5px) scale(1.01);
  }
  100% {
    opacity: 1;
    transform: translateY(0) scale(1);
    filter: blur(0);
  }
}

@keyframes hide {
  0% {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
  100% {
    opacity: 0;
    transform: translateY(-20px) scale(0.95);
  }
}

/* Warning item animation */
.warning-item-enter-active {
  animation: slideInLeft 0.5s ease-out;
}

.warning-item-leave-active {
  animation: slideOutRight 0.3s ease-in;
}

.warning-item-move {
  transition: transform 0.3s ease;
}

@keyframes slideInLeft {
  0% {
    opacity: 0;
    transform: translateX(-30px);
  }
  100% {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes slideOutRight {
  0% {
    opacity: 1;
    transform: translateX(0);
  }
  100% {
    opacity: 0;
    transform: translateX(30px);
  }
}

/* Scale animation */
.scale-enter-active {
  transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}

.scale-leave-active {
  transition: all 0.2s ease-in;
}

.scale-enter-from {
  opacity: 0;
  transform: scale(0);
}

.scale-leave-to {
  opacity: 0;
  transform: scale(0);
}

/* Shimmer animation for progress bar */
@keyframes shimmer {
  0% {
    transform: translateX(-100%);
  }
  100% {
    transform: translateX(100%);
  }
}

.animate-shimmer {
  animation: shimmer 2s infinite;
}
</style>