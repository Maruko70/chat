<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h2 class="text-2xl font-bold text-gray-900">إدارة المستخدمين</h2>
    </div>

    <!-- Search and Filter -->
    <div class="flex gap-4">
      <InputText
        v-model="searchQuery"
        placeholder="البحث بالاسم أو اسم المستخدم أو البريد الإلكتروني..."
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
        @change="fetchUsers"
      />
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <i class="pi pi-spin pi-spinner text-4xl text-gray-400"></i>
      <p class="mt-4 text-gray-500">جاري التحميل...</p>
    </div>

    <!-- Users Table -->
    <DataTable
      v-else-if="!loading"
      :value="users"
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
      <Column header="المستخدم">
        <template #body="{ data }">
          <div class="flex items-center gap-3">
            <Avatar
              :image="data.avatar_url || getDefaultUserImage()"
              shape="circle"
              size="small"
            />
            <div>
              <div class="font-medium">{{ data.name || data.username }}</div>
              <div class="text-sm text-gray-500">@{{ data.username }}</div>
            </div>
          </div>
        </template>
      </Column>
      <Column field="email" header="البريد الإلكتروني">
        <template #body="{ data }">
          <span class="text-sm">{{ data.email || '-' }}</span>
        </template>
      </Column>
      <Column header="مجموعات الأدوار">
        <template #body="{ data }">
          <div class="flex flex-wrap gap-1">
            <Tag
              v-for="roleGroup in (data.role_groups || []).slice(0, 2)"
              :key="roleGroup.id"
              :value="roleGroup.name"
              severity="info"
              class="text-xs"
            />
            <Tag
              v-if="(data.role_groups || []).length > 2"
              :value="`+${(data.role_groups || []).length - 2}`"
              severity="secondary"
              class="text-xs"
            />
            <span v-if="!data.role_groups || data.role_groups.length === 0" class="text-gray-400 text-sm">-</span>
          </div>
        </template>
      </Column>
      <Column field="is_guest" header="نوع المستخدم" sortable>
        <template #body="{ data }">
          <div class="flex flex-col gap-1">
            <Tag
              :value="data.is_guest ? 'زائر' : 'عضو'"
              :severity="data.is_guest ? 'warning' : 'success'"
            />
            <Tag
              v-if="data.is_blocked"
              value="محظور"
              severity="danger"
              class="text-xs"
            />
          </div>
        </template>
      </Column>
      <Column field="ip_address" header="عنوان IP" sortable>
        <template #body="{ data }">
          <span class="text-sm font-mono">{{ data.ip_address || '-' }}</span>
        </template>
      </Column>
      <Column field="country" header="البلد" sortable>
        <template #body="{ data }">
          <div class="flex items-center gap-2">
            <span v-if="data.country" class="text-sm">{{ data.country }}</span>
            <span v-else class="text-gray-400 text-sm">-</span>
          </div>
        </template>
      </Column>
      <Column field="created_at" header="تاريخ التسجيل" sortable>
        <template #body="{ data }">
          <span class="text-sm">{{ formatDate(data.created_at) }}</span>
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
              @click="viewUserDetails(data)"
              v-tooltip.top="'عرض التفاصيل'"
            />
            <Button
              v-if="!data.is_blocked"
              icon="pi pi-ban"
              text
              rounded
              severity="danger"
              @click="openBanDialog(data)"
              v-tooltip.top="'حظر مستخدم'"
              :disabled="data.id === currentUserId"
            />
            <Button
              v-else
              icon="pi pi-unlock"
              text
              rounded
              severity="success"
              @click="unbanUser(data)"
              v-tooltip.top="'إلغاء حظر المستخدم'"
              :disabled="data.id === currentUserId"
            />
            <Button
              icon="pi pi-trash"
              text
              rounded
              severity="danger"
              @click="confirmDelete(data)"
              v-tooltip.top="'حذف'"
              :disabled="data.id === currentUserId"
            />
          </div>
        </template>
      </Column>
    </DataTable>

    <!-- Edit User Dialog -->
    <Dialog
      v-model:visible="editDialogVisible"
      header="تعديل المستخدم"
      :modal="true"
      :style="{ width: '700px', maxHeight: '90vh' }"
      :draggable="false"
      class="overflow-y-auto"
    >
      <div v-if="editingUser" class="space-y-6">
        <!-- Basic Info Section -->
        <div class="space-y-4">
          <h3 class="text-lg font-semibold border-b pb-2">المعلومات الأساسية</h3>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-2">الاسم</label>
              <InputText
                v-model="userForm.name"
                class="w-full"
                :class="{ 'p-invalid': errors.name }"
              />
              <small v-if="errors.name" class="p-error">{{ errors.name }}</small>
            </div>

            <div>
              <label class="block text-sm font-medium mb-2">اسم المستخدم</label>
              <InputText
                v-model="userForm.username"
                class="w-full"
                :class="{ 'p-invalid': errors.username }"
              />
              <small v-if="errors.username" class="p-error">{{ errors.username }}</small>
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium mb-2">السيرة الذاتية</label>
            <Textarea
              v-model="userForm.bio"
              class="w-full"
              rows="3"
              :autoResize="true"
            />
          </div>

          <div class="flex items-center gap-2 pt-2">
            <Checkbox
              v-model="userForm.is_guest"
              inputId="is_guest"
              :binary="true"
            />
            <label for="is_guest" class="cursor-pointer">مستخدم زائر</label>
          </div>

          <div class="flex justify-end">
            <Button
              type="button"
              label="حفظ المعلومات الأساسية"
              :loading="saving"
              @click="saveBasicInfo"
            />
          </div>
        </div>

        <!-- Password Section -->
        <div class="border-t pt-4">
          <h3 class="text-lg font-semibold border-b pb-2 mb-4">كلمة المرور</h3>
          <div>
            <label class="block text-sm font-medium mb-2">كلمة المرور الجديدة</label>
            <InputText
              v-model="userForm.password"
              type="password"
              class="w-full"
              placeholder="أدخل كلمة المرور الجديدة"
            />
            <small class="text-gray-500 text-xs mt-1">سيتم تحديث كلمة المرور فقط إذا تم إدخال قيمة</small>
          </div>
          <div class="flex justify-end mt-4">
            <Button
              type="button"
              label="حفظ كلمة المرور"
              :loading="savingPassword"
              @click="savePassword"
            />
          </div>
        </div>

        <!-- Membership Toggles Section -->
        <div class="border-t pt-4">
          <h3 class="text-lg font-semibold border-b pb-2 mb-4">إعدادات العضوية</h3>
          <div class="space-y-2">
            <div class="flex items-center gap-2">
              <Checkbox
                v-model="userForm.premium_entry"
                inputId="premium_entry"
                :binary="true"
              />
              <label for="premium_entry" class="cursor-pointer">دخول مميز</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox
                v-model="userForm.designed_membership"
                inputId="designed_membership"
                :binary="true"
              />
              <label for="designed_membership" class="cursor-pointer">عضوية مصممة</label>
            </div>
            <div class="flex items-center gap-2">
              <Checkbox
                v-model="userForm.verify_membership"
                inputId="verify_membership"
                :binary="true"
              />
              <label for="verify_membership" class="cursor-pointer">عضوية موثقة</label>
            </div>
          </div>
          <div class="flex justify-end mt-4">
            <Button
              type="button"
              label="حفظ إعدادات العضوية"
              :loading="savingMembership"
              @click="saveMembershipSettings"
            />
          </div>
        </div>

        <!-- Role Group Section -->
        <div class="border-t pt-4">
          <h3 class="text-lg font-semibold border-b pb-2 mb-4">مجموعة الدور</h3>
          <div class="space-y-3">
            <!-- Current Role Group -->
            <div v-if="userForm.selectedRoleGroup" class="p-3 bg-gray-50 rounded">
              <div class="flex items-center justify-between">
                <div class="flex-1">
                  <div class="font-medium">{{ userForm.selectedRoleGroup.name }}</div>
                  <div v-if="userForm.roleGroupExpiration" class="text-xs text-gray-500 mt-1">
                    ينتهي في: {{ formatDate(userForm.roleGroupExpiration) }}
                  </div>
                  <div v-else class="text-xs text-gray-500 mt-1">دون انتهاء</div>
                </div>
                <Button
                  icon="pi pi-times"
                  text
                  rounded
                  size="small"
                  severity="danger"
                  @click="removeRoleGroup"
                  v-tooltip.top="'إزالة'"
                />
              </div>
            </div>
            <div v-else class="text-sm text-gray-500 mb-4">لا توجد مجموعة دور</div>

            <!-- Select Role Group -->
            <div class="space-y-2">
              <Select
                v-model="selectedRoleGroupId"
                :options="availableRoleGroups"
                optionLabel="name"
                optionValue="id"
                placeholder="اختر مجموعة دور"
                class="w-full"
                :disabled="loadingRoleGroups"
              />
              <div v-if="selectedRoleGroupId" class="flex items-center gap-2 p-2 bg-gray-50 rounded">
                <Checkbox
                  inputId="add-with-expiration"
                  :binary="true"
                  :modelValue="newRoleGroupExpiration !== null"
                  @update:modelValue="toggleNewRoleGroupExpiration($event)"
                />
                <label for="add-with-expiration" class="cursor-pointer text-sm">محدد المدة</label>
                <Calendar
                  v-if="newRoleGroupExpiration !== null"
                  v-model="newRoleGroupExpiration"
                  inputId="new-role-group-expiration"
                  dateFormat="yy-mm-dd"
                  :showTime="true"
                  hourFormat="24"
                  :minDate="new Date()"
                  placeholder="تاريخ الانتهاء"
                  class="flex-1"
                  size="small"
                />
              </div>
            </div>
          </div>
          <div class="flex justify-end mt-4">
            <Button
              type="button"
              label="حفظ مجموعة الدور"
              :loading="savingRoleGroup"
              @click="saveRoleGroup"
              :disabled="!selectedRoleGroupId"
            />
          </div>
        </div>

        <div class="flex justify-end gap-2 mt-6 border-t pt-4">
          <Button
            label="إلغاء"
            severity="secondary"
            @click="closeEditDialog"
            type="button"
          />
        </div>
      </div>
    </Dialog>

    <!-- User Details Dialog -->
    <Dialog
      v-model:visible="detailsDialogVisible"
      header="تفاصيل المستخدم"
      :modal="true"
      :style="{ width: '700px' }"
      :draggable="false"
    >
      <div v-if="selectedUser" class="space-y-4">
        <div class="flex items-center gap-4 mb-4">
          <Avatar
            :image="selectedUser.avatar_url || getDefaultUserImage()"
            shape="circle"
            size="large"
          />
          <div>
            <h3 class="text-xl font-semibold">{{ selectedUser.name || selectedUser.username }}</h3>
            <p class="text-gray-500">@{{ selectedUser.username }}</p>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-sm font-medium text-gray-600">البريد الإلكتروني:</label>
            <p class="text-gray-800">{{ selectedUser.email || '-' }}</p>
          </div>
          <div>
            <label class="text-sm font-medium text-gray-600">الهاتف:</label>
            <p class="text-gray-800">{{ selectedUser.phone || '-' }}</p>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-sm font-medium text-gray-600">عنوان IP:</label>
            <p class="text-gray-800 font-mono text-sm">{{ selectedUser.ip_address || '-' }}</p>
          </div>
          <div>
            <label class="text-sm font-medium text-gray-600">البلد:</label>
            <p class="text-gray-800">{{ selectedUser.country || '-' }}</p>
          </div>
        </div>

        <div v-if="selectedUser.bio">
          <label class="text-sm font-medium text-gray-600">السيرة الذاتية:</label>
          <p class="text-gray-800">{{ selectedUser.bio }}</p>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-sm font-medium text-gray-600">نوع المستخدم:</label>
            <Tag
              :value="selectedUser.is_guest ? 'زائر' : 'عضو'"
              :severity="selectedUser.is_guest ? 'warning' : 'success'"
            />
          </div>
        </div>

        <div v-if="selectedUser.role_groups && selectedUser.role_groups.length > 0">
          <label class="text-sm font-medium text-gray-600 mb-2 block">مجموعات الأدوار:</label>
          <div class="flex flex-wrap gap-2">
            <Tag
              v-for="roleGroup in selectedUser.role_groups"
              :key="roleGroup.id"
              :value="roleGroup.name"
              severity="info"
            />
          </div>
        </div>

        <div v-if="selectedUser.all_permissions && selectedUser.all_permissions.length > 0">
          <label class="text-sm font-medium text-gray-600 mb-2 block">الصلاحيات:</label>
          <div class="flex flex-wrap gap-2">
            <Badge
              v-for="permission in selectedUser.all_permissions.slice(0, 10)"
              :key="permission"
              :value="getPermissionLabel(permission)"
              severity="secondary"
              class="text-xs"
            />
            <Badge
              v-if="selectedUser.all_permissions.length > 10"
              :value="`+${selectedUser.all_permissions.length - 10} أخرى`"
              severity="info"
              class="text-xs"
            />
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="text-sm font-medium text-gray-600">تاريخ التسجيل:</label>
            <p class="text-gray-800">{{ formatDate(selectedUser.created_at) }}</p>
          </div>
          <div>
            <label class="text-sm font-medium text-gray-600">آخر تحديث:</label>
            <p class="text-gray-800">{{ formatDate(selectedUser.updated_at) }}</p>
          </div>
        </div>

        <div class="flex justify-end gap-2 mt-6 border-t pt-4">
          <Button
            v-if="!selectedUser.is_blocked"
            label="حظر المستخدم"
            icon="pi pi-ban"
            severity="danger"
            @click="openBanDialog(selectedUser)"
            :disabled="selectedUser.id === currentUserId"
          />
          <Button
            v-else
            label="إلغاء حظر المستخدم"
            icon="pi pi-unlock"
            severity="success"
            @click="unbanUser(selectedUser)"
            :disabled="selectedUser.id === currentUserId"
          />
        </div>
      </div>
    </Dialog>

    <!-- Ban User Dialog -->
    <Dialog
      v-model:visible="banDialogVisible"
      header="حظر مستخدم"
      :modal="true"
      :style="{ width: '600px' }"
      :draggable="false"
    >
      <form @submit.prevent="banUser" class="space-y-4">
        <div v-if="banningUserData">
          <label class="block text-sm font-medium mb-2">المستخدم</label>
          <div class="flex items-center gap-2 p-2 bg-gray-50 rounded">
            <Avatar
              :image="banningUserData.avatar_url || getDefaultUserImage()"
              shape="circle"
              size="small"
            />
            <div>
              <div class="font-medium">{{ banningUserData.name || banningUserData.username }}</div>
              <div class="text-xs text-gray-500">@{{ banningUserData.username }}</div>
            </div>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium mb-2">السبب (اختياري)</label>
          <Textarea
            v-model="banForm.reason"
            class="w-full"
            rows="3"
            :autoResize="true"
            placeholder="أدخل سبب الحظر..."
          />
        </div>
        <div class="flex items-center gap-2">
          <Checkbox
            v-model="banForm.is_permanent"
            inputId="is_permanent"
            :binary="true"
          />
          <label for="is_permanent" class="cursor-pointer">حظر دائم</label>
        </div>
        <div v-if="!banForm.is_permanent">
          <label class="block text-sm font-medium mb-2">تاريخ الانتهاء</label>
          <Calendar
            v-model="banForm.ends_at"
            showIcon
            dateFormat="yy-mm-dd"
            :showTime="true"
            hourFormat="24"
            :minDate="new Date()"
            class="w-full"
          />
        </div>
        <div class="flex justify-end gap-2">
          <Button
            type="button"
            label="إلغاء"
            severity="secondary"
            @click="closeBanDialog"
          />
          <Button
            type="submit"
            label="حظر"
            severity="danger"
            :loading="banningUser"
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
import type { User, RoleGroup } from '~~/types'
import { PERMISSIONS_LIST } from '~~/types'

const { $api } = useNuxtApp()
const confirm = useConfirm()
const toast = useToast()
const authStore = useAuthStore()
const { getDefaultUserImage } = useSiteSettings()

// State
const users = ref<User[]>([])
const loading = ref(false)
const saving = ref(false)
const savingPassword = ref(false)
const savingMembership = ref(false)
const savingRoleGroup = ref(false)
const searchQuery = ref('')
const filterGuest = ref<boolean | null>(null)
const editDialogVisible = ref(false)
const detailsDialogVisible = ref(false)
const banDialogVisible = ref(false)
const editingUser = ref<User | null>(null)
const selectedUser = ref<User | null>(null)
const banningUserData = ref<User | null>(null)
const banningUser = ref(false)
const unbanningUser = ref(false)
const currentPage = ref(1)
const perPage = ref(20)
const totalRecords = ref(0)

const banForm = ref({
  reason: '',
  is_permanent: false,
  ends_at: null as Date | null,
})

const filterOptions = [
  { label: 'الكل', value: null },
  { label: 'أعضاء', value: false },
  { label: 'زوار', value: true },
]

const userForm = ref({
  name: '',
  username: '',
  bio: '',
  password: '',
  is_guest: false,
  premium_entry: false,
  designed_membership: false,
  verify_membership: false,
  selectedRoleGroup: null as (RoleGroup & { pivot?: { expires_at?: string | null } }) | null,
  roleGroupExpiration: null as string | null,
})

const errors = ref<Record<string, string>>({})

const currentUserId = computed(() => authStore.user?.id)

// Role Groups Management
const availableRoleGroups = ref<RoleGroup[]>([])
const loadingRoleGroups = ref(false)
const selectedRoleGroupId = ref<number | null>(null)
const newRoleGroupExpiration = ref<Date | null>(null)

// Debounced search
let searchTimeout: ReturnType<typeof setTimeout> | null = null
const debouncedSearch = () => {
  if (searchTimeout) {
    clearTimeout(searchTimeout)
  }
  searchTimeout = setTimeout(() => {
    currentPage.value = 1
    fetchUsers()
  }, 500)
}

// Methods
const fetchUsers = async () => {
  // Wait for authentication to be ready
  if (!authStore.isAuthenticated) {
    console.warn('Not authenticated, skipping fetchUsers')
    return
  }

  loading.value = true
  try {
    const params = new URLSearchParams()
    if (searchQuery.value) {
      params.append('search', searchQuery.value)
    }
    if (filterGuest.value !== null) {
      params.append('is_guest', filterGuest.value.toString())
    }
    params.append('page', currentPage.value.toString())
    params.append('per_page', perPage.value.toString())
    
    const queryString = params.toString()
    console.log('Fetching users with query:', queryString)
    const response = await $api(`/users?${queryString}`)
    console.log('Users response:', response)
    console.log('Response type:', typeof response)
    console.log('Response keys:', Object.keys(response || {}))
    
    // Laravel paginate() returns: { data: [...], total: X, per_page: Y, current_page: Z, ... }
    // Handle both direct response and nested response.data
    let usersData = []
    let total = 0
    let currentPageFromResponse = currentPage.value
    
    if (Array.isArray(response)) {
      // If response is directly an array (shouldn't happen with pagination, but handle it)
      usersData = response
      total = response.length
    } else if (response && response.data) {
      // Standard Laravel pagination response
      usersData = Array.isArray(response.data) ? response.data : []
      total = response.total || 0
      currentPageFromResponse = response.current_page || currentPage.value
    } else if (response && Array.isArray(response)) {
      usersData = response
      total = response.length
    }
    
    console.log('Extracted usersData:', usersData)
    console.log('Extracted total:', total)
    console.log('Extracted current_page:', currentPageFromResponse)
    
    // Assign the data
    users.value = usersData
    totalRecords.value = total
    currentPage.value = currentPageFromResponse
    
    console.log(`Loaded ${users.value.length} users out of ${totalRecords.value} total`)
    console.log('Users array:', users.value)
    console.log('Users array length:', users.value.length)
    console.log('Current page:', currentPage.value, 'Per page:', perPage.value)
    
    // Force reactivity update
    await nextTick()
    console.log('After nextTick - Users:', users.value.length, 'Total records:', totalRecords.value)
  } catch (error: any) {
    console.error('Error fetching users:', error)
    console.error('Error details:', {
      message: error.message,
      response: error.response,
      data: error.data,
    })
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.message || error.data?.message || 'فشل تحميل المستخدمين',
      life: 3000,
    })
    users.value = []
    totalRecords.value = 0
  } finally {
    loading.value = false
  }
}

const onPageChange = (event: any) => {
  console.log('Page change event:', event)
  // PrimeVue DataTable uses 0-based page index
  currentPage.value = event.page + 1
  perPage.value = event.rows
  fetchUsers()
}

const formatDate = (date: string | null | undefined): string => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('ar-SA', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  })
}

const getPermissionLabel = (key: string): string => {
  const permission = PERMISSIONS_LIST.find(p => p.key === key)
  return permission?.label || key
}

const openEditDialog = async (user: User) => {
  editingUser.value = user
  
  // Fetch full user data with role groups
  try {
    const fullUser = await $api(`/users/${user.id}`)
    editingUser.value = fullUser
    
    // Get the first role group (only one allowed)
    const firstRoleGroup = (fullUser.role_groups || [])[0] || null
    
    userForm.value = {
      name: fullUser.name || '',
      username: fullUser.username || '',
      bio: fullUser.bio || '',
      password: '',
      is_guest: fullUser.is_guest || false,
      premium_entry: fullUser.premium_entry || false,
      designed_membership: fullUser.designed_membership || false,
      verify_membership: fullUser.verify_membership || false,
      selectedRoleGroup: firstRoleGroup ? {
        ...firstRoleGroup,
        pivot: firstRoleGroup.pivot || { expires_at: null },
      } : null,
      roleGroupExpiration: firstRoleGroup?.pivot?.expires_at || null,
    }
  } catch (error: any) {
    // Fallback to basic user data if fetch fails
    const firstRoleGroup = (user.role_groups || [])[0] || null
    
    userForm.value = {
      name: user.name || '',
      username: user.username || '',
      bio: user.bio || '',
      password: '',
      is_guest: user.is_guest || false,
      premium_entry: user.premium_entry || false,
      designed_membership: user.designed_membership || false,
      verify_membership: user.verify_membership || false,
      selectedRoleGroup: firstRoleGroup ? {
        ...firstRoleGroup,
        pivot: firstRoleGroup.pivot || { expires_at: null },
      } : null,
      roleGroupExpiration: firstRoleGroup?.pivot?.expires_at || null,
    }
  }
  
  errors.value = {}
  selectedRoleGroupId.value = null
  newRoleGroupExpiration.value = null
  
  // Fetch available role groups
  await fetchRoleGroups()
  
  editDialogVisible.value = true
}

const closeEditDialog = () => {
  editDialogVisible.value = false
  editingUser.value = null
  userForm.value = {
    name: '',
    username: '',
    bio: '',
    password: '',
    is_guest: false,
    premium_entry: false,
    designed_membership: false,
    verify_membership: false,
    selectedRoleGroup: null,
    roleGroupExpiration: null,
  }
  errors.value = {}
  selectedRoleGroupId.value = null
  newRoleGroupExpiration.value = null
}

const saveBasicInfo = async () => {
  if (!editingUser.value) return
  
  errors.value = {}
  
  if (!userForm.value.username.trim()) {
    errors.value.username = 'اسم المستخدم مطلوب'
    return
  }

  saving.value = true
  try {
    const payload: any = {
      name: userForm.value.name || null,
      username: userForm.value.username.trim(),
      bio: userForm.value.bio || null,
      is_guest: userForm.value.is_guest,
    }

    await $api(`/users/${editingUser.value.id}`, {
      method: 'PUT',
      body: payload,
    })
    
    toast.add({
      severity: 'success',
      summary: 'نجح',
      detail: 'تم تحديث المعلومات الأساسية بنجاح',
      life: 3000,
    })
    
    await fetchUsers()
  } catch (error: any) {
    const errorData = error.data || {}
    if (errorData.errors) {
      errors.value = errorData.errors
    } else {
      toast.add({
        severity: 'error',
        summary: 'خطأ',
        detail: error.message || 'فشل تحديث المعلومات',
        life: 3000,
      })
    }
  } finally {
    saving.value = false
  }
}

const savePassword = async () => {
  if (!editingUser.value) return
  
  if (!userForm.value.password.trim()) {
    toast.add({
      severity: 'warn',
      summary: 'تحذير',
      detail: 'يرجى إدخال كلمة مرور جديدة',
      life: 3000,
    })
    return
  }

  savingPassword.value = true
  try {
    await $api(`/users/${editingUser.value.id}`, {
      method: 'PUT',
      body: {
        password: userForm.value.password.trim(),
      },
    })
    
    toast.add({
      severity: 'success',
      summary: 'نجح',
      detail: 'تم تحديث كلمة المرور بنجاح',
      life: 3000,
    })
    
    userForm.value.password = ''
    await fetchUsers()
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.message || 'فشل تحديث كلمة المرور',
      life: 3000,
    })
  } finally {
    savingPassword.value = false
  }
}

const saveMembershipSettings = async () => {
  if (!editingUser.value) return

  savingMembership.value = true
  try {
    await $api(`/users/${editingUser.value.id}`, {
      method: 'PUT',
      body: {
        premium_entry: userForm.value.premium_entry,
        designed_membership: userForm.value.designed_membership,
        verify_membership: userForm.value.verify_membership,
      },
    })
    
    toast.add({
      severity: 'success',
      summary: 'نجح',
      detail: 'تم تحديث إعدادات العضوية بنجاح',
      life: 3000,
    })
    
    await fetchUsers()
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.message || 'فشل تحديث إعدادات العضوية',
      life: 3000,
    })
  } finally {
    savingMembership.value = false
  }
}

const viewUserDetails = async (user: User) => {
  try {
    const fullUser = await $api(`/users/${user.id}`)
    selectedUser.value = fullUser
    detailsDialogVisible.value = true
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.message || 'فشل تحميل تفاصيل المستخدم',
      life: 3000,
    })
  }
}

const openBanDialog = (user: User) => {
  if (user.id === currentUserId.value) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: 'لا يمكنك حظر نفسك',
      life: 3000,
    })
    return
  }
  
  banningUserData.value = user
  banForm.value = {
    reason: '',
    is_permanent: false,
    ends_at: null,
  }
  banDialogVisible.value = true
}

const closeBanDialog = () => {
  banDialogVisible.value = false
  banningUserData.value = null
  banForm.value = {
    reason: '',
    is_permanent: false,
    ends_at: null,
  }
}

const banUser = async () => {
  if (!banningUserData.value) return

  banningUser.value = true
  try {
    const payload: any = {
      user_id: banningUserData.value.id,
      reason: banForm.value.reason || null,
      is_permanent: banForm.value.is_permanent,
    }

    if (!banForm.value.is_permanent && banForm.value.ends_at) {
      payload.ends_at = banForm.value.ends_at.toISOString()
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
    
    closeBanDialog()
    await fetchUsers()
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.data?.message || error.message || 'فشل حظر المستخدم',
      life: 3000,
    })
  } finally {
    banningUser.value = false
  }
}

const unbanUser = async (user: User) => {
  // Prevent multiple calls
  if (unbanningUser.value) {
    return
  }

  if (user.id === currentUserId.value) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: 'لا يمكنك إلغاء حظر نفسك',
      life: 3000,
    })
    return
  }

  unbanningUser.value = true

  // First, find the ban record for this user
  try {
    const bannedUsers = await $api('/bans/users?active_only=true')
    const bannedUser = Array.isArray(bannedUsers?.data) 
      ? bannedUsers.data.find((bu: any) => bu.user_id === user.id)
      : bannedUsers?.find((bu: any) => bu.user_id === user.id)

    if (!bannedUser) {
      toast.add({
        severity: 'error',
        summary: 'خطأ',
        detail: 'لم يتم العثور على سجل الحظر',
        life: 3000,
      })
      unbanningUser.value = false
      return
    }

    confirm.require({
      message: `هل أنت متأكد من إلغاء حظر المستخدم "${user.name || user.username}"؟`,
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
          
          await fetchUsers()
        } catch (error: any) {
          toast.add({
            severity: 'error',
            summary: 'خطأ',
            detail: error.data?.message || error.message || 'فشل إلغاء حظر المستخدم',
            life: 3000,
          })
        } finally {
          unbanningUser.value = false
        }
      },
      reject: () => {
        unbanningUser.value = false
      },
    })
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: 'فشل تحميل بيانات الحظر',
      life: 3000,
    })
    unbanningUser.value = false
  }
}

const confirmDelete = (user: User) => {
  if (user.id === currentUserId.value) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: 'لا يمكنك حذف نفسك',
      life: 3000,
    })
    return
  }

  confirm.require({
    message: `هل أنت متأكد من حذف المستخدم "${user.name || user.username}"؟ سيتم حذف جميع بيانات المستخدم بشكل نهائي.`,
    header: 'تأكيد الحذف',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: 'نعم',
    rejectLabel: 'لا',
    accept: async () => {
      try {
        await $api(`/users/${user.id}`, {
          method: 'DELETE',
        })
        toast.add({
          severity: 'success',
          summary: 'نجح',
          detail: 'تم حذف المستخدم بنجاح',
          life: 3000,
        })
        await fetchUsers()
      } catch (error: any) {
        toast.add({
          severity: 'error',
          summary: 'خطأ',
          detail: error.message || 'فشل حذف المستخدم',
          life: 3000,
        })
      }
    },
  })
}

// Role Groups Management Functions
const fetchRoleGroups = async () => {
  loadingRoleGroups.value = true
  try {
    availableRoleGroups.value = await $api('/role-groups?is_active=true')
  } catch (error: any) {
    console.error('Error fetching role groups:', error)
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: 'فشل تحميل مجموعات الأدوار',
      life: 3000,
    })
  } finally {
    loadingRoleGroups.value = false
  }
}

const toggleNewRoleGroupExpiration = (hasExpiration: boolean) => {
  if (hasExpiration) {
    // Set default expiration to 30 days from now
    const defaultDate = new Date()
    defaultDate.setDate(defaultDate.getDate() + 30)
    newRoleGroupExpiration.value = defaultDate
  } else {
    newRoleGroupExpiration.value = null
  }
}

const removeRoleGroup = () => {
  userForm.value.selectedRoleGroup = null
  userForm.value.roleGroupExpiration = null
  selectedRoleGroupId.value = null
}

const saveRoleGroup = async () => {
  if (!editingUser.value) return
  
  if (!selectedRoleGroupId.value) {
    toast.add({
      severity: 'warn',
      summary: 'تحذير',
      detail: 'يرجى اختيار مجموعة دور',
      life: 3000,
    })
    return
  }

  savingRoleGroup.value = true
  try {
    const roleGroup = availableRoleGroups.value.find(rg => rg.id === selectedRoleGroupId.value)
    if (!roleGroup) return

    // Remove all existing role groups first
    const currentRoleGroups = editingUser.value.role_groups || []
    for (const rg of currentRoleGroups) {
      try {
        await $api(`/role-groups/${rg.id}/users`, {
          method: 'DELETE',
          body: {
            user_ids: [editingUser.value.id],
          },
        })
      } catch (error: any) {
        console.error(`Error removing role group ${rg.id}:`, error)
      }
    }

    // Add the new role group
    const expiresAt = newRoleGroupExpiration.value
      ? newRoleGroupExpiration.value.toISOString()
      : null

    await $api(`/role-groups/${selectedRoleGroupId.value}/users`, {
      method: 'POST',
      body: {
        user_ids: [editingUser.value.id],
        expires_at: expiresAt,
      },
    })

    // Update local form state
    userForm.value.selectedRoleGroup = {
      ...roleGroup,
      pivot: { expires_at: expiresAt },
    }
    userForm.value.roleGroupExpiration = expiresAt
    
    toast.add({
      severity: 'success',
      summary: 'نجح',
      detail: 'تم تحديث مجموعة الدور بنجاح',
      life: 3000,
    })
    
    selectedRoleGroupId.value = null
    newRoleGroupExpiration.value = null
    await fetchUsers()
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.message || 'فشل تحديث مجموعة الدور',
      life: 3000,
    })
  } finally {
    savingRoleGroup.value = false
  }
}

// Lifecycle
onMounted(async () => {
  // Wait for authentication to be initialized
  if (!authStore.isAuthenticated) {
    // Wait a bit for auth to initialize
    await new Promise(resolve => setTimeout(resolve, 100))
  }
  
  // Only fetch if authenticated
  if (authStore.isAuthenticated) {
    await fetchUsers()
  } else {
    console.warn('UsersManagement: Not authenticated on mount')
  }
})

// Watch for authentication changes
watch(() => authStore.isAuthenticated, async (isAuthenticated) => {
  if (isAuthenticated && users.value.length === 0) {
    await fetchUsers()
  }
})
</script>

