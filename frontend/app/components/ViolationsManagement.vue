<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h2 class="text-2xl font-bold text-gray-900">مراقبة الكلمات المحظورة</h2>
      <Button
        icon="pi pi-chart-bar"
        label="إحصائيات"
        @click="showStatisticsDialog = true"
        severity="info"
      />
    </div>

    <!-- Search and Filter -->
    <div class="flex gap-4">
      <InputText
        v-model="searchQuery"
        placeholder="البحث في المحتوى..."
        class="flex-1"
        @input="debouncedSearch"
      />
      <Select
        v-model="statusFilter"
        :options="statusOptions"
        optionLabel="label"
        optionValue="value"
        placeholder="الحالة"
        class="w-48"
        @change="fetchViolations"
      />
      <Select
        v-model="contentTypeFilter"
        :options="contentTypeOptions"
        optionLabel="label"
        optionValue="value"
        placeholder="نوع المحتوى"
        class="w-48"
        @change="fetchViolations"
      />
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <i class="pi pi-spin pi-spinner text-4xl text-gray-400"></i>
      <p class="mt-4 text-gray-500">جاري التحميل...</p>
    </div>

    <!-- Violations Table -->
    <DataTable
      v-else
      :value="violations"
      :paginator="true"
      :rows="perPage"
      :first="(currentPage - 1) * perPage"
      :rowsPerPageOptions="[10, 20, 50, 100]"
      :totalRecords="totalRecords"
      paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
      currentPageReportTemplate="{first} إلى {last} من {totalRecords}"
      class="p-datatable-sm"
      stripedRows
      lazy
      @page="onPageChange"
    >
      <Column header="المستخدم">
        <template #body="{ data }">
          <div class="flex items-center gap-2">
            <Avatar
              :image="data.user?.avatar_url || getDefaultUserImage()"
              shape="circle"
              size="small"
            />
            <div>
              <div class="font-medium text-sm">{{ data.user?.username }}</div>
              <div v-if="data.user?.name" class="text-xs text-gray-500">
                {{ data.user.name }}
              </div>
            </div>
          </div>
        </template>
      </Column>
      <Column header="الكلمة المحظورة">
        <template #body="{ data }">
          <Tag
            :value="data.filtered_word?.word || '-'"
            severity="danger"
            class="text-xs"
          />
        </template>
      </Column>
      <Column header="نوع المحتوى">
        <template #body="{ data }">
          <Tag
            :value="getContentTypeLabel(data.content_type)"
            :severity="getContentTypeSeverity(data.content_type)"
          />
        </template>
      </Column>
      <Column header="المحتوى الأصلي">
        <template #body="{ data }">
          <div class="max-w-md">
            <p class="text-sm line-clamp-2">{{ data.original_content }}</p>
            <Button
              v-if="data.original_content.length > 50"
              label="عرض كامل"
              text
              size="small"
              @click="showContentDialog(data)"
              class="mt-1 p-0 text-xs"
            />
          </div>
        </template>
      </Column>
      <Column header="المحتوى المفلتر" v-if="false">
        <template #body="{ data }">
          <div v-if="data.filtered_content" class="max-w-md">
            <p class="text-sm line-clamp-2 text-gray-500">{{ data.filtered_content }}</p>
          </div>
          <span v-else class="text-gray-400 text-sm">-</span>
        </template>
      </Column>
      <Column header="الحالة" sortable>
        <template #body="{ data }">
          <Tag
            :value="getStatusLabel(data.status)"
            :severity="getStatusSeverity(data.status)"
          />
        </template>
      </Column>
      <Column header="التاريخ" sortable>
        <template #body="{ data }">
          <span class="text-sm">{{ formatDateTime(data.created_at) }}</span>
        </template>
      </Column>
      <Column header="الإجراءات" :exportable="false" style="min-width:12rem">
        <template #body="{ data }">
          <div class="flex gap-2">
            <Button
              v-if="data.status === 'pending'"
              icon="pi pi-eye"
              text
              rounded
              size="small"
              @click="openActionDialog(data)"
              v-tooltip.top="'اتخاذ إجراء'"
            />
            <Button
              icon="pi pi-info-circle"
              text
              rounded
              size="small"
              @click="viewDetails(data)"
              v-tooltip.top="'التفاصيل'"
            />
          </div>
        </template>
      </Column>
    </DataTable>

    <!-- Empty State -->
    <Card v-if="!loading && violations.length === 0">
      <template #content>
        <div class="text-center py-12">
          <i class="pi pi-shield text-6xl text-gray-300 mb-4"></i>
          <h3 class="text-xl font-semibold text-gray-700 mb-2">لا توجد انتهاكات</h3>
          <p class="text-gray-500">لا توجد انتهاكات للكلمات المحظورة حالياً</p>
        </div>
      </template>
    </Card>

    <!-- Action Dialog -->
    <Dialog
      v-model:visible="actionDialogVisible"
      header="اتخاذ إجراء على الانتهاك"
      :modal="true"
      :style="{ width: '600px' }"
      :draggable="false"
    >
      <div v-if="selectedViolation" class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-2">المستخدم</label>
          <div class="flex items-center gap-2 p-2 bg-gray-50 rounded">
            <Avatar
              :image="selectedViolation.user?.avatar_url || getDefaultUserImage()"
              shape="circle"
              size="small"
            />
            <div>
              <div class="font-medium">{{ selectedViolation.user?.username }}</div>
              <div class="text-xs text-gray-500">{{ selectedViolation.user?.name }}</div>
            </div>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-2">المحتوى الأصلي</label>
          <div class="p-3 bg-red-50 border border-red-200 rounded text-sm">
            {{ selectedViolation.original_content }}
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-2">الكلمة المحظورة</label>
          <Tag
            :value="selectedViolation.filtered_word?.word || '-'"
            severity="danger"
          />
        </div>

        <div>
          <label class="block text-sm font-medium mb-2">الإجراءات</label>
          <div class="space-y-2">
            <div class="flex items-center gap-2">
              <Checkbox
                v-model="actionForm.delete_message"
                :binary="true"
                inputId="delete_message"
                :disabled="!selectedViolation.message_id"
              />
              <label for="delete_message" class="text-sm">
                حذف الرسالة
                <span v-if="!selectedViolation.message_id" class="text-gray-400">(غير متاح)</span>
              </label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox
                v-model="actionForm.warn_user"
                :binary="true"
                inputId="warn_user"
              />
              <label for="warn_user" class="text-sm">تحذير المستخدم</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox
                v-model="actionForm.ban_user"
                :binary="true"
                inputId="ban_user"
              />
              <label for="ban_user" class="text-sm">حظر المستخدم</label>
            </div>
            <div v-if="actionForm.ban_user" class="ml-6 space-y-2">
              <div class="flex items-center gap-2">
                <RadioButton
                  v-model="actionForm.ban_type"
                  inputId="permanent_ban"
                  value="permanent"
                />
                <label for="permanent_ban" class="text-sm">حظر دائم</label>
              </div>
              <div class="flex items-center gap-2">
                <RadioButton
                  v-model="actionForm.ban_type"
                  inputId="temporary_ban"
                  value="temporary"
                />
                <label for="temporary_ban" class="text-sm">حظر مؤقت</label>
              </div>
              <div v-if="actionForm.ban_type === 'temporary'" class="ml-6">
                <InputNumber
                  v-model="actionForm.ban_duration"
                  :min="1"
                  :max="365"
                  suffix=" يوم"
                  class="w-full"
                />
              </div>
            </div>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-2">ملاحظات</label>
          <Textarea
            v-model="actionForm.notes"
            class="w-full"
            rows="3"
            placeholder="أضف ملاحظات حول الإجراء المتخذ..."
          />
        </div>

        <div class="flex justify-end gap-2 mt-6">
          <Button
            label="إلغاء"
            severity="secondary"
            @click="closeActionDialog"
            type="button"
          />
          <Button
            label="حفظ"
            @click="saveAction"
            :loading="saving"
          />
        </div>
      </div>
    </Dialog>

    <!-- Content View Dialog -->
    <Dialog
      v-model:visible="contentDialogVisible"
      header="عرض المحتوى الكامل"
      :modal="true"
      :style="{ width: '600px' }"
    >
      <div v-if="viewingContent" class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-2">المحتوى الأصلي</label>
          <div class="p-3 bg-red-50 border border-red-200 rounded">
            {{ viewingContent.original_content }}
          </div>
        </div>
        <div v-if="viewingContent.filtered_content">
          <label class="block text-sm font-medium mb-2">المحتوى المفلتر</label>
          <div class="p-3 bg-gray-50 border border-gray-200 rounded">
            {{ viewingContent.filtered_content }}
          </div>
        </div>
      </div>
    </Dialog>

    <!-- Statistics Dialog -->
    <Dialog
      v-model:visible="showStatisticsDialog"
      header="إحصائيات الانتهاكات"
      :modal="true"
      :style="{ width: '700px' }"
    >
      <div v-if="statistics" class="space-y-6">
        <div class="grid grid-cols-2 gap-4">
          <Card>
            <template #content>
              <div class="text-center">
                <div class="text-3xl font-bold text-primary">{{ statistics.total_violations }}</div>
                <div class="text-sm text-gray-500 mt-2">إجمالي الانتهاكات</div>
              </div>
            </template>
          </Card>
          <Card>
            <template #content>
              <div class="text-center">
                <div class="text-3xl font-bold text-orange-500">{{ statistics.pending_violations }}</div>
                <div class="text-sm text-gray-500 mt-2">انتهاكات قيد المراجعة</div>
              </div>
            </template>
          </Card>
        </div>

        <div>
          <h3 class="font-semibold mb-3">الانتهاكات حسب نوع المحتوى</h3>
          <DataTable :value="Object.entries(statistics.by_content_type || {}).map(([key, value]) => ({ type: key, count: value }))">
            <Column field="type" header="النوع">
              <template #body="{ data }">
                {{ getContentTypeLabel(data.type) }}
              </template>
            </Column>
            <Column field="count" header="العدد" />
          </DataTable>
        </div>

        <div>
          <h3 class="font-semibold mb-3">أكثر المستخدمين انتهاكاً</h3>
          <DataTable :value="statistics.top_offenders || []">
            <Column header="المستخدم">
              <template #body="{ data }">
                {{ data.user?.username || 'مستخدم محذوف' }}
              </template>
            </Column>
            <Column field="count" header="عدد الانتهاكات" />
          </DataTable>
        </div>
      </div>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'

interface FilteredWordViolation {
  id: number
  user_id: number
  filtered_word_id: number
  content_type: 'chats' | 'names' | 'bios' | 'walls' | 'statuses'
  original_content: string
  filtered_content?: string | null
  message_id?: number | null
  status: 'pending' | 'reviewed' | 'action_taken' | 'dismissed'
  reviewed_by?: number | null
  reviewed_at?: string | null
  action_taken?: string | null
  notes?: string | null
  created_at: string
  user?: {
    id: number
    username: string
    name?: string
    avatar_url?: string
  }
  filtered_word?: {
    id: number
    word: string
  }
  message?: any
  reviewer?: {
    id: number
    username: string
  }
}

const { $api } = useNuxtApp()
const confirm = useConfirm()
const toast = useToast()

// State
const violations = ref<FilteredWordViolation[]>([])
const loading = ref(false)
const saving = ref(false)
const currentPage = ref(1)
const perPage = ref(20)
const totalRecords = ref(0)
const searchQuery = ref('')
const statusFilter = ref<string | null>(null)
const contentTypeFilter = ref<string | null>(null)

const actionDialogVisible = ref(false)
const contentDialogVisible = ref(false)
const showStatisticsDialog = ref(false)
const selectedViolation = ref<FilteredWordViolation | null>(null)
const viewingContent = ref<FilteredWordViolation | null>(null)
const statistics = ref<any>(null)

const actionForm = ref({
  status: 'action_taken' as const,
  delete_message: false,
  warn_user: false,
  ban_user: false,
  ban_type: 'permanent' as 'permanent' | 'temporary',
  ban_duration: 7,
  notes: '',
})

const statusOptions = [
  { label: 'الكل', value: null },
  { label: 'قيد المراجعة', value: 'pending' },
  { label: 'تمت المراجعة', value: 'reviewed' },
  { label: 'تم اتخاذ إجراء', value: 'action_taken' },
  { label: 'مرفوض', value: 'dismissed' },
]

const contentTypeOptions = [
  { label: 'الكل', value: null },
  { label: 'المحادثات', value: 'chats' },
  { label: 'الأسماء', value: 'names' },
  { label: 'السير الذاتية', value: 'bios' },
  { label: 'الجدران', value: 'walls' },
  { label: 'الحالات', value: 'statuses' },
]

// Debounced search
let searchTimeout: ReturnType<typeof setTimeout> | null = null
const debouncedSearch = () => {
  if (searchTimeout) {
    clearTimeout(searchTimeout)
  }
  searchTimeout = setTimeout(() => {
    currentPage.value = 1
    fetchViolations()
  }, 500)
}

// Methods
const getDefaultUserImage = () => {
  return '/default-avatar.png'
}

const getContentTypeLabel = (type: string): string => {
  const option = contentTypeOptions.find(opt => opt.value === type)
  return option?.label || type
}

const getContentTypeSeverity = (type: string): string => {
  const severityMap: Record<string, string> = {
    chats: 'info',
    names: 'warning',
    bios: 'success',
    walls: 'danger',
    statuses: 'secondary',
  }
  return severityMap[type] || 'secondary'
}

const getStatusLabel = (status: string): string => {
  const option = statusOptions.find(opt => opt.value === status)
  return option?.label || status
}

const getStatusSeverity = (status: string): string => {
  const severityMap: Record<string, string> = {
    pending: 'warning',
    reviewed: 'info',
    action_taken: 'success',
    dismissed: 'secondary',
  }
  return severityMap[status] || 'secondary'
}

const formatDateTime = (dateString: string) => {
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

const fetchViolations = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (searchQuery.value) {
      params.append('search', searchQuery.value)
    }
    if (statusFilter.value) {
      params.append('status', statusFilter.value)
    }
    if (contentTypeFilter.value) {
      params.append('content_type', contentTypeFilter.value)
    }
    params.append('page', currentPage.value.toString())
    params.append('per_page', perPage.value.toString())

    const response = await $api(`/filtered-word-violations?${params.toString()}`)

    if (Array.isArray(response)) {
      violations.value = response
      totalRecords.value = response.length
    } else if (response && response.data) {
      violations.value = Array.isArray(response.data) ? response.data : []
      totalRecords.value = response.total || 0
    } else {
      violations.value = []
      totalRecords.value = 0
    }
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.message || 'فشل تحميل الانتهاكات',
      life: 3000,
    })
  } finally {
    loading.value = false
  }
}

const fetchStatistics = async () => {
  try {
    statistics.value = await $api('/filtered-word-violations/statistics')
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: 'فشل تحميل الإحصائيات',
      life: 3000,
    })
  }
}

const onPageChange = (event: any) => {
  currentPage.value = event.page + 1
  perPage.value = event.rows
  fetchViolations()
}

const openActionDialog = (violation: FilteredWordViolation) => {
  selectedViolation.value = violation
  actionForm.value = {
    status: 'action_taken',
    delete_message: false,
    warn_user: false,
    ban_user: false,
    ban_type: 'permanent',
    ban_duration: 7,
    notes: '',
  }
  actionDialogVisible.value = true
}

const closeActionDialog = () => {
  actionDialogVisible.value = false
  selectedViolation.value = null
}

const saveAction = async () => {
  if (!selectedViolation.value) return

  saving.value = true
  try {
    const payload: any = {
      status: actionForm.value.status,
      notes: actionForm.value.notes,
    }

    if (actionForm.value.delete_message) {
      payload.delete_message = true
    }
    if (actionForm.value.warn_user) {
      payload.warn_user = true
    }
    if (actionForm.value.ban_user) {
      payload.ban_user = true
      // For permanent bans, don't send ban_duration (null)
      // For temporary bans, send ban_duration in days
      if (actionForm.value.ban_type === 'temporary' && actionForm.value.ban_duration) {
        payload.ban_duration = actionForm.value.ban_duration
      } else if (actionForm.value.ban_type === 'permanent') {
        // Explicitly set to null for permanent bans
        payload.ban_duration = null
      }
    }

    await $api(`/filtered-word-violations/${selectedViolation.value.id}`, {
      method: 'PUT',
      body: payload,
    })

    toast.add({
      severity: 'success',
      summary: 'نجح',
      detail: 'تم اتخاذ الإجراء بنجاح',
      life: 3000,
    })

    closeActionDialog()
    await fetchViolations()
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.data?.message || error.message || 'فشل اتخاذ الإجراء',
      life: 3000,
    })
  } finally {
    saving.value = false
  }
}

const showContentDialog = (violation: FilteredWordViolation) => {
  viewingContent.value = violation
  contentDialogVisible.value = true
}

const viewDetails = (violation: FilteredWordViolation) => {
  viewingContent.value = violation
  contentDialogVisible.value = true
}

// Lifecycle
onMounted(() => {
  fetchViolations()
})

watch(() => showStatisticsDialog.value, (val) => {
  if (val) {
    fetchStatistics()
  }
})
</script>

