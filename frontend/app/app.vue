<template>
  <div>
    <NuxtPage />
    <Toast />
    <ConfirmDialog />
  </div>
</template>

<script setup lang="ts">
// Initialize site settings on app load (force refresh)
const { fetchSettings } = useSiteSettings()
onMounted(async () => {
  // Force refresh to get latest settings
  await fetchSettings(true)
})
</script>