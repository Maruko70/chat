<template>
  <div class="space-y-6">
    <!-- Header with Add Button -->
    <div class="flex items-center justify-between">
      <h2 class="text-2xl font-bold text-gray-900">الرسائل المجدولة</h2>
      <Button
        icon="pi pi-plus"
        label="إضافة رسالة مجدولة جديدة"
        @click="openCreateDialog"
        severity="success"
      />
    </div>

    <!-- Search and Filter -->
    <div class="flex gap-4">
      <InputText
        v-model="searchQuery"
        placeholder="البحث في العنوان أو الرسالة..."
        class="flex-1"
        @input="debouncedSearch"
      />
      <Select
        v-model="filterType"
        :options="typeOptions"
        optionLabel="label"
        optionValue="value"
        placeholder="النوع"
        class="w-48"
        @change="fetchMessages"
      />
      <Select
        v-model="filterActive"
        :options="filterOptions"
        optionLabel="label"
        optionValue="value"
        placeholder="الحالة"
        class="w-48"
        @change="fetchMessages"
      />
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <i class="pi pi-spin pi-spinner text-4xl text-gray-400"></i>
      <p class="mt-4 text-gray-500">جاري التحميل...</p>
    </div>

    <!-- Messages Table -->
    <DataTable
      v-else
      :value="messages"
      :paginator="true"
      :rows="10"
      :rowsPerPageOptions="[5, 10, 20, 50]"
      paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
      currentPageReportTemplate="{first} إلى {last} من {totalRecords}"
      class="p-datatable-sm"
      stripedRows
    >
      <Column field="title" header="العنوان" sortable>
        <template #body="{ data }">
          <span class="font-medium">{{ data.title }}</span>
        </template>
      </Column>
      <Column field="type" header="النوع" sortable>
        <template #body="{ data }">
          <Tag
            :value="data.type === 'daily' ? 'يومي' : 'ترحيبي'"
            :severity="data.type === 'daily' ? 'info' : 'success'"
          />
        </template>
      </Column>
      <Column header="الغرفة">
        <template #body="{ data }">
          <span v-if="data.room">{{ data.room.name }}</span>
          <Tag v-else value="جميع الغرف" severity="secondary" />
        </template>
      </Column>
      <Column header="الفترة الزمنية">
        <template #body="{ data }">
          <span class="text-sm">
            كل {{ formatTimeSpan(data.time_span) }}
          </span>
        </template>
      </Column>
      <Column field="is_active" header="الحالة" sortable>
        <template #body="{ data }">
          <Tag
            :value="data.is_active ? 'نشط' : 'غير نشط'"
            :severity="data.is_active ? 'success' : 'danger'"
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
      :header="editingMessage ? 'تعديل الرسالة المجدولة' : 'إضافة رسالة مجدولة جديدة'"
      :modal="true"
      :style="{ width: '700px', maxHeight: '90vh' }"
      :draggable="false"
      class="overflow-y-auto"
    >
      <form @submit.prevent="saveMessage" class="space-y-4">
        <div class="space-y-4">
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-2">النوع <span class="text-red-500">*</span></label>
              <Select
                v-model="formData.type"
                :options="typeOptions"
                optionLabel="label"
                optionValue="value"
                placeholder="اختر النوع"
                class="w-full"
                :class="{ 'p-invalid': errors.type }"
              />
              <small v-if="errors.type" class="p-error">{{ errors.type }}</small>
            </div>

            <div>
              <label class="block text-sm font-medium mb-2">الغرفة</label>
              <Select
                v-model="formData.room_id"
                :options="roomOptions"
                optionLabel="name"
                optionValue="id"
                placeholder="جميع الغرف"
                class="w-full"
                :showClear="true"
              />
              <small class="text-gray-500 text-xs mt-1">اتركه فارغاً لجميع الغرف</small>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium mb-2">العنوان <span class="text-red-500">*</span></label>
            <InputText
              v-model="formData.title"
              class="w-full"
              :class="{ 'p-invalid': errors.title }"
              placeholder="عنوان الرسالة"
            />
            <small v-if="errors.title" class="p-error">{{ errors.title }}</small>
          </div>

          <div>
            <label class="block text-sm font-medium mb-2">الرسالة <span class="text-red-500">*</span></label>
            <Textarea
              v-model="formData.message"
              class="w-full"
              rows="5"
              :autoResize="true"
              :class="{ 'p-invalid': errors.message }"
              placeholder="محتوى الرسالة"
            />
            <small v-if="errors.message" class="p-error">{{ errors.message }}</small>
          </div>

          <div v-if="formData.type === 'daily'">
            <label class="block text-sm font-medium mb-2">الفترة الزمنية (بالدقائق) <span class="text-red-500">*</span></label>
            <InputNumber
              v-model="formData.time_span"
              :min="1"
              class="w-full"
              :class="{ 'p-invalid': errors.time_span }"
              placeholder="مثال: 60 (كل ساعة)"
            />
            <small v-if="errors.time_span" class="p-error">{{ errors.time_span }}</small>
            <small class="text-gray-500 text-xs mt-1">
              مثال: 1 (كل دقيقة)، 60 (كل ساعة)، 1440 (يومياً)
            </small>
          </div>

          <div v-else>
            <div>
              <label class="block text-sm font-medium mb-2">الفترة الزمنية (بالدقائق) <span class="text-red-500">*</span></label>
              <InputNumber
                v-model="formData.time_span"
                :min="1"
                class="w-full"
                :class="{ 'p-invalid': errors.time_span }"
                placeholder="مثال: 1 (كل دقيقة)"
              />
              <small v-if="errors.time_span" class="p-error">{{ errors.time_span }}</small>
              <small class="text-gray-500 text-xs mt-1">
                سيتم إرسال الرسالة عند انضمام المستخدمين للغرفة
              </small>
            </div>
          </div>

          <div class="flex items-center gap-2">
            <Checkbox
              v-model="formData.is_active"
              inputId="is_active"
              :binary="true"
            />
            <label for="is_active" class="cursor-pointer">نشط</label>
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
            label="حفظ"
            :loading="saving"
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
import type { Room } from '~~/types'

interface ScheduledMessage {
  id: number
  type: 'daily' | 'welcoming'
  time_span: number
  title: string
  message: string
  room_id?: number | null
  room?: Room | null
  is_active: boolean
  last_sent_at?: string | null
  created_at?: string
  updated_at?: string
}

const { $api } = useNuxtApp()
const confirm = useConfirm()
const toast = useToast()

// State
const messages = ref<ScheduledMessage[]>([])
const loading = ref(false)
const saving = ref(false)
const searchQuery = ref('')
const filterType = ref<'daily' | 'welcoming' | null>(null)
const filterActive = ref<boolean | null>(null)
const dialogVisible = ref(false)
const editingMessage = ref<ScheduledMessage | null>(null)
const availableRooms = ref<Room[]>([])

const filterOptions = [
  { label: 'الكل', value: null },
  { label: 'نشط', value: true },
  { label: 'غير نشط', value: false },
]

const typeOptions = [
  { label: 'يومي', value: 'daily' },
  { label: 'ترحيبي', value: 'welcoming' },
]

const roomOptions = computed(() => [
  { id: null, name: 'جميع الغرف' },
  ...availableRooms.value,
])

const formData = ref({
  type: 'daily' as 'daily' | 'welcoming',
  time_span: 60,
  title: '',
  message: '',
  room_id: null as number | null,
  is_active: true,
})

const errors = ref<Record<string, string>>({})

// Debounced search
let searchTimeout: ReturnType<typeof setTimeout> | null = null
const debouncedSearch = () => {
  if (searchTimeout) {
    clearTimeout(searchTimeout)
  }
  searchTimeout = setTimeout(() => {
    fetchMessages()
  }, 500)
}

// Methods
const fetchMessages = async () => {
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
    const endpoint = `/scheduled-messages${queryString ? `?${queryString}` : ''}`
    messages.value = await $api(endpoint)
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.message || 'فشل تحميل الرسائل المجدولة',
      life: 3000,
    })
  } finally {
    loading.value = false
  }
}

const fetchRooms = async () => {
  try {
    availableRooms.value = await $api('/chat')
  } catch (error: any) {
    console.error('Error fetching rooms:', error)
  }
}

const formatTimeSpan = (minutes: number): string => {
  if (minutes < 60) {
    return `${minutes} دقيقة`
  } else if (minutes < 1440) {
    const hours = Math.floor(minutes / 60)
    return `${hours} ساعة`
  } else {
    const days = Math.floor(minutes / 1440)
    return `${days} يوم`
  }
}

const openCreateDialog = () => {
  editingMessage.value = null
  formData.value = {
    type: 'daily',
    time_span: 60,
    title: '',
    message: '',
    room_id: null,
    is_active: true,
  }
  errors.value = {}
  dialogVisible.value = true
}

const openEditDialog = (message: ScheduledMessage) => {
  editingMessage.value = message
  formData.value = {
    type: message.type,
    time_span: message.time_span,
    title: message.title,
    message: message.message,
    room_id: message.room_id || null,
    is_active: message.is_active,
  }
  
  errors.value = {}
  dialogVisible.value = true
}

const closeDialog = () => {
  dialogVisible.value = false
  editingMessage.value = null
  formData.value = {
    type: 'daily',
    time_span: 60,
    title: '',
    message: '',
    room_id: null,
    is_active: true,
  }
  errors.value = {}
}

const saveMessage = async () => {
  errors.value = {}
  
  if (!formData.value.title.trim()) {
    errors.value.title = 'العنوان مطلوب'
    return
  }
  
  if (!formData.value.message.trim()) {
    errors.value.message = 'الرسالة مطلوبة'
    return
  }
  
  if (!formData.value.time_span || formData.value.time_span < 1) {
    errors.value.time_span = 'الفترة الزمنية يجب أن تكون على الأقل دقيقة واحدة'
    return
  }

  saving.value = true
  try {
    const payload: any = {
      type: formData.value.type,
      time_span: formData.value.time_span,
      title: formData.value.title.trim(),
      message: formData.value.message.trim(),
      room_id: formData.value.room_id || null,
      is_active: formData.value.is_active,
    }

    if (editingMessage.value) {
      await $api(`/scheduled-messages/${editingMessage.value.id}`, {
        method: 'PUT',
        body: payload,
      })
      toast.add({
        severity: 'success',
        summary: 'نجح',
        detail: 'تم تحديث الرسالة المجدولة بنجاح',
        life: 3000,
      })
    } else {
      await $api('/scheduled-messages', {
        method: 'POST',
        body: payload,
      })
      toast.add({
        severity: 'success',
        summary: 'نجح',
        detail: 'تم إضافة الرسالة المجدولة بنجاح',
        life: 3000,
      })
    }
    
    closeDialog()
    await fetchMessages()
  } catch (error: any) {
    const errorData = error.data || {}
    if (errorData.errors) {
      errors.value = errorData.errors
    } else {
      toast.add({
        severity: 'error',
        summary: 'خطأ',
        detail: error.message || 'فشل حفظ الرسالة المجدولة',
        life: 3000,
      })
    }
  } finally {
    saving.value = false
  }
}

const confirmDelete = (message: ScheduledMessage) => {
  confirm.require({
    message: `هل أنت متأكد من حذف الرسالة المجدولة "${message.title}"؟`,
    header: 'تأكيد الحذف',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: 'نعم',
    rejectLabel: 'لا',
    accept: async () => {
      try {
        await $api(`/scheduled-messages/${message.id}`, {
          method: 'DELETE',
        })
        toast.add({
          severity: 'success',
          summary: 'نجح',
          detail: 'تم حذف الرسالة المجدولة بنجاح',
          life: 3000,
        })
        await fetchMessages()
      } catch (error: any) {
        toast.add({
          severity: 'error',
          summary: 'خطأ',
          detail: error.message || 'فشل حذف الرسالة المجدولة',
          life: 3000,
        })
      }
    },
  })
}

// Lifecycle
onMounted(async () => {
  await Promise.all([fetchMessages(), fetchRooms()])
})
</script>

