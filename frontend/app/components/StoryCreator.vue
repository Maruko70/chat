<template>
  <Dialog
    v-model:visible="visible"
    :modal="true"
    :closable="true"
    :dismissableMask="true"
    class="story-creator-dialog"
    @hide="close"
  >
    <template #header>
      <div class="flex items-center justify-between w-full">
        <h3>إنشاء قصة جديدة</h3>
        <Button
          icon="pi pi-times"
          text
          rounded
          @click="close"
        />
      </div>
    </template>

    <div class="story-creator-content">
      <!-- Preview -->
      <div v-if="previewUrl" class="preview-container mb-4">
        <img
          v-if="mediaType === 'image'"
          :src="previewUrl"
          alt="Preview"
          class="preview-image w-full max-h-[400px] object-contain rounded"
        />
        <video
          v-else-if="mediaType === 'video'"
          :src="previewUrl"
          controls
          class="preview-video w-full max-h-[400px] rounded"
        />
      </div>

      <!-- File input -->
      <div v-if="!previewUrl" class="file-input-container">
        <FileUpload
          mode="basic"
          accept="image/*,video/*"
          :maxFileSize="10240000"
          chooseLabel="اختر صورة أو فيديو"
          @select="handleFileSelect"
          class="w-full"
        />
        <small class="text-gray-500 mt-2 block">الحد الأقصى: 10MB</small>
      </div>

      <!-- Caption input -->
      <div v-if="previewUrl" class="caption-input mb-4">
        <label class="block mb-2 font-semibold">وصف (اختياري)</label>
        <Textarea
          v-model="caption"
          :rows="3"
          placeholder="أضف وصفاً لقصتك..."
          :maxlength="500"
          class="w-full"
        />
        <small class="text-gray-500">{{ caption.length }}/500</small>
      </div>

      <!-- Actions -->
      <div v-if="previewUrl" class="flex gap-2 justify-end">
        <Button
          label="إلغاء"
          severity="secondary"
          @click="reset"
        />
        <Button
          label="نشر القصة"
          :loading="uploading"
          @click="uploadStory"
        />
      </div>
    </div>
  </Dialog>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import Dialog from 'primevue/dialog'
import Button from 'primevue/button'
import FileUpload from 'primevue/fileupload'
import Textarea from 'primevue/textarea'
import { useApi } from '~/composables/useApi'
import { useToast } from 'primevue/usetoast'

const { api } = useApi()
const toast = useToast()

const visible = ref(false)
const previewUrl = ref<string | null>(null)
const mediaType = ref<'image' | 'video'>('image')
const caption = ref('')
const uploading = ref(false)
const selectedFile = ref<File | null>(null)

const handleFileSelect = (event: any) => {
  const file = event.files[0]
  if (!file) return

  selectedFile.value = file

  // Determine media type
  if (file.type.startsWith('video/')) {
    mediaType.value = 'video'
  } else {
    mediaType.value = 'image'
  }

  // Create preview
  const reader = new FileReader()
  reader.onload = (e) => {
    previewUrl.value = e.target?.result as string
  }
  reader.readAsDataURL(file)
}

const uploadStory = async () => {
  if (!selectedFile.value) return

  uploading.value = true

  try {
    const formData = new FormData()
    formData.append('media', selectedFile.value)
    if (caption.value.trim()) {
      formData.append('caption', caption.value.trim())
    }

    await api('/stories', {
      method: 'POST',
      body: formData,
    })

    toast.add({
      severity: 'success',
      summary: 'نجح',
      detail: 'تم نشر القصة بنجاح',
      life: 3000,
    })

    close()
    emit('created')
  } catch (error: any) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.message || 'فشل نشر القصة',
      life: 3000,
    })
  } finally {
    uploading.value = false
  }
}

const reset = () => {
  previewUrl.value = null
  caption.value = ''
  selectedFile.value = null
  mediaType.value = 'image'
}

const close = () => {
  reset()
  visible.value = false
}

const emit = defineEmits<{
  created: []
}>()

defineExpose({
  open: () => {
    visible.value = true
  },
  close,
})
</script>

<style scoped>
.story-creator-dialog :deep(.p-dialog) {
  max-width: 600px;
  width: 90vw;
}

.preview-container {
  background: #f5f5f5;
  border-radius: 8px;
  padding: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 200px;
}

.preview-image,
.preview-video {
  max-width: 100%;
}
</style>

