<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h2 class="text-2xl font-bold text-gray-900">إدارة الاشتراكات</h2>
    </div>

    <!-- Search and Filter -->
    <div class="flex gap-4">
      <InputText
        v-model="searchQuery"
        placeholder="البحث بالاسم أو اسم المستخدم..."
        class="flex-1"
        @input="debouncedSearch"
      />
      <Select
        v-model="roleGroupFilter"
        :options="roleGroupOptions"
        optionLabel="label"
        optionValue="value"
        placeholder="مجموعة الدور"
        class="w-48"
        @change="fetchSubscriptions"
      />
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <i class="pi pi-spin pi-spinner text-4xl text-gray-400"></i>
      <p class="mt-4 text-gray-500">جاري التحميل...</p>
    </div>

    <!-- Subscriptions Table -->
    <DataTable
      v-else
      :value="subscriptions"
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
      <Column field="username" header="اسم المستخدم" sortable>
        <template #body="{ data }">
          <div class="flex items-center gap-2">
            <Avatar
              :image="data.user?.avatar_url || getDefaultUserImage()"
              shape="circle"
              size="small"
            />
            <div>
              <div class="font-medium text-sm">{{ data.username }}</div>
              <div v-if="data.name" class="text-xs text-gray-500">{{ data.name }}</div>
            </div>
          </div>
        </template>
      </Column>
      <Column field="chat_name" header="اسم الغرفة" sortable>
        <template #body="{ data }">
          <span class="text-sm">{{ data.chat_name || '-' }}</span>
        </template>
      </Column>
      <Column field="ip_address" header="IP" sortable>
        <template #body="{ data }">
          <span class="text-sm font-mono">{{ data.ip_address || '-' }}</span>
        </template>
      </Column>
      <Column field="device" header="الجهاز">
        <template #body="{ data }">
          <span class="text-sm">{{ data.device || '-' }}</span>
        </template>
      </Column>
      <Column header="مجموعة الدور" sortable>
        <template #body="{ data }">
          <Tag
            v-if="data.role_group"
            :value="data.role_group.name"
            severity="info"
          />
          <span v-else class="text-gray-400 text-sm">-</span>
        </template>
      </Column>
      <Column header="تاريخ الانتهاء" sortable>
        <template #body="{ data }">
          <span v-if="data.expire_date" class="text-sm">
            {{ formatDate(data.expire_date) }}
          </span>
          <Tag v-else value="دائم" severity="success" />
        </template>
      </Column>
      <Column header="آخر تسجيل دخول" sortable>
        <template #body="{ data }">
          <span v-if="data.last_login_date" class="text-sm">
            {{ formatDateTime(data.last_login_date) }}
          </span>
          <span v-else class="text-gray-400 text-sm">-</span>
        </template>
      </Column>
      <Column header="تاريخ منح الدور" sortable>
        <template #body="{ data }">
          <span v-if="data.role_given_at" class="text-sm">
            {{ formatDateTime(data.role_given_at) }}
          </span>
          <span v-else class="text-gray-400 text-sm">-</span>
        </template>
      </Column>
      <Column header="الإجراءات" :exportable="false">
        <template #body="{ data }">
          <Button
            icon="pi pi-pencil"
            text
            rounded
            severity="info"
            @click="openEditDialog(data)"
            v-tooltip.top="'تعديل'"
          />
        </template>
      </Column>
      <template #empty>
        <div class="text-center py-12">
          <i class="pi pi-inbox text-6xl text-gray-300 mb-4"></i>
          <p class="text-gray-500">لا توجد اشتراكات</p>
        </div>
      </template>
    </DataTable>

    <!-- Edit Subscription Dialog -->
    <Dialog
      v-model:visible="showEditDialog"
      header="تعديل الاشتراك"
      :modal="true"
      :style="{ width: '600px' }"
      :draggable="false"
    >
      <form @submit.prevent="saveSubscription" class="space-y-4" v-if="editingSubscription">
        <div>
          <label class="block text-sm font-medium mb-2">المستخدم</label>
          <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
            <Avatar
              :image="editingSubscription.user?.avatar_url || getDefaultUserImage()"
              shape="circle"
              size="normal"
            />
            <div>
              <div class="font-medium">{{ editingSubscription.username }}</div>
              <div v-if="editingSubscription.name" class="text-sm text-gray-500">
                {{ editingSubscription.name }}
              </div>
            </div>
          </div>
        </div>

        <div>
          <label class="block text-sm font-medium mb-2">مجموعة الدور الحالية</label>
          <div v-if="editingSubscription.role_group" class="p-3 bg-gray-50 rounded-lg">
            <Tag :value="editingSubscription.role_group.name" severity="info" />
          </div>
          <span v-else class="text-gray-400">لا توجد مجموعة دور</span>
        </div>

        <div>
          <label class="block text-sm font-medium mb-2">الإجراء</label>
          <Select
            v-model="subscriptionForm.action"
            :options="actionOptions"
            optionLabel="label"
            optionValue="value"
            placeholder="اختر الإجراء"
            class="w-full"
            :class="{ 'p-invalid': subscriptionFormErrors.action }"
          />
          <small v-if="subscriptionFormErrors.action" class="p-error">
            {{ subscriptionFormErrors.errors }}
          </small>
        </div>

        <div v-if="subscriptionForm.action === 'change'">
          <label class="block text-sm font-medium mb-2">مجموعة الدور الجديدة</label>
          <Select
            v-model="subscriptionForm.role_group_id"
            :options="availableRoleGroups"
            optionLabel="name"
            optionValue="id"
            placeholder="اختر مجموعة الدور"
            class="w-full"
            :class="{ 'p-invalid': subscriptionFormErrors.role_group_id }"
            :loading="loadingRoleGroups"
          />
          <small v-if="subscriptionFormErrors.role_group_id" class="p-error">
            {{ subscriptionFormErrors.role_group_id }}
          </small>
        </div>

        <div v-if="subscriptionForm.action === 'change' && subscriptionForm.role_group_id">
          <label class="block text-sm font-medium mb-2">تاريخ الانتهاء (اختياري)</label>
          <div class="flex items-center gap-2 mb-2">
            <Checkbox
              v-model="subscriptionForm.is_permanent"
              inputId="is_permanent_sub"
              :binary="true"
            />
            <label for="is_permanent_sub" class="cursor-pointer">اشتراك دائم</label>
          </div>
          <Calendar
            v-if="!subscriptionForm.is_permanent"
            v-model="subscriptionForm.expires_at"
            showIcon
            dateFormat="yy-mm-dd"
            :minDate="new Date()"
            class="w-full"
          />
        </div>

        <div class="flex justify-end gap-2 pt-2">
          <Button
            type="button"
            label="إلغاء"
            severity="secondary"
            @click="showEditDialog = false"
          />
          <Button
            type="submit"
            label="حفظ"
            :loading="saving"
          />
        </div>
      </form>
    </Dialog>

    <!-- Confirmation Dialogs -->
    <ConfirmDialog />
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue'
import { useDebounceFn } from '@vueuse/core'
import { useToast } from 'primevue/usetoast'
import { useConfirm } from 'primevue/useconfirm'

const { $api } = useNuxtApp()
const toast = useToast()
const confirm = useConfirm()
const { getDefaultUserImage } = useSiteSettings()

interface Subscription {
  id: number
  username: string
  name?: string
  chat_name: string | null
  ip_address: string | null
  device: string | null
  role_group: {
    id: number
    name: string
    priority: number
  } | null
  expire_date: string | null
  last_login_date: string | null
  role_given_at: string | null
  user?: any
}

interface RoleGroup {
  id: number
  name: string
  priority: number
}

const subscriptions = ref<Subscription[]>([])
const loading = ref(false)
const searchQuery = ref('')
const roleGroupFilter = ref<number | null>(null)
const currentPage = ref(1)
const perPage = ref(20)
const totalRecords = ref(0)
const showEditDialog = ref(false)
const editingSubscription = ref<Subscription | null>(null)
const saving = ref(false)
const availableRoleGroups = ref<RoleGroup[]>([])
const loadingRoleGroups = ref(false)

const subscriptionForm = ref({
  action: 'change' as 'change' | 'remove',
  role_group_id: null as number | null,
  expires_at: null as Date | null,
  is_permanent: false,
})

const subscriptionFormErrors = ref<any>({})

const actionOptions = [
  { label: 'تغيير مجموعة الدور', value: 'change' },
  { label: 'إزالة الاشتراك (إرجاع إلى الأولوية 0)', value: 'remove' },
]

const roleGroupOptions = ref<{ label: string; value: number | null }[]>([
  { label: 'الكل', value: null },
])

// Fetch subscriptions
const fetchSubscriptions = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    params.append('page', currentPage.value.toString())
    params.append('per_page', perPage.value.toString())

    if (searchQuery.value) {
      params.append('search', searchQuery.value)
    }

    if (roleGroupFilter.value) {
      params.append('role_group_id', roleGroupFilter.value.toString())
    }

    const response = await $api(`/subscriptions?${params.toString()}`)

    if (Array.isArray(response)) {
      subscriptions.value = response
      totalRecords.value = response.length
    } else if (response && response.data) {
      subscriptions.value = Array.isArray(response.data) ? response.data : []
      totalRecords.value = response.total || 0
    } else {
      subscriptions.value = []
      totalRecords.value = 0
    }
  } catch (error: any) {
    console.error('Error fetching subscriptions:', error)
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: 'فشل تحميل الاشتراكات',
      life: 3000,
    })
    subscriptions.value = []
    totalRecords.value = 0
  } finally {
    loading.value = false
  }
}

// Fetch role groups for filter and selection
const fetchRoleGroups = async () => {
  loadingRoleGroups.value = true
  try {
    const response = await $api('/role-groups?is_active=true')
    const roleGroups = Array.isArray(response) ? response : []
    
    // Filter for priority > 0 for selection dropdown
    const premiumRoleGroups = roleGroups.filter((rg: RoleGroup) => rg.priority > 0)
    availableRoleGroups.value = premiumRoleGroups
    
    // Add to filter options
    roleGroupOptions.value = [
      { label: 'الكل', value: null },
      ...premiumRoleGroups.map((rg: RoleGroup) => ({
        label: rg.name,
        value: rg.id,
      })),
    ]
  } catch (error: any) {
    console.error('Error fetching role groups:', error)
  } finally {
    loadingRoleGroups.value = false
  }
}

const debouncedSearch = useDebounceFn(() => {
  currentPage.value = 1
  fetchSubscriptions()
}, 500)

const onPageChange = (event: any) => {
  currentPage.value = event.page + 1
  perPage.value = event.rows
  fetchSubscriptions()
}

const openEditDialog = (subscription: Subscription) => {
  editingSubscription.value = subscription
  subscriptionForm.value = {
    action: 'change',
    role_group_id: subscription.role_group?.id || null,
    expires_at: subscription.expire_date ? new Date(subscription.expire_date) : null,
    is_permanent: !subscription.expire_date,
  }
  subscriptionFormErrors.value = {}
  showEditDialog.value = true
}

const saveSubscription = async () => {
  if (!editingSubscription.value) return

  subscriptionFormErrors.value = {}

  if (subscriptionForm.value.action === 'remove') {
    // Remove subscription - confirm first
    confirm.require({
      message: 'هل أنت متأكد من إزالة الاشتراك وإرجاع المستخدم إلى الأولوية 0؟',
      header: 'تأكيد إزالة الاشتراك',
      icon: 'pi pi-exclamation-triangle',
      accept: async () => {
        saving.value = true
        try {
          await $api(`/subscriptions/${editingSubscription.value.id}`, {
            method: 'PUT',
            body: {
              remove_subscription: true,
            },
          })
          toast.add({
            severity: 'success',
            summary: 'نجح',
            detail: 'تم إزالة الاشتراك بنجاح',
            life: 3000,
          })
          showEditDialog.value = false
          fetchSubscriptions()
        } catch (error: any) {
          console.error('Error removing subscription:', error)
          toast.add({
            severity: 'error',
            summary: 'خطأ',
            detail: error.data?.message || 'فشل إزالة الاشتراك',
            life: 3000,
          })
        } finally {
          saving.value = false
        }
      },
    })
    return
  }

  // Change role group
  if (!subscriptionForm.value.role_group_id) {
    subscriptionFormErrors.value.role_group_id = 'يرجى اختيار مجموعة دور'
    return
  }

  saving.value = true
  try {
    const payload: any = {
      role_group_id: subscriptionForm.value.role_group_id,
    }

    if (!subscriptionForm.value.is_permanent && subscriptionForm.value.expires_at) {
      payload.expires_at = subscriptionForm.value.expires_at.toISOString()
    }

    await $api(`/subscriptions/${editingSubscription.value.id}`, {
      method: 'PUT',
      body: payload,
    })
    toast.add({
      severity: 'success',
      summary: 'نجح',
      detail: 'تم تحديث الاشتراك بنجاح',
      life: 3000,
    })
    showEditDialog.value = false
    fetchSubscriptions()
  } catch (error: any) {
    console.error('Error updating subscription:', error)
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.data?.message || 'فشل تحديث الاشتراك',
      life: 3000,
    })
  } finally {
    saving.value = false
  }
}

const formatDate = (dateString: string) => {
  if (!dateString) return '-'
  const date = new Date(dateString)
  return new Intl.DateTimeFormat('ar-SA', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  }).format(date)
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

watch(roleGroupFilter, () => {
  currentPage.value = 1
  fetchSubscriptions()
})

onMounted(() => {
  fetchRoleGroups()
  fetchSubscriptions()
})
</script>

<style scoped>
:deep(.p-datatable .p-datatable-thead > tr > th) {
  background-color: #f9fafb;
  font-weight: 600;
}
</style>

