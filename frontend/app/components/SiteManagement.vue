<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h2 class="text-2xl font-bold text-gray-900">إعدادات الموقع</h2>
      <Button
        icon="pi pi-save"
        label="حفظ جميع الإعدادات"
        @click="saveAllSettings"
        :loading="saving"
        severity="success"
      />
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <i class="pi pi-spin pi-spinner text-4xl text-gray-400"></i>
      <p class="mt-4 text-gray-500">جاري التحميل...</p>
    </div>

    <!-- Settings Form -->
    <div v-else class="grid grid-cols-1 gap-6">
      <!-- Images Section -->
      <Card>
        <template #title>الصور الافتراضية</template>
        <template #content>
          <div class="space-x-6 grid grid-cols-4">
            <!-- Favicon -->
            <div>
              <label class="block text-sm font-medium mb-2">أيقونة الموقع (Favicon)</label>
              <div class="flex gap-4 items-start">
                <div class="flex-shrink-0">
                  <div class="w-24 h-24 border-2 border-gray-300 rounded flex items-center justify-center bg-gray-50">
                    <img
                      v-if="settings.favicon?.value"
                      :src="settings.favicon?.value"
                      alt="Favicon"
                      class="w-full h-full object-contain"
                      @error="handleImageError"
                    />
                    <i v-else class="pi pi-image text-3xl text-gray-400"></i>
                  </div>
                </div>
                <div class="flex-1 space-y-2">
                  <FileUpload
                    mode="basic"
                    accept="image/x-icon,image/png,image/jpeg,image/svg+xml"
                    :maxFileSize="512000"
                    :auto="false"
                    chooseLabel="اختر الأيقونة"
                    @select="onFaviconSelect"
                  />
                  <small class="text-gray-500 text-xs block">
                    الصيغ المدعومة: ICO, PNG, JPEG, SVG (حد أقصى 512KB)
                  </small>
                  <Button
                    v-if="settings.favicon?.value"
                    icon="pi pi-trash"
                    label="حذف"
                    severity="danger"
                    size="small"
                    @click="deleteImage('favicon')"
                  />
                </div>
              </div>
            </div>

            <!-- System Messages Image -->
            <div>
              <label class="block text-sm font-medium mb-2">صورة رسائل النظام</label>
              <div class="flex gap-4 items-start">
                <div class="flex-shrink-0">
                  <div class="w-32 h-32 border-2 border-gray-300 rounded flex items-center justify-center bg-gray-50">
                    <img
                      v-if="settings.system_messages_image?.value"
                      :src="settings.system_messages_image?.value"
                      alt="System Messages Image"
                      class="w-full h-full object-cover rounded"
                      @error="handleImageError"
                    />
                    <i v-else class="pi pi-image text-3xl text-gray-400"></i>
                  </div>
                </div>
                <div class="flex-1 space-y-2">
                  <FileUpload
                    mode="basic"
                    accept="image/*"
                    :maxFileSize="2048000"
                    :auto="false"
                    chooseLabel="اختر الصورة"
                    @select="onSystemMessagesImageSelect"
                  />
                  <small class="text-gray-500 text-xs block">
                    الصيغ المدعومة: JPEG, PNG, JPG, GIF, WEBP (حد أقصى 2MB)
                  </small>
                  <Button
                    v-if="settings.system_messages_image?.value"
                    icon="pi pi-trash"
                    label="حذف"
                    severity="danger"
                    size="small"
                    @click="deleteImage('system_messages_image')"
                  />
                </div>
              </div>
            </div>

            <!-- Default Room Image -->
            <div>
              <label class="block text-sm font-medium mb-2">صورة الغرفة الافتراضية</label>
              <div class="flex gap-4 items-start">
                <div class="flex-shrink-0">
                  <div class="w-32 h-32 border-2 border-gray-300 rounded flex items-center justify-center bg-gray-50">
                    <img
                      v-if="settings.default_room_image?.value"
                      :src="settings.default_room_image?.value"
                      alt="Default Room Image"
                      class="w-full h-full object-cover rounded"
                      @error="handleImageError"
                    />
                    <i v-else class="pi pi-image text-3xl text-gray-400"></i>
                  </div>
                </div>
                <div class="flex-1 space-y-2">
                  <FileUpload
                    mode="basic"
                    accept="image/*"
                    :maxFileSize="2048000"
                    :auto="false"
                    chooseLabel="اختر الصورة"
                    @select="onDefaultRoomImageSelect"
                  />
                  <small class="text-gray-500 text-xs block">
                    الصيغ المدعومة: JPEG, PNG, JPG, GIF, WEBP (حد أقصى 2MB)
                  </small>
                  <Button
                    v-if="settings.default_room_image?.value"
                    icon="pi pi-trash"
                    label="حذف"
                    severity="danger"
                    size="small"
                    @click="deleteImage('default_room_image')"
                  />
                </div>
              </div>
            </div>

            <!-- Default User Image -->
            <div>
              <label class="block text-sm font-medium mb-2">صورة المستخدم الافتراضية</label>
              <div class="flex gap-4 items-start">
                <div class="flex-shrink-0">
                  <div class="w-32 h-32 border-2 border-gray-300 rounded-full flex items-center justify-center bg-gray-50">
                    <img
                      v-if="settings.default_user_image?.value"
                      :src="settings.default_user_image?.value"
                      alt="Default User Image"
                      class="w-full h-full object-cover rounded-full"
                      @error="handleImageError"
                    />
                    <i v-else class="pi pi-user text-3xl text-gray-400"></i>
                  </div>
                </div>
                <div class="flex-1 space-y-2">
                  <FileUpload
                    mode="basic"
                    accept="image/*"
                    :maxFileSize="2048000"
                    :auto="false"
                    chooseLabel="اختر الصورة"
                    @select="onDefaultUserImageSelect"
                  />
                  <small class="text-gray-500 text-xs block">
                    الصيغ المدعومة: JPEG, PNG, JPG, GIF, WEBP (حد أقصى 2MB)
                  </small>
                  <Button
                    v-if="settings.default_user_image?.value"
                    icon="pi pi-trash"
                    label="حذف"
                    severity="danger"
                    size="small"
                    @click="deleteImage('default_user_image')"
                  />
                </div>
              </div>
            </div>
          </div>
        </template>
      </Card>

      <!-- Colors Section -->
      <Card>
        <template #title>ألوان الموقع</template>
        <template #content>
          <div class="space-x-4 grid grid-cols-3 gap-4 justify-center items-center">
            <!-- Primary Color -->
            <div>
              <label class="block text-sm font-medium mb-2">اللون الأساسي</label>
              <div class="flex gap-2 items-center">
                <input
                  v-model="colorsForm.primary"
                  type="color"
                  class="w-16 h-12 rounded border-2 border-gray-300 cursor-pointer"
                />
                <InputText
                  v-model="colorsForm.primary"
                  class="flex-1"
                  placeholder="#450924"
                />
                <div 
                  class="w-12 h-12 rounded border-2 border-gray-300 flex-shrink-0"
                  :style="{ backgroundColor: colorsForm.primary || '#450924' }"
                ></div>
              </div>
              <small class="text-gray-500 text-xs mt-1">
                يستخدم للخلفيات الأساسية والعناصر الرئيسية
              </small>
            </div>

            <!-- Secondary Color -->
            <div>
              <label class="block text-sm font-medium mb-2">اللون الثانوي</label>
              <div class="flex gap-2 items-center">
                <input
                  v-model="colorsForm.secondary"
                  type="color"
                  class="w-16 h-12 rounded border-2 border-gray-300 cursor-pointer"
                />
                <InputText
                  v-model="colorsForm.secondary"
                  class="flex-1"
                  placeholder="#ffffff"
                />
                <div 
                  class="w-12 h-12 rounded border-2 border-gray-300 flex-shrink-0"
                  :style="{ backgroundColor: colorsForm.secondary || '#ffffff' }"
                ></div>
              </div>
              <small class="text-gray-500 text-xs mt-1">
                يستخدم للخلفيات الثانوية
              </small>
            </div>

            <!-- Button Color -->
            <div>
              <label class="block text-sm font-medium mb-2">لون الأزرار</label>
              <div class="flex gap-2 items-center">
                <input
                  v-model="colorsForm.button"
                  type="color"
                  class="w-16 h-12 rounded border-2 border-gray-300 cursor-pointer"
                />
                <InputText
                  v-model="colorsForm.button"
                  class="flex-1"
                  placeholder="#450924"
                />
                <div 
                  class="w-12 h-12 rounded border-2 border-gray-300 flex-shrink-0"
                  :style="{ backgroundColor: colorsForm.button || '#450924' }"
                ></div>
              </div>
              <small class="text-gray-500 text-xs mt-1">
                يستخدم لأزرار الموقع
              </small>
            </div>
          </div>
        </template>
      </Card>

      <!-- Site Name Section -->
      <Card>
        <template #title>عنوان الصفحة الرئيسية</template>
        <template #content>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium mb-2">اسم الموقع (Site Name)</label>
              <InputText
                v-model="siteNameForm.name"
                class="w-full"
                placeholder="اسم الموقع"
              />
              <small class="text-gray-500 text-xs mt-1">
                سيظهر في الصفحة الرئيسية (عنوان مرئي للمستخدمين)
              </small>
            </div>
          </div>
        </template>
      </Card>

      <!-- SEO Section -->
      <Card>
        <template #title>إعدادات SEO</template>
        <template #content>
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium mb-2">عنوان SEO (SEO Title)</label>
              <InputText
                v-model="seoForm.title"
                class="w-full"
                placeholder="عنوان SEO"
              />
              <small class="text-gray-500 text-xs mt-1">
                سيظهر في نتائج محركات البحث (مختلف عن عنوان الصفحة الرئيسية)
              </small>
            </div>

            <div>
              <label class="block text-sm font-medium mb-2">وصف الموقع (Description)</label>
              <Textarea
                v-model="seoForm.description"
                class="w-full"
                rows="3"
                :autoResize="true"
                placeholder="وصف مختصر عن الموقع"
              />
              <small class="text-gray-500 text-xs mt-1">
                {{ seoForm.description?.length || 0 }} / 160 حرف
              </small>
            </div>

            <div>
              <label class="block text-sm font-medium mb-2">الكلمات المفتاحية (Keywords)</label>
              <InputText
                v-model="seoForm.keywords"
                class="w-full"
                placeholder="كلمة1, كلمة2, كلمة3"
              />
              <small class="text-gray-500 text-xs mt-1">
                افصل بين الكلمات بفواصل
              </small>
            </div>

            <div>
              <label class="block text-sm font-medium mb-2">صورة Open Graph</label>
              <div class="flex gap-4 items-start">
                <div class="flex-shrink-0">
                  <div class="w-32 h-32 border-2 border-gray-300 rounded flex items-center justify-center bg-gray-50">
                    <img
                      v-if="settings.og_image?.value"
                      :src="settings.og_image?.value"
                      alt="OG Image"
                      class="w-full h-full object-cover rounded"
                      @error="handleImageError"
                    />
                    <i v-else class="pi pi-image text-3xl text-gray-400"></i>
                  </div>
                </div>
                <div class="flex-1 space-y-2">
                  <FileUpload
                    mode="basic"
                    accept="image/*"
                    :maxFileSize="2048000"
                    :auto="false"
                    chooseLabel="اختر الصورة"
                    @select="onOgImageSelect"
                  />
                  <small class="text-gray-500 text-xs block">
                    الصيغ المدعومة: JPEG, PNG, JPG, GIF, WEBP (حد أقصى 2MB)
                  </small>
                  <Button
                    v-if="settings.og_image?.value"
                    icon="pi pi-trash"
                    label="حذف"
                    severity="danger"
                    size="small"
                    @click="deleteImage('og_image')"
                  />
                </div>
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium mb-2">رابط Open Graph (OG URL)</label>
              <InputText
                v-model="seoForm.og_url"
                class="w-full"
                placeholder="https://example.com"
              />
            </div>
          </div>
        </template>
      </Card>

      <!-- Rate Limiting Settings -->
      <Card>
        <template #title>إعدادات منع الإفراط في الطلبات</template>
        <template #content>
          <div class="space-x-4 grid grid-cols-3 gap-4 justify-center items-center">
            <div>
              <label class="block text-sm font-medium mb-2">
                عدد الطلبات المسموحة (Ignore Count)
                <span class="text-red-500">*</span>
              </label>
              <InputNumber
                v-model="rateLimitForm.ignore_count"
                :min="1"
                :max="100"
                class="w-full"
                placeholder="5"
              />
              <small class="text-gray-500 text-xs mt-1">
                عدد الطلبات المسموحة خلال دقيقة واحدة قبل التجاهل
              </small>
            </div>

            <div>
              <label class="block text-sm font-medium mb-2">
                عدد مرات التجاهل قبل الحظر (Ignore Time Till Ban)
                <span class="text-red-500">*</span>
              </label>
              <InputNumber
                v-model="rateLimitForm.ignore_time_till_ban"
                :min="1"
                :max="20"
                class="w-full"
                placeholder="3"
              />
              <small class="text-gray-500 text-xs mt-1">
                عدد مرات ظهور رسالة التجاهل قبل حظر المستخدم مؤقتاً
              </small>
            </div>

            <div>
              <label class="block text-sm font-medium mb-2">
                مدة الحظر بالدقائق (Ignore Ban Time Minutes)
                <span class="text-red-500">*</span>
              </label>
              <InputNumber
                v-model="rateLimitForm.ignore_ban_time_minutes"
                :min="0"
                :max="1440"
                class="w-full"
                placeholder="5"
              />
              <small class="text-gray-500 text-xs mt-1">
                مدة الحظر بالدقائق عند الوصول لعدد مرات التجاهل المحدد (0 = طرد المستخدم بدلاً من الحظر)
              </small>
            </div>
          </div>
        </template>
      </Card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useToast } from 'primevue/usetoast'

const { $api } = useNuxtApp()
const toast = useToast()

// State
const loading = ref(false)
const saving = ref(false)
const settings = ref<Record<string, any>>({})
const imageFiles = ref<Record<string, File>>({})

const siteNameForm = ref({
  name: '',
})

const seoForm = ref({
  title: '',
  description: '',
  keywords: '',
  og_url: '',
})

const colorsForm = ref({
  primary: '#450924',
  secondary: '#ffffff',
  button: '#450924',
})

const rateLimitForm = ref({
  ignore_count: 5,
  ignore_time_till_ban: 3,
  ignore_ban_time_minutes: 5,
})

// Methods
const fetchSettings = async () => {
  loading.value = true
  try {
    const data = await $api('/site-settings')
    settings.value = data
    
    // Load site name
    siteNameForm.value = {
      name: data.site_name?.value || '',
    }
    
    // Load SEO settings
    seoForm.value = {
      title: data.seo_title?.value || '',
      description: data.seo_description?.value || '',
      keywords: data.seo_keywords?.value || '',
      og_url: data.og_url?.value || '',
    }
    
    // Load color settings
    colorsForm.value = {
      primary: data.site_primary_color?.value || '#450924',
      secondary: data.site_secondary_color?.value || '#ffffff',
      button: data.site_button_color?.value || '#450924',
    }
    
    // Load rate limit settings
    rateLimitForm.value = {
      ignore_count: parseInt(data.ignore_count?.value || '5'),
      ignore_time_till_ban: parseInt(data.ignore_time_till_ban?.value || '3'),
      ignore_ban_time_minutes: parseInt(data.ignore_ban_time_minutes?.value ?? '5'),
    }
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.message || 'فشل تحميل الإعدادات',
      life: 3000,
    })
  } finally {
    loading.value = false
  }
}

const handleImageError = (event: Event) => {
  const img = event.target as HTMLImageElement
  img.style.display = 'none'
}

const uploadImage = async (key: string, file: File) => {
  try {
    const formData = new FormData()
    formData.append('image', file)
    
    const authStore = useAuthStore()
    const config = useRuntimeConfig()
    const baseUrl = config.public.apiBaseUrl
    const headers: HeadersInit = {
      'Accept': 'application/json',
    }
    
    if (authStore.token) {
      headers['Authorization'] = `Bearer ${authStore.token}`
    }
    
    const response = await fetch(`${baseUrl}/site-settings/${key}/image`, {
      method: 'POST',
      headers,
      body: formData,
    })
    
    if (!response.ok) {
      const error = await response.json().catch(() => ({ message: 'فشل رفع الصورة' }))
      throw new Error(error.message || 'فشل رفع الصورة')
    }
    
    const result = await response.json()
    
    // Update local settings
    if (!settings.value[key]) {
      settings.value[key] = {}
    }
    settings.value[key].value = result.value
    settings.value[key].url = result.url
    
    // Refresh global settings in composable to update SEO/favicon immediately
    const { fetchSettings: refreshGlobalSettings } = useSiteSettings()
    await refreshGlobalSettings(true)
    
    toast.add({
      severity: 'success',
      summary: 'نجح',
      detail: 'تم رفع الصورة بنجاح',
      life: 3000,
    })
    
    return result
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.message || 'فشل رفع الصورة',
      life: 3000,
    })
    throw error
  }
}

const deleteImage = async (key: string) => {
  try {
    await $api(`/site-settings/${key}/image`, {
      method: 'DELETE',
    })
    
    if (settings.value[key]) {
      settings.value[key].value = null
    }
    
    // Refresh global settings in composable
    const { fetchSettings: refreshGlobalSettings } = useSiteSettings()
    await refreshGlobalSettings(true)
    
    toast.add({
      severity: 'success',
      summary: 'نجح',
      detail: 'تم حذف الصورة بنجاح',
      life: 3000,
    })
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.message || 'فشل حذف الصورة',
      life: 3000,
    })
  }
}

const onFaviconSelect = (event: any) => {
  const file = event.files?.[0]
  if (file) {
    imageFiles.value.favicon = file
    uploadImage('favicon', file)
  }
}

const onSystemMessagesImageSelect = (event: any) => {
  const file = event.files?.[0]
  if (file) {
    imageFiles.value.system_messages_image = file
    uploadImage('system_messages_image', file)
  }
}

const onDefaultRoomImageSelect = (event: any) => {
  const file = event.files?.[0]
  if (file) {
    imageFiles.value.default_room_image = file
    uploadImage('default_room_image', file)
  }
}

const onDefaultUserImageSelect = (event: any) => {
  const file = event.files?.[0]
  if (file) {
    imageFiles.value.default_user_image = file
    uploadImage('default_user_image', file)
  }
}

const onOgImageSelect = (event: any) => {
  const file = event.files?.[0]
  if (file) {
    imageFiles.value.og_image = file
    uploadImage('og_image', file)
  }
}

const saveAllSettings = async () => {
  saving.value = true
  try {
    // Save site name (home page title)
    const siteNameSettings = [
      { key: 'site_name', value: siteNameForm.value.name },
    ]
    
    // Save SEO settings
    const seoSettings = [
      { key: 'seo_title', value: seoForm.value.title },
      { key: 'seo_description', value: seoForm.value.description },
      { key: 'seo_keywords', value: seoForm.value.keywords },
      { key: 'og_url', value: seoForm.value.og_url },
    ]
    
    // Save color settings
    const colorSettings = [
      { key: 'site_primary_color', value: colorsForm.value.primary },
      { key: 'site_secondary_color', value: colorsForm.value.secondary },
      { key: 'site_button_color', value: colorsForm.value.button },
    ]
    
    // Save rate limit settings
    const rateLimitSettings = [
      { key: 'ignore_count', value: (rateLimitForm.value.ignore_count ?? 5).toString(), type: 'number' },
      { key: 'ignore_time_till_ban', value: (rateLimitForm.value.ignore_time_till_ban ?? 3).toString(), type: 'number' },
      { key: 'ignore_ban_time_minutes', value: (rateLimitForm.value.ignore_ban_time_minutes ?? 5).toString(), type: 'number' },
    ]
    
    await Promise.all([
      ...siteNameSettings.map(setting =>
        $api(`/site-settings/${setting.key}`, {
          method: 'PUT',
          body: {
            value: setting.value,
            type: 'text',
          },
        })
      ),
      ...seoSettings.map(setting =>
        $api(`/site-settings/${setting.key}`, {
          method: 'PUT',
          body: {
            value: setting.value,
            type: 'text',
          },
        })
      ),
      ...colorSettings.map(setting =>
        $api(`/site-settings/${setting.key}`, {
          method: 'PUT',
          body: {
            value: setting.value,
            type: 'color',
          },
        })
      ),
      ...rateLimitSettings.map(setting =>
        $api(`/site-settings/${setting.key}`, {
          method: 'PUT',
          body: {
            value: setting.value,
            type: setting.type,
            description: setting.key === 'ignore_count' 
              ? 'عدد الطلبات المسموحة خلال دقيقة واحدة قبل التجاهل'
              : setting.key === 'ignore_time_till_ban'
              ? 'عدد مرات ظهور رسالة التجاهل قبل حظر المستخدم مؤقتاً'
              : 'مدة الحظر بالدقائق عند الوصول لعدد مرات التجاهل المحدد',
          },
        })
      ),
    ])
    
    toast.add({
      severity: 'success',
      summary: 'نجح',
      detail: 'تم حفظ جميع الإعدادات بنجاح',
      life: 3000,
    })
    
    // Refresh settings in the composable
    const { fetchSettings: refreshGlobalSettings } = useSiteSettings()
    await refreshGlobalSettings(true)
    
    await fetchSettings()
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.message || 'فشل حفظ الإعدادات',
      life: 3000,
    })
  } finally {
    saving.value = false
  }
}

// Lifecycle
onMounted(() => {
  fetchSettings()
})
</script>

