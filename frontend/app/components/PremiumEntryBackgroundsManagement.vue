<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h2 class="text-2xl font-bold text-gray-900">إدارة خلفيات الدخول المميز</h2>
      <Button
        icon="pi pi-plus"
        label="إضافة خلفية"
        @click="openAddDialog"
        severity="success"
      />
    </div>

    <!-- Search -->
    <Card>
      <template #content>
        <div class="flex gap-4 items-center">
          <div class="flex-1">
            <label class="block text-sm font-medium mb-2">البحث</label>
            <InputText
              v-model="searchQuery"
              placeholder="ابحث بالاسم أو اسم المستخدم..."
              class="w-full"
              @input="debouncedSearch"
            />
          </div>
        </div>
      </template>
    </Card>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <i class="pi pi-spin pi-spinner text-4xl text-gray-400"></i>
      <p class="mt-4 text-gray-500">جاري التحميل...</p>
    </div>

    <!-- Users Grid -->
    <div v-else-if="users.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <Card v-for="user in users" :key="user.id" class="overflow-hidden">
        <template #header>
          <div class="relative h-48 bg-gray-100">
            <img
              :src="user.premium_entry_background"
              :alt="`${user.name || user.username} Premium Entry Background`"
              class="w-full h-full object-cover"
              @error="handleImageError"
            />
            <Tag
              value="دخول مميز"
              severity="warning"
              class="absolute top-2 right-2"
            />
          </div>
        </template>
        <template #title>
          <div class="flex items-center gap-2">
            <Avatar
              :image="user.avatar_url"
              :label="!user.avatar_url ? (user.name || user.username || '').charAt(0).toUpperCase() : undefined"
              shape="circle"
              class="w-8 h-8"
            />
            <span>{{ user.name || user.username || 'بدون اسم' }}</span>
          </div>
        </template>
        <template #content>
          <div class="space-y-2 text-sm">
            <div v-if="user.username" class="text-gray-600">
              <i class="pi pi-user mr-1"></i>
              {{ user.username }}
            </div>
            <div v-if="user.email" class="text-gray-600">
              <i class="pi pi-envelope mr-1"></i>
              {{ user.email }}
            </div>
            <div class="text-xs text-gray-500">
              <i class="pi pi-calendar mr-1"></i>
              {{ formatDate(user.updated_at) }}
            </div>
          </div>
        </template>
        <template #footer>
          <div class="flex gap-2">
            <Button
              icon="pi pi-eye"
              label="عرض"
              severity="info"
              size="small"
              @click="viewBackground(user)"
              class="flex-1"
            />
            <Button
              icon="pi pi-trash"
              label="حذف"
              severity="danger"
              size="small"
              @click="confirmDelete(user)"
              class="flex-1"
            />
          </div>
        </template>
      </Card>
    </div>

    <!-- Empty State -->
    <Card v-else>
      <template #content>
        <div class="text-center py-12">
          <i class="pi pi-image text-6xl text-gray-300 mb-4"></i>
          <h3 class="text-xl font-semibold text-gray-700 mb-2">لا توجد خلفيات</h3>
          <p class="text-gray-500">لا يوجد مستخدمون لديهم خلفيات دخول مميز حالياً</p>
        </div>
      </template>
    </Card>

    <!-- Pagination -->
    <div v-if="pagination && pagination.total > pagination.per_page" class="flex justify-center">
      <Paginator
        :rows="pagination.per_page"
        :totalRecords="pagination.total"
        :first="(pagination.current_page - 1) * pagination.per_page"
        @page="onPageChange"
        template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink"
      />
    </div>

    <!-- View Dialog -->
    <Dialog
      v-model:visible="viewDialogVisible"
      header="عرض خلفية الدخول المميز"
      :style="{ width: '600px', maxWidth: '90vw' }"
      :modal="true"
      class="p-fluid"
      :draggable="false"
    >
      <div v-if="selectedUser" class="space-y-4">
        <div class="flex items-center gap-3 mb-4">
          <Avatar
            :image="selectedUser.avatar_url"
            :label="!selectedUser.avatar_url ? (selectedUser.name || selectedUser.username || '').charAt(0).toUpperCase() : undefined"
            shape="circle"
            class="w-12 h-12"
          />
          <div>
            <div class="font-semibold">{{ selectedUser.name || selectedUser.username || 'بدون اسم' }}</div>
            <div v-if="selectedUser.username" class="text-sm text-gray-500">@{{ selectedUser.username }}</div>
          </div>
        </div>
        <div class="relative w-full h-64 bg-gray-100 rounded border-2 border-gray-300 overflow-hidden">
          <img
            :src="selectedUser.premium_entry_background"
            :alt="`${selectedUser.name || selectedUser.username} Premium Entry Background`"
            class="w-full h-full object-cover"
            @error="handleImageError"
          />
        </div>
        <div class="text-sm text-gray-600">
          <div><strong>تاريخ التحديث:</strong> {{ formatDate(selectedUser.updated_at) }}</div>
        </div>
      </div>
      <template #footer>
        <Button label="إغلاق" icon="pi pi-times" @click="viewDialogVisible = false" />
      </template>
    </Dialog>

    <!-- Add Dialog -->
    <Dialog
      v-model:visible="addDialogVisible"
      header="إضافة خلفية دخول مميز"
      :style="{ width: '700px', maxWidth: '90vw' }"
      :modal="true"
      class="p-fluid"
      :draggable="false"
    >
      <div class="space-y-4 max-h-[70vh] overflow-y-auto px-2">
        <!-- User Selection -->
        <div>
          <label class="block text-sm font-medium mb-2">اختر المستخدم <span class="text-red-500">*</span></label>
          <div class="flex gap-2 mb-2">
            <InputText
              v-model="userSearchQuery"
              placeholder="ابحث بالاسم أو اسم المستخدم..."
              class="flex-1"
              @input="searchUsersForAssignment"
            />
          </div>
          <div v-if="availableUsers.length > 0" class="max-h-48 overflow-y-auto border rounded p-2">
            <div
              v-for="user in availableUsers"
              :key="user.id"
              class="flex items-center justify-between p-2 border-b last:border-b-0 hover:bg-gray-50 cursor-pointer rounded"
              :class="{ 'bg-primary/10': selectedUserId === user.id }"
              @click="selectUserForAssignment(user)"
            >
              <div class="flex items-center gap-2">
                <Avatar
                  :image="user.avatar_url"
                  :label="!user.avatar_url ? (user.name || user.username || '').charAt(0).toUpperCase() : undefined"
                  shape="circle"
                  size="small"
                />
                <div>
                  <div class="font-medium">{{ user.name || user.username || 'بدون اسم' }}</div>
                  <div v-if="user.username" class="text-xs text-gray-500">@{{ user.username }}</div>
                </div>
              </div>
              <i v-if="selectedUserId === user.id" class="pi pi-check text-primary"></i>
            </div>
          </div>
          <div v-else-if="userSearchQuery && !searchingUsers" class="text-center py-4 text-gray-500 text-sm">
            لا توجد نتائج
          </div>
          <div v-else-if="!userSearchQuery" class="text-center py-4 text-gray-500 text-sm">
            ابحث عن مستخدم لاختياره
          </div>
          <small v-if="errors.user" class="p-error">{{ errors.user }}</small>
        </div>

        <!-- Image Upload -->
        <div>
          <label class="block text-sm font-medium mb-2">صورة الخلفية <span class="text-red-500">*</span></label>
          <div v-if="imagePreview" class="mb-2">
            <img :src="imagePreview" alt="Preview" class="w-full h-48 object-cover rounded border" />
          </div>
          <FileUpload
            mode="basic"
            accept="image/*"
            :maxFileSize="5120000"
            :auto="false"
            chooseLabel="اختر الصورة"
            @select="onImageSelect"
            :class="{ 'p-invalid': errors.image }"
          />
          <small v-if="errors.image" class="p-error">{{ errors.image }}</small>
          <small class="text-gray-500 text-xs block mt-1">
            الصيغ المدعومة: JPEG, PNG, JPG, GIF, WEBP (حد أقصى 5MB)
          </small>
        </div>
      </div>

      <template #footer>
        <Button label="إلغاء" icon="pi pi-times" text @click="closeAddDialog" />
        <Button
          label="حفظ"
          icon="pi pi-check"
          @click="saveAssignment"
          :loading="saving"
        />
      </template>
    </Dialog>

    <!-- Delete Confirmation -->
    <ConfirmDialog />
  </div>
</template>

<script setup lang="ts">
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'

const { $api } = useNuxtApp()
const confirm = useConfirm()
const toast = useToast()

interface User {
  id: number
  name?: string
  username?: string
  email?: string
  avatar_url?: string
  premium_entry: boolean
  premium_entry_background: string
  updated_at: string
}

// State
const loading = ref(false)
const users = ref<User[]>([])
const searchQuery = ref('')
const viewDialogVisible = ref(false)
const selectedUser = ref<User | null>(null)
const pagination = ref<any>(null)

// Add dialog state
const addDialogVisible = ref(false)
const userSearchQuery = ref('')
const availableUsers = ref<any[]>([])
const selectedUserId = ref<number | null>(null)
const selectedUserForAssignment = ref<any | null>(null)
const imageFile = ref<File | null>(null)
const imagePreview = ref<string | null>(null)
const saving = ref(false)
const searchingUsers = ref(false)
const errors = ref<Record<string, string>>({})

let searchTimeout: NodeJS.Timeout | null = null
let userSearchTimeout: NodeJS.Timeout | null = null

// Methods
const fetchUsers = async (page = 1) => {
  loading.value = true
  try {
    const params: any = {
      page,
      per_page: 12,
    }
    
    if (searchQuery.value) {
      params.search = searchQuery.value
    }

    const queryString = new URLSearchParams(params).toString()
    const response = await $api(`/users/premium-entry?${queryString}`)
    
    users.value = response.data || []
    pagination.value = {
      current_page: response.current_page || 1,
      per_page: response.per_page || 12,
      total: response.total || 0,
      last_page: response.last_page || 1,
    }
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.message || 'فشل تحميل البيانات',
      life: 3000,
    })
  } finally {
    loading.value = false
  }
}

const debouncedSearch = () => {
  if (searchTimeout) {
    clearTimeout(searchTimeout)
  }
  searchTimeout = setTimeout(() => {
    fetchUsers(1)
  }, 500)
}

const onPageChange = (event: any) => {
  const page = (event.page || 0) + 1
  fetchUsers(page)
}

const viewBackground = (user: User) => {
  selectedUser.value = user
  viewDialogVisible.value = true
}

const confirmDelete = (user: User) => {
  confirm.require({
    message: `هل أنت متأكد من حذف خلفية الدخول المميز للمستخدم "${user.name || user.username || 'بدون اسم'}"؟`,
    header: 'تأكيد الحذف',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: 'نعم، احذف',
    rejectLabel: 'إلغاء',
    accept: async () => {
      try {
        // Delete the premium entry background for this user
        await $api(`/users/${user.id}`, {
          method: 'PUT',
          body: {
            premium_entry_background: null,
          },
        })

        toast.add({
          severity: 'success',
          summary: 'نجح',
          detail: 'تم حذف الخلفية بنجاح',
          life: 3000,
        })

        await fetchUsers(pagination.value?.current_page || 1)
      } catch (error: any) {
        toast.add({
          severity: 'error',
          summary: 'خطأ',
          detail: error.message || 'فشل حذف الخلفية',
          life: 3000,
        })
      }
    },
  })
}

const handleImageError = (event: Event) => {
  const img = event.target as HTMLImageElement
  img.style.display = 'none'
}

const formatDate = (dateString: string) => {
  return new Date(dateString).toLocaleDateString('ar-EG', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const openAddDialog = () => {
  addDialogVisible.value = true
  userSearchQuery.value = ''
  availableUsers.value = []
  selectedUserId.value = null
  selectedUserForAssignment.value = null
  imageFile.value = null
  imagePreview.value = null
  errors.value = {}
}

const closeAddDialog = () => {
  addDialogVisible.value = false
  userSearchQuery.value = ''
  availableUsers.value = []
  selectedUserId.value = null
  selectedUserForAssignment.value = null
  imageFile.value = null
  imagePreview.value = null
  errors.value = {}
}

const searchUsersForAssignment = async () => {
  if (!userSearchQuery.value || userSearchQuery.value.length < 2) {
    availableUsers.value = []
    return
  }

  if (userSearchTimeout) {
    clearTimeout(userSearchTimeout)
  }

  userSearchTimeout = setTimeout(async () => {
    searchingUsers.value = true
    try {
      const response = await $api(`/users/search?search=${encodeURIComponent(userSearchQuery.value)}&limit=10`)
      // Filter to only show users with premium_entry enabled
      availableUsers.value = response.filter((user: any) => user.premium_entry === true)
    } catch (error: any) {
      console.error('Error searching users:', error)
      availableUsers.value = []
    } finally {
      searchingUsers.value = false
    }
  }, 500)
}

const selectUserForAssignment = (user: any) => {
  selectedUserId.value = user.id
  selectedUserForAssignment.value = user
  errors.value.user = ''
}

const onImageSelect = (event: any) => {
  const file = event.files?.[0]
  if (!file) return

  if (!file.type.startsWith('image/')) {
    errors.value.image = 'الملف يجب أن يكون صورة'
    return
  }

  if (file.size > 5120000) {
    errors.value.image = 'حجم الصورة يجب أن يكون أقل من 5MB'
    return
  }

  errors.value.image = ''
  imageFile.value = file

  // Create preview
  const reader = new FileReader()
  reader.onload = (e) => {
    imagePreview.value = e.target?.result as string
  }
  reader.readAsDataURL(file)
}

const saveAssignment = async () => {
  errors.value = {}

  // Validate
  if (!selectedUserId.value) {
    errors.value.user = 'يجب اختيار مستخدم'
    return
  }

  if (!imageFile.value) {
    errors.value.image = 'يجب اختيار صورة'
    return
  }

  saving.value = true
  try {
    // Upload image for the selected user using admin endpoint
    const formData = new FormData()
    formData.append('image', imageFile.value)

    const authStore = useAuthStore()
    const config = useRuntimeConfig()
    const baseUrl = config.public.apiBaseUrl

    const headers: HeadersInit = {
      'Accept': 'application/json',
    }

    if (authStore.token) {
      headers['Authorization'] = `Bearer ${authStore.token}`
    }

    // Upload image for the selected user via admin endpoint
    const uploadResponse = await fetch(`${baseUrl}/users/${selectedUserId.value}/premium-entry-background`, {
      method: 'POST',
      headers,
      body: formData,
    })

    if (!uploadResponse.ok) {
      const error = await uploadResponse.json().catch(() => ({ message: 'فشل رفع الصورة' }))
      throw new Error(error.message || 'فشل رفع الصورة')
    }

    // Ensure premium_entry is enabled for the user
    await $api(`/users/${selectedUserId.value}`, {
      method: 'PUT',
      body: {
        premium_entry: true,
      },
    })

    toast.add({
      severity: 'success',
      summary: 'نجح',
      detail: 'تم إضافة الخلفية بنجاح',
      life: 3000,
    })

    closeAddDialog()
    await fetchUsers(pagination.value?.current_page || 1)
  } catch (error: any) {
    console.error('Error saving assignment:', error)
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.message || 'فشل إضافة الخلفية',
      life: 3000,
    })
  } finally {
    saving.value = false
  }
}

// Lifecycle
onMounted(() => {
  fetchUsers()
})

onUnmounted(() => {
  if (searchTimeout) {
    clearTimeout(searchTimeout)
  }
  if (userSearchTimeout) {
    clearTimeout(userSearchTimeout)
  }
})
</script>

