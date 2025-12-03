<template>
  <div class="space-y-6">
    <!-- Header with Add Button -->
    <div class="flex items-center justify-between">
      <h2 class="text-2xl font-bold text-gray-900">الإختصارات</h2>
      <Button
        icon="pi pi-plus"
        label="إضافة اختصار جديد"
        @click="openAddDialog"
        severity="success"
      />
    </div>

    <!-- Filters -->
    <div class="flex gap-4">
      <InputText
        v-model="searchQuery"
        placeholder="البحث..."
        class="flex-1"
        @input="fetchShortcuts"
      />
      <Select
        v-model="filterActive"
        :options="filterOptions"
        optionLabel="label"
        optionValue="value"
        placeholder="الحالة"
        class="w-48"
        @change="fetchShortcuts"
      />
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <i class="pi pi-spin pi-spinner text-4xl text-gray-400"></i>
      <p class="mt-4 text-gray-500">جاري التحميل...</p>
    </div>

    <!-- Shortcuts Table -->
    <Card v-else>
      <template #content>
        <DataTable
          :value="shortcuts"
          :paginator="true"
          :rows="10"
          :rowsPerPageOptions="[10, 20, 50]"
          sortMode="multiple"
          :sortField="'key'"
          :sortOrder="1"
          stripedRows
          showGridlines
          responsiveLayout="scroll"
        >
          <Column field="key" header="المفتاح" sortable>
            <template #body="{ data }">
              <code class="px-2 py-1 bg-gray-100 rounded text-sm font-mono">{{ data.key }}</code>
            </template>
          </Column>
          <Column field="value" header="القيمة" sortable>
            <template #body="{ data }">
              <span class="text-sm">{{ data.value }}</span>
            </template>
          </Column>
          <Column field="is_active" header="الحالة" sortable>
            <template #body="{ data }">
              <InputSwitch
                :modelValue="data.is_active"
                @update:modelValue="(val) => updateActiveStatus(data.id, val)"
              />
            </template>
          </Column>
          <Column header="الإجراءات" :exportable="false" style="min-width:8rem">
            <template #body="{ data }">
              <div class="flex gap-2">
                <Button
                  icon="pi pi-pencil"
                  text
                  rounded
                  size="small"
                  @click="editShortcut(data)"
                  v-tooltip.top="'تعديل'"
                />
                <Button
                  icon="pi pi-trash"
                  text
                  rounded
                  size="small"
                  severity="danger"
                  @click="confirmDelete(data)"
                  v-tooltip.top="'حذف'"
                />
              </div>
            </template>
          </Column>
        </DataTable>
      </template>
    </Card>

    <!-- Empty State -->
    <Card v-if="!loading && shortcuts.length === 0">
      <template #content>
        <div class="text-center py-12">
          <i class="pi pi-link text-6xl text-gray-300 mb-4"></i>
          <h3 class="text-xl font-semibold text-gray-700 mb-2">لا توجد اختصارات</h3>
          <p class="text-gray-500 mb-4">ابدأ بإضافة اختصار جديد</p>
          <Button
            icon="pi pi-plus"
            label="إضافة اختصار جديد"
            @click="openAddDialog"
          />
        </div>
      </template>
    </Card>

    <!-- Add/Edit Dialog -->
    <Dialog
      v-model:visible="dialogVisible"
      :header="editingShortcut ? 'تعديل اختصار' : 'إضافة اختصار جديد'"
      :modal="true"
      :style="{ width: '500px' }"
      :draggable="false"
    >
      <form @submit.prevent="saveShortcut" class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-2">المفتاح *</label>
          <InputText
            v-model="form.key"
            class="w-full"
            :class="{ 'p-invalid': errors.key }"
            placeholder="مثال: w1"
            :disabled="editingShortcut"
          />
          <small v-if="errors.key" class="p-error">{{ errors.key }}</small>
          <small class="text-gray-500 text-xs mt-1 block">
            المفتاح الذي سيتم كتابته في المحادثة (مثال: w1)
          </small>
        </div>

        <div>
          <label class="block text-sm font-medium mb-2">القيمة *</label>
          <Textarea
            v-model="form.value"
            class="w-full"
            :class="{ 'p-invalid': errors.value }"
            placeholder="مثال: Welcoooooooom:7:"
            rows="3"
          />
          <small v-if="errors.value" class="p-error">{{ errors.value }}</small>
          <small class="text-gray-500 text-xs mt-1 block">
            النص الذي سيتم استبدال المفتاح به عند الكتابة
          </small>
        </div>

        <div>
          <div class="flex items-center gap-2">
            <InputSwitch v-model="form.is_active" />
            <label class="text-sm font-medium">نشط</label>
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
            :label="editingShortcut ? 'حفظ' : 'إضافة'"
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

interface Shortcut {
  id: number
  key: string
  value: string
  is_active: boolean
}

const { $api } = useNuxtApp()
const confirm = useConfirm()
const toast = useToast()

// State
const shortcuts = ref<Shortcut[]>([])
const loading = ref(false)
const saving = ref(false)
const dialogVisible = ref(false)
const editingShortcut = ref<Shortcut | null>(null)
const searchQuery = ref('')
const filterActive = ref<boolean | null>(null)

const form = ref({
  key: '',
  value: '',
  is_active: true,
})

const errors = ref<Record<string, string>>({})

const filterOptions = [
  { label: 'الكل', value: null },
  { label: 'نشط', value: true },
  { label: 'غير نشط', value: false },
]

// Methods
const fetchShortcuts = async () => {
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
    const endpoint = `/shortcuts${queryString ? `?${queryString}` : ''}`
    shortcuts.value = await $api(endpoint)
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.message || 'فشل تحميل الاختصارات',
      life: 3000,
    })
  } finally {
    loading.value = false
  }
}

const openAddDialog = () => {
  editingShortcut.value = null
  form.value = {
    key: '',
    value: '',
    is_active: true,
  }
  errors.value = {}
  dialogVisible.value = true
}

const editShortcut = (shortcut: Shortcut) => {
  editingShortcut.value = shortcut
  form.value = {
    key: shortcut.key,
    value: shortcut.value,
    is_active: shortcut.is_active,
  }
  errors.value = {}
  dialogVisible.value = true
}

const closeDialog = () => {
  dialogVisible.value = false
  editingShortcut.value = null
  form.value = {
    key: '',
    value: '',
    is_active: true,
  }
  errors.value = {}
}

const saveShortcut = async () => {
  errors.value = {}
  
  if (!form.value.key.trim()) {
    errors.value.key = 'يرجى إدخال المفتاح'
    return
  }

  if (!form.value.value.trim()) {
    errors.value.value = 'يرجى إدخال القيمة'
    return
  }

  saving.value = true

  try {
    if (editingShortcut.value) {
      await $api(`/shortcuts/${editingShortcut.value.id}`, {
        method: 'PUT',
        body: form.value,
      })
      toast.add({
        severity: 'success',
        summary: 'نجح',
        detail: 'تم تحديث الاختصار بنجاح',
        life: 3000,
      })
    } else {
      await $api('/shortcuts', {
        method: 'POST',
        body: form.value,
      })
      toast.add({
        severity: 'success',
        summary: 'نجح',
        detail: 'تم إضافة الاختصار بنجاح',
        life: 3000,
      })
    }

    closeDialog()
    await fetchShortcuts()
  } catch (error: any) {
    const errorData = error.data || {}
    if (errorData.errors) {
      errors.value = errorData.errors
    } else {
      toast.add({
        severity: 'error',
        summary: 'خطأ',
        detail: error.message || (editingShortcut.value ? 'فشل تحديث الاختصار' : 'فشل إضافة الاختصار'),
        life: 3000,
      })
    }
  } finally {
    saving.value = false
  }
}

const updateActiveStatus = async (id: number, isActive: boolean) => {
  try {
    await $api(`/shortcuts/${id}`, {
      method: 'PUT',
      body: { is_active: isActive },
    })
    await fetchShortcuts()
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: 'فشل تحديث الحالة',
      life: 3000,
    })
  }
}

const confirmDelete = (shortcut: Shortcut) => {
  confirm.require({
    message: `هل أنت متأكد من حذف الاختصار "${shortcut.key}"؟`,
    header: 'تأكيد الحذف',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: 'نعم',
    rejectLabel: 'لا',
    accept: async () => {
      try {
        await $api(`/shortcuts/${shortcut.id}`, {
          method: 'DELETE',
        })
        toast.add({
          severity: 'success',
          summary: 'نجح',
          detail: 'تم حذف الاختصار بنجاح',
          life: 3000,
        })
        await fetchShortcuts()
      } catch (error: any) {
        toast.add({
          severity: 'error',
          summary: 'خطأ',
          detail: error.message || 'فشل حذف الاختصار',
          life: 3000,
        })
      }
    },
  })
}

// Lifecycle
onMounted(() => {
  fetchShortcuts()
})
</script>

