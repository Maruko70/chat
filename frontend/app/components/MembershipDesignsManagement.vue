<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h2 class="text-2xl font-bold text-gray-900">إدارة تصاميم العضويات</h2>
      <Button
        icon="pi pi-plus"
        label="إضافة تصميم جديد"
        @click="openCreateDialog"
        severity="success"
      />
    </div>

    <!-- Filters -->
    <Card>
      <template #content>
        <div class="flex gap-4 items-center">
          <div class="flex-1">
            <label class="block text-sm font-medium mb-2">نوع التصميم</label>
            <Select
              v-model="filterType"
              :options="typeOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="جميع الأنواع"
              class="w-full"
              @change="fetchDesigns"
            />
          </div>
          <div class="flex-1">
            <label class="block text-sm font-medium mb-2">الحالة</label>
            <Select
              v-model="filterActive"
              :options="activeOptions"
              optionLabel="label"
              optionValue="value"
              placeholder="جميع الحالات"
              class="w-full"
              @change="fetchDesigns"
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

    <!-- Designs Grid -->
    <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <Card v-for="design in designs" :key="design.id" class="overflow-hidden">
        <template #header>
          <div class="relative h-48 bg-gray-100">
            <img
              :src="design.image_url"
              :alt="design.name"
              class="w-full h-full object-cover"
              @error="handleImageError"
            />
            <Tag
              :value="design.type === 'background' ? 'خلفية' : 'إطار'"
              :severity="design.type === 'background' ? 'info' : 'warning'"
              class="absolute top-2 right-2"
            />
            <Tag
              :value="design.is_active ? 'نشط' : 'غير نشط'"
              :severity="design.is_active ? 'success' : 'secondary'"
              class="absolute top-2 left-2"
            />
          </div>
        </template>
        <template #title>{{ design.name || 'تصميم بدون اسم' }}</template>
        <template #content>
          <div class="flex items-center justify-between text-xs text-gray-500">
            <span>{{ formatDate(design.created_at) }}</span>
          </div>
        </template>
        <template #footer>
          <div class="flex gap-2">
            <Button
              icon="pi pi-pencil"
              label="تعديل"
              severity="info"
              size="small"
              @click="openEditDialog(design)"
              class="flex-1"
            />
            <Button
              icon="pi pi-trash"
              label="حذف"
              severity="danger"
              size="small"
              @click="confirmDelete(design)"
              class="flex-1"
            />
          </div>
        </template>
      </Card>
    </div>

    <!-- Create/Edit Dialog -->
    <Dialog
      v-model:visible="dialogVisible"
      :header="editingDesign ? 'تعديل التصميم' : 'إضافة تصميم جديد'"
      :style="{ width: '700px', maxWidth: '90vw' }"
      :modal="true"
      class="p-fluid"
      :draggable="false"
    >
      <div class="space-y-4 max-h-[70vh] overflow-y-auto px-2">
        <div>
          <label class="block text-sm font-medium mb-2">الاسم</label>
          <InputText 
            v-model="formData.name" 
            class="w-full" 
            placeholder="أدخل اسم التصميم (اختياري)"
          />
        </div>

        <div>
          <label class="block text-sm font-medium mb-2">النوع <span class="text-red-500">*</span></label>
          <Select
            v-model="formData.type"
            :options="typeOptions"
            optionLabel="label"
            optionValue="value"
            class="w-full"
            :class="{ 'p-invalid': errors.type }"
            placeholder="اختر نوع التصميم"
          />
          <small v-if="errors.type" class="p-error">{{ errors.type }}</small>
        </div>

        <div>
          <label class="block text-sm font-medium mb-2">الصورة <span class="text-red-500">*</span></label>
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

        <!-- Active Status -->
        <div class="border-t pt-4">
          <div class="flex items-center gap-2">
            <InputSwitch v-model="formData.is_active" inputId="is_active" />
            <label for="is_active" class="cursor-pointer text-sm font-medium">نشط</label>
          </div>
        </div>
      </div>

      <template #footer>
        <Button label="إلغاء" icon="pi pi-times" text @click="closeDialog" />
        <Button
          label="حفظ"
          icon="pi pi-check"
          @click="saveDesign"
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

interface MembershipDesign {
  id: number
  name: string
  type: 'background' | 'frame'
  image_url: string
  description?: string
  is_active: boolean
  priority: number
  created_at: string
  updated_at: string
}

// State
const loading = ref(false)
const saving = ref(false)
const designs = ref<MembershipDesign[]>([])
const dialogVisible = ref(false)
const editingDesign = ref<MembershipDesign | null>(null)
const imageFile = ref<File | null>(null)
const imagePreview = ref<string | null>(null)
const filterType = ref<string | null>(null)
const filterActive = ref<boolean | null>(null)

const typeOptions = [
  { label: 'خلفية', value: 'background' },
  { label: 'إطار', value: 'frame' },
]

const activeOptions = [
  { label: 'نشط', value: true },
  { label: 'غير نشط', value: false },
]

const formData = ref({
  name: '',
  type: 'background' as 'background' | 'frame',
  is_active: true,
})

const errors = ref<Record<string, string>>({})

// Methods
const fetchDesigns = async () => {
  loading.value = true
  try {
    const params: any = {}
    if (filterType.value) params.type = filterType.value
    if (filterActive.value !== null) params.active = filterActive.value

    const queryString = new URLSearchParams(params).toString()
    designs.value = await $api(`/membership-designs${queryString ? `?${queryString}` : ''}`)
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.message || 'فشل تحميل التصاميم',
      life: 3000,
    })
  } finally {
    loading.value = false
  }
}

const openCreateDialog = () => {
  editingDesign.value = null
  formData.value = {
    name: '',
    type: 'background',
    is_active: true,
  }
  imageFile.value = null
  imagePreview.value = null
  errors.value = {}
  dialogVisible.value = true
}

const openEditDialog = (design: MembershipDesign) => {
  editingDesign.value = design
  formData.value = {
    name: design.name,
    type: design.type,
    is_active: design.is_active,
  }
  imageFile.value = null
  imagePreview.value = design.image_url
  errors.value = {}
  dialogVisible.value = true
}

const closeDialog = () => {
  dialogVisible.value = false
  editingDesign.value = null
  formData.value = {
    name: '',
    type: 'background',
    is_active: true,
  }
  imageFile.value = null
  imagePreview.value = null
  errors.value = {}
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

const saveDesign = async () => {
  errors.value = {}

  if (!editingDesign.value && !imageFile.value) {
    errors.value.image = 'الصورة مطلوبة'
    return
  }

  saving.value = true
  try {
    const formDataToSend = new FormData()
    if (formData.value.name && formData.value.name.trim()) {
      formDataToSend.append('name', formData.value.name.trim())
    }
    formDataToSend.append('type', formData.value.type)
    formDataToSend.append('is_active', formData.value.is_active ? '1' : '0')

    if (imageFile.value) {
      formDataToSend.append('image', imageFile.value)
    }

    const authStore = useAuthStore()
    const config = useRuntimeConfig()
    const baseUrl = config.public.apiBaseUrl
    const headers: HeadersInit = {
      'Accept': 'application/json',
    }

    if (authStore.token) {
      headers['Authorization'] = `Bearer ${authStore.token}`
    }

    let url = `${baseUrl}/membership-designs`
    let method = 'POST'

    if (editingDesign.value) {
      url = `${baseUrl}/membership-designs/${editingDesign.value.id}`
      method = 'PUT'
    }

    const response = await fetch(url, {
      method,
      headers,
      body: formDataToSend,
    })

    if (!response.ok) {
      const error = await response.json().catch(() => ({ message: 'فشل حفظ التصميم' }))
      throw new Error(error.message || 'فشل حفظ التصميم')
    }

    toast.add({
      severity: 'success',
      summary: 'نجح',
      detail: editingDesign.value ? 'تم تحديث التصميم بنجاح' : 'تم إضافة التصميم بنجاح',
      life: 3000,
    })

    closeDialog()
    await fetchDesigns()
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.message || 'فشل حفظ التصميم',
      life: 3000,
    })
  } finally {
    saving.value = false
  }
}

const confirmDelete = (design: MembershipDesign) => {
  confirm.require({
    message: `هل أنت متأكد من حذف التصميم "${design.name}"؟`,
    header: 'تأكيد الحذف',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: 'نعم، احذف',
    rejectLabel: 'إلغاء',
    accept: async () => {
      try {
        await $api(`/membership-designs/${design.id}`, {
          method: 'DELETE',
        })

        toast.add({
          severity: 'success',
          summary: 'نجح',
          detail: 'تم حذف التصميم بنجاح',
          life: 3000,
        })

        await fetchDesigns()
      } catch (error: any) {
        toast.add({
          severity: 'error',
          summary: 'خطأ',
          detail: error.message || 'فشل حذف التصميم',
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
  return new Date(dateString).toLocaleDateString('ar-EG')
}

// Lifecycle
onMounted(() => {
  fetchDesigns()
})
</script>

