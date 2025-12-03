<template>
  <Dialog
    v-model:visible="visible"
    header="الإبلاغ عن مستخدم"
    :modal="true"
    :style="{ width: '500px' }"
    :draggable="false"
    @hide="resetForm"
  >
    <form @submit.prevent="submitReport" class="space-y-4">
      <div>
        <label class="block text-sm font-medium mb-2">المستخدم المبلغ عنه</label>
        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
          <Avatar
            :image="reportedUser?.avatar_url || getDefaultUserImage()"
            shape="circle"
            size="normal"
          />
          <div>
            <div class="font-medium">{{ reportedUser?.name || reportedUser?.username }}</div>
            <div class="text-sm text-gray-500">@{{ reportedUser?.username }}</div>
          </div>
        </div>
      </div>

      <div>
        <label class="block text-sm font-medium mb-2">الرسالة / الملاحظة</label>
        <Textarea
          v-model="form.message"
          class="w-full"
          rows="5"
          :autoResize="true"
          placeholder="يرجى وصف سبب الإبلاغ..."
          :class="{ 'p-invalid': errors.message }"
        />
        <small v-if="errors.message" class="p-error">{{ errors.message }}</small>
        <small class="text-gray-500 text-xs mt-1">
          {{ form.message.length }}/5000 حرف
        </small>
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <Button
          type="button"
          label="إلغاء"
          severity="secondary"
          @click="visible = false"
        />
        <Button
          type="submit"
          label="إرسال البلاغ"
          :loading="submitting"
        />
      </div>
    </form>
  </Dialog>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import type { User } from '~/types'

const props = defineProps<{
  modelValue: boolean
  reportedUser: User | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  'reported': []
}>()

const { $api } = useNuxtApp()
const toast = useToast()

const visible = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})

const form = ref({
  message: '',
})

const errors = ref<any>({})
const submitting = ref(false)

const getDefaultUserImage = () => {
  // You can add default user image logic here
  return null
}

const resetForm = () => {
  form.value = { message: '' }
  errors.value = {}
}

const submitReport = async () => {
  if (!props.reportedUser) {
    return
  }

  // Validation
  errors.value = {}
  if (!form.value.message.trim()) {
    errors.value.message = 'يرجى إدخال رسالة أو ملاحظة'
    return
  }

  if (form.value.message.length > 5000) {
    errors.value.message = 'الرسالة طويلة جداً (الحد الأقصى 5000 حرف)'
    return
  }

  submitting.value = true

  try {
    await $api('/reports', {
      method: 'POST',
      body: {
        reported_user_id: props.reportedUser.id,
        message: form.value.message.trim(),
      },
    })

    toast.add({
      severity: 'success',
      summary: 'نجح',
      detail: 'تم إرسال البلاغ بنجاح',
      life: 3000,
    })

    visible.value = false
    emit('reported')
  } catch (error: any) {
    console.error('Error submitting report:', error)
    const errorMessage = error.data?.message || error.message || 'فشل إرسال البلاغ'
    
    if (errorMessage.includes('already reported')) {
      errors.value.message = 'لقد قمت بالإبلاغ عن هذا المستخدم مؤخراً. يرجى الانتظار قبل الإبلاغ مرة أخرى.'
    } else if (errorMessage.includes('cannot report yourself')) {
      errors.value.message = 'لا يمكنك الإبلاغ عن نفسك'
    } else {
      errors.value.message = errorMessage
    }

    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: errorMessage,
      life: 3000,
    })
  } finally {
    submitting.value = false
  }
}

watch(visible, (newValue) => {
  if (!newValue) {
    resetForm()
  }
})
</script>




