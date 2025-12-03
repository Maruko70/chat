<template>
  <div class="space-y-8">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h2 class="text-2xl font-bold text-gray-900">إدارة الحظر</h2>
    </div>

    <!-- Browser and OS Ban Section -->
    <Card>
      <template #title>حظر المتصفحات وأنظمة التشغيل</template>
      <template #content>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <!-- Banned Browsers -->
          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <h3 class="text-lg font-semibold">المتصفحات المحظورة</h3>
              <Button
                icon="pi pi-plus"
                label="إضافة متصفح"
                size="small"
                @click="showBrowserDialog = true"
              />
            </div>
            
            <div v-if="bannedBrowsersLoading" class="text-center py-4">
              <i class="pi pi-spin pi-spinner text-2xl text-gray-400"></i>
            </div>
            
            <div v-else-if="bannedBrowsers.length === 0" class="text-center py-4 text-gray-500">
              لا توجد متصفحات محظورة
            </div>
            
            <div v-else class="space-y-2">
              <div
                v-for="browser in bannedBrowsers"
                :key="browser.id"
                class="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
              >
                <div>
                  <div class="font-medium">{{ browser.browser_name }}</div>
                  <div v-if="browser.description" class="text-sm text-gray-500">
                    {{ browser.description }}
                  </div>
                </div>
                <Button
                  icon="pi pi-trash"
                  text
                  rounded
                  severity="danger"
                  @click="confirmUnbanBrowser(browser)"
                  v-tooltip.top="'إلغاء الحظر'"
                />
              </div>
            </div>
          </div>

          <!-- Banned Operating Systems -->
          <div class="space-y-4">
            <div class="flex items-center justify-between">
              <h3 class="text-lg font-semibold">أنظمة التشغيل المحظورة</h3>
              <Button
                icon="pi pi-plus"
                label="إضافة نظام"
                size="small"
                @click="showOSDialog = true"
              />
            </div>
            
            <div v-if="bannedOSLoading" class="text-center py-4">
              <i class="pi pi-spin pi-spinner text-2xl text-gray-400"></i>
            </div>
            
            <div v-else-if="bannedOS.length === 0" class="text-center py-4 text-gray-500">
              لا توجد أنظمة تشغيل محظورة
            </div>
            
            <div v-else class="space-y-2">
              <div
                v-for="os in bannedOS"
                :key="os.id"
                class="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
              >
                <div>
                  <div class="font-medium">{{ os.os_name }}</div>
                  <div v-if="os.description" class="text-sm text-gray-500">
                    {{ os.description }}
                  </div>
                </div>
                <Button
                  icon="pi pi-trash"
                  text
                  rounded
                  severity="danger"
                  @click="confirmUnbanOS(os)"
                  v-tooltip.top="'إلغاء الحظر'"
                />
              </div>
            </div>
          </div>
        </div>
      </template>
    </Card>

    <!-- Banned Users Section -->
    <Card>
      <template #title>المستخدمون المحظورون</template>
      <template #content>
        <div class="space-y-4">
          <!-- Search and Filter -->
          <div class="flex gap-4">
            <InputText
              v-model="searchQuery"
              placeholder="البحث بالاسم أو اسم المستخدم أو IP..."
              class="flex-1"
              @input="debouncedSearch"
            />
            <Button
              icon="pi pi-plus"
              label="حظر مستخدم"
              @click="showBanUserDialog = true"
            />
          </div>

          <!-- Loading State -->
          <div v-if="bannedUsersLoading" class="text-center py-12">
            <i class="pi pi-spin pi-spinner text-4xl text-gray-400"></i>
            <p class="mt-4 text-gray-500">جاري التحميل...</p>
          </div>

          <!-- Banned Users Table -->
          <DataTable
            v-else
            :value="bannedUsers"
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
            <Column header="اسم المستخدم">
              <template #body="{ data }">
                <div>
                  <div class="font-medium">{{ data.username }}</div>
                  <div v-if="data.name" class="text-sm text-gray-500">{{ data.name }}</div>
                </div>
              </template>
            </Column>
            <Column header="حظر بواسطة">
              <template #body="{ data }">
                <span class="text-sm">{{ data.banned_by?.username || '-' }}</span>
              </template>
            </Column>
            <Column header="السبب">
              <template #body="{ data }">
                <span class="text-sm">{{ data.reason || '-' }}</span>
              </template>
            </Column>
            <Column header="الجهاز">
              <template #body="{ data }">
                <span class="text-sm">{{ data.device || '-' }}</span>
              </template>
            </Column>
            <Column header="IP">
              <template #body="{ data }">
                <span class="text-sm font-mono">{{ data.ip_address || '-' }}</span>
              </template>
            </Column>
            <Column header="اسم الحساب">
              <template #body="{ data }">
                <span class="text-sm">{{ data.account_name || '-' }}</span>
              </template>
            </Column>
            <Column header="البلد">
              <template #body="{ data }">
                <span class="text-sm">{{ data.country || '-' }}</span>
              </template>
            </Column>
            <Column header="تاريخ الانتهاء">
              <template #body="{ data }">
                <Tag
                  v-if="data.is_permanent"
                  value="دائم"
                  severity="danger"
                />
                <span v-else-if="data.ends_at" class="text-sm">
                  {{ formatDate(data.ends_at) }}
                </span>
                <span v-else class="text-sm text-gray-400">-</span>
              </template>
            </Column>
            <Column header="تاريخ الحظر">
              <template #body="{ data }">
                <span class="text-sm">{{ formatDate(data.banned_at) }}</span>
              </template>
            </Column>
            <Column header="الإجراءات" :exportable="false">
              <template #body="{ data }">
                <Button
                  icon="pi pi-unlock"
                  text
                  rounded
                  severity="success"
                  @click="confirmUnbanUser(data)"
                  v-tooltip.top="'إلغاء الحظر'"
                />
              </template>
            </Column>
            <template #empty>
              <div class="text-center py-12">
                <i class="pi pi-inbox text-6xl text-gray-300 mb-4"></i>
                <p class="text-gray-500">لا توجد مستخدمين محظورين</p>
              </div>
            </template>
          </DataTable>
        </div>
      </template>
    </Card>

    <!-- Ban Browser Dialog -->
    <Dialog
      v-model:visible="showBrowserDialog"
      header="حظر متصفح"
      :modal="true"
      :style="{ width: '500px' }"
      :draggable="false"
    >
      <form @submit.prevent="banBrowser" class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-2">المتصفح</label>
          <Select
            v-model="browserForm.browser_name"
            :options="availableBrowsers"
            optionLabel="label"
            optionValue="value"
            placeholder="اختر المتصفح"
            class="w-full"
            :class="{ 'p-invalid': browserFormErrors.browser_name }"
          />
          <small v-if="browserFormErrors.browser_name" class="p-error">
            {{ browserFormErrors.browser_name }}
          </small>
        </div>
        <div>
          <label class="block text-sm font-medium mb-2">الوصف (اختياري)</label>
          <Textarea
            v-model="browserForm.description"
            class="w-full"
            rows="3"
            :autoResize="true"
          />
        </div>
        <div class="flex justify-end gap-2">
          <Button
            type="button"
            label="إلغاء"
            severity="secondary"
            @click="showBrowserDialog = false"
          />
          <Button
            type="submit"
            label="حظر"
            :loading="banningBrowser"
          />
        </div>
      </form>
    </Dialog>

    <!-- Ban OS Dialog -->
    <Dialog
      v-model:visible="showOSDialog"
      header="حظر نظام تشغيل"
      :modal="true"
      :style="{ width: '500px' }"
      :draggable="false"
    >
      <form @submit.prevent="banOS" class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-2">نظام التشغيل</label>
          <Select
            v-model="osForm.os_name"
            :options="availableOS"
            optionLabel="label"
            optionValue="value"
            placeholder="اختر نظام التشغيل"
            class="w-full"
            :class="{ 'p-invalid': osFormErrors.os_name }"
          />
          <small v-if="osFormErrors.os_name" class="p-error">
            {{ osFormErrors.os_name }}
          </small>
        </div>
        <div>
          <label class="block text-sm font-medium mb-2">الوصف (اختياري)</label>
          <Textarea
            v-model="osForm.description"
            class="w-full"
            rows="3"
            :autoResize="true"
          />
        </div>
        <div class="flex justify-end gap-2">
          <Button
            type="button"
            label="إلغاء"
            severity="secondary"
            @click="showOSDialog = false"
          />
          <Button
            type="submit"
            label="حظر"
            :loading="banningOS"
          />
        </div>
      </form>
    </Dialog>

    <!-- Ban User Dialog -->
    <Dialog
      v-model:visible="showBanUserDialog"
      header="حظر مستخدم"
      :modal="true"
      :style="{ width: '600px' }"
      :draggable="false"
    >
      <form @submit.prevent="banUser" class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-2">المستخدم</label>
          <AutoComplete
            v-model="banUserForm.user"
            :suggestions="userSuggestions"
            @complete="searchUsers"
            optionLabel="username"
            placeholder="ابحث عن مستخدم..."
            class="w-full"
            :class="{ 'p-invalid': banUserFormErrors.user_id }"
            forceSelection
          >
            <template #item="slotProps">
              <div class="flex items-center gap-2">
                <span>{{ slotProps.item.username }}</span>
                <span v-if="slotProps.item.name" class="text-sm text-gray-500">
                  ({{ slotProps.item.name }})
                </span>
              </div>
            </template>
          </AutoComplete>
          <small v-if="banUserFormErrors.user_id" class="p-error">
            {{ banUserFormErrors.user_id }}
          </small>
        </div>
        <div>
          <label class="block text-sm font-medium mb-2">السبب (اختياري)</label>
          <Textarea
            v-model="banUserForm.reason"
            class="w-full"
            rows="3"
            :autoResize="true"
          />
        </div>
        <div class="flex items-center gap-2">
          <Checkbox
            v-model="banUserForm.is_permanent"
            inputId="is_permanent"
            :binary="true"
          />
          <label for="is_permanent" class="cursor-pointer">حظر دائم</label>
        </div>
        <div v-if="!banUserForm.is_permanent">
          <label class="block text-sm font-medium mb-2">تاريخ الانتهاء</label>
          <Calendar
            v-model="banUserForm.ends_at"
            showIcon
            dateFormat="yy-mm-dd"
            :minDate="new Date()"
            class="w-full"
          />
        </div>
        <div class="flex justify-end gap-2">
          <Button
            type="button"
            label="إلغاء"
            severity="secondary"
            @click="showBanUserDialog = false"
          />
          <Button
            type="submit"
            label="حظر"
            :loading="banningUser"
          />
        </div>
      </form>
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

// Browser/OS ban state
const bannedBrowsers = ref<any[]>([])
const bannedOS = ref<any[]>([])
const bannedBrowsersLoading = ref(false)
const bannedOSLoading = ref(false)
const showBrowserDialog = ref(false)
const showOSDialog = ref(false)
const banningBrowser = ref(false)
const banningOS = ref(false)

const browserForm = ref({
  browser_name: null as string | null,
  description: '',
})
const browserFormErrors = ref<any>({})

const osForm = ref({
  os_name: null as string | null,
  description: '',
})
const osFormErrors = ref<any>({})

const availableBrowsers = [
  { label: 'Chrome', value: 'Chrome' },
  { label: 'Firefox', value: 'Firefox' },
  { label: 'Safari', value: 'Safari' },
  { label: 'Opera', value: 'Opera' },
  { label: 'Internet Explorer', value: 'Internet Explorer' },
  { label: 'Edge', value: 'Edge' },
  { label: 'Android WebView', value: 'Android WebView' },
  { label: 'Samsung Internet', value: 'Samsung Internet' },
]

const availableOS = [
  { label: 'Windows', value: 'Windows' },
  { label: 'Linux', value: 'Linux' },
  { label: 'Android', value: 'Android' },
  { label: 'iOS', value: 'iOS' },
  { label: 'Windows Phone', value: 'Windows Phone' },
  { label: 'Mac OS', value: 'Mac OS' },
]

// Banned users state
const bannedUsers = ref<any[]>([])
const bannedUsersLoading = ref(false)
const searchQuery = ref('')
const currentPage = ref(1)
const perPage = ref(20)
const totalRecords = ref(0)
const showBanUserDialog = ref(false)
const banningUser = ref(false)

const banUserForm = ref({
  user: null as any,
  reason: '',
  is_permanent: false,
  ends_at: null as Date | null,
})
const banUserFormErrors = ref<any>({})
const userSuggestions = ref<any[]>([])

// Fetch banned browsers
const fetchBannedBrowsers = async () => {
  bannedBrowsersLoading.value = true
  try {
    bannedBrowsers.value = await $api('/bans/browsers')
  } catch (error: any) {
    console.error('Error fetching banned browsers:', error)
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: 'فشل تحميل المتصفحات المحظورة',
      life: 3000,
    })
  } finally {
    bannedBrowsersLoading.value = false
  }
}

// Fetch banned OS
const fetchBannedOS = async () => {
  bannedOSLoading.value = true
  try {
    bannedOS.value = await $api('/bans/operating-systems')
  } catch (error: any) {
    console.error('Error fetching banned OS:', error)
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: 'فشل تحميل أنظمة التشغيل المحظورة',
      life: 3000,
    })
  } finally {
    bannedOSLoading.value = false
  }
}

// Ban browser
const banBrowser = async () => {
  if (!browserForm.value.browser_name) {
    browserFormErrors.value.browser_name = 'يرجى اختيار متصفح'
    return
  }

  banningBrowser.value = true
  browserFormErrors.value = {}

  try {
    await $api('/bans/browsers', {
      method: 'POST',
      body: browserForm.value,
    })
    toast.add({
      severity: 'success',
      summary: 'نجح',
      detail: 'تم حظر المتصفح بنجاح',
      life: 3000,
    })
    showBrowserDialog.value = false
    browserForm.value = { browser_name: null, description: '' }
    fetchBannedBrowsers()
  } catch (error: any) {
    console.error('Error banning browser:', error)
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.data?.message || 'فشل حظر المتصفح',
      life: 3000,
    })
  } finally {
    banningBrowser.value = false
  }
}

// Unban browser
const confirmUnbanBrowser = (browser: any) => {
  confirm.require({
    message: `هل أنت متأكد من إلغاء حظر المتصفح "${browser.browser_name}"؟`,
    header: 'تأكيد إلغاء الحظر',
    icon: 'pi pi-exclamation-triangle',
    accept: async () => {
      try {
        await $api(`/bans/browsers/${browser.id}`, {
          method: 'DELETE',
        })
        toast.add({
          severity: 'success',
          summary: 'نجح',
          detail: 'تم إلغاء حظر المتصفح بنجاح',
          life: 3000,
        })
        fetchBannedBrowsers()
      } catch (error: any) {
        console.error('Error unbanning browser:', error)
        toast.add({
          severity: 'error',
          summary: 'خطأ',
          detail: 'فشل إلغاء حظر المتصفح',
          life: 3000,
        })
      }
    },
  })
}

// Ban OS
const banOS = async () => {
  if (!osForm.value.os_name) {
    osFormErrors.value.os_name = 'يرجى اختيار نظام تشغيل'
    return
  }

  banningOS.value = true
  osFormErrors.value = {}

  try {
    await $api('/bans/operating-systems', {
      method: 'POST',
      body: osForm.value,
    })
    toast.add({
      severity: 'success',
      summary: 'نجح',
      detail: 'تم حظر نظام التشغيل بنجاح',
      life: 3000,
    })
    showOSDialog.value = false
    osForm.value = { os_name: null, description: '' }
    fetchBannedOS()
  } catch (error: any) {
    console.error('Error banning OS:', error)
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.data?.message || 'فشل حظر نظام التشغيل',
      life: 3000,
    })
  } finally {
    banningOS.value = false
  }
}

// Unban OS
const confirmUnbanOS = (os: any) => {
  confirm.require({
    message: `هل أنت متأكد من إلغاء حظر نظام التشغيل "${os.os_name}"؟`,
    header: 'تأكيد إلغاء الحظر',
    icon: 'pi pi-exclamation-triangle',
    accept: async () => {
      try {
        await $api(`/bans/operating-systems/${os.id}`, {
          method: 'DELETE',
        })
        toast.add({
          severity: 'success',
          summary: 'نجح',
          detail: 'تم إلغاء حظر نظام التشغيل بنجاح',
          life: 3000,
        })
        fetchBannedOS()
      } catch (error: any) {
        console.error('Error unbanning OS:', error)
        toast.add({
          severity: 'error',
          summary: 'خطأ',
          detail: 'فشل إلغاء حظر نظام التشغيل',
          life: 3000,
        })
      }
    },
  })
}

// Fetch banned users
const fetchBannedUsers = async () => {
  bannedUsersLoading.value = true
  try {
    const params = new URLSearchParams()
    params.append('page', currentPage.value.toString())
    params.append('per_page', perPage.value.toString())

    if (searchQuery.value) {
      params.append('search', searchQuery.value)
    }

    const response = await $api(`/bans/users?${params.toString()}`)

    if (Array.isArray(response)) {
      bannedUsers.value = response
      totalRecords.value = response.length
    } else if (response && response.data) {
      bannedUsers.value = Array.isArray(response.data) ? response.data : []
      totalRecords.value = response.total || 0
    } else {
      bannedUsers.value = []
      totalRecords.value = 0
    }
  } catch (error: any) {
    console.error('Error fetching banned users:', error)
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: 'فشل تحميل المستخدمين المحظورين',
      life: 3000,
    })
    bannedUsers.value = []
    totalRecords.value = 0
  } finally {
    bannedUsersLoading.value = false
  }
}

// Search users
const searchUsers = async (event: any) => {
  try {
    const response = await $api(`/users/search?search=${event.query}&limit=10`)
    userSuggestions.value = Array.isArray(response) ? response : []
  } catch (error) {
    console.error('Error searching users:', error)
    userSuggestions.value = []
  }
}

// Ban user
const banUser = async () => {
  if (!banUserForm.value.user || !banUserForm.value.user.id) {
    banUserFormErrors.value.user_id = 'يرجى اختيار مستخدم'
    return
  }

  banningUser.value = true
  banUserFormErrors.value = {}

  try {
    const payload: any = {
      user_id: banUserForm.value.user.id,
      reason: banUserForm.value.reason || null,
      is_permanent: banUserForm.value.is_permanent,
    }

    if (!banUserForm.value.is_permanent && banUserForm.value.ends_at) {
      payload.ends_at = banUserForm.value.ends_at.toISOString()
    }

    await $api('/bans/users', {
      method: 'POST',
      body: payload,
    })
    toast.add({
      severity: 'success',
      summary: 'نجح',
      detail: 'تم حظر المستخدم بنجاح',
      life: 3000,
    })
    showBanUserDialog.value = false
    banUserForm.value = {
      user: null,
      reason: '',
      is_permanent: false,
      ends_at: null,
    }
    fetchBannedUsers()
  } catch (error: any) {
    console.error('Error banning user:', error)
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.data?.message || 'فشل حظر المستخدم',
      life: 3000,
    })
  } finally {
    banningUser.value = false
  }
}

// Unban user
const confirmUnbanUser = (bannedUser: any) => {
  confirm.require({
    message: `هل أنت متأكد من إلغاء حظر المستخدم "${bannedUser.username}"؟`,
    header: 'تأكيد إلغاء الحظر',
    icon: 'pi pi-exclamation-triangle',
    accept: async () => {
      try {
        await $api(`/bans/users/${bannedUser.id}`, {
          method: 'DELETE',
        })
        toast.add({
          severity: 'success',
          summary: 'نجح',
          detail: 'تم إلغاء حظر المستخدم بنجاح',
          life: 3000,
        })
        fetchBannedUsers()
      } catch (error: any) {
        console.error('Error unbanning user:', error)
        toast.add({
          severity: 'error',
          summary: 'خطأ',
          detail: 'فشل إلغاء حظر المستخدم',
          life: 3000,
        })
      }
    },
  })
}

const debouncedSearch = useDebounceFn(() => {
  currentPage.value = 1
  fetchBannedUsers()
}, 500)

const onPageChange = (event: any) => {
  currentPage.value = event.page + 1
  perPage.value = event.rows
  fetchBannedUsers()
}

const formatDate = (dateString: string) => {
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

onMounted(() => {
  fetchBannedBrowsers()
  fetchBannedOS()
  fetchBannedUsers()
})
</script>

<style scoped>
:deep(.p-datatable .p-datatable-thead > tr > th) {
  background-color: #f9fafb;
  font-weight: 600;
}
</style>

