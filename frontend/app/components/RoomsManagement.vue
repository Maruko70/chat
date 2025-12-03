<template>
  <div class="space-y-6">
    <!-- Header with Add Button -->
    <div class="flex items-center justify-between">
      <h2 class="text-2xl font-bold text-gray-900">إدارة الغرف</h2>
      <Button
        icon="pi pi-plus"
        label="إضافة غرفة جديدة"
        @click="openCreateDialog"
        severity="success"
      />
    </div>

    <!-- Search and Filter -->
    <div class="flex gap-4">
      <InputText
        v-model="searchQuery"
        placeholder="البحث..."
        class="flex-1"
        @input="fetchRooms"
      />
      <Select
        v-model="filterPublic"
        :options="filterOptions"
        optionLabel="label"
        optionValue="value"
        placeholder="النوع"
        class="w-48"
        @change="fetchRooms"
      />
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <i class="pi pi-spin pi-spinner text-4xl text-gray-400"></i>
      <p class="mt-4 text-gray-500">جاري التحميل...</p>
    </div>

    <!-- Rooms Table -->
    <DataTable
      v-else
      :value="rooms"
      :paginator="true"
      :rows="10"
      :rowsPerPageOptions="[5, 10, 20, 50]"
      paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
      currentPageReportTemplate="{first} إلى {last} من {totalRecords}"
      class="p-datatable-sm"
      stripedRows
    >
      <Column field="name" header="الاسم" sortable>
        <template #body="{ data }">
          <div class="flex items-center gap-3">
            <div class="relative flex-shrink-0">
              <div class="w-12 h-12 overflow-hidden border-2 rounded"
                   :style="{ borderColor: getRoomBorderColor(data) }">
                <img
                  v-if="getRoomImageUrl(data)"
                  :src="getRoomImageUrl(data)"
                  :alt="data.name"
                  class="w-full h-full object-cover"
                  @error="handleImageError"
                />
                <div
                  v-else
                  class="w-full h-full flex items-center justify-center"
                  :style="{ backgroundColor: getRoomBackgroundColor(data) }"
                >
                  <i class="pi pi-comments text-lg" :style="{ color: getRoomNameColor(data) }"></i>
                </div>
              </div>
              <!-- Hashtag Badge -->
              <div
                v-if="data.room_hashtag"
                class="absolute -top-1 -right-1 bg-black/70 text-white text-xs font-bold rounded-full w-5 h-5 flex items-center justify-center"
              >
                #{{ data.room_hashtag }}
              </div>
            </div>
            <span class="font-medium">{{ data.name }}</span>
          </div>
        </template>
      </Column>
      <Column field="description" header="الوصف">
        <template #body="{ data }">
          <span class="text-sm text-gray-600">{{ data.description || '-' }}</span>
        </template>
      </Column>
      <Column field="users" header="عدد المستخدمين" sortable>
        <template #body="{ data }">
          <Badge :value="(data.users || []).length" severity="secondary" />
        </template>
      </Column>
      <Column field="max_count" header="الحد الأقصى" sortable>
        <template #body="{ data }">
          <Tag :value="data.max_count || '∞'" severity="info" />
        </template>
      </Column>
      <Column field="is_public" header="النوع" sortable>
        <template #body="{ data }">
          <Tag
            :value="data.is_public ? 'عامة' : 'خاصة'"
            :severity="data.is_public ? 'success' : 'warning'"
          />
        </template>
      </Column>
      <Column field="is_staff_only" header="للموظفين فقط" sortable>
        <template #body="{ data }">
          <Tag
            :value="data.is_staff_only ? 'نعم' : 'لا'"
            :severity="data.is_staff_only ? 'info' : 'secondary'"
          />
        </template>
      </Column>
      <Column header="الإجراءات" :exportable="false">
        <template #body="{ data }">
          <div class="flex gap-2">
            <Button
              icon="pi pi-pencil"
              text
              rounded
              severity="info"
              @click="openEditDialog(data)"
              v-tooltip.top="'تعديل'"
            />
            <Button
              icon="pi pi-eye"
              text
              rounded
              severity="secondary"
              @click="viewRoomDetails(data)"
              v-tooltip.top="'عرض التفاصيل'"
            />
            <Button
              icon="pi pi-trash"
              text
              rounded
              severity="danger"
              @click="confirmDelete(data)"
              v-tooltip.top="'حذف'"
            />
          </div>
        </template>
      </Column>
    </DataTable>

    <!-- Create/Edit Dialog -->
    <Dialog
      v-model:visible="dialogVisible"
      :header="editingRoom ? 'تعديل الغرفة' : 'إضافة غرفة جديدة'"
      :modal="true"
      :style="{ width: '800px', maxHeight: '90vh' }"
      :draggable="false"
      class="overflow-y-auto"
    >
      <form @submit.prevent="saveRoom" class="space-y-4">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium mb-2">اسم الغرفة *</label>
            <InputText
              v-model="formData.name"
              class="w-full"
              :class="{ 'p-invalid': errors.name }"
              required
            />
            <small v-if="errors.name" class="p-error">{{ errors.name }}</small>
          </div>

          <div>
            <label class="block text-sm font-medium mb-2">الوصف</label>
            <Textarea
              v-model="formData.description"
              class="w-full"
              rows="3"
              :autoResize="true"
            />
          </div>

          <div>
            <label class="block text-sm font-medium mb-2">رسالة الترحيب</label>
            <Textarea
              v-model="formData.welcome_message"
              class="w-full"
              rows="2"
              :autoResize="true"
            />
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-2">الحد الأقصى للمستخدمين</label>
              <InputNumber
                v-model="formData.max_count"
                class="w-full"
                :min="2"
                :max="40"
              />
            </div>

            <div>
              <label class="block text-sm font-medium mb-2">عدد الإعجابات المطلوبة</label>
              <InputNumber
                v-model="formData.required_likes"
                class="w-full"
                :min="0"
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-2">رمز الهاشتاغ (1-100)</label>
              <InputNumber
                v-model="formData.room_hashtag"
                class="w-full"
                :min="1"
                :max="100"
              />
              <small class="text-gray-500 text-xs mt-1">اتركه فارغاً للاختيار التلقائي</small>
            </div>

            <div>
              <label class="block text-sm font-medium mb-2">كلمة المرور (اختياري)</label>
              <InputText
                v-model="formData.password"
                type="password"
                class="w-full"
                placeholder="اتركه فارغاً للغرف العامة"
              />
            </div>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-2">صورة الغرفة</label>
              <FileUpload
                mode="basic"
                accept="image/*"
                :maxFileSize="2048000"
                :auto="false"
                chooseLabel="اختر صورة"
                @select="onImageSelect"
              />
              <div v-if="imagePreview" class="mt-2">
                <img
                  :src="imagePreview"
                  alt="Room image preview"
                  class="w-32 h-32 object-cover border rounded"
                />
              </div>
            </div>
          </div>

          <div class="space-y-2">
            <div class="flex items-center gap-2">
              <Checkbox
                v-model="formData.is_public"
                inputId="is_public"
                :binary="true"
              />
              <label for="is_public" class="cursor-pointer">غرفة عامة</label>
            </div>

            <div class="flex items-center gap-2">
              <Checkbox
                v-model="formData.is_staff_only"
                inputId="is_staff_only"
                :binary="true"
              />
              <label for="is_staff_only" class="cursor-pointer">للموظفين فقط</label>
            </div>

            <div class="flex items-center gap-2">
              <Checkbox
                v-model="formData.enable_mic"
                inputId="enable_mic"
                :binary="true"
              />
              <label for="enable_mic" class="cursor-pointer">تفعيل الميكروفون</label>
            </div>

            <div class="flex items-center gap-2">
              <Checkbox
                v-model="formData.disable_incognito"
                inputId="disable_incognito"
                :binary="true"
              />
              <label for="disable_incognito" class="cursor-pointer">تعطيل الوضع الخفي</label>
            </div>
          </div>
        </div>

        <div class="flex justify-end gap-2 mt-6">
          <Button
            label="إلغاء"
            severity="secondary"
            @click="closeDialog"
            type="button"
          />
          <Button
            type="submit"
            :label="editingRoom ? 'تحديث' : 'إنشاء'"
            :loading="saving"
          />
        </div>
      </form>
    </Dialog>

    <!-- Room Details Dialog -->
    <Dialog
      v-model:visible="detailsDialogVisible"
      header="تفاصيل الغرفة"
      :modal="true"
      :style="{ width: '700px' }"
      :draggable="false"
    >
      <div v-if="selectedRoom" class="space-y-4">
        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-sm font-medium text-gray-600">الاسم:</label>
            <p class="text-lg font-semibold">{{ selectedRoom.name }}</p>
          </div>
          <div>
            <label class="text-sm font-medium text-gray-600">الرمز:</label>
            <p class="text-lg">{{ selectedRoom.slug }}</p>
          </div>
        </div>

        <div v-if="selectedRoom.description">
          <label class="text-sm font-medium text-gray-600">الوصف:</label>
          <p class="text-gray-800">{{ selectedRoom.description }}</p>
        </div>

        <div v-if="selectedRoom.welcome_message">
          <label class="text-sm font-medium text-gray-600">رسالة الترحيب:</label>
          <p class="text-gray-800">{{ selectedRoom.welcome_message }}</p>
        </div>

        <div class="grid grid-cols-3 gap-4">
          <div>
            <label class="text-sm font-medium text-gray-600">عدد المستخدمين:</label>
            <Badge :value="(selectedRoom.users || []).length" severity="info" />
          </div>
          <div>
            <label class="text-sm font-medium text-gray-600">الحد الأقصى:</label>
            <Tag :value="selectedRoom.max_count || '∞'" severity="secondary" />
          </div>
          <div>
            <label class="text-sm font-medium text-gray-600">رمز الهاشتاغ:</label>
            <Tag v-if="selectedRoom.room_hashtag" :value="`#${selectedRoom.room_hashtag}`" severity="info" />
            <span v-else class="text-gray-500">-</span>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-sm font-medium text-gray-600">النوع:</label>
            <Tag
              :value="selectedRoom.is_public ? 'عامة' : 'خاصة'"
              :severity="selectedRoom.is_public ? 'success' : 'warning'"
            />
          </div>
          <div>
            <label class="text-sm font-medium text-gray-600">للموظفين فقط:</label>
            <Tag
              :value="selectedRoom.is_staff_only ? 'نعم' : 'لا'"
              :severity="selectedRoom.is_staff_only ? 'info' : 'secondary'"
            />
          </div>
        </div>

        <div v-if="getRoomImageUrl(selectedRoom)" class="mt-4">
          <label class="text-sm font-medium text-gray-600">صورة الغرفة:</label>
          <div class="relative mt-2">
            <img
              :src="getRoomImageUrl(selectedRoom)"
              :alt="selectedRoom.name"
              class="w-full max-h-64 object-cover rounded border-2"
              :style="{ borderColor: getRoomBorderColor(selectedRoom) }"
              @error="handleImageError"
            />
            <div
              v-if="selectedRoom.room_hashtag"
              class="absolute top-2 right-2 bg-black/70 text-white text-sm font-bold rounded-full w-8 h-8 flex items-center justify-center"
            >
              #{{ selectedRoom.room_hashtag }}
            </div>
          </div>
        </div>
        <div v-else class="mt-4">
          <label class="text-sm font-medium text-gray-600">صورة الغرفة:</label>
          <div
            class="w-full h-48 flex items-center justify-center rounded border-2 mt-2"
            :style="{
              backgroundColor: getRoomBackgroundColor(selectedRoom),
              borderColor: getRoomBorderColor(selectedRoom),
            }"
          >
            <i class="pi pi-comments text-4xl" :style="{ color: getRoomNameColor(selectedRoom) }"></i>
          </div>
        </div>

        <div v-if="selectedRoom.users && selectedRoom.users.length > 0" class="mt-4">
          <label class="text-sm font-medium text-gray-600 mb-2 block">المستخدمين:</label>
          <DataTable
            :value="selectedRoom.users"
            :paginator="true"
            :rows="5"
            class="p-datatable-sm"
          >
            <Column field="name" header="الاسم">
              <template #body="{ data }">
                <div class="flex items-center gap-2">
                  <Avatar
                    :image="data.avatar_url"
                    shape="circle"
                    size="small"
                  />
                  <span>{{ data.name || data.username }}</span>
                </div>
              </template>
            </Column>
            <Column field="username" header="اسم المستخدم" />
            <Column header="الدور">
              <template #body="{ data }">
                <Tag
                  :value="data.pivot?.role === 'admin' ? 'مدير' : 'عضو'"
                  :severity="data.pivot?.role === 'admin' ? 'warning' : 'secondary'"
                />
              </template>
            </Column>
          </DataTable>
        </div>
      </div>
    </Dialog>

    <!-- Delete Confirmation -->
    <ConfirmDialog />
  </div>
</template>

<script setup lang="ts">
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import type { Room } from '~~/types'

const { $api } = useNuxtApp()
const confirm = useConfirm()
const toast = useToast()

// State
const rooms = ref<Room[]>([])
const loading = ref(false)
const saving = ref(false)
const searchQuery = ref('')
const filterPublic = ref<boolean | null>(null)
const dialogVisible = ref(false)
const detailsDialogVisible = ref(false)
const editingRoom = ref<Room | null>(null)
const selectedRoom = ref<Room | null>(null)
const imagePreview = ref<string | null>(null)
const imageFile = ref<File | null>(null)

const filterOptions = [
  { label: 'الكل', value: null },
  { label: 'عامة', value: true },
  { label: 'خاصة', value: false },
]

const formData = ref({
  name: '',
  description: '',
  welcome_message: '',
  required_likes: 0,
  room_hashtag: null as number | null,
  max_count: 20,
  password: '',
  is_public: true,
  is_staff_only: false,
  enable_mic: false,
  disable_incognito: false,
})

const errors = ref<Record<string, string>>({})

// Helper functions for room images and colors
const getRoomImageUrl = (room: Room): string => {
  const imagePath = room.room_image_url || room.room_image || room.room_cover
  if (!imagePath) return ''
  
  // If it's already a full URL, return as is
  if (imagePath.startsWith('http://') || imagePath.startsWith('https://')) {
    return imagePath
  }
  
  // If it starts with /, it's a relative path from root
  if (imagePath.startsWith('/')) {
    const config = useRuntimeConfig()
    const apiBaseUrl = config.public.apiBaseUrl || ''
    return `${apiBaseUrl}${imagePath}`
  }
  
  // Otherwise, assume it's a storage path and prepend storage URL
  const config = useRuntimeConfig()
  const apiBaseUrl = config.public.apiBaseUrl || ''
  return `${apiBaseUrl}/storage/${imagePath}`
}

const handleImageError = (event: Event) => {
  const img = event.target as HTMLImageElement
  // Hide the image if it fails to load
  img.style.display = 'none'
}

const getRoomBorderColor = (room: Room): string => {
  if (room.settings?.roomBorderColor) {
    return room.settings.roomBorderColor
  }
  return '#e5e7eb' // default gray border
}

const getRoomBackgroundColor = (room: Room): string => {
  if (room.settings?.backgroundColor) {
    return room.settings.backgroundColor
  }
  return '#f3f4f6' // default gray background
}

const getRoomNameColor = (room: Room): string => {
  if (room.settings?.roomNameColor) {
    return room.settings.roomNameColor
  }
  return '#6b7280' // default gray text
}

// Methods
const fetchRooms = async () => {
  loading.value = true
  try {
    // Fetch all rooms - the API will filter based on user permissions
    const allRooms = await $api('/chat')
    
    // Filter by search query
    let filteredRooms = allRooms
    if (searchQuery.value) {
      const query = searchQuery.value.toLowerCase()
      filteredRooms = allRooms.filter((room: Room) =>
        room.name.toLowerCase().includes(query) ||
        (room.description && room.description.toLowerCase().includes(query))
      )
    }
    
    // Filter by public/private
    if (filterPublic.value !== null) {
      filteredRooms = filteredRooms.filter((room: Room) => room.is_public === filterPublic.value)
    }
    
    rooms.value = filteredRooms
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.message || 'فشل تحميل الغرف',
      life: 3000,
    })
  } finally {
    loading.value = false
  }
}

const openCreateDialog = () => {
  editingRoom.value = null
  formData.value = {
    name: '',
    description: '',
    welcome_message: '',
    required_likes: 0,
    room_hashtag: null,
    max_count: 20,
    password: '',
    is_public: true,
    is_staff_only: false,
    enable_mic: false,
    disable_incognito: false,
  }
  imagePreview.value = null
  imageFile.value = null
  errors.value = {}
  dialogVisible.value = true
}

const openEditDialog = (room: Room) => {
  editingRoom.value = room
  formData.value = {
    name: room.name,
    description: room.description || '',
    welcome_message: room.welcome_message || '',
    required_likes: room.required_likes || 0,
    room_hashtag: room.room_hashtag || null,
    max_count: room.max_count || 20,
    password: '', // Don't show password
    is_public: room.is_public,
    is_staff_only: room.is_staff_only || false,
    enable_mic: room.enable_mic || false,
    disable_incognito: room.disable_incognito || false,
  }
  imagePreview.value = room.room_image_url || null
  imageFile.value = null
  errors.value = {}
  dialogVisible.value = true
}

const closeDialog = () => {
  dialogVisible.value = false
  editingRoom.value = null
  formData.value = {
    name: '',
    description: '',
    welcome_message: '',
    required_likes: 0,
    room_hashtag: null,
    max_count: 20,
    password: '',
    is_public: true,
    is_staff_only: false,
    enable_mic: false,
    disable_incognito: false,
  }
  imagePreview.value = null
  imageFile.value = null
  errors.value = {}
}

const onImageSelect = (event: any) => {
  const file = event.files?.[0]
  if (!file) return

  // Validate file type
  const validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp']
  if (!validTypes.includes(file.type)) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: 'نوع الملف غير مدعوم. يرجى اختيار صورة.',
      life: 3000,
    })
    return
  }

  // Validate file size (2MB)
  if (file.size > 2048000) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: 'حجم الملف كبير جداً. الحد الأقصى 2MB.',
      life: 3000,
    })
    return
  }

  imageFile.value = file

  // Create preview
  const reader = new FileReader()
  reader.onload = (e) => {
    imagePreview.value = e.target?.result as string
  }
  reader.readAsDataURL(file)
}

const saveRoom = async () => {
  errors.value = {}
  
  if (!formData.value.name.trim()) {
    errors.value.name = 'اسم الغرفة مطلوب'
    return
  }

  saving.value = true
  try {
    const payload: any = {
      name: formData.value.name.trim(),
      description: formData.value.description || null,
      welcome_message: formData.value.welcome_message || null,
      required_likes: formData.value.required_likes || 0,
      room_hashtag: formData.value.room_hashtag || null,
      max_count: formData.value.max_count || 20,
      is_public: formData.value.is_public,
      is_staff_only: formData.value.is_staff_only,
      enable_mic: formData.value.enable_mic,
      disable_incognito: formData.value.disable_incognito,
    }

    // Only include password if it's provided
    if (formData.value.password.trim()) {
      payload.password = formData.value.password.trim()
    }

    let room: Room
    if (editingRoom.value) {
      room = await $api(`/chat/${editingRoom.value.id}`, {
        method: 'PUT',
        body: payload,
      })
      toast.add({
        severity: 'success',
        summary: 'نجح',
        detail: 'تم تحديث الغرفة بنجاح',
        life: 3000,
      })
    } else {
      room = await $api('/chat', {
        method: 'POST',
        body: payload,
      })
      toast.add({
        severity: 'success',
        summary: 'نجح',
        detail: 'تم إنشاء الغرفة بنجاح',
        life: 3000,
      })
    }

    // Upload image if a file was selected
    if (imageFile.value && room && room.id) {
      try {
        const formDataObj = new FormData()
        formDataObj.append('image', imageFile.value)
        
        const authStore = useAuthStore()
        const config = useRuntimeConfig()
        const baseUrl = config.public.apiBaseUrl
        const headers: HeadersInit = {
          'Accept': 'application/json',
        }
        
        if (authStore.token) {
          headers['Authorization'] = `Bearer ${authStore.token}`
        }
        
        await fetch(`${baseUrl}/chat/${room.id}/image`, {
          method: 'POST',
          headers,
          body: formDataObj,
        })
      } catch (error: any) {
        console.error('Error uploading room image:', error)
        // Don't fail the room save if image upload fails
        toast.add({
          severity: 'warn',
          summary: 'تحذير',
          detail: 'تم حفظ الغرفة لكن فشل رفع الصورة',
          life: 3000,
        })
      }
    }

    closeDialog()
    await fetchRooms()
  } catch (error: any) {
    const errorData = error.data || {}
    if (errorData.errors) {
      errors.value = errorData.errors
    } else {
      toast.add({
        severity: 'error',
        summary: 'خطأ',
        detail: error.message || 'فشل حفظ الغرفة',
        life: 3000,
      })
    }
  } finally {
    saving.value = false
  }
}

const viewRoomDetails = async (room: Room) => {
  try {
    // Fetch full room details with users
    const fullRoom = await $api(`/chat/${room.id}`)
    selectedRoom.value = fullRoom
    detailsDialogVisible.value = true
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.message || 'فشل تحميل تفاصيل الغرفة',
      life: 3000,
    })
  }
}

const confirmDelete = (room: Room) => {
  confirm.require({
    message: `هل أنت متأكد من حذف الغرفة "${room.name}"؟ سيتم إزالة جميع المستخدمين من هذه الغرفة.`,
    header: 'تأكيد الحذف',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: 'نعم',
    rejectLabel: 'لا',
    accept: async () => {
      try {
        await $api(`/chat/${room.id}`, {
          method: 'DELETE',
        })
        toast.add({
          severity: 'success',
          summary: 'نجح',
          detail: 'تم حذف الغرفة بنجاح',
          life: 3000,
        })
        await fetchRooms()
      } catch (error: any) {
        toast.add({
          severity: 'error',
          summary: 'خطأ',
          detail: error.message || 'فشل حذف الغرفة',
          life: 3000,
        })
      }
    },
  })
}

// Lifecycle
onMounted(() => {
  fetchRooms()
})
</script>

