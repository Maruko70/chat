<template>
  <div class="min-h-screen bg-gray-50">
    <div class="max-w-4xl mx-auto p-4">
      <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-900">غرف الدردشة</h1>
        <div class="flex gap-2">
          <Button
            label="تحديث"
            icon="pi pi-refresh"
            @click="fetchRooms"
            :loading="chatStore.loading"
          />
          <Button
            label="تسجيل الخروج"
            severity="danger"
            icon="pi pi-sign-out"
            @click="handleLogout"
          />
        </div>
      </div>
      
      <!-- Search and Create Row -->
      <div class="mb-6 flex gap-3 items-center">
        <div class="flex-1">
          <span class="p-input-icon-left w-full">
            <i class="pi pi-search" />
            <InputText 
              v-model="searchQuery" 
              placeholder="ابحث عن غرفة بالاسم أو الرقم..."
              class="w-full"
            />
          </span>
        </div>
        <Button
          label="إنشاء غرفة جديدة"
          icon="pi pi-plus"
          @click="showCreateModal = true"
          class="whitespace-nowrap"
        />
      </div>
      
      <div v-if="chatStore.loading && filteredRooms.length === 0" class="text-center py-8">
        <p class="text-gray-600">جاري تحميل الغرف...</p>
      </div>
      
      <div v-else-if="filteredRooms.length === 0" class="text-center py-8">
        <p class="text-gray-600">لا توجد غرف متاحة</p>
      </div>
      
      <div v-else class="grid gap-4">
        <Card
          v-for="room in filteredRooms"
          :key="room.id"
          class="cursor-pointer hover:shadow-lg transition-all duration-200 border border-gray-200 hover:border-primary"
          @click="navigateToRoom(room.id)"
        >
          <template #title>
            <div class="flex items-center gap-2">
              <i class="pi pi-comments text-primary"></i>
              <span>{{ room.name }}</span>
            </div>
          </template>
          <template #content>
            <div class="space-y-2">
              <p v-if="room.description" class="text-sm text-gray-600 line-clamp-2">
                {{ room.description }}
              </p>
              <div class="flex justify-between items-center pt-2 border-t border-gray-100">
                <div class="flex items-center gap-3">
                  <span class="flex items-center gap-1 text-sm">
                    <i :class="room.is_public ? 'pi pi-globe text-blue-500' : 'pi pi-lock text-orange-500'"></i>
                    <span class="text-gray-600">{{ room.is_public ? 'عامة' : 'خاصة' }}</span>
                  </span>
                  <span class="flex items-center gap-1 text-sm">
                    <i class="pi pi-users text-gray-400"></i>
                    <span class="text-gray-600">{{ room.users?.length || 0 }} عضو</span>
                  </span>
                </div>
                <span class="text-xs text-gray-400">#{{ room.id }}</span>
              </div>
            </div>
          </template>
        </Card>
      </div>
    </div>

    <!-- Create Room Modal -->
    <Dialog 
      v-model:visible="showCreateModal" 
      modal 
      header="إنشاء غرفة جديدة" 
      :style="{ width: '500px' }"
      :draggable="false"
    >
      <form @submit.prevent="createRoom" class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-2">اسم الغرفة *</label>
          <InputText 
            v-model="newRoom.name" 
            placeholder="أدخل اسم الغرفة"
            class="w-full"
            required
          />
        </div>
        
        <div>
          <label class="block text-sm font-medium mb-2">الوصف</label>
          <Textarea 
            v-model="newRoom.description" 
            placeholder="وصف الغرفة (اختياري)"
            class="w-full"
            rows="3"
          />
        </div>
        
        <div class="flex items-center gap-3">
          <Checkbox 
            v-model="newRoom.is_public" 
            inputId="is_public"
            :binary="true"
          />
          <label for="is_public" class="text-sm">غرفة عامة</label>
        </div>
        
        <div>
          <label class="block text-sm font-medium mb-2">الحد الأقصى للأعضاء</label>
          <InputNumber 
            v-model="newRoom.max_count" 
            :min="1"
            :max="1000"
            class="w-full"
            placeholder="200"
          />
        </div>
        
        <div>
          <label class="block text-sm font-medium mb-2">كلمة المرور (اختياري)</label>
          <Password 
            v-model="newRoom.password" 
            placeholder="كلمة مرور للغرفة"
            class="w-full"
            :feedback="false"
            toggleMask
          />
        </div>
        
        <div class="flex gap-2 justify-end pt-4">
          <Button 
            label="إلغاء" 
            severity="secondary" 
            @click="showCreateModal = false"
            :disabled="creating"
          />
          <Button 
            label="إنشاء" 
            icon="pi pi-check" 
            type="submit"
            :loading="creating"
          />
        </div>
      </form>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import type { Room } from '~/types'

definePageMeta({
  middleware: 'auth',
})

const authStore = useAuthStore()
const chatStore = useChatStore()
const router = useRouter()

const searchQuery = ref('')
const showCreateModal = ref(false)
const creating = ref(false)

const newRoom = ref({
  name: '',
  description: '',
  is_public: true,
  max_count: 200,
  password: '',
})

const displayRooms = computed(() => chatStore.displayRooms)

const filteredRooms = computed(() => {
  if (!searchQuery.value.trim()) {
    return displayRooms.value
  }
  
  const query = searchQuery.value.toLowerCase().trim()
  
  return displayRooms.value.filter((room: Room) => {
    // Search by name
    const nameMatch = room.name?.toLowerCase().includes(query)
    // Search by id
    const idMatch = String(room.id).includes(query)
    
    return nameMatch || idMatch
  })
})

const fetchRooms = async () => {
  try {
    await chatStore.fetchRooms()
  } catch (error) {
    console.error('Error fetching rooms:', error)
  }
}

const navigateToRoom = (roomId: number) => {
  router.push(`/chat/${roomId}`)
}

const handleLogout = async () => {
  await authStore.logout()
  router.push('/')
}

const createRoom = async () => {
  if (!newRoom.value.name.trim()) {
    return
  }
  
  creating.value = true
  try {
    const { $api } = useNuxtApp()
    const room = await $api('/chat', {
      method: 'POST',
      body: {
        name: newRoom.value.name.trim(),
        description: newRoom.value.description || null,
        is_public: newRoom.value.is_public,
        max_count: newRoom.value.max_count || 200,
        password: newRoom.value.password || null,
      },
    })
    
    // Refresh rooms list
    await fetchRooms()
    
    // Reset form and close modal
    newRoom.value = {
      name: '',
      description: '',
      is_public: true,
      max_count: 200,
      password: '',
    }
    showCreateModal.value = false
    
    // Navigate to the new room
    if (room && room.id) {
      navigateToRoom(room.id)
    }
  } catch (error: any) {
    console.error('Error creating room:', error)
    // You could show a toast notification here
    alert(error?.message || 'حدث خطأ أثناء إنشاء الغرفة')
  } finally {
    creating.value = false
  }
}

onMounted(() => {
  fetchRooms()
})
</script>

