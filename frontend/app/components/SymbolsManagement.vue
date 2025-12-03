<template>
  <div class="space-y-6">
    <!-- Header with Upload Button -->
    <div class="flex items-center justify-between">
      <h2 class="text-2xl font-bold text-gray-900">الرموز</h2>
      <Button
        icon="pi pi-upload"
        label="رفع رمز جديد"
        @click="openUploadDialog"
        severity="success"
      />
    </div>

    <!-- Filters -->
    <div class="flex gap-4">
      <InputText
        v-model="searchQuery"
        placeholder="البحث..."
        class="flex-1"
        @input="fetchSymbols"
      />
      <Select
        v-model="filterType"
        :options="typeOptions"
        optionLabel="label"
        optionValue="value"
        placeholder="نوع الرمز"
        class="w-64"
        @change="fetchSymbols"
      />
      <Select
        v-model="filterActive"
        :options="filterOptions"
        optionLabel="label"
        optionValue="value"
        placeholder="الحالة"
        class="w-48"
        @change="fetchSymbols"
      />
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <i class="pi pi-spin pi-spinner text-4xl text-gray-400"></i>
      <p class="mt-4 text-gray-500">جاري التحميل...</p>
    </div>

    <!-- Symbols Grid -->
    <div v-else-if="symbols.length > 0" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
      <div
        v-for="symbol in symbols"
        :key="symbol.id"
        class="border rounded-lg p-4 hover:shadow-lg transition-shadow"
      >
        <div class="aspect-square flex items-center justify-center bg-gray-50 rounded mb-2">
          <img
            :src="symbol.url"
            :alt="symbol.name"
            class="max-w-full max-h-full object-contain"
          />
        </div>
        <p class="text-sm font-medium text-center truncate" :title="symbol.name">
          {{ symbol.name }}
        </p>
        <p class="text-xs text-gray-500 text-center">
          {{ getTypeLabel(symbol.type) }}
        </p>
        <div class="flex gap-2 mt-2">
          <Button
            icon="pi pi-copy"
            text
            rounded
            size="small"
            @click="copyUrl(symbol.url)"
            v-tooltip.top="'نسخ الرابط'"
          />
          <Button
            icon="pi pi-trash"
            text
            rounded
            size="small"
            severity="danger"
            @click="confirmDelete(symbol)"
            v-tooltip.top="'حذف'"
          />
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <Card v-else>
      <template #content>
        <div class="text-center py-12">
          <i class="pi pi-image text-6xl text-gray-300 mb-4"></i>
          <h3 class="text-xl font-semibold text-gray-700 mb-2">لا توجد رموز</h3>
          <p class="text-gray-500 mb-4">ابدأ برفع رمز جديد</p>
          <Button
            icon="pi pi-upload"
            label="رفع رمز جديد"
            @click="openUploadDialog"
          />
        </div>
      </template>
    </Card>

    <!-- Upload Dialog -->
    <Dialog
      v-model:visible="uploadDialogVisible"
      header="رفع رمز جديد"
      :modal="true"
      :style="{ width: '500px' }"
      :draggable="false"
    >
      <form @submit.prevent="uploadSymbol" class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-2">اختر ملف الصورة</label>
          <FileUpload
            mode="basic"
            accept="image/*"
            :maxFileSize="2048000"
            :auto="false"
            chooseLabel="اختر ملف"
            @select="onFileSelect"
            :class="{ 'p-invalid': errors.file }"
          />
          <small v-if="errors.file" class="p-error">{{ errors.file }}</small>
          <small class="text-gray-500 text-xs mt-1 block">
            الصيغ المدعومة: JPEG, PNG, JPG, GIF, WEBP, SVG (حد أقصى 2MB)
          </small>
        </div>

        <div>
          <label class="block text-sm font-medium mb-2">نوع الرمز *</label>
          <Select
            v-model="symbolType"
            :options="SYMBOL_TYPES"
            optionLabel="label"
            optionValue="value"
            class="w-full"
            :class="{ 'p-invalid': errors.type }"
            placeholder="اختر نوع الرمز"
          />
          <small v-if="errors.type" class="p-error">{{ errors.type }}</small>
        </div>

        <div v-if="selectedFile">
          <label class="block text-sm font-medium mb-2">اسم الرمز (اختياري)</label>
          <InputText
            v-model="fileName"
            class="w-full"
            placeholder="اتركه فارغاً لاستخدام اسم الملف الأصلي"
          />
        </div>

        <div>
          <label class="block text-sm font-medium mb-2">الأولوية</label>
          <InputNumber
            v-model="priority"
            class="w-full"
            :min="0"
          />
        </div>

        <div v-if="selectedFile" class="border rounded p-4">
          <p class="text-sm font-medium mb-2">معاينة:</p>
          <img
            :src="previewUrl"
            alt="Preview"
            class="max-w-full max-h-48 object-contain mx-auto"
          />
        </div>

        <div class="flex justify-end gap-2 mt-6">
          <Button
            label="إلغاء"
            severity="secondary"
            @click="closeUploadDialog"
            type="button"
          />
          <Button
            type="submit"
            label="رفع"
            :loading="uploading"
            :disabled="!selectedFile"
          />
        </div>
      </form>
    </Dialog>

    <!-- Delete Confirmation -->
    <ConfirmDialog />
  </div>
</template>

<script setup lang="ts">
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import type { Symbol } from '~~/types'
import { SYMBOL_TYPES } from '~~/types'

const { $api } = useNuxtApp()
const confirm = useConfirm()
const toast = useToast()

// State
const symbols = ref<Symbol[]>([])
const loading = ref(false)
const uploading = ref(false)
const uploadDialogVisible = ref(false)
const selectedFile = ref<File | null>(null)
const fileName = ref('')
const previewUrl = ref('')
const symbolType = ref<string>('')
const priority = ref(0)
const searchQuery = ref('')
const filterType = ref<string | null>(null)
const filterActive = ref<boolean | null>(null)

const errors = ref<Record<string, string>>({})

const typeOptions = [
  { label: 'الكل', value: null },
  ...SYMBOL_TYPES,
]

const filterOptions = [
  { label: 'الكل', value: null },
  { label: 'نشط', value: true },
  { label: 'غير نشط', value: false },
]

const getTypeLabel = (type: string) => {
  return SYMBOL_TYPES.find(t => t.value === type)?.label || type
}

// Methods
const fetchSymbols = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (searchQuery.value) {
      params.append('search', searchQuery.value)
    }
    if (filterType.value) {
      params.append('type', filterType.value)
    }
    if (filterActive.value !== null) {
      params.append('is_active', filterActive.value.toString())
    }
    
    const queryString = params.toString()
    const endpoint = `/symbols${queryString ? `?${queryString}` : ''}`
    symbols.value = await $api(endpoint)
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.message || 'فشل تحميل الرموز',
      life: 3000,
    })
  } finally {
    loading.value = false
  }
}

const openUploadDialog = () => {
  uploadDialogVisible.value = true
  selectedFile.value = null
  fileName.value = ''
  previewUrl.value = ''
  symbolType.value = ''
  priority.value = 0
  errors.value = {}
}

const closeUploadDialog = () => {
  uploadDialogVisible.value = false
  selectedFile.value = null
  fileName.value = ''
  previewUrl.value = ''
  symbolType.value = ''
  priority.value = 0
  errors.value = {}
}

const onFileSelect = (event: any) => {
  const file = event.files?.[0]
  if (!file) return

  // Validate file type
  const validTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp', 'image/svg+xml']
  if (!validTypes.includes(file.type)) {
    errors.value.file = 'نوع الملف غير مدعوم. يرجى اختيار صورة.'
    return
  }

  // Validate file size (2MB)
  if (file.size > 2048000) {
    errors.value.file = 'حجم الملف كبير جداً. الحد الأقصى 2MB.'
    return
  }

  selectedFile.value = file
  fileName.value = ''
  errors.value.file = ''

  // Create preview
  const reader = new FileReader()
  reader.onload = (e) => {
    previewUrl.value = e.target?.result as string
  }
  reader.readAsDataURL(file)
}

const uploadSymbol = async () => {
  errors.value = {}
  
  if (!selectedFile.value) {
    errors.value.file = 'يرجى اختيار ملف'
    return
  }

  if (!symbolType.value) {
    errors.value.type = 'يرجى اختيار نوع الرمز'
    return
  }

  uploading.value = true

  try {
    const formData = new FormData()
    formData.append('file', selectedFile.value)
    formData.append('type', symbolType.value)
    if (fileName.value.trim()) {
      formData.append('name', fileName.value.trim())
    }
    formData.append('priority', priority.value.toString())

    const symbol = await $api('/symbols', {
      method: 'POST',
      body: formData,
    })

    toast.add({
      severity: 'success',
      summary: 'نجح',
      detail: 'تم رفع الرمز بنجاح',
      life: 3000,
    })

    closeUploadDialog()
    await fetchSymbols()
  } catch (error: any) {
    const errorData = error.data || {}
    if (errorData.errors) {
      errors.value = errorData.errors
    } else {
      toast.add({
        severity: 'error',
        summary: 'خطأ',
        detail: error.message || 'فشل رفع الرمز',
        life: 3000,
      })
    }
  } finally {
    uploading.value = false
  }
}

const copyUrl = (url: string) => {
  navigator.clipboard.writeText(url).then(() => {
    toast.add({
      severity: 'success',
      summary: 'نجح',
      detail: 'تم نسخ الرابط',
      life: 2000,
    })
  }).catch(() => {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: 'فشل نسخ الرابط',
      life: 2000,
    })
  })
}

const confirmDelete = (symbol: Symbol) => {
  confirm.require({
    message: `هل أنت متأكد من حذف الرمز "${symbol.name}"؟`,
    header: 'تأكيد الحذف',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: 'نعم',
    rejectLabel: 'لا',
    accept: async () => {
      try {
        await $api(`/symbols/${symbol.id}`, {
          method: 'DELETE',
          body: { path: symbol.path },
        })
        toast.add({
          severity: 'success',
          summary: 'نجح',
          detail: 'تم حذف الرمز بنجاح',
          life: 3000,
        })
        await fetchSymbols()
      } catch (error: any) {
        toast.add({
          severity: 'error',
          summary: 'خطأ',
          detail: error.message || 'فشل حذف الرمز',
          life: 3000,
        })
      }
    },
  })
}

// Lifecycle
onMounted(() => {
  fetchSymbols()
})
</script>

