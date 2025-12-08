<template>
  <Dialog
    v-model:visible="visible"
    :modal="true"
    :closable="true"
    :dismissableMask="true"
    class="story-viewer-dialog"
    @hide="close"
  >
    <template #header>
      <div class="flex items-center justify-between w-full">
        <div class="flex items-center gap-3">
          <Avatar
            :image="currentStoryUser?.avatar_url || getDefaultUserImage()"
            shape="circle"
            class="w-10 h-10"
          />
          <div>
            <div class="font-semibold text-white">{{ currentStoryUser?.name || currentStoryUser?.username }}</div>
            <div class="text-xs text-gray-300">{{ formatTime(currentStory?.created_at) }}</div>
          </div>
        </div>
        <Button
          icon="pi pi-times"
          text
          rounded
          class="text-white hover:bg-white hover:bg-opacity-20"
          @click="close"
        />
      </div>
    </template>

    <div v-if="stories.length > 0" class="story-container">
      <!-- Progress bars for each story -->
      <div class="progress-bars flex gap-1">
        <div
          v-for="(story, index) in stories"
          :key="story.id"
          class="progress-bar flex-1 h-1 bg-white bg-opacity-30 rounded overflow-hidden"
        >
          <div
            class="progress-fill h-full bg-white transition-all duration-100"
            :style="{ width: getProgressWidth(index) + '%' }"
          />
        </div>
      </div>

      <!-- Story media -->
      <div class="story-media-container">
        <img
          v-if="currentStory?.media_type === 'image'"
          :src="getFullMediaUrl(currentStory.media_url)"
          alt="Story"
          class="story-image"
        />
        <video
          v-else-if="currentStory?.media_type === 'video'"
          :src="getFullMediaUrl(currentStory.media_url)"
          autoplay
          loop
          muted
          class="story-video"
        />

        <!-- Navigation buttons -->
        <button
          v-if="currentStoryIndex > 0"
          class="nav-button nav-button-left"
          @click="previousStory"
        >
          <i class="pi pi-chevron-left"></i>
        </button>
        <button
          v-if="currentStoryIndex < stories.length - 1"
          class="nav-button nav-button-right"
          @click="nextStory"
        >
          <i class="pi pi-chevron-right"></i>
        </button>

        <!-- Caption -->
        <div v-if="currentStory?.caption" class="story-caption">
          {{ currentStory.caption }}
        </div>
      </div>

      <!-- Story info -->
      <div class="story-info absolute bottom-0 left-0 right-0 p-4 bg-gradient-to-t from-black to-transparent text-white flex items-center justify-between">
        <div class="flex items-center gap-2">
          <i class="pi pi-eye"></i>
          <span class="text-sm">{{ currentStory?.views_count || 0 }} مشاهدة</span>
        </div>
        <div v-if="currentStory?.is_viewed" class="text-sm text-gray-300">
          تم المشاهدة
        </div>
      </div>
    </div>

    <div v-else class="text-center py-8">
      <p>لا توجد قصص متاحة</p>
    </div>
  </Dialog>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import Dialog from 'primevue/dialog'
import Avatar from 'primevue/avatar'
import Button from 'primevue/button'
import { useApi } from '~/composables/useApi'
import { useSiteSettings } from '~/composables/useSiteSettings'

interface Story {
  id: number
  media_url: string
  media_type: 'image' | 'video'
  caption?: string
  expires_at: string
  created_at: string
  is_viewed: boolean
  views_count: number
}

interface StoryUser {
  id: number
  name?: string
  username?: string
  avatar_url?: string
}

interface Props {
  userId: number | null
}

const props = defineProps<Props>()
const emit = defineEmits<{
  close: []
}>()

const { api } = useApi()
const { getDefaultUserImage } = useSiteSettings()

const visible = ref(false)
const stories = ref<Story[]>([])
const currentStoryIndex = ref(0)
const progress = ref(0)
const progressInterval = ref<ReturnType<typeof setInterval> | null>(null)
const STORY_DURATION = 5000 // 5 seconds per story

const currentStory = computed(() => stories.value[currentStoryIndex.value])
const currentStoryUser = ref<StoryUser | null>(null)

const getFullMediaUrl = (url: string) => {
  if (url.startsWith('http')) return url
  const config = useRuntimeConfig()
  const baseUrl = config.public.apiBaseUrl || ''
  return url.startsWith('/') ? `${baseUrl}${url}` : `${baseUrl}/${url}`
}

const formatTime = (dateString?: string) => {
  if (!dateString) return ''
  const date = new Date(dateString)
  const now = new Date()
  const diff = now.getTime() - date.getTime()
  const hours = Math.floor(diff / (1000 * 60 * 60))
  if (hours < 1) return 'منذ قليل'
  if (hours < 24) return `منذ ${hours} ساعة`
  const days = Math.floor(hours / 24)
  return `منذ ${days} يوم`
}

const getProgressWidth = (index: number) => {
  if (index < currentStoryIndex.value) return 100
  if (index > currentStoryIndex.value) return 0
  return progress.value
}

const loadStories = async () => {
  if (!props.userId) return

  try {
    const response = await api(`/stories/user/${props.userId}`) as { user: StoryUser | null, stories: Story[] }
    stories.value = response.stories || []
    currentStoryUser.value = response.user
    currentStoryIndex.value = 0
    progress.value = 0

    if (stories.value.length > 0 && stories.value[0]) {
      markAsViewed(stories.value[0].id)
      startProgress()
    }
  } catch (error) {
    console.error('Error loading stories:', error)
  }
}

const markAsViewed = async (storyId: number) => {
  const story = stories.value.find(s => s.id === storyId)
  if (!story || story.is_viewed) return

  try {
    await api(`/stories/${storyId}/view`, { method: 'POST' })
    const foundStory = stories.value.find(s => s.id === storyId)
    if (foundStory) {
      foundStory.is_viewed = true
      foundStory.views_count = (foundStory.views_count || 0) + 1
    }
  } catch (error) {
    console.error('Error marking story as viewed:', error)
  }
}

const startProgress = () => {
  if (progressInterval.value) {
    clearInterval(progressInterval.value)
  }

  progress.value = 0
  const increment = 100 / (STORY_DURATION / 100)

  progressInterval.value = setInterval(() => {
    progress.value += increment
    if (progress.value >= 100) {
      nextStory()
    }
  }, 100)
}

const nextStory = () => {
  if (currentStoryIndex.value < stories.value.length - 1) {
    currentStoryIndex.value++
    progress.value = 0
    const nextStory = stories.value[currentStoryIndex.value]
    if (nextStory) {
      markAsViewed(nextStory.id)
    }
    startProgress()
  } else {
    close()
  }
}

const previousStory = () => {
  if (currentStoryIndex.value > 0) {
    if (progressInterval.value) {
      clearInterval(progressInterval.value)
    }
    currentStoryIndex.value--
    progress.value = 0
    startProgress()
  }
}

const close = () => {
  if (progressInterval.value) {
    clearInterval(progressInterval.value)
    progressInterval.value = null
  }
  visible.value = false
  emit('close')
}

watch(() => props.userId, (newUserId) => {
  if (newUserId) {
    visible.value = true
    loadStories()
  }
})

watch(visible, (isVisible) => {
  if (isVisible && stories.value.length > 0) {
    startProgress()
  } else {
    if (progressInterval.value) {
      clearInterval(progressInterval.value)
      progressInterval.value = null
    }
  }
})

onUnmounted(() => {
  if (progressInterval.value) {
    clearInterval(progressInterval.value)
  }
})

defineExpose({
  open: (userId: number) => {
    if (userId) {
      visible.value = true
      loadStories()
    }
  },
  close,
})
</script>

<style scoped>
.story-viewer-dialog :deep(.p-dialog) {
  max-width: 100%;
  width: 100vw;
  height: 100vh;
  margin: 0;
  border-radius: 0;
}

.story-viewer-dialog :deep(.p-dialog-content) {
  padding: 0;
  height: 100%;
  display: flex;
  flex-direction: column;
}

.story-viewer-dialog :deep(.p-dialog-header) {
  padding: 1rem;
  background: rgba(0, 0, 0, 0.8);
  color: white;
  border: none;
}

.story-container {
  position: relative;
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 75vh;
  background: #000;
}

.progress-bars {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  z-index: 10;
  padding: 1rem;
  background: linear-gradient(to bottom, rgba(0, 0, 0, 0.5), transparent);
}

.story-media-container {
  position: relative;
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 75vh;
  overflow: hidden;
}

.story-image,
.story-video {
  width: 100%;
  height: 100%;
  object-fit: contain;
  max-width: 100%;
  max-height: 100%;
}

.nav-button {
  position: absolute;
  top: 50%;
  transform: translateY(-50%);
  background: rgba(0, 0, 0, 0.5);
  color: white;
  border: none;
  width: 40px;
  height: 40px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  z-index: 10;
  transition: background 0.2s;
}

.nav-button:hover {
  background: rgba(0, 0, 0, 0.7);
}

.nav-button-left {
  left: 10px;
}

.nav-button-right {
  right: 10px;
}

.story-caption {
  position: absolute;
  bottom: 0;
  left: 0;
  right: 0;
  background: linear-gradient(to top, rgba(0, 0, 0, 0.8), transparent);
  color: white;
  padding: 20px 15px 15px;
  font-size: 14px;
}

.story-info {
  padding: 10px 0;
}
</style>

