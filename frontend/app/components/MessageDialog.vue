<template>
  <Teleport to="body">
    <TransitionGroup name="message-slide" tag="div" class="message-container">
      <Dialog
        v-for="msg in messages"
        :key="msg.id"
        :visible="true"
        :modal="false"
        :closable="true"
        :draggable="true"
        :style="{
          width: '400px',
          maxWidth: '90vw',
          position: 'fixed',
          left: `${msg.x}px`,
          top: `${msg.y}px`,
          margin: 0,
        }"
        :pt="{
          root: {
            class: 'message-dialog-root',
            style: {
              backgroundColor: 'var(--site-secondary-color, #ffffff)',
              border: '2px solid var(--site-primary-color, #450924)',
            }
          },
          header: {
            class: 'message-dialog-header',
            style: {
              backgroundColor: 'var(--site-primary-color, #450924)',
              color: 'var(--site-secondary-color, #ffffff)',
            }
          }
        }"
        @hide="close(msg.id)"
      >
        <template #header>
          <div class="flex items-center gap-2">
            <i
              :class="getIconClass(msg.type)"
              class="text-sm"
            ></i>
            <span class="font-semibold">{{ msg.title }}</span>
          </div>
        </template>

        <div class="py-2">
          <p class="text-sm leading-relaxed" style="color: var(--site-primary-color, #450924)">
            {{ msg.message }}
          </p>
        </div>
      </Dialog>
    </TransitionGroup>
  </Teleport>
</template>

<script setup lang="ts">
import { useMessageDialog } from '~/composables/useMessageDialog'

const { messages, removeMessage } = useMessageDialog()

const getIconClass = (type: 'success' | 'error' | 'info') => {
  switch (type) {
    case 'success':
      return 'pi pi-check-circle'
    case 'error':
      return 'pi pi-exclamation-circle'
    case 'info':
      return 'pi pi-info-circle'
    default:
      return 'pi pi-info-circle'
  }
}

const close = (id: string) => {
  removeMessage(id)
}
</script>

<style scoped>
.message-container {
  position: fixed;
  z-index: 1100;
  pointer-events: none;
}

.message-container :deep(.p-dialog) {
  pointer-events: all;
}

.message-slide-enter-active,
.message-slide-leave-active {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.message-slide-enter-from {
  opacity: 0;
  transform: translate(-50%, -50%) scale(0.8) translateY(-20px);
}

.message-slide-leave-to {
  opacity: 0;
  transform: translate(-50%, -50%) scale(0.8) translateY(-20px);
}

.message-slide-enter-to,
.message-slide-leave-from {
  opacity: 1;
  transform: translate(-50%, -50%) scale(1) translateY(0);
}

.message-slide-move {
  transition: transform 0.3s ease;
}

:deep(.message-dialog-root) {
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
}

:deep(.message-dialog-header) {
  padding: 0.75rem 1rem;
}

:deep(.p-dialog-header-close) {
  color: var(--site-secondary-color, #ffffff) !important;
}

:deep(.p-dialog-header-close:hover) {
  background-color: rgba(255, 255, 255, 0.1);
}
</style>
