<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
      <h2 class="text-2xl font-bold text-gray-900">الإعدادات</h2>
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
    <div v-else class="space-y-6">
      <!-- Message Character Limits Section -->
      <Card>
        <template #title>حدود أحرف الرسائل</template>
        <template #content>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium mb-2">عدد أحرف الرسالة المسموح بها في الحائط</label>
              <InputNumber
                v-model="settingsForm.wall_message_chars"
                :min="0"
                :max="10000"
                class="w-full"
                showButtons
                :useGrouping="false"
              />
            </div>
            <div>
              <label class="block text-sm font-medium mb-2">عدد أحرف الرسالة المسموح بها في الخاص</label>
              <InputNumber
                v-model="settingsForm.private_message_chars"
                :min="0"
                :max="10000"
                class="w-full"
                showButtons
                :useGrouping="false"
              />
            </div>
            <div>
              <label class="block text-sm font-medium mb-2">عدد أحرف الرسالة المسموح بها في العام</label>
              <InputNumber
                v-model="settingsForm.public_message_chars"
                :min="0"
                :max="10000"
                class="w-full"
                showButtons
                :useGrouping="false"
              />
            </div>
          </div>
        </template>
      </Card>

      <!-- Message Timing Section -->
      <Card>
        <template #title>توقيت الرسائل</template>
        <template #content>
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label class="block text-sm font-medium mb-2">المدة بين رسائل اليومية بالدقيقة</label>
              <InputNumber
                v-model="settingsForm.daily_messages_interval"
                :min="0"
                :max="1440"
                class="w-full"
                showButtons
                :useGrouping="false"
              />
            </div>
            <div>
              <label class="block text-sm font-medium mb-2">المدة بين رسائل الحائط بالدقيقة</label>
              <InputNumber
                v-model="settingsForm.wall_messages_interval"
                :min="0"
                :max="1440"
                class="w-full"
                showButtons
                :useGrouping="false"
              />
            </div>
          </div>
        </template>
      </Card>

      <!-- Like Requirements Section -->
      <Card>
        <template #title>متطلبات اللايكات</template>
        <template #content>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium mb-2">عدد لايكات الأعلان</label>
              <InputNumber
                v-model="settingsForm.ad_likes_required"
                :min="0"
                :max="10000"
                class="w-full"
                showButtons
                :useGrouping="false"
              />
            </div>
            <div>
              <label class="block text-sm font-medium mb-2">عدد لايكات الحائط</label>
              <InputNumber
                v-model="settingsForm.wall_likes_required"
                :min="0"
                :max="10000"
                class="w-full"
                showButtons
                :useGrouping="false"
              />
            </div>
            <div>
              <label class="block text-sm font-medium mb-2">عدد لايكات للإتصال في الخاص</label>
              <InputNumber
                v-model="settingsForm.private_contact_likes_required"
                :min="0"
                :max="10000"
                class="w-full"
                showButtons
                :useGrouping="false"
              />
            </div>
            <div>
              <label class="block text-sm font-medium mb-2">عدد لايكات المايك</label>
              <InputNumber
                v-model="settingsForm.mic_likes_required"
                :min="0"
                :max="10000"
                class="w-full"
                showButtons
                :useGrouping="false"
              />
            </div>
            <div>
              <label class="block text-sm font-medium mb-2">عدد لايكات إنشاء قصة</label>
              <InputNumber
                v-model="settingsForm.create_story_likes_required"
                :min="0"
                :max="10000"
                class="w-full"
                showButtons
                :useGrouping="false"
              />
            </div>
            <div>
              <label class="block text-sm font-medium mb-2">عدد لايكات تغيير الاسم</label>
              <InputNumber
                v-model="settingsForm.change_name_likes_required"
                :min="0"
                :max="10000"
                class="w-full"
                showButtons
                :useGrouping="false"
              />
            </div>
            <div>
              <label class="block text-sm font-medium mb-2">عدد لايكات تغيير الصورة</label>
              <InputNumber
                v-model="settingsForm.change_picture_likes_required"
                :min="0"
                :max="10000"
                class="w-full"
                showButtons
                :useGrouping="false"
              />
            </div>
            <div>
              <label class="block text-sm font-medium mb-2">عدد لايكات تغيير رابط اليوتيوب</label>
              <InputNumber
                v-model="settingsForm.change_youtube_link_likes_required"
                :min="0"
                :max="10000"
                class="w-full"
                showButtons
                :useGrouping="false"
              />
            </div>
            <div>
              <label class="block text-sm font-medium mb-2">عدد لايكات إرسال رسالة في الخاص</label>
              <InputNumber
                v-model="settingsForm.send_private_message_likes_required"
                :min="0"
                :max="10000"
                class="w-full"
                showButtons
                :useGrouping="false"
              />
            </div>
            <div>
              <label class="block text-sm font-medium mb-2">عدد لايكات الكتابة في الغرفة</label>
              <InputNumber
                v-model="settingsForm.write_in_room_likes_required"
                :min="0"
                :max="10000"
                class="w-full"
                showButtons
                :useGrouping="false"
              />
            </div>
            <div>
              <label class="block text-sm font-medium mb-2">عدد لايكات إرسال ملف في الخاص</label>
              <InputNumber
                v-model="settingsForm.send_file_private_likes_required"
                :min="0"
                :max="10000"
                class="w-full"
                showButtons
                :useGrouping="false"
              />
            </div>
            <div>
              <label class="block text-sm font-medium mb-2">عدد لايكات لارسال ملف في الحائط</label>
              <InputNumber
                v-model="settingsForm.send_file_wall_likes_required"
                :min="0"
                :max="10000"
                class="w-full"
                showButtons
                :useGrouping="false"
              />
            </div>
          </div>
        </template>
      </Card>

      <!-- Membership Settings Section -->
      <Card>
        <template #title>إعدادات العضويات</template>
        <template #content>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div>
              <label class="block text-sm font-medium mb-2">عدد العضويات التي يمكنك الدخول بيها</label>
              <InputNumber
                v-model="settingsForm.max_memberships_per_user"
                :min="0"
                :max="100"
                class="w-full"
                showButtons
                :useGrouping="false"
              />
            </div>
            <div>
              <label class="block text-sm font-medium mb-2">عدد تسجيل عضويات للمستخدم الواحد</label>
              <InputNumber
                v-model="settingsForm.max_registrations_per_user"
                :min="0"
                :max="100"
                class="w-full"
                showButtons
                :useGrouping="false"
              />
            </div>
            <div>
              <label class="block text-sm font-medium mb-2">عدد الأحرف لأسم الزائر</label>
              <InputNumber
                v-model="settingsForm.visitor_name_chars"
                :min="0"
                :max="100"
                class="w-full"
                showButtons
                :useGrouping="false"
              />
            </div>
            <div>
              <label class="block text-sm font-medium mb-2">عدد الأحرف لتسجيل العضوية</label>
              <InputNumber
                v-model="settingsForm.membership_registration_chars"
                :min="0"
                :max="100"
                class="w-full"
                showButtons
                :useGrouping="false"
              />
            </div>
          </div>
        </template>
      </Card>

      <!-- Feature Toggles Section -->
      <Card>
        <template #title>تفعيل/تعطيل الميزات</template>
        <template #content>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <div class="flex items-center justify-between p-3 border rounded">
              <label class="text-sm font-medium">عدم تثبيت الحائط</label>
              <InputSwitch v-model="settingsForm.disable_wall_pin" />
            </div>
            <div class="flex items-center justify-between p-3 border rounded">
              <label class="text-sm font-medium">عدم السماح بدخول الزائر</label>
              <InputSwitch v-model="settingsForm.disable_visitor_entry" />
            </div>
            <div class="flex items-center justify-between p-3 border rounded">
              <label class="text-sm font-medium">عدم السماح بتسجيل عضوية</label>
              <InputSwitch v-model="settingsForm.disable_membership_registration" />
            </div>
            <div class="flex items-center justify-between p-3 border rounded">
              <label class="text-sm font-medium">تثبيت العضويات</label>
              <InputSwitch v-model="settingsForm.pin_memberships" />
            </div>
            <div class="flex items-center justify-between p-3 border rounded">
              <label class="text-sm font-medium">الرد في الغرف</label>
              <InputSwitch v-model="settingsForm.enable_room_reply" />
            </div>
            <div class="flex items-center justify-between p-3 border rounded">
              <label class="text-sm font-medium">التعليقات في الحائط</label>
              <InputSwitch v-model="settingsForm.enable_wall_comments" />
            </div>
            <div class="flex items-center justify-between p-3 border rounded">
              <label class="text-sm font-medium">المكالمة في الخاص</label>
              <InputSwitch v-model="settingsForm.enable_private_call" />
            </div>
            <div class="flex items-center justify-between p-3 border rounded">
              <label class="text-sm font-medium">البصمة في الخاص</label>
              <InputSwitch v-model="settingsForm.enable_private_fingerprint" />
            </div>
            <div class="flex items-center justify-between p-3 border rounded">
              <label class="text-sm font-medium">بحث اليوتيوب في الجدار</label>
              <InputSwitch v-model="settingsForm.enable_wall_youtube_search" />
            </div>
            <div class="flex items-center justify-between p-3 border rounded">
              <label class="text-sm font-medium">القصص في الجدار</label>
              <InputSwitch v-model="settingsForm.enable_wall_stories" />
            </div>
            <div class="flex items-center justify-between p-3 border rounded">
              <label class="text-sm font-medium">مبدع الحائط</label>
              <InputSwitch v-model="settingsForm.enable_wall_creator" />
            </div>
            <div class="flex items-center justify-between p-3 border rounded">
              <label class="text-sm font-medium">حظر VPN</label>
              <InputSwitch v-model="settingsForm.block_vpn" />
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

// Settings form with all fields
const settingsForm = ref({
  // Message character limits
  wall_message_chars: 0,
  private_message_chars: 0,
  public_message_chars: 0,
  
  // Message timing
  daily_messages_interval: 0,
  wall_messages_interval: 0,
  
  // Like requirements
  ad_likes_required: 0,
  wall_likes_required: 0,
  private_contact_likes_required: 0,
  mic_likes_required: 0,
  create_story_likes_required: 0,
  change_name_likes_required: 0,
  change_picture_likes_required: 0,
  change_youtube_link_likes_required: 0,
  send_private_message_likes_required: 0,
  write_in_room_likes_required: 0,
  send_file_private_likes_required: 0,
  send_file_wall_likes_required: 0,
  
  // Membership settings
  max_memberships_per_user: 0,
  max_registrations_per_user: 0,
  visitor_name_chars: 0,
  membership_registration_chars: 0,
  
  // Feature toggles (booleans)
  disable_wall_pin: false,
  disable_visitor_entry: false,
  disable_membership_registration: false,
  pin_memberships: false,
  enable_room_reply: false,
  enable_wall_comments: false,
  enable_private_call: false,
  enable_private_fingerprint: false,
  enable_wall_youtube_search: false,
  enable_wall_stories: false,
  enable_wall_creator: false,
  block_vpn: false,
})

// Map of setting keys to form fields
const settingsMap: Record<string, keyof typeof settingsForm.value> = {
  'wall_message_chars': 'wall_message_chars',
  'private_message_chars': 'private_message_chars',
  'public_message_chars': 'public_message_chars',
  'daily_messages_interval': 'daily_messages_interval',
  'wall_messages_interval': 'wall_messages_interval',
  'ad_likes_required': 'ad_likes_required',
  'wall_likes_required': 'wall_likes_required',
  'private_contact_likes_required': 'private_contact_likes_required',
  'mic_likes_required': 'mic_likes_required',
  'create_story_likes_required': 'create_story_likes_required',
  'change_name_likes_required': 'change_name_likes_required',
  'change_picture_likes_required': 'change_picture_likes_required',
  'change_youtube_link_likes_required': 'change_youtube_link_likes_required',
  'send_private_message_likes_required': 'send_private_message_likes_required',
  'write_in_room_likes_required': 'write_in_room_likes_required',
  'send_file_private_likes_required': 'send_file_private_likes_required',
  'send_file_wall_likes_required': 'send_file_wall_likes_required',
  'max_memberships_per_user': 'max_memberships_per_user',
  'max_registrations_per_user': 'max_registrations_per_user',
  'visitor_name_chars': 'visitor_name_chars',
  'membership_registration_chars': 'membership_registration_chars',
  'disable_wall_pin': 'disable_wall_pin',
  'disable_visitor_entry': 'disable_visitor_entry',
  'disable_membership_registration': 'disable_membership_registration',
  'pin_memberships': 'pin_memberships',
  'enable_room_reply': 'enable_room_reply',
  'enable_wall_comments': 'enable_wall_comments',
  'enable_private_call': 'enable_private_call',
  'enable_private_fingerprint': 'enable_private_fingerprint',
  'enable_wall_youtube_search': 'enable_wall_youtube_search',
  'enable_wall_stories': 'enable_wall_stories',
  'enable_wall_creator': 'enable_wall_creator',
  'block_vpn': 'block_vpn',
}

// Methods
const fetchSettings = async () => {
  loading.value = true
  try {
    const data = await $api('/site-settings')
    
    // Load all settings from API response
    Object.keys(settingsMap).forEach(key => {
      const formKey = settingsMap[key]
      const setting = data[key]
      
      if (setting) {
        // Handle boolean values
        if (typeof settingsForm.value[formKey] === 'boolean') {
          settingsForm.value[formKey] = setting.value === '1' || setting.value === 'true' || setting.value === true
        } else {
          // Handle numeric values
          const numValue = parseInt(setting.value) || 0
          settingsForm.value[formKey] = numValue as any
        }
      }
    })
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

const saveAllSettings = async () => {
  saving.value = true
  try {
    const settingsToSave = Object.keys(settingsMap).map(key => {
      const formKey = settingsMap[key]
      const value = settingsForm.value[formKey]
      
      // Determine type based on value
      let type = 'text'
      if (typeof value === 'boolean') {
        type = 'boolean'
      } else if (typeof value === 'number') {
        type = 'number'
      }
      
      return {
        key,
        value: value.toString(),
        type,
      }
    })
    
    await Promise.all(
      settingsToSave.map(setting =>
        $api(`/site-settings/${setting.key}`, {
          method: 'PUT',
          body: {
            value: setting.value,
            type: setting.type,
          },
        })
      )
    )
    
    toast.add({
      severity: 'success',
      summary: 'نجح',
      detail: 'تم حفظ جميع الإعدادات بنجاح',
      life: 3000,
    })
    
    // Refresh settings
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






