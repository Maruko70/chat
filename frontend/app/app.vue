<template>
  <div>
    <NuxtPage />
    <Toast position="top-left" />
    <ConfirmDialog />
    <MessageDialog />
  </div>
</template>

<script setup lang="ts">
import Toast from 'primevue/toast'
import ConfirmDialog from 'primevue/confirmdialog'
import MessageDialog from '~/components/MessageDialog.vue'

// Initialize site settings on app load (force refresh)
const { fetchSettings } = useSiteSettings()
onMounted(async () => {
  // Force refresh to get latest settings
  await fetchSettings(true)
})
</script>