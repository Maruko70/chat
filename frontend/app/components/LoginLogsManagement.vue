<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h2 class="text-2xl font-bold text-gray-900">سجل تسجيلات الدخول</h2>
    </div>

    <!-- Search and Filter -->
    <div class="flex gap-4">
      <InputText
        v-model="searchQuery"
        placeholder="البحث بالاسم أو اسم المستخدم أو IP أو البلد..."
        class="flex-1"
        @input="debouncedSearch"
      />
      <Select
        v-model="filterGuest"
        :options="filterOptions"
        optionLabel="label"
        optionValue="value"
        placeholder="نوع المستخدم"
        class="w-48"
        @change="fetchLogs"
      />
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <i class="pi pi-spin pi-spinner text-4xl text-gray-400"></i>
      <p class="mt-4 text-gray-500">جاري التحميل...</p>
    </div>

    <!-- Login Logs Table -->
    <template v-else>
      <DataTable
        :value="logs"
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
        <Column header="نوع المستخدم" sortable>
          <template #body="{ data }">
            <Tag
              :value="data.is_guest ? 'زائر' : 'عضو'"
              :severity="data.is_guest ? 'warning' : 'success'"
            />
          </template>
        </Column>
        <Column field="username" header="اسم المستخدم" sortable>
          <template #body="{ data }">
            <span class="text-sm font-medium">{{ data.username }}</span>
          </template>
        </Column>
        <Column field="room_name" header="اسم الغرفة" sortable>
          <template #body="{ data }">
            <span class="text-sm">{{ data.room_name || '-' }}</span>
          </template>
        </Column>
        <Column field="ip_address" header="عنوان IP" sortable>
          <template #body="{ data }">
            <span class="text-sm font-mono">{{ data.ip_address || '-' }}</span>
          </template>
        </Column>
        <Column field="country" header="البلد" sortable>
          <template #body="{ data }">
            <span v-if="data.country" class="text-sm">{{ data.country }}</span>
            <span v-else class="text-gray-400 text-sm">-</span>
          </template>
        </Column>
        <Column field="user_agent" header="الجهاز (المتصفح)">
          <template #body="{ data }">
            <span class="text-sm">{{ parseUserAgent(data.user_agent) }}</span>
          </template>
        </Column>
        <Column field="created_at" header="التاريخ والوقت" sortable>
          <template #body="{ data }">
            <span class="text-sm">{{ formatDateTime(data.created_at) }}</span>
          </template>
        </Column>
        <template #empty>
          <div class="text-center py-12">
            <i class="pi pi-inbox text-6xl text-gray-300 mb-4"></i>
            <p class="text-gray-500">لا توجد سجلات دخول</p>
          </div>
        </template>
      </DataTable>
    </template>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'

interface LoginLog {
  id: number
  user_id: number
  is_guest: boolean
  username: string
  room_id: number | null
  room_name: string | null
  ip_address: string | null
  country: string | null
  user_agent: string | null
  created_at: string
}

const { $api } = useNuxtApp()

const logs = ref<LoginLog[]>([])
const loading = ref(false)
const searchQuery = ref('')
const filterGuest = ref<boolean | null>(null)
const currentPage = ref(1)
const perPage = ref(20)
const totalRecords = ref(0)

const filterOptions = [
  { label: 'الكل', value: null },
  { label: 'أعضاء فقط', value: false },
  { label: 'زوار فقط', value: true },
]

const fetchLogs = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    params.append('page', currentPage.value.toString())
    params.append('per_page', perPage.value.toString())

    if (searchQuery.value) {
      params.append('search', searchQuery.value)
    }

    if (filterGuest.value !== null) {
      params.append('is_guest', filterGuest.value.toString())
    }

    const queryString = params.toString()
    console.log('Fetching login logs with query:', queryString)
    const response = await $api(`/login-logs?${queryString}`)
    console.log('Login logs response:', response)
    console.log('Response type:', typeof response)
    console.log('Response keys:', Object.keys(response || {}))

    // Handle Laravel pagination response
    let logsData = []
    let total = 0

    if (Array.isArray(response)) {
      logsData = response
      total = response.length
    } else if (response && response.data) {
      logsData = Array.isArray(response.data) ? response.data : []
      total = response.total || 0
    } else {
      logsData = []
      total = 0
    }

    console.log('Extracted logsData:', logsData)
    console.log('Extracted total:', total)
    console.log(`Loaded ${logsData.length} logs out of ${total} total`)

    logs.value = logsData
    totalRecords.value = total
  } catch (error: any) {
    console.error('Error fetching login logs:', error)
    console.error('Error details:', {
      message: error.message,
      response: error.response,
      data: error.data,
    })
    logs.value = []
    totalRecords.value = 0
  } finally {
    loading.value = false
  }
}

const debouncedSearch = useDebounceFn(() => {
  currentPage.value = 1
  fetchLogs()
}, 500)

const onPageChange = (event: any) => {
  console.log('Page change event:', event)
  // PrimeVue DataTable uses 0-based page index
  currentPage.value = event.page + 1
  perPage.value = event.rows
  fetchLogs()
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
    second: '2-digit',
  }).format(date)
}

const parseUserAgent = (userAgent: string | null) => {
  if (!userAgent) return '-'
  
  // Simple user agent parsing
  if (userAgent.includes('Chrome')) {
    return 'Chrome'
  } else if (userAgent.includes('Firefox')) {
    return 'Firefox'
  } else if (userAgent.includes('Safari') && !userAgent.includes('Chrome')) {
    return 'Safari'
  } else if (userAgent.includes('Edge')) {
    return 'Edge'
  } else if (userAgent.includes('Opera')) {
    return 'Opera'
  } else if (userAgent.includes('Mobile')) {
    return 'Mobile'
  }
  
  return userAgent.substring(0, 50) + (userAgent.length > 50 ? '...' : '')
}

watch(filterGuest, () => {
  currentPage.value = 1
  fetchLogs()
})

onMounted(() => {
  fetchLogs()
})
</script>

<style scoped>
:deep(.p-datatable .p-datatable-thead > tr > th) {
  background-color: #f9fafb;
  font-weight: 600;
}
</style>

