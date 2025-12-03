<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h2 class="text-2xl font-bold text-gray-900">إدارة البلاغات</h2>
    </div>

    <!-- Search and Filter -->
    <div class="flex gap-4">
      <InputText
        v-model="searchQuery"
        placeholder="البحث بالاسم أو الرسالة..."
        class="flex-1"
        @input="debouncedSearch"
      />
      <Select
        v-model="statusFilter"
        :options="statusOptions"
        optionLabel="label"
        optionValue="value"
        placeholder="حالة البلاغ"
        class="w-48"
        @change="fetchReports"
      />
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <i class="pi pi-spin pi-spinner text-4xl text-gray-400"></i>
      <p class="mt-4 text-gray-500">جاري التحميل...</p>
    </div>

    <!-- Reports Table -->
    <DataTable
      v-else
      :value="reports"
      :paginator="true"
      :rows="perPage"
      :first="0"
      :rowsPerPageOptions="[10, 20, 50, 100]"
      :totalRecords="totalRecords"
      paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
      currentPageReportTemplate="{first} إلى {last} من {totalRecords}"
      class="p-datatable-sm"
      stripedRows
      lazy
      @page="onPageChange"
    >
      <Column header="المبلغ">
        <template #body="{ data }">
          <div class="flex items-center gap-2">
            <Avatar
              :image="data.reporter?.avatar_url || getDefaultUserImage()"
              shape="circle"
              size="small"
            />
            <div>
              <div class="font-medium text-sm">{{ data.reporter?.username }}</div>
              <div v-if="data.reporter?.name" class="text-xs text-gray-500">
                {{ data.reporter.name }}
              </div>
            </div>
          </div>
        </template>
      </Column>
      <Column header="المبلغ عنه">
        <template #body="{ data }">
          <div class="flex items-center gap-2">
            <Avatar
              :image="data.reported_user?.avatar_url || getDefaultUserImage()"
              shape="circle"
              size="small"
            />
            <div>
              <div class="font-medium text-sm">{{ data.reported_user?.username }}</div>
              <div v-if="data.reported_user?.name" class="text-xs text-gray-500">
                {{ data.reported_user.name }}
              </div>
            </div>
          </div>
        </template>
      </Column>
      <Column header="الرسالة">
        <template #body="{ data }">
          <div class="max-w-md">
            <p class="text-sm line-clamp-3">{{ data.message }}</p>
            <Button
              v-if="data.message.length > 100"
              label="عرض كامل"
              text
              size="small"
              @click="showFullMessage(data)"
              class="mt-1 p-0 text-xs"
            />
          </div>
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
      <Column header="تاريخ البلاغ" sortable>
        <template #body="{ data }">
          <span class="text-sm">{{ formatDateTime(data.created_at) }}</span>
        </template>
      </Column>
      <Column header="تم الحل بواسطة">
        <template #body="{ data }">
          <span v-if="data.resolved_by" class="text-sm">
            {{ data.resolved_by?.username || '-' }}
          </span>
          <span v-else class="text-gray-400 text-sm">-</span>
        </template>
      </Column>
      <Column header="تاريخ الحل">
        <template #body="{ data }">
          <span v-if="data.resolved_at" class="text-sm">
            {{ formatDateTime(data.resolved_at) }}
          </span>
          <span v-else class="text-gray-400 text-sm">-</span>
        </template>
      </Column>
      <Column header="الإجراءات" :exportable="false">
        <template #body="{ data }">
          <div class="flex gap-2">
            <Button
              v-if="data.status === 'pending'"
              icon="pi pi-check"
              text
              rounded
              severity="success"
              @click="resolveReport(data)"
              v-tooltip.top="'حل البلاغ'"
            />
            <Button
              v-if="data.status === 'pending'"
              icon="pi pi-times"
              text
              rounded
              severity="warning"
              @click="dismissReport(data)"
              v-tooltip.top="'رفض البلاغ'"
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
      <template #empty>
        <div class="text-center py-12">
          <i class="pi pi-inbox text-6xl text-gray-300 mb-4"></i>
          <p class="text-gray-500">لا توجد بلاغات</p>
        </div>
      </template>
    </DataTable>

    <!-- Full Message Dialog -->
    <Dialog
      v-model:visible="showMessageDialog"
      header="الرسالة الكاملة"
      :modal="true"
      :style="{ width: '600px' }"
      :draggable="false"
    >
      <div v-if="selectedReport" class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-2">المبلغ</label>
          <div class="flex items-center gap-2">
            <Avatar
              :image="selectedReport.reporter?.avatar_url || getDefaultUserImage()"
              shape="circle"
              size="normal"
            />
            <div>
              <div class="font-medium">{{ selectedReport.reporter?.username }}</div>
              <div v-if="selectedReport.reporter?.name" class="text-sm text-gray-500">
                {{ selectedReport.reporter.name }}
              </div>
            </div>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium mb-2">المبلغ عنه</label>
          <div class="flex items-center gap-2">
            <Avatar
              :image="selectedReport.reported_user?.avatar_url || getDefaultUserImage()"
              shape="circle"
              size="normal"
            />
            <div>
              <div class="font-medium">{{ selectedReport.reported_user?.username }}</div>
              <div v-if="selectedReport.reported_user?.name" class="text-sm text-gray-500">
                {{ selectedReport.reported_user.name }}
              </div>
            </div>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium mb-2">الرسالة</label>
          <div class="p-4 bg-gray-50 rounded-lg">
            <p class="whitespace-pre-wrap">{{ selectedReport.message }}</p>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium mb-2">الحالة</label>
          <Tag
            :value="getStatusLabel(selectedReport.status)"
            :severity="getStatusSeverity(selectedReport.status)"
          />
        </div>
        <div v-if="selectedReport.resolved_at">
          <label class="block text-sm font-medium mb-2">تم الحل بواسطة</label>
          <span class="text-sm">{{ selectedReport.resolved_by?.username }} في {{ formatDateTime(selectedReport.resolved_at) }}</span>
        </div>
      </div>
    </Dialog>

    <!-- Confirmation Dialogs -->
    <ConfirmDialog />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'

const { $api } = useNuxtApp()
const toast = useToast()
const confirm = useConfirm()

interface Report {
  id: number
  reporter_id: number
  reported_user_id: number
  message: string
  status: 'pending' | 'resolved' | 'dismissed'
  resolved_by: number | null
  resolved_at: string | null
  created_at: string
  reporter?: any
  reported_user?: any
  resolved_by_user?: any
}

const reports = ref<Report[]>([])
const loading = ref(false)
const searchQuery = ref('')
const statusFilter = ref<string | null>(null)
const currentPage = ref(1)
const perPage = ref(20)
const totalRecords = ref(0)
const showMessageDialog = ref(false)
const selectedReport = ref<Report | null>(null)

const statusOptions = [
  { label: 'الكل', value: null },
  { label: 'قيد الانتظار', value: 'pending' },
  { label: 'تم الحل', value: 'resolved' },
  { label: 'مرفوض', value: 'dismissed' },
]

const fetchReports = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    params.append('page', currentPage.value.toString())
    params.append('per_page', perPage.value.toString())

    if (searchQuery.value) {
      params.append('search', searchQuery.value)
    }

    if (statusFilter.value) {
      params.append('status', statusFilter.value)
    }

    const response = await $api(`/reports?${params.toString()}`)

    if (Array.isArray(response)) {
      reports.value = response
      totalRecords.value = response.length
    } else if (response && response.data) {
      reports.value = Array.isArray(response.data) ? response.data : []
      totalRecords.value = response.total || 0
    } else {
      reports.value = []
      totalRecords.value = 0
    }
  } catch (error: any) {
    console.error('Error fetching reports:', error)
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: 'فشل تحميل البلاغات',
      life: 3000,
    })
    reports.value = []
    totalRecords.value = 0
  } finally {
    loading.value = false
  }
}

const debouncedSearch = useDebounceFn(() => {
  currentPage.value = 1
  fetchReports()
}, 500)

const onPageChange = (event: any) => {
  currentPage.value = event.page + 1
  perPage.value = event.rows
  fetchReports()
}

const resolveReport = (report: Report) => {
  confirm.require({
    message: 'هل أنت متأكد من حل هذا البلاغ؟',
    header: 'تأكيد حل البلاغ',
    icon: 'pi pi-exclamation-triangle',
    accept: async () => {
      try {
        await $api(`/reports/${report.id}`, {
          method: 'PUT',
          body: { status: 'resolved' },
        })
        toast.add({
          severity: 'success',
          summary: 'نجح',
          detail: 'تم حل البلاغ بنجاح',
          life: 3000,
        })
        fetchReports()
      } catch (error: any) {
        console.error('Error resolving report:', error)
        toast.add({
          severity: 'error',
          summary: 'خطأ',
          detail: 'فشل حل البلاغ',
          life: 3000,
        })
      }
    },
  })
}

const dismissReport = (report: Report) => {
  confirm.require({
    message: 'هل أنت متأكد من رفض هذا البلاغ؟',
    header: 'تأكيد رفض البلاغ',
    icon: 'pi pi-exclamation-triangle',
    accept: async () => {
      try {
        await $api(`/reports/${report.id}`, {
          method: 'PUT',
          body: { status: 'dismissed' },
        })
        toast.add({
          severity: 'success',
          summary: 'نجح',
          detail: 'تم رفض البلاغ بنجاح',
          life: 3000,
        })
        fetchReports()
      } catch (error: any) {
        console.error('Error dismissing report:', error)
        toast.add({
          severity: 'error',
          summary: 'خطأ',
          detail: 'فشل رفض البلاغ',
          life: 3000,
        })
      }
    },
  })
}

const confirmDelete = (report: Report) => {
  confirm.require({
    message: 'هل أنت متأكد من حذف هذا البلاغ؟',
    header: 'تأكيد الحذف',
    icon: 'pi pi-exclamation-triangle',
    accept: async () => {
      try {
        await $api(`/reports/${report.id}`, {
          method: 'DELETE',
        })
        toast.add({
          severity: 'success',
          summary: 'نجح',
          detail: 'تم حذف البلاغ بنجاح',
          life: 3000,
        })
        fetchReports()
      } catch (error: any) {
        console.error('Error deleting report:', error)
        toast.add({
          severity: 'error',
          summary: 'خطأ',
          detail: 'فشل حذف البلاغ',
          life: 3000,
        })
      }
    },
  })
}

const showFullMessage = (report: Report) => {
  selectedReport.value = report
  showMessageDialog.value = true
}

const getStatusLabel = (status: string) => {
  const labels: Record<string, string> = {
    pending: 'قيد الانتظار',
    resolved: 'تم الحل',
    dismissed: 'مرفوض',
  }
  return labels[status] || status
}

const getStatusSeverity = (status: string) => {
  const severities: Record<string, string> = {
    pending: 'warning',
    resolved: 'success',
    dismissed: 'danger',
  }
  return severities[status] || 'info'
}

const formatDateTime = (dateString: string) => {
  if (!dateString) return '-'
  const date = new Date(dateString)
  return new Intl.DateTimeFormat('ar-SA', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(date)
}

const getDefaultUserImage = () => {
  return null
}

watch(statusFilter, () => {
  currentPage.value = 1
  fetchReports()
})

onMounted(() => {
  fetchReports()
})
</script>

<style scoped>
:deep(.p-datatable .p-datatable-thead > tr > th) {
  background-color: #f9fafb;
  font-weight: 600;
}
</style>




