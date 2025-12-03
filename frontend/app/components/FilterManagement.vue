<template>
  <div class="space-y-6">
    <!-- Header with Add Button -->
    <div class="flex items-center justify-between">
      <h2 class="text-2xl font-bold text-gray-900">إدارة الكلمات المحظورة</h2>
      <Button
        icon="pi pi-plus"
        label="إضافة كلمة محظورة جديدة"
        @click="openAddDialog"
        severity="success"
      />
    </div>

    <!-- Filters -->
    <div class="flex gap-4">
      <InputText
        v-model="searchQuery"
        placeholder="البحث في الكلمات..."
        class="flex-1"
        @input="debouncedSearch"
      />
      <Select
        v-model="filterAppliesTo"
        :options="filterAppliesToOptions"
        optionLabel="label"
        optionValue="value"
        placeholder="نوع المحتوى"
        class="w-64"
        @change="fetchFilteredWords"
      />
      <Select
        v-model="filterActive"
        :options="filterOptions"
        optionLabel="label"
        optionValue="value"
        placeholder="الحالة"
        class="w-48"
        @change="fetchFilteredWords"
      />
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <i class="pi pi-spin pi-spinner text-4xl text-gray-400"></i>
      <p class="mt-4 text-gray-500">جاري التحميل...</p>
    </div>

    <!-- Filtered Words Table -->
    <Card v-else>
      <template #content>
        <DataTable
          :value="filteredWords"
          :paginator="true"
          :rows="perPage"
          :first="(currentPage - 1) * perPage"
          :rowsPerPageOptions="[10, 20, 50, 100]"
          :totalRecords="totalRecords"
          paginatorTemplate="RowsPerPageDropdown FirstPageLink PrevPageLink CurrentPageReport NextPageLink LastPageLink"
          currentPageReportTemplate="{first} إلى {last} من {totalRecords}"
          sortMode="multiple"
          stripedRows
          showGridlines
          responsiveLayout="scroll"
          lazy
          @page="onPageChange"
        >
          <Column field="word" header="الكلمة" sortable>
            <template #body="{ data }">
              <span class="font-medium">{{ data.word }}</span>
            </template>
          </Column>
          <Column field="applies_to" header="ينطبق على" sortable>
            <template #body="{ data }">
              <Tag
                :value="getAppliesToLabel(data.applies_to)"
                :severity="getAppliesToSeverity(data.applies_to)"
              />
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
                  @click="editWord(data)"
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
    <Card v-if="!loading && filteredWords.length === 0">
      <template #content>
        <div class="text-center py-12">
          <i class="pi pi-filter text-6xl text-gray-300 mb-4"></i>
          <h3 class="text-xl font-semibold text-gray-700 mb-2">لا توجد كلمات محظورة</h3>
          <p class="text-gray-500 mb-4">ابدأ بإضافة كلمة محظورة جديدة</p>
          <Button
            icon="pi pi-plus"
            label="إضافة كلمة محظورة جديدة"
            @click="openAddDialog"
          />
        </div>
      </template>
    </Card>

    <!-- Add/Edit Dialog -->
    <Dialog
      v-model:visible="dialogVisible"
      :header="editingWord ? 'تعديل كلمة محظورة' : 'إضافة كلمة محظورة جديدة'"
      :modal="true"
      :style="{ width: '500px' }"
      :draggable="false"
    >
      <form @submit.prevent="saveWord" class="space-y-4">
        <div>
          <label class="block text-sm font-medium mb-2">الكلمة *</label>
          <InputText
            v-model="form.word"
            class="w-full"
            :class="{ 'p-invalid': errors.word }"
            placeholder="أدخل الكلمة المحظورة"
          />
          <small v-if="errors.word" class="p-error">{{ errors.word }}</small>
          <small class="text-gray-500 text-xs mt-1 block">
            الكلمة التي سيتم حظرها (سيتم البحث عنها بدون تمييز بين الأحرف الكبيرة والصغيرة)
          </small>
        </div>

        <div>
          <label class="block text-sm font-medium mb-2">ينطبق على *</label>
          <Select
            v-model="form.applies_to"
            :options="formAppliesToOptions"
            optionLabel="label"
            optionValue="value"
            class="w-full"
            :class="{ 'p-invalid': errors.applies_to }"
            placeholder="اختر نوع المحتوى"
            :disabled="!!editingWord"
          />
          <small v-if="errors.applies_to" class="p-error">{{ errors.applies_to }}</small>
          <small class="text-gray-500 text-xs mt-1 block">
            نوع المحتوى الذي سيتم تطبيق الفلتر عليه
          </small>
        </div>

        <div>
          <div class="flex items-center gap-2">
            <InputSwitch v-model="form.is_active" />
            <label class="text-sm font-medium">نشط</label>
          </div>
          <small class="text-gray-500 text-xs mt-1 block">
            الكلمات غير النشطة لن يتم تطبيقها
          </small>
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
            :label="editingWord ? 'حفظ' : 'إضافة'"
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

interface FilteredWord {
  id: number
  word: string
  applies_to: 'chats' | 'names' | 'bios' | 'walls' | 'statuses' | 'all'
  is_active: boolean
  created_at?: string
  updated_at?: string
}

const { $api } = useNuxtApp()
const confirm = useConfirm()
const toast = useToast()

// State
const filteredWords = ref<FilteredWord[]>([])
const loading = ref(false)
const saving = ref(false)
const dialogVisible = ref(false)
const editingWord = ref<FilteredWord | null>(null)
const searchQuery = ref('')
const filterAppliesTo = ref<string | null>(null)
const filterActive = ref<boolean | null>(null)
const currentPage = ref(1)
const perPage = ref(20)
const totalRecords = ref(0)

const form = ref({
  word: '',
  applies_to: 'chats' as FilteredWord['applies_to'],
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
    currentPage.value = 1
    fetchFilteredWords()
  }, 500)
}

// Options for filter dropdown (includes "All" for filtering)
const filterAppliesToOptions = [
  { label: 'الكل', value: null },
  { label: 'المحادثات', value: 'chats' },
  { label: 'الأسماء', value: 'names' },
  { label: 'السير الذاتية', value: 'bios' },
  { label: 'الجدران', value: 'walls' },
  { label: 'الحالات', value: 'statuses' },
  { label: 'الكل (ينطبق على كل الأنواع)', value: 'all' },
]

// Options for form dropdown (includes "All" option)
const formAppliesToOptions = [
  { label: 'المحادثات', value: 'chats' },
  { label: 'الأسماء', value: 'names' },
  { label: 'السير الذاتية', value: 'bios' },
  { label: 'الجدران', value: 'walls' },
  { label: 'الحالات', value: 'statuses' },
  { label: 'الكل (ينطبق على كل الأنواع)', value: 'all' },
]

const filterOptions = [
  { label: 'الكل', value: null },
  { label: 'نشط', value: true },
  { label: 'غير نشط', value: false },
]

// Methods
const getAppliesToLabel = (type: string): string => {
  const option = formAppliesToOptions.find(opt => opt.value === type)
  return option?.label || type
}

const getAppliesToSeverity = (type: string): string => {
  const severityMap: Record<string, string> = {
    chats: 'info',
    names: 'warning',
    bios: 'success',
    walls: 'danger',
    statuses: 'secondary',
    all: 'danger',
  }
  return severityMap[type] || 'secondary'
}

const fetchFilteredWords = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (searchQuery.value) {
      params.append('search', searchQuery.value)
    }
    if (filterAppliesTo.value) {
      params.append('applies_to', filterAppliesTo.value)
    }
    if (filterActive.value !== null) {
      params.append('is_active', filterActive.value.toString())
    }
    params.append('page', currentPage.value.toString())
    params.append('per_page', perPage.value.toString())
    
    const queryString = params.toString()
    const response = await $api(`/filtered-words?${queryString}`)
    
    // Handle Laravel pagination response
    if (Array.isArray(response)) {
      filteredWords.value = response
      totalRecords.value = response.length
    } else if (response && response.data) {
      filteredWords.value = Array.isArray(response.data) ? response.data : []
      totalRecords.value = response.total || 0
    } else {
      filteredWords.value = []
      totalRecords.value = 0
    }
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.message || 'فشل تحميل الكلمات المحظورة',
      life: 3000,
    })
  } finally {
    loading.value = false
  }
}

const onPageChange = (event: any) => {
  currentPage.value = event.page + 1
  perPage.value = event.rows
  fetchFilteredWords()
}

const openAddDialog = () => {
  editingWord.value = null
  form.value = {
    word: '',
    applies_to: 'chats',
    is_active: true,
  }
  errors.value = {}
  dialogVisible.value = true
}

const editWord = (word: FilteredWord) => {
  editingWord.value = word
  form.value = {
    word: word.word,
    applies_to: word.applies_to,
    is_active: word.is_active,
  }
  errors.value = {}
  dialogVisible.value = true
}

const closeDialog = () => {
  dialogVisible.value = false
  editingWord.value = null
  form.value = {
    word: '',
    applies_to: 'chats',
    is_active: true,
  }
  errors.value = {}
}

const saveWord = async () => {
  errors.value = {}
  
  if (!form.value.word.trim()) {
    errors.value.word = 'يرجى إدخال الكلمة'
    return
  }

  if (!form.value.applies_to) {
    errors.value.applies_to = 'يرجى اختيار نوع المحتوى'
    return
  }

  saving.value = true

  try {
    if (editingWord.value) {
      await $api(`/filtered-words/${editingWord.value.id}`, {
        method: 'PUT',
        body: form.value,
      })
      toast.add({
        severity: 'success',
        summary: 'نجح',
        detail: 'تم تحديث الكلمة المحظورة بنجاح',
        life: 3000,
      })
    } else {
      await $api('/filtered-words', {
        method: 'POST',
        body: form.value,
      })
      toast.add({
        severity: 'success',
        summary: 'نجح',
        detail: 'تم إضافة الكلمة المحظورة بنجاح',
        life: 3000,
      })
    }

    closeDialog()
    await fetchFilteredWords()
  } catch (error: any) {
    const errorData = error.data || {}
    if (errorData.errors) {
      errors.value = errorData.errors
    } else {
      toast.add({
        severity: 'error',
        summary: 'خطأ',
        detail: error.message || (editingWord.value ? 'فشل تحديث الكلمة المحظورة' : 'فشل إضافة الكلمة المحظورة'),
        life: 3000,
      })
    }
  } finally {
    saving.value = false
  }
}

const updateActiveStatus = async (id: number, isActive: boolean) => {
  try {
    await $api(`/filtered-words/${id}`, {
      method: 'PUT',
      body: { is_active: isActive },
    })
    toast.add({
      severity: 'success',
      summary: 'نجح',
      detail: 'تم تحديث الحالة بنجاح',
      life: 3000,
    })
    await fetchFilteredWords()
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: 'فشل تحديث الحالة',
      life: 3000,
    })
    // Revert the change on error
    await fetchFilteredWords()
  }
}

const confirmDelete = (word: FilteredWord) => {
  confirm.require({
    message: `هل أنت متأكد من حذف الكلمة المحظورة "${word.word}"؟`,
    header: 'تأكيد الحذف',
    icon: 'pi pi-exclamation-triangle',
    acceptClass: 'p-button-danger',
    acceptLabel: 'نعم',
    rejectLabel: 'لا',
    accept: async () => {
      try {
        await $api(`/filtered-words/${word.id}`, {
          method: 'DELETE',
        })
        toast.add({
          severity: 'success',
          summary: 'نجح',
          detail: 'تم حذف الكلمة المحظورة بنجاح',
          life: 3000,
        })
        await fetchFilteredWords()
      } catch (error: any) {
        toast.add({
          severity: 'error',
          summary: 'خطأ',
          detail: error.message || 'فشل حذف الكلمة المحظورة',
          life: 3000,
        })
      }
    },
  })
}

// Lifecycle
onMounted(() => {
  fetchFilteredWords()
})
</script>

