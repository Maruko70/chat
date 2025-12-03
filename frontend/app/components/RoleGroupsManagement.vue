<template>
  <div class="space-y-6">
    <!-- Header with Add Button -->
    <div class="flex items-center justify-between">
      <h2 class="text-2xl font-bold text-gray-900">مجموعات الأدوار</h2>
      <Button
        icon="pi pi-plus"
        label="إضافة مجموعة دور جديدة"
        @click="openCreateDialog"
        severity="success"
      />
    </div>

    <!-- Search and Filter -->
    <div class="flex gap-4">
      <InputText
        v-model="searchQuery"
        placeholder="البحث..."
        class="flex-1"
        @input="fetchRoleGroups"
      />
      <Select
        v-model="filterActive"
        :options="filterOptions"
        optionLabel="label"
        optionValue="value"
        placeholder="الحالة"
        class="w-48"
        @change="fetchRoleGroups"
      />
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <i class="pi pi-spin pi-spinner text-4xl text-gray-400"></i>
      <p class="mt-4 text-gray-500">جاري التحميل...</p>
    </div>

    <!-- Role Groups Table -->
    <DataTable
      v-else
      :value="roleGroups"
      :paginator="true"
      :rows="10"
      :rowsPerPageOptions="[5, 10, 20, 50]"
      paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
      currentPageReportTemplate="{first} إلى {last} من {totalRecords}"
      class="p-datatable-sm"
      stripedRows
    >
      <Column field="name" header="الاسم" sortable>
        <template #body="{ data }">
          <div class="flex items-center gap-2">
            <img
              v-if="data.banner"
              :src="data.banner"
              :alt="data.name"
              class="w-8 h-8 object-contain"
            />
            <span class="font-medium">{{ data.name }}</span>
          </div>
        </template>
      </Column>
      <Column field="priority" header="الأولوية" sortable>
        <template #body="{ data }">
          <Tag :value="data.priority" severity="info" />
        </template>
      </Column>
      <Column field="users_count" header="عدد المستخدمين" sortable>
        <template #body="{ data }">
          <Badge :value="data.users_count || 0" severity="secondary" />
        </template>
      </Column>
      <Column header="الصلاحيات">
        <template #body="{ data }">
          <Badge
            :value="(data.permissions || []).length"
            severity="info"
            v-tooltip.top="getPermissionsTooltip(data.permissions)"
          />
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
              icon="pi pi-users"
              text
              rounded
              severity="secondary"
              @click="openUsersDialog(data)"
              v-tooltip.top="'إدارة المستخدمين'"
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
      :header="editingRoleGroup ? 'تعديل مجموعة الدور' : 'إضافة مجموعة دور جديدة'"
      :modal="true"
      :style="{ width: '700px', maxHeight: '90vh' }"
      :draggable="false"
      class="overflow-y-auto"
    >
      <form @submit.prevent="saveRoleGroup" class="space-y-4">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium mb-2">الاسم *</label>
            <InputText
              v-model="formData.name"
              class="w-full"
              :class="{ 'p-invalid': errors.name }"
              required
            />
            <small v-if="errors.name" class="p-error">{{ errors.name }}</small>
          </div>

          <div>
            <label class="block text-sm font-medium mb-2">البانر/الرمز</label>
            <div class="flex gap-2">
            <InputText
              v-model="formData.banner"
              placeholder="رابط الصورة أو اختر من الرموز"
              class="flex-1"
              :value="formData.banner || ''"
            />
              <Button
                icon="pi pi-image"
                label="اختر رمز"
                @click="openSymbolSelector"
                type="button"
              />
            </div>
            <div v-if="formData.banner" class="mt-2">
              <img
                :src="formData.banner"
                alt="Banner preview"
                class="w-16 h-16 object-contain border rounded"
              />
            </div>
          </div>

          <div>
            <label class="block text-sm font-medium mb-2">الأولوية</label>
            <InputNumber
              v-model="formData.priority"
              class="w-full"
              :min="0"
            />
            <small class="text-gray-500 text-xs mt-1">كلما زادت القيمة، زادت الأولوية</small>
          </div>

          <div>
            <label class="block text-sm font-medium mb-2">الحالة</label>
            <Select
              v-model="formData.is_active"
              :options="[
                { label: 'نشط', value: true },
                { label: 'غير نشط', value: false },
              ]"
              optionLabel="label"
              optionValue="value"
              class="w-full"
            />
          </div>

          <div>
            <label class="block text-sm font-medium mb-2">الصلاحيات</label>
            <div class="border rounded p-4 max-h-96 overflow-y-auto">
              <div class="space-y-2">
                <div
                  v-for="permission in PERMISSIONS_LIST"
                  :key="permission.key"
                  class="flex items-center gap-2"
                >
                  <Checkbox
                    :inputId="permission.key"
                    :binary="true"
                    :modelValue="formData.permissions.includes(permission.key)"
                    @update:modelValue="togglePermission(permission.key, $event)"
                  />
                  <label :for="permission.key" class="cursor-pointer">
                    {{ permission.label }}
                  </label>
                </div>
              </div>
            </div>
            <small class="text-gray-500 text-xs mt-1">
              تم تحديد {{ formData.permissions.length }} من {{ PERMISSIONS_LIST.length }} صلاحية
            </small>
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
            :label="editingRoleGroup ? 'تحديث' : 'إنشاء'"
            :loading="saving"
          />
        </div>
      </form>
    </Dialog>

    <!-- Symbol Selector Dialog -->
    <Dialog
      v-model:visible="symbolSelectorVisible"
      header="اختر رمز"
      :modal="true"
      :style="{ width: '600px' }"
      :draggable="false"
    >
      <div class="space-y-4">
        <div v-if="symbolsLoading" class="text-center py-8">
          <i class="pi pi-spin pi-spinner text-2xl text-gray-400"></i>
        </div>
        <div v-else-if="symbols.length === 0" class="text-center py-8 text-gray-500">
          لا توجد رموز متاحة. ارفع رمزاً جديداً من قسم الرموز.
        </div>
        <div v-else class="grid grid-cols-4 gap-4 max-h-96 overflow-y-auto">
          <div
            v-for="symbol in symbols"
            :key="symbol.id"
            class="border rounded p-2 cursor-pointer hover:bg-gray-50 transition"
            :class="{ 'ring-2 ring-primary': formData.banner === symbol.url }"
            @click="selectSymbol(symbol)"
          >
            <img
              :src="symbol.url"
              :alt="symbol.name"
              class="w-full h-16 object-contain"
            />
            <p class="text-xs text-center mt-1 truncate">{{ symbol.name }}</p>
          </div>
        </div>
      </div>
    </Dialog>

    <!-- Users Management Dialog -->
    <Dialog
      v-model:visible="usersDialogVisible"
      header="إدارة مستخدمي مجموعة الدور"
      :modal="true"
      :style="{ width: '800px' }"
      :draggable="false"
    >
      <div v-if="selectedRoleGroup" class="space-y-4">
        <div class="flex items-center justify-between mb-4">
          <h3 class="font-semibold">{{ selectedRoleGroup.name }}</h3>
          <Button
            icon="pi pi-plus"
            label="إضافة مستخدمين"
            size="small"
            @click="openAddUsersDialog"
          />
        </div>

        <DataTable
          :value="selectedRoleGroup.users || []"
          :paginator="true"
          :rows="10"
          class="p-datatable-sm"
        >
          <Column field="name" header="الاسم">
            <template #body="{ data }">
              <div class="flex items-center gap-2">
                <Avatar
                  :image="data.avatar_url"
                  shape="circle"
                  size="small"
                />
                <span>{{ data.name || data.username }}</span>
              </div>
            </template>
          </Column>
          <Column field="username" header="اسم المستخدم" />
          <Column field="email" header="البريد الإلكتروني" />
          <Column header="الإجراءات" :exportable="false">
            <template #body="{ data }">
              <Button
                icon="pi pi-times"
                text
                rounded
                severity="danger"
                @click="removeUser(data.id)"
                v-tooltip.top="'إزالة'"
              />
            </template>
          </Column>
        </DataTable>
      </div>
    </Dialog>

    <!-- Add Users Dialog -->
    <Dialog
      v-model:visible="addUsersDialogVisible"
      header="إضافة مستخدمين"
      :modal="true"
      :style="{ width: '600px' }"
      :draggable="false"
    >
      <div class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-2">البحث عن المستخدمين</label>
          <InputText
            v-model="userSearchQuery"
            placeholder="ابحث بالاسم أو اسم المستخدم..."
            class="w-full"
            @input="searchUsers"
          />
        </div>

        <div v-if="availableUsers.length > 0" class="max-h-96 overflow-y-auto">
          <div
            v-for="user in availableUsers"
            :key="user.id"
            class="flex items-center justify-between p-3 border rounded mb-2 hover:bg-gray-50"
          >
            <div class="flex items-center gap-3">
              <Avatar
                :image="user.avatar_url"
                shape="circle"
                size="small"
              />
              <div>
                <div class="font-medium">{{ user.name || user.username }}</div>
                <div class="text-sm text-gray-500">{{ user.username }}</div>
              </div>
            </div>
            <Button
              icon="pi pi-plus"
              text
              rounded
              severity="success"
              @click="addUser(user.id)"
              :disabled="isUserInGroup(user.id)"
            />
          </div>
        </div>
        <div v-else class="text-center py-8 text-gray-500">
          لا توجد نتائج
        </div>
      </div>
    </Dialog>

    <!-- Delete Confirmation -->
    <ConfirmDialog />
  </div>
</template>

<script setup lang="ts">
import { useConfirm } from 'primevue/useconfirm'
import { useToast } from 'primevue/usetoast'
import type { RoleGroup, User, Symbol } from '~~/types'
import { PERMISSIONS_LIST } from '~~/types'

const { $api } = useNuxtApp()
const confirm = useConfirm()
const toast = useToast()

// State
const roleGroups = ref<RoleGroup[]>([])
const loading = ref(false)
const saving = ref(false)
const searchQuery = ref('')
const filterActive = ref<boolean | null>(null)
const dialogVisible = ref(false)
const usersDialogVisible = ref(false)
const addUsersDialogVisible = ref(false)
const symbolSelectorVisible = ref(false)
const editingRoleGroup = ref<RoleGroup | null>(null)
const selectedRoleGroup = ref<RoleGroup | null>(null)
const availableUsers = ref<User[]>([])
const userSearchQuery = ref('')
const symbols = ref<Symbol[]>([])
const symbolsLoading = ref(false)

const filterOptions = [
  { label: 'الكل', value: null },
  { label: 'نشط', value: true },
  { label: 'غير نشط', value: false },
]

const formData = ref({
  name: '',
  slug: '',
  banner: '',
  priority: 0,
  permissions: [] as string[],
  is_active: true,
})

const errors = ref<Record<string, string>>({})

// Computed
const isUserInGroup = (userId: number) => {
  return selectedRoleGroup.value?.users?.some(u => u.id === userId) || false
}

const getPermissionsTooltip = (permissions: string[] | null | undefined) => {
  if (!permissions || permissions.length === 0) return 'لا توجد صلاحيات'
  const labels = permissions
    .map(key => PERMISSIONS_LIST.find(p => p.key === key)?.label)
    .filter(Boolean)
    .slice(0, 5)
  return labels.join(', ') + (permissions.length > 5 ? '...' : '')
}

// Methods
const fetchRoleGroups = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (searchQuery.value) {
      params.append('search', searchQuery.value)
    }
    if (filterActive.value !== null) {
      params.append('is_active', filterActive.value.toString())
    }
    
    const queryString = params.toString()
    const endpoint = `/role-groups${queryString ? `?${queryString}` : ''}`
    roleGroups.value = await $api(endpoint)
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.message || 'فشل تحميل مجموعات الأدوار',
      life: 3000,
    })
  } finally {
    loading.value = false
  }
}

const fetchSymbols = async () => {
  symbolsLoading.value = true
  try {
    // Only fetch role_group_banner type symbols
    symbols.value = await $api('/symbols?type=role_group_banner&is_active=true')
  } catch (error: any) {
    console.error('Error fetching symbols:', error)
  } finally {
    symbolsLoading.value = false
  }
}

const openCreateDialog = () => {
  editingRoleGroup.value = null
  formData.value = {
    name: '',
    slug: '',
    banner: '',
    priority: 0,
    permissions: [],
    is_active: true,
  }
  errors.value = {}
  dialogVisible.value = true
}

const openEditDialog = (roleGroup: RoleGroup) => {
  editingRoleGroup.value = roleGroup
  formData.value = {
    name: roleGroup.name,
    slug: roleGroup.slug,
    banner: roleGroup.banner || '',
    priority: roleGroup.priority,
    permissions: roleGroup.permissions || [],
    is_active: roleGroup.is_active,
  }
  errors.value = {}
  dialogVisible.value = true
}

const closeDialog = () => {
  dialogVisible.value = false
  editingRoleGroup.value = null
  formData.value = {
    name: '',
    slug: '',
    banner: '',
    priority: 0,
    permissions: [],
    is_active: true,
  }
  errors.value = {}
}

const togglePermission = (key: string, value: boolean) => {
  if (value) {
    if (!formData.value.permissions.includes(key)) {
      formData.value.permissions.push(key)
    }
  } else {
    formData.value.permissions = formData.value.permissions.filter(p => p !== key)
  }
}

const openSymbolSelector = async () => {
  symbolSelectorVisible.value = true
  if (symbols.value.length === 0) {
    await fetchSymbols()
  }
}

const selectSymbol = (symbol: Symbol) => {
  formData.value.banner = symbol.url
  symbolSelectorVisible.value = false
}

const saveRoleGroup = async () => {
  errors.value = {}
  
  if (!formData.value.name.trim()) {
    errors.value.name = 'الاسم مطلوب'
    return
  }

  saving.value = true
  try {
    const payload = {
      ...formData.value,
      permissions: formData.value.permissions.length > 0 ? formData.value.permissions : null,
    }

    if (editingRoleGroup.value) {
      await $api(`/role-groups/${editingRoleGroup.value.id}`, {
        method: 'PUT',
        body: payload,
      })
      toast.add({
        severity: 'success',
        summary: 'نجح',
        detail: 'تم تحديث مجموعة الدور بنجاح',
        life: 3000,
      })
    } else {
      await $api('/role-groups', {
        method: 'POST',
        body: payload,
      })
      toast.add({
        severity: 'success',
        summary: 'نجح',
        detail: 'تم إنشاء مجموعة الدور بنجاح',
        life: 3000,
      })
    }

    closeDialog()
    await fetchRoleGroups()
  } catch (error: any) {
    const errorData = error.data || {}
    if (errorData.errors) {
      errors.value = errorData.errors
    } else {
      toast.add({
        severity: 'error',
        summary: 'خطأ',
        detail: error.message || 'فشل حفظ مجموعة الدور',
        life: 3000,
      })
    }
  } finally {
    saving.value = false
  }
}

const confirmDelete = (roleGroup: RoleGroup) => {
  confirm.require({
    message: `هل أنت متأكد من حذف مجموعة الدور "${roleGroup.name}"؟`,
    header: 'تأكيد الحذف',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: 'نعم',
    rejectLabel: 'لا',
    accept: async () => {
      try {
        await $api(`/role-groups/${roleGroup.id}`, {
          method: 'DELETE',
        })
        toast.add({
          severity: 'success',
          summary: 'نجح',
          detail: 'تم حذف مجموعة الدور بنجاح',
          life: 3000,
        })
        await fetchRoleGroups()
      } catch (error: any) {
        toast.add({
          severity: 'error',
          summary: 'خطأ',
          detail: error.message || 'فشل حذف مجموعة الدور',
          life: 3000,
        })
      }
    },
  })
}

const openUsersDialog = async (roleGroup: RoleGroup) => {
  selectedRoleGroup.value = roleGroup
  try {
    const data = await $api(`/role-groups/${roleGroup.id}`)
    selectedRoleGroup.value = data
    usersDialogVisible.value = true
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.message || 'فشل تحميل بيانات مجموعة الدور',
      life: 3000,
    })
  }
}

const openAddUsersDialog = () => {
  userSearchQuery.value = ''
  availableUsers.value = []
  addUsersDialogVisible.value = true
}

const searchUsers = async () => {
  if (!userSearchQuery.value.trim()) {
    availableUsers.value = []
    return
  }

  try {
    const users = await $api(`/users?search=${encodeURIComponent(userSearchQuery.value)}`)
    availableUsers.value = users.filter((u: User) => !isUserInGroup(u.id))
  } catch (error: any) {
    console.error('Error searching users:', error)
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.message || 'فشل البحث عن المستخدمين',
      life: 3000,
    })
  }
}

const addUser = async (userId: number) => {
  if (!selectedRoleGroup.value) return

  try {
    await $api(`/role-groups/${selectedRoleGroup.value.id}/users`, {
      method: 'POST',
      body: { user_ids: [userId] },
    })
    toast.add({
      severity: 'success',
      summary: 'نجح',
      detail: 'تم إضافة المستخدم بنجاح',
      life: 3000,
    })
    // Refresh role group data
    const data = await $api(`/role-groups/${selectedRoleGroup.value.id}`)
    selectedRoleGroup.value = data
    // Remove from available users
    availableUsers.value = availableUsers.value.filter(u => u.id !== userId)
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.message || 'فشل إضافة المستخدم',
      life: 3000,
    })
  }
}

const removeUser = async (userId: number) => {
  if (!selectedRoleGroup.value) return

  confirm.require({
    message: 'هل أنت متأكد من إزالة هذا المستخدم من مجموعة الدور؟',
    header: 'تأكيد الإزالة',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: 'نعم',
    rejectLabel: 'لا',
    accept: async () => {
      try {
        await $api(`/role-groups/${selectedRoleGroup.value!.id}/users`, {
          method: 'DELETE',
          body: { user_ids: [userId] },
        })
        toast.add({
          severity: 'success',
          summary: 'نجح',
          detail: 'تم إزالة المستخدم بنجاح',
          life: 3000,
        })
        // Refresh role group data
        const data = await $api(`/role-groups/${selectedRoleGroup.value!.id}`)
        selectedRoleGroup.value = data
      } catch (error: any) {
        toast.add({
          severity: 'error',
          summary: 'خطأ',
          detail: error.message || 'فشل إزالة المستخدم',
          life: 3000,
        })
      }
    },
  })
}

// Lifecycle
onMounted(() => {
  fetchRoleGroups()
})
</script>
