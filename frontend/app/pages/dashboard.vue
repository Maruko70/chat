<template>
  <div class="min-h-screen bg-gray-50 flex overflow-x-hidden">
    <!-- Sidebar -->
    <aside
      :class="[
        'bg-white border-r shadow-lg transition-transform duration-300 z-40',
        'fixed lg:static h-screen w-64 flex flex-col left-0',
        sidebarVisible ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
        !sidebarVisible ? 'pointer-events-none lg:pointer-events-auto' : ''
      ]"
    >
      <!-- Sidebar Header -->
      <div class="flex items-center justify-between px-4 py-4 border-b">
        <h2 class="text-lg font-bold">لوحة التحكم</h2>
        <Button
          icon="pi pi-times"
          text
          rounded
          @click="sidebarVisible = false"
          class="lg:hidden"
        />
      </div>
      
      <!-- Menu Items -->
      <div class="flex-1 overflow-y-auto py-2">
        <div
          v-for="item in menuItems"
          :key="item.id"
          @click="selectMenuItem(item.id)"
          :class="[
            'flex items-center gap-3 px-4 py-3 cursor-pointer transition-colors',
            selectedMenuItem === item.id
              ? 'bg-primary text-white'
              : 'hover:bg-gray-100'
          ]"
        >
          <div class="w-10 h-10 bg-black flex items-center justify-center rounded flex-shrink-0">
            <i :class="[item.icon, 'text-white text-base']"></i>
          </div>
          <span class="flex-1 text-sm font-medium">{{ item.label }}</span>
        </div>
      </div>
    </aside>

    <!-- Sidebar Overlay (Mobile) -->
    <div
      v-if="sidebarVisible"
      class="fixed inset-0 bg-black bg-opacity-50 z-30 lg:hidden"
      @click="sidebarVisible = false"
    ></div>
 
    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-w-0">
      <!-- Header -->
      <header class="bg-white border-b shadow-sm sticky top-0 z-20">
        <div class="px-6 py-4 flex items-center justify-between">
          <div class="flex items-center gap-4">
            <Button
              icon="pi pi-bars"
              text
              rounded
              @click="sidebarVisible = true"
              class="lg:hidden"
            />
            <h1 class="text-xl font-bold text-gray-900">
              {{ currentMenuItem?.label || 'لوحة التحكم' }}
            </h1>
          </div>
          <div class="flex items-center gap-2">
            <Button
              icon="pi pi-sign-out"
              label="تسجيل الخروج"
              severity="danger"
              @click="handleLogout"
            />
          </div>
        </div>
      </header>

      <!-- Content Area -->
      <main class="flex-1 p-6 overflow-y-auto">
        <!-- Lazy loaded management components -->
        <!-- Login Logs Management -->
        <component :is="LoginLogsManagement" v-if="selectedMenuItem === 'log'" :key="'log'" />
        
        <!-- Ban Management -->
        <component :is="BanManagement" v-else-if="selectedMenuItem === 'ban'" :key="'ban'" />
        
        <!-- Reports Management -->
        <component :is="ReportsManagement" v-else-if="selectedMenuItem === 'reports'" :key="'reports'" />
        
        <!-- Subscriptions Management -->
        <component :is="SubscriptionsManagement" v-else-if="selectedMenuItem === 'subscriptions'" :key="'subscriptions'" />
        
        <!-- Role Groups Management -->
        <component :is="RoleGroupsManagement" v-else-if="selectedMenuItem === 'permissions'" :key="'permissions'" />
        
        <!-- Symbols Management -->
        <component :is="SymbolsManagement" v-else-if="selectedMenuItem === 'symbols'" :key="'symbols'" />
        
        <!-- Rooms Management -->
        <component :is="RoomsManagement" v-else-if="selectedMenuItem === 'rooms'" :key="'rooms'" />
        
        <!-- Site Management -->
        <component :is="SiteManagement" v-else-if="selectedMenuItem === 'site'" :key="'site'" />
        
        <!-- Users Management -->
        <component :is="UsersManagement" v-else-if="selectedMenuItem === 'members'" :key="'members'" />
        
        <!-- Scheduled Messages Management -->
        <component :is="ScheduledMessagesManagement" v-else-if="selectedMenuItem === 'messages'" :key="'messages'" />
        
        <!-- Settings Management -->
        <component :is="SettingsManagement" v-else-if="selectedMenuItem === 'settings'" :key="'settings'" />
        
        <!-- Membership Designs Management -->
        <component :is="MembershipDesignsManagement" v-else-if="selectedMenuItem === 'membership-designs'" :key="'membership-designs'" />
        
        <!-- Premium Entry Backgrounds Management -->
        <component :is="PremiumEntryBackgroundsManagement" v-else-if="selectedMenuItem === 'premium-entry-backgrounds'" :key="'premium-entry-backgrounds'" />
        
        <!-- Shortcuts Management -->
        <component :is="ShortcutsManagement" v-else-if="selectedMenuItem === 'shortcuts'" :key="'shortcuts'" />
        
        <!-- Filter Management -->
        <component :is="FilterManagement" v-else-if="selectedMenuItem === 'filter'" :key="'filter'" />
        
        <!-- Violations Management -->
        <component :is="ViolationsManagement" v-else-if="selectedMenuItem === 'violations'" :key="'violations'" />
        
        <!-- Default placeholder for other menu items -->
        <Card v-else>
          <template #content>
            <div class="text-center py-12">
              <i :class="[currentMenuItem?.icon || 'pi pi-info-circle', 'text-6xl text-gray-300 mb-4']"></i>
              <h2 class="text-2xl font-semibold text-gray-700 mb-2">
                {{ currentMenuItem?.label || 'القسم' }}
              </h2>
              <p class="text-gray-500">
                لا توجد بيانات متاحة حالياً
              </p>
            </div>
          </template>
        </Card>
      </main>
    </div>
  </div>
</template>

<script setup lang="ts">
import { defineAsyncComponent } from 'vue'

definePageMeta({
  middleware: 'auth',
})

const authStore = useAuthStore()
const router = useRouter()

// Sidebar visible by default on desktop, hidden on mobile
const sidebarVisible = ref(false)
const selectedMenuItem = ref('log')

// Lazy load components using defineAsyncComponent
const LoginLogsManagement = defineAsyncComponent(() => import('~~/app/components/LoginLogsManagement.vue'))
const BanManagement = defineAsyncComponent(() => import('~~/app/components/BanManagement.vue'))
const ReportsManagement = defineAsyncComponent(() => import('~~/app/components/ReportsManagement.vue'))
const SubscriptionsManagement = defineAsyncComponent(() => import('~~/app/components/SubscriptionsManagement.vue'))
const RoleGroupsManagement = defineAsyncComponent(() => import('~~/app/components/RoleGroupsManagement.vue'))
const SymbolsManagement = defineAsyncComponent(() => import('~~/app/components/SymbolsManagement.vue'))
const RoomsManagement = defineAsyncComponent(() => import('~~/app/components/RoomsManagement.vue'))
const SiteManagement = defineAsyncComponent(() => import('~~/app/components/SiteManagement.vue'))
const UsersManagement = defineAsyncComponent(() => import('~~/app/components/UsersManagement.vue'))
const ScheduledMessagesManagement = defineAsyncComponent(() => import('~~/app/components/ScheduledMessagesManagement.vue'))
const SettingsManagement = defineAsyncComponent(() => import('~~/app/components/SettingsManagement.vue'))
const MembershipDesignsManagement = defineAsyncComponent(() => import('~~/app/components/MembershipDesignsManagement.vue'))
const PremiumEntryBackgroundsManagement = defineAsyncComponent(() => import('~~/app/components/PremiumEntryBackgroundsManagement.vue'))
const ShortcutsManagement = defineAsyncComponent(() => import('~~/app/components/ShortcutsManagement.vue'))
const FilterManagement = defineAsyncComponent(() => import('~~/app/components/FilterManagement.vue'))
const ViolationsManagement = defineAsyncComponent(() => import('~~/app/components/ViolationsManagement.vue'))

const menuItems = [
  {
    id: 'log',
    label: 'السجل',
    icon: 'pi pi-history',
  },
  {
    id: 'members',
    label: 'الأعضاء',
    icon: 'pi pi-users',
  },
  {
    id: 'ban',
    label: 'الحظر',
    icon: 'pi pi-ban',
  },
  {
    id: 'permissions',
    label: 'الصلاحيات',
    icon: 'pi pi-key',
  },
  {
    id: 'filter',
    label: 'فلتر',
    icon: 'pi pi-filter',
  },
  {
    id: 'violations',
    label: 'الانتهاكات',
    icon: 'pi pi-shield',
  },
  {
    id: 'reports',
    label: 'التبليغات',
    icon: 'pi pi-flag',
  },
  {
    id: 'rooms',
    label: 'الغرف',
    icon: 'pi pi-home',
  },
  {
    id: 'shortcuts',
    label: 'الإختصارات',
    icon: 'pi pi-link',
  },
  {
    id: 'subscriptions',
    label: 'الإشتراكات',
    icon: 'pi pi-box',
  },
  {
    id: 'messages',
    label: 'الرسائل',
    icon: 'pi pi-comments',
  },
  {
    id: 'symbols',
    label: 'الرموز',
    icon: 'pi pi-image',
  },
  {
    id: 'settings',
    label: 'الإعدادات',
    icon: 'pi pi-cog',
  },
  {
    id: 'site',
    label: 'الموقع',
    icon: 'pi pi-building',
  },
  {
    id: 'addons',
    label: 'الإضافات',
    icon: 'pi pi-plus-circle',
  },
  {
    id: 'bots',
    label: 'Bots',
    icon: 'pi pi-android',
  },
  {
    id: 'membership-designs',
    label: 'تصاميم العضويات',
    icon: 'pi pi-palette',
  },
  {
    id: 'premium-entry-backgrounds',
    label: 'خلفيات الدخول المميز',
    icon: 'pi pi-image',
  },
]

const currentMenuItem = computed(() => {
  return menuItems.find(item => item.id === selectedMenuItem.value)
})

const selectMenuItem = (id: string) => {
  selectedMenuItem.value = id
  // On mobile, close sidebar after selection
  if (import.meta.client && window.innerWidth < 1024) {
    sidebarVisible.value = false
  }
}

const handleLogout = async () => {
  await authStore.logout()
  router.push('/')
}

// Initialize sidebar visibility based on screen size and handle resize
let resizeHandler: (() => void) | null = null

onMounted(() => {
  if (import.meta.client) {
    sidebarVisible.value = window.innerWidth >= 1024
    
    resizeHandler = () => {
      if (window.innerWidth >= 1024) {
        sidebarVisible.value = true
      }
    }
    window.addEventListener('resize', resizeHandler)
  }
})

onUnmounted(() => {
  if (import.meta.client && resizeHandler) {
    window.removeEventListener('resize', resizeHandler)
  }
})
</script>

<style scoped>
/* Custom styles for dashboard */
/* Prevent horizontal overflow */
.min-h-screen {
  overflow-x: hidden;
  width: 100%;
}

/* Ensure sidebar is completely hidden on mobile when closed */
@media (max-width: 1023px) {
  aside {
    left: 0;
    max-width: 100vw;
  }
  
  /* When closed, ensure sidebar is completely off-screen to the left */
  aside.-translate-x-full {
    transform: translateX(-100%) !important;
  }
}
</style>

