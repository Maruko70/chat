<template>
  <div class="h-screen flex justify-center overflow-hidden" :style="{ backgroundColor: 'var(--site-primary-color, #450924)' }">
    <div class="w-full bg-gray-50 h-screen flex flex-col overflow-hidden">

      <!-- Main Content -->
      <div class="w-full flex-1 flex flex-col min-h-0 overflow-hidden">
        <!-- Messages Container -->
        <div class="flex-1 flex flex-col min-h-0 overflow-hidden">
          <div ref="messagesContainer" class="flex-1 overflow-y-auto min-h-0"
            :style="getRoomStyles()">
            <!-- Room Banner/Cover -->
            <div v-if="chatStore.currentRoom?.room_cover" 
                 class="w-full relative"
                 :style="{ height: chatStore.currentRoom?.settings?.bannerHeight || '200px' }">
              <img 
                :src="getRoomImageUrl(chatStore.currentRoom.room_cover)" 
                :alt="chatStore.currentRoom.name"
                class="w-full h-full object-cover"
                @error="handleImageError"
              />
              <!-- Optional overlay for text readability -->
              <div v-if="chatStore.currentRoom.settings?.bannerOverlay" 
                   class="absolute inset-0 bg-black bg-opacity-20"></div>
            </div>
            
            <!-- Loading message removed - messages are only received via real-time -->

            <!-- Premium Entry Notifications -->
            <div class="fixed top-4 left-4 z-50 space-y-2" v-if="premiumEntryNotifications.length > 0">
              <TransitionGroup name="premium-entry">
                <div
                  v-for="notification in premiumEntryNotifications"
                  :key="notification.id"
                  class="p-4 flex items-center gap-3 min-w-[300px] max-w-[400px] relative"
                  :style="{
                    backgroundImage: notification.background ? `url(${notification.background})` : 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                    backgroundSize: 'cover',
                    backgroundPosition: 'center',
                    backgroundColor: notification.background ? 'transparent' : '#667eea',
                  }"
                >
                  <!-- Overlay for better text readability -->
                  <div class="absolute inset-0" v-if="notification.background"></div>
                 
                  <div class="flex-shrink-0 relative z-10">
                    <Avatar
                      :image="notification.user.avatar_url || getDefaultUserImage()"
                      shape="circle"
                      class="ml-4 w-14 h-14 border-2 border-white"
                    />
                  </div>
                  <div class="flex-1 relative z-10">
                    <div class="font-bold text-lg mx-2 text-white drop-shadow-lg">
                      {{ notification.user.name || notification.user.username }}
                    </div>
                  </div>
                </div>
              </TransitionGroup>
            </div>

            <!-- Date Separator -->
            <div v-if="chatStore.messages.length > 0" class="flex items-center gap-2 my-4">
              <div class="flex-1 border-t border-gray-300"></div>
              <span class="text-xs text-gray-500 px-2">اليوم</span>
              <div class="flex-1 border-t border-gray-300"></div>
            </div>


            <template v-for="message in sortedMessages" :key="message.id">
              <!-- All Messages (including System/Welcome) -->
              <div 
                :data-message-id="message.id"
                class="w-full flex items-start border rounded-[4px] transition h-fit group"
                :style="{
                  backgroundColor: message.meta?.is_connection_state
                    ? (message.meta?.connection_state === 'connected' 
                        ? '#d4edda' 
                        : message.meta?.connection_state === 'disconnected'
                          ? '#f8d7da'
                          : message.meta?.connection_state === 'error'
                            ? '#fff3cd'
                            : message.meta?.connection_state === 'reconnecting'
                              ? '#d1ecf1'
                              : '#faf0e6')
                    : message.meta?.is_welcome_message 
                      ? '#ebf1ff' 
                      : (message.meta?.is_system || message.user?.id === 0)
                        ? '#faf0e6'
                        : undefined
                }"
                :class="{
                  'hover:bg-gray-50': !message.meta?.is_system && !message.meta?.is_welcome_message && message.user?.id !== 0
                }"
              >
              <!-- Profile Image -->
              <div 
                :style="{ border: `1px solid ${rgbToCss(message.user?.image_border_color || { r: 69, g: 9, b: 36 })}` }"
                @click="handleUserClick(message.user, message)"
                class="cursor-pointer relative"
              >
                <Avatar
                  v-if="message.meta?.is_system || message.meta?.is_welcome_message || message.user?.id === 0"
                  :image="message.meta?.is_welcome_message 
                    ? (message.user?.avatar_url || getSystemMessagesImage() || null)
                    : (message.user?.avatar_url || getSystemMessagesImage() || null)"
                  :icon="!message.user?.avatar_url && !getSystemMessagesImage() ? 'pi pi-cog' : undefined"
                  shape="square" 
                  class="flex-shrink-0 !w-[58px] !h-[50px]" 
                  style="width: 58px !important; height: 50px !important;"
                  size="small"
                />
                <Avatar
                  v-else
                  :image="message.user?.avatar_url || getDefaultUserImage()"
                  shape="square" 
                  class="flex-shrink-0 !w-[58px] !h-[50px]" 
                  style="width: 58px !important; height: 50px !important;"
                  size="small" 
                />
                <!-- Social Media Icon Overlay -->
                <div
                  v-if="message.user && message.user.social_media_type && message.user.social_media_url && !message.meta?.is_system && message.user?.id !== 0"
                  @click.stop="openSocialMediaPopup(message.user)"
                  class="absolute bottom-0 right-0 bg-white rounded-full shadow-md cursor-pointer hover:scale-110 transition-transform z-10 flex items-center justify-center"
                  :class="getSocialMediaIcon(message.user.social_media_type)?.color"
                  style="width: 28px; height: 28px; padding: 4px;"
                  v-html="getSocialMediaIcon(message.user.social_media_type)?.svg"
                ></div>
              </div>

              <!-- Message Content -->
              <div class="flex-1 flex flex-col min-w-0 mx-2">
                <!-- Name -->
                <div class="text-sm font-medium mb-1 h-6 flex items-center justify-between">
                  <span 
                    v-if="message.meta?.is_welcome_message"
                    class="text-gray-600 flex items-center gap-1"
                    :style="{
                      color: rgbToCss(message.user?.name_color || { r: 100, g: 100, b: 100 })
                    }"
                  >
                    {{ message.user?.name || message.user?.username || 'الغرفة' }}
                  </span>
                  <span 
                    v-else-if="message.meta?.is_system || message.user?.id === 0"
                    class="text-gray-600 flex items-center gap-1"
                  >
                    {{ message.user?.name || message.user?.username || 'النظام' }}
                  </span>
                  <span 
                    v-else
                    :style="{ 
                      color: rgbToCss(message.user?.name_color || { r: 69, g: 9, b: 36 }), 
                      backgroundColor: message.user?.name_bg_color === null || message.user?.name_bg_color === undefined || message.user?.name_bg_color === 'transparent'
                        ? 'transparent'
                        : rgbToCss(message.user?.name_bg_color || 'transparent')
                    }"
                    @click="handleUserClick(message.user, message)"
                    class="cursor-pointer hover:underline flex items-center gap-1"
                  >
                    <!-- Role Group Banner -->
                    <img
                      v-if="getRoleGroupBanner(message.user)"
                      :src="getRoleGroupBanner(message.user)"
                      :alt="getRoleGroupName(message.user)"
                      class="h-4 w-auto object-contain inline-block"
                      :title="getRoleGroupName(message.user)"
                    />
                    {{ message.user?.name || message.user?.username }}
                    <!-- Gift Icon -->
                    <i v-if="message.user?.gifts && message.user.gifts.length > 0" 
                       class="pi pi-gift text-xs ml-1" 
                       :title="`Gifts: ${message.user.gifts.join(', ')}`"></i>
                  </span>
                  <Button
                    v-if="!message.meta?.is_system && !message.meta?.is_welcome_message && message.user?.id !== 0"
                    icon="pi pi-reply"
                    text
                    rounded
                    size="small"
                    class="opacity-0 group-hover:opacity-100 transition-opacity"
                    @click="setReplyTo(message)"
                    v-tooltip.top="'رد'"
                  />
                </div>

                <!-- Reply Preview (if this message is a reply) -->
                <div 
                  v-if="message.meta?.reply_to" 
                  class="mb-2 p-2 bg-gray-100 border-r-2 border-primary rounded text-xs cursor-pointer hover:bg-gray-200 transition"
                  @click="scrollToMessage(message.meta.reply_to.id)"
                >
                  <div class="font-medium text-gray-600 mb-1">
                    {{ getReplyUserName(message.meta.reply_to) }}
                  </div>
                  <div class="text-gray-500 truncate">
                    {{ message.meta.reply_to.content }}
                  </div>
                </div>

                <!-- Message Text -->
                <div
                  class="text-sm w-5/6 break-words whitespace-pre-wrap mb-1"
                  :style="{ 
                    color: message.meta?.is_connection_state
                      ? (message.meta?.connection_state === 'connected' 
                          ? '#155724' 
                          : message.meta?.connection_state === 'disconnected'
                            ? '#721c24'
                            : message.meta?.connection_state === 'error'
                              ? '#856404'
                              : message.meta?.connection_state === 'reconnecting'
                                ? '#0c5460'
                                : '#6b7280')
                      : message.meta?.is_system || message.meta?.is_welcome_message || message.user?.id === 0
                        ? '#6b7280'
                        : rgbToCss(message.user?.message_color || { r: 69, g: 9, b: 36 })
                  }">
                  <!-- System messages with clickable room buttons -->
                  <template v-if="(message.meta?.action === 'joined' || message.meta?.action === 'moved') && (message.meta?.room_id || message.room_id)">
                    
                    <button
                      v-if="message.meta?.room_id || message.room_id"
                      @click="navigateToRoom(message.meta?.room_id || message.room_id)"
                      class="btn-styled p-2 mr-1 font-medium cursor-pointer hover:opacity-80"
                      :style="{ color: 'var(--site-secondary-color, #ffffff)' }"
                    >
                      <span>
                        <div class="flex items-center">
                          <Icon name="solar:multiple-forward-right-bold" class="w-4 h-4 mr-1" />
                          <p>{{ message.meta?.room_name || `الغرفة ${message.meta?.room_id || message.room_id}` }}</p>
                        </div>
                      </span>
                    </button>
                    <span v-else>{{ message.content }}</span>
                    <span>{{ message.content.split(' إلى')[0] }} إلى </span>
                  </template>
                  <!-- Leave message (no clickable button) -->
                  <template v-else-if="message.meta?.action === 'left'">
                    <template v-for="(part, index) in parseMessageWithEmojis(message.content)" :key="index">
                      <img v-if="part.type === 'emoji' && part.emojiId !== undefined" 
                        :src="getEmojiPath(part.emojiId)"
                        :alt="`:${part.emojiId}:`" 
                        class="inline-block w-4 h-4 align-middle" />
                      <span v-else-if="part.type === 'text'" class="inline">
                        {{ part.text }}
                      </span>
                    </template>
                  </template>
                  <!-- Fallback for moved/joined messages without room_id (shouldn't happen but just in case) -->
                  <template v-else-if="(message.meta?.action === 'joined' || message.meta?.action === 'moved')">
                    {{ message.content }}
                  </template>
                  <!-- Regular message content -->
                  <template v-else>
                    <template v-for="(part, index) in parseMessageWithEmojis(message.content)" :key="index">
                      <img v-if="part.type === 'emoji' && part.emojiId !== undefined" 
                        :src="getEmojiPath(part.emojiId)"
                        :alt="`:${part.emojiId}:`" 
                        class="inline-block w-4 h-4 align-middle" />
                      <span v-else-if="part.type === 'text'" class="inline">
                        {{ part.text }}
                      </span>
                    </template>
                  </template>
                </div>
              </div>

              <!-- Date and Status (at the end) -->
              <div class="flex-shrink-0 mx-2 flex items-center gap-1">
                <div class="text-xs text-gray-400 whitespace-nowrap">
                  {{ timeSinceArabic(message.created_at) }}
                </div>
                <!-- Error indicator for failed messages -->
                <div v-if="message.send_failed" 
                  class="flex items-center gap-1 text-red-500" 
                  v-tooltip.top="message.error_message || 'فشل إرسال الرسالة'">
                  <i class="pi pi-exclamation-circle text-xs"></i>
                  <span class="text-xs">فشل الإرسال</span>
                </div>
                <!-- Optimistic message indicator (sending) -->
                <div v-else-if="message.is_optimistic && !message.send_failed" 
                  class="flex items-center gap-1 text-gray-400">
                  <i class="pi pi-spin pi-spinner text-xs"></i>
                </div>
              </div>
              </div>
            </template>
          </div>
        </div>

        <!-- Chat Input -->
        <div class="border-t p-3" :style="{ backgroundColor: 'var(--site-secondary-color, #ffffff)' }">
          <!-- Reply Preview -->
          <div v-if="replyingTo" class="mb-2 p-2 bg-gray-100 border-r-2 rounded flex items-center justify-between" :style="{ borderColor: 'var(--site-primary-color, #450924)' }">
            <div class="flex-1">
              <div class="text-xs font-medium text-gray-600 mb-1 flex items-center gap-1">
                <img
                  v-if="replyingTo.user && getRoleGroupBanner(replyingTo.user)"
                  :src="getRoleGroupBanner(replyingTo.user)"
                  :alt="getRoleGroupName(replyingTo.user)"
                  class="h-3 w-auto object-contain"
                />
                رد على {{ replyingTo.user?.name || replyingTo.user?.username }}
              </div>
              <div class="text-xs text-gray-500 truncate">
                {{ replyingTo.content }}
              </div>
            </div>
            <Button
              icon="pi pi-times"
              text
              rounded
              size="small"
              @click="cancelReply"
              class="flex-shrink-0"
            />
          </div>
          
          <form @submit.prevent="sendMessage" class="flex gap-1 sm:gap-2 items-center">
            <Button 
              type="button" 
              text 
              rounded 
              class="flex-shrink-0 !text-red-600 hover:!bg-red-50"
              @click="leaveRoom" 
              v-tooltip.top="'مغادرة الغرفة'" 
            >
              <template #icon>
                <Icon 
                  name="solar:logout-2-linear" 
                  class="w-8 h-8"
                />
              </template>
            </Button>
            <Button ref="emojiButton" type="button" rounded severity="secondary" class="flex-shrink-0 !p-0 w-8 h-8 sm:w-10 sm:h-10"
              @click="emojiPanel.toggle($event)" v-tooltip.top="'لوحة الإيموجي'">
              <img 
                :src="emojiList.length > 0 ? getEmojiPath(emojiList[0]) : getEmojiPath(0)" 
                width="20" 
                height="20" 
                class="!p-0 md:w-8 md:h-8 sm:w-7 sm:h-7"
                alt="Emoji"
              >
            </Button>
            <InputText ref="messageInput" v-model="messageContent" placeholder="اكتب رسالتك هنا..."
              class="w-auto md:w-full text-xs sm:text-sm" size="small" :disabled="sending || showPasswordDialog || !chatStore.currentRoom" />
            <Button type="submit" text label="إرسال" :loading="sending" :disabled="!messageContent.trim() || showPasswordDialog || !chatStore.currentRoom"
              class="flex-shrink-0 btn-styled !text-white hover:!text-white border-0 !p-2 sm:!p-2" :style="{ backgroundColor: 'var(--site-button-color, #450924)' }" v-tooltip.top="!chatStore.currentRoom ? 'يرجى الانضمام إلى غرفة أولاً' : 'إرسال'" />
          </form>

          <!-- Emoji Panel Popup -->
          <OverlayPanel ref="emojiPanel" class="emoji-panel">
            <div class="w-80 max-h-96 overflow-y-auto">
              <div v-if="emojiList.length === 0" class="p-4 text-center text-gray-500">
                <p class="text-sm">لا توجد إيموجي متاحة</p>
                <p class="text-xs mt-2">يرجى إضافة إيموجي من لوحة التحكم</p>
              </div>
              <div v-else class="grid grid-cols-8 gap-2 p-2">
                <button v-for="emojiId in emojiList" :key="emojiId" @click="insertEmoji(emojiId)"
                  class="w-10 h-10 p-1 hover:bg-gray-100 rounded transition flex items-center justify-center"
                  type="button">
                  <img :src="getEmojiPath(emojiId)" :alt="`Emoji ${emojiId}`" class="w-full h-full object-contain" />
                </button>
              </div>
            </div>
          </OverlayPanel>
        </div>

        <!-- Bottom Navigation Bar -->
        <div class="border-t px-1 sm:px-4 py-1 sm:py-2 overflow-x-auto" :style="{ backgroundColor: 'var(--site-primary-color, #450924)', zIndex: 9999 }">
          <div class="flex justify-start items-center gap-x-1 sm:gap-x-4 min-w-max">
            <button @click="showUsersSidebar = true"
              class="btn-styled flex items-center text-white border border-white px-1.5 sm:px-4 py-0.5 sm:py-1 rounded-tr rounded-bl hover:text-gray-200 transition flex-shrink-0 whitespace-nowrap">
              <i class="pi pi-user text-xs sm:text-sm mx-0.5 sm:mx-2"></i>
              <span class="text-[12px] sm:text-[14px] font-medium">{{ onlineCount }}</span>
            </button>
            <button @click="showPrivateMessages = true"
              class="btn-styled flex items-center text-white border border-white px-1.5 sm:px-4 py-0.5 sm:py-1 rounded-tr rounded-bl hover:text-gray-200 transition flex-shrink-0 whitespace-nowrap relative">
              <i class="pi pi-comment text-xs sm:text-sm mx-0.5 sm:mx-2"></i>
              <span class="text-[12px] sm:text-[14px] font-medium">خاص</span>
              <Badge v-if="privateMessagesStore.unreadCount > 0" 
                :value="privateMessagesStore.unreadCount" 
                severity="danger" 
                class="absolute -top-1 -right-1" />
            </button>
            <button @click="showRoomsList = true"
              class="btn-styled flex items-center text-white border border-white px-1.5 sm:px-4 py-0.5 sm:py-1 rounded-tr rounded-bl flex-shrink-0 whitespace-nowrap">
              <i class="pi pi-users text-xs sm:text-sm mx-0.5 sm:mx-2"></i>
              <span class="text-[12px] sm:text-[14px] font-medium text-white">الغرف</span>
            </button>
            <button @click="showWall = true"
              class="btn-styled flex items-center text-white border border-white px-1.5 sm:px-4 py-0.5 sm:py-1 rounded-tr rounded-bl hover:text-gray-200 transition flex-shrink-0 whitespace-nowrap relative">
              <i class="pi pi-th-large text-xs sm:text-sm mx-0.5 sm:mx-2"></i>
              <span class="text-[12px] sm:text-[14px] font-medium">الحائط</span>
              <!-- Orange notification overlay -->
              <div 
                v-if="hasNewWallPost" 
                class="absolute inset-0 bg-orange-500 bg-opacity-60 rounded-tr rounded-bl animate-pulse pointer-events-none"
                style="animation-duration: 2s;"
              ></div>
              <!-- Orange dot indicator -->
              <div 
                v-if="hasNewWallPost" 
                class="absolute -top-1 -right-1 w-3 h-3 bg-orange-500 rounded-full border-2 border-white animate-pulse"
                style="animation-duration: 1s;"
              ></div>
            </button>
            <button @click="openSettings"
              class="btn-styled flex items-center text-white border border-white px-1.5 sm:px-4 py-0.5 sm:py-1 rounded-tr rounded-bl hover:text-gray-200 transition relative flex-shrink-0 whitespace-nowrap">
              <i class="pi pi-cog text-xs sm:text-sm mx-0.5 sm:mx-2"></i>
              <span class="text-[12px] sm:text-[14px] font-medium">الضبط</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Users Sidebar (Subs Menu) -->
    <Sidebar v-model:visible="showUsersSidebar" position="right" class="!w-64 md:!w-96">
      <template #header>
        <div class="flex items-start justify-between w-full">
          <h2 class="text-lg font-bold">المتواجدين</h2>
        </div>
      </template>
      <div>
        <!-- Stories Section -->
        <div class="px-2 mb-2">
          <div class="flex gap-2 overflow-x-auto">
            <!-- Add Story Button -->
            <div class="flex flex-col items-center gap-1 flex-shrink-0">
              <div class="relative">
                <Avatar
                  :image="authStore.user?.avatar_url || getDefaultUserImage()"
                  shape="square"
                  class="w-12 h-12 cursor-pointer border-2 border-dashed border-gray-400 opacity-70 hover:opacity-100 transition-opacity"
                  @click="openStoryCreator"
                />
                <div class="absolute -bottom-1 -right-1 w-5 h-5 bg-primary rounded-full flex items-center justify-center border-2 border-white">
                  <i class="pi pi-plus text-white text-xs"></i>
                </div>
              </div>
              <span class="text-xs text-center max-w-[60px] truncate">قصتي</span>
            </div>
            
            <!-- User Stories -->
            <div v-for="storyUser in usersWithStories" :key="storyUser.user.id" class="flex flex-col items-center gap-1 flex-shrink-0">
              <div class="relative">
                <Avatar
                  :image="storyUser.user.avatar_url || getDefaultUserImage()"
                  shape="square"
                  class="w-12 h-12 cursor-pointer border-2"
                  :class="storyUser.has_unviewed ? 'border-primary' : 'border-gray-300'"
                  @click="viewUserStory(storyUser.user.id)"
                />
                <div v-if="storyUser.has_unviewed" class="absolute -top-1 -right-1 w-3 h-3 bg-primary rounded-full border-2 border-white"></div>
              </div>
              <span class="text-xs text-center max-w-[60px] truncate">{{ storyUser.user.name || storyUser.user.username }}</span>
            </div>
          </div>
        </div>
        
        <!-- Story Viewer - Lazy loaded -->
        <LazyStoryViewer
          v-if="selectedStoryUserId"
          ref="storyViewerRef"
          :user-id="selectedStoryUserId"
          @close="selectedStoryUserId = null"
        />
        
        <!-- Story Creator - Lazy loaded -->
        <LazyStoryCreator
          ref="storyCreatorRef"
          @created="handleStoryCreated"
        />

        <!-- Search Bar -->
        <div class="" style="background-color: var(--site-primary-color); color: var(--site-secondary-color, #ffffff)">
          <InputText v-model="userSearchQuery" placeholder="البحث.." class="w-full bg-transparent border-0 placeholder:text-white" style="color: var(--site-secondary-color, #ffffff)" />
        </div>

          <!-- Users List -->
        <div class="overflow-y-auto" style="max-height: calc(100vh - 300px);">
          <!-- Users Inside Current Room -->
          <div v-if="currentRoomUsers.length > 0">
            <div
              v-for="user in currentRoomUsers"
              :key="user.id"
              class="relative flex items-start mt-px hover:bg-gray-100 transition border-b border-gray-200 hover:border-gray-300 hover:shadow-sm cursor-pointer last:border-b-0"
              @click="openUserProfile(user)"
            >
              <!-- Connection Status Line (full-height on the far left) -->
              

              <div class="flex mb-0.5">
                <div
                  class="w-1"
                  :class="getUserConnectionStatusClass(user)"
                ></div>
                <img
                  :src="user.avatar_url || getDefaultUserImage()"
                  alt="Avatar"
                  width="52"
                  height="48"
                  class="!w-[52px] !h-[48px]"
                >
              </div>
              <div class="flex-1 min-w-0 items-center justify-center">
                <div class="flex items-center justify-between">
                  <div class="font-medium text-[15px] mx-2 flex items-center gap-1" :style="{
                    color: rgbToCss(user.name_color || { r: 69, g: 9, b: 36 }),
                    backgroundColor: user.name_bg_color === null || user.name_bg_color === undefined || user.name_bg_color === 'transparent'
                      ? 'transparent'
                      : rgbToCss(user.name_bg_color)
                  }">
                    <!-- Role Group Banner -->
                    <img
                      v-if="getRoleGroupBanner(user)"
                      :src="getRoleGroupBanner(user)"
                      :alt="getRoleGroupName(user)"
                      class="h-4 w-auto object-contain"
                      :title="getRoleGroupName(user)"
                    />
                    {{ user.name || user.username }}
                  </div>
                  <div class="flex items-center gap-1 mx-2">
                    <span class="text-xs text-gray-400">#{{ user.id }}</span>
                    <div
                      v-if="user.social_media_type && user.social_media_url"
                      @click.stop="openSocialMediaPopup(user)"
                      class="flex items-center justify-center cursor-pointer hover:scale-110 transition-transform"
                      :class="getSocialMediaIcon(user.social_media_type)?.color"
                      style="width: 16px; height: 16px;"
                      v-html="getSocialMediaIcon(user.social_media_type)?.svg"
                      v-tooltip.top="getSocialMediaPlatformName(user.social_media_type)"
                    ></div>
                    <img
                      v-if="user.country_code"
                      :src="`/flags/${user.country_code.toLowerCase()}.png`"
                      :alt="user.country_code"
                      class="w-5 h-4 object-contain"
                      @error="(e: Event) => { const target = e.target as HTMLImageElement; if (target) target.style.display = 'none' }"
                    />
                  </div>
                </div>
                <div class="flex-1 min-w-0 flex justify-start mx-2 w-56">
                  <p class="text-sm inline"
                    :style="{ color: rgbToCss(user.bio_color || { r: 107, g: 114, b: 128 }) }">
                    {{ user.bio ? user.bio : user.is_guest ? '(زائر)' : '(عضو جديد)' }}
                  </p>
                </div>
              </div>
            </div>
          </div>

          <!-- Separator -->
          <div v-if="currentRoomUsers.length > 0 && otherRoomsUsers.length > 0">
            <div class="h-px"></div>
          </div>

          <!-- Users Online In Other Rooms -->
          <div>
            <div
              class="px-2 py-1 my-px font-semibold text-sm text-center"
              style="background-color: var(--site-primary-color); color: var(--site-secondary-color, #ffffff)"
            >
              المتواجدين في الدردشة
            </div>
            <div v-if="otherRoomsUsers.length === 0" class="px-2 py-4 text-center text-sm text-gray-500">
            </div>
            <div v-else>
              <div
                v-for="user in otherRoomsUsers"
                :key="user.id"
                class="relative flex items-start hover:bg-gray-100 transition border-b border-gray-200 hover:border-gray-300 hover:shadow-sm cursor-pointer last:border-b-0"
                @click="openUserProfile(user)"
              >
                <!-- Connection Status Line (full-height on the far left) -->
                

                <div class="flex mb-0.5">
                  <div
                    class="w-1"
                    :class="getUserConnectionStatusClass(user)"
                  ></div>
                  <img
                    :src="user.avatar_url || getDefaultUserImage()"
                    alt="Avatar"
                    width="52"
                    height="48"
                    class="!w-[52px] !h-[48px]"
                  >
                </div>
                <div class="flex-1 min-w-0 items-center justify-center">
                  <div class="flex items-center justify-between">
                    <div class="font-medium text-[15px] mx-2 flex items-center gap-1" :style="{
                      color: rgbToCss(user.name_color || { r: 69, g: 9, b: 36 }),
                      backgroundColor: user.name_bg_color === null || user.name_bg_color === undefined || user.name_bg_color === 'transparent'
                        ? 'transparent'
                        : rgbToCss(user.name_bg_color)
                    }">
                      <!-- Role Group Banner -->
                      <img
                        v-if="getRoleGroupBanner(user)"
                        :src="getRoleGroupBanner(user)"
                        :alt="getRoleGroupName(user)"
                        class="h-4 w-auto object-contain"
                        :title="getRoleGroupName(user)"
                      />
                      {{ user.name || user.username }}
                    </div>
                    <div class="flex items-center mx-2">
                      <span class="text-xs text-gray-400">#{{ user.id }}</span>
                      <img
                        v-if="user.country_code"
                        :src="`/flags/${user.country_code.toLowerCase()}.png`"
                        :alt="user.country_code"
                        class="w-5 h-4 object-contain"
                        @error="(e: Event) => { const target = e.target as HTMLImageElement; if (target) target.style.display = 'none' }"
                      />
                    </div>
                  </div>
                  <div class="flex-1 min-w-0 flex justify-start mx-2 w-56">
                    <p class="text-sm inline"
                      :style="{ color: rgbToCss(user.bio_color || { r: 107, g: 114, b: 128 }) }">
                      {{ user.bio ? user.bio : user.is_guest ? '(زائر)' : '(عضو جديد)' }}
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Sidebar>

    <!-- Private Messages Sidebar -->
    <Sidebar v-model:visible="showPrivateMessages" position="right" class="!w-64 md:!w-96">
      <template #header>
        <div class="flex items-center justify-between w-full">
          <h2 class="text-lg font-bold">الرسائل الخاصة</h2>
          <div class="flex items-center gap-2">
            <Badge v-if="privateMessagesStore.unreadCount > 0" :value="privateMessagesStore.unreadCount" severity="danger" />
          </div>
        </div>
      </template>
      <div class="space-y-2">
        <div v-if="privateMessagesStore.loading" class="text-center py-8 text-gray-500">
          جاري التحميل...
        </div>
        <div v-else-if="privateMessagesStore.conversations.length === 0" class="text-center py-8 text-gray-500">
          لا توجد رسائل خاصة
        </div>
        <div v-else v-for="conversation in privateMessagesStore.conversations" :key="conversation.user.id"
          class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer"
          @click="openPrivateChat(conversation.user)">
          <div class="relative">
            <Avatar :image="conversation.user.avatar_url || getDefaultUserImage()" shape="circle" class="w-10 h-10" />
            <Badge v-if="conversation.unread_count > 0" :value="conversation.unread_count" severity="danger" class="absolute -top-1 -right-1" />
          </div>
          <div class="flex-1 min-w-0">
            <div class="font-medium truncate">{{ conversation.user.name || conversation.user.username }}</div>
            <div class="text-sm text-gray-500 truncate">{{ conversation.last_message?.content || '' }}</div>
          </div>
          <div class="text-xs text-gray-400 whitespace-nowrap">
            {{ moment(conversation.last_message_at).fromNow() }}
          </div>
        </div>
      </div>
    </Sidebar>

    <!-- Rooms List Sidebar -->
    <Sidebar v-model:visible="showRoomsList" position="right" class="!w-64 md:!w-96">
      <template #header>
        <div class="flex items-center justify-between w-full">
          <h2 class="font-bold">غرف الدردشة ({{ filteredRoomsList.length }})</h2>
        </div>
      </template>
      
      <!-- Search and Create Row -->
      <div class="mb-4 flex gap-x-2">
        <Button
          label="غرفة جديدة"
          icon="pi pi-plus"
          @click="showCreateRoomModal = true"
          class="w-1/3 h-10 text-xs"
          size="small"
        />
        <div class="w-2/3">
          <InputText 
            v-model="roomSearchQuery" 
            placeholder="ابحث عن غرفة..."
            class="w-full h-10"
          />
        </div>
        
      </div>
      
      <div class="space-y-2">
        <div v-if="chatStore.loading" class="text-center py-8">
          <p class="text-gray-600">جاري التحميل...</p>
        </div>
        <div v-else-if="filteredRoomsList.length === 0" class="text-center py-8">
          <p class="text-gray-600">لا توجد غرف متاحة</p>
        </div>
        <div v-else>
          <div 
            v-for="room in filteredRoomsList" 
            :key="room.id" 
            class="cursor-pointer hover:shadow-md !h-16 transition-all duration-200 overflow-hidden flex relative"
            :style="{ ...getRoomCardStyles(room), ...getRoomBannerStyle(room) }"
            @click="navigateToRoom(room.id)"
          >
            <!-- Banner Background Overlay -->
            <div 
              v-if="getRoomSettings(room)?.useImageAsBanner && (room.room_cover || room.room_image || room.room_image_url)"
              class="absolute inset-0 z-0"
            >
              <img 
                :src="getRoomImageUrl(room.room_cover || room.room_image || room.room_image_url)" 
                :alt="room.name"
                class="w-full h-full object-cover"
                @error="handleImageError"
              />
              <!-- Dark overlay for better text readability -->
              <div class="absolute inset-0 bg-black/5"></div>
            </div>
            
            <!-- Content Container with relative positioning -->
            <div class="relative z-10 flex w-full">
              <!-- Square Image with Border and Hashtag Overlay (hidden when banner mode) -->
              <div 
                v-if="!getRoomSettings(room)?.useImageAsBanner"
                class="relative flex-shrink-0"
              >
                <div 
                  class="!w-20 !h-16 overflow-hidden border-2 mb-0.5 pb-0.5"
                  :style="{ borderColor: getRoomBorderColor(room) }"
                >
                  <img 
                    v-if="room.room_cover || room.room_image || room.room_image_url"
                    :src="getRoomImageUrl(room.room_cover || room.room_image || room.room_image_url)" 
                    :alt="room.name"
                    class="w-full h-full object-cover"
                    @error="handleImageError"
                  />
                  <div 
                    v-else
                    class="w-full h-full flex items-center justify-center"
                    :style="{ backgroundColor: getRoomBackgroundColor(room) }"
                  >
                    <i class="pi pi-comments text-2xl" :style="{ color: getRoomNameColor(room) }"></i>
                  </div>
                </div>
                <!-- Hashtag Overlay -->
                <div 
                  v-if="room.room_hashtag"
                  class="absolute -top-1 bg-black/45 text-white text-xs font-bold rounded w-6 h-6 flex items-center justify-center shadow-md"
                >
                  #{{ room.id }}
                </div>
              </div>
              
              <!-- Hashtag Badge for Banner Mode -->
              <div 
                v-if="getRoomSettings(room)?.useImageAsBanner && room.id"
                class="absolute top-2 left-2 bg-black/60 text-white text-xs font-bold rounded-full px-2 py-1 shadow-lg z-20"
              >
                #{{ room.id }}
              </div>
              
              <!-- Room Info -->
              <div class="flex-1 min-w-0 mx-1" :class="{ 'px-2 py-2': getRoomSettings(room)?.useImageAsBanner }">
                <!-- Room Name with Lock Icon -->
                <div class="flex items-center justify-between mb-1">
                  <div class="flex items-center gap-2">
                    <h3 
                      class="font-semibold text-sm flex-1 truncate" 
                      :class="{'ml-8': getRoomSettings(room)?.useImageAsBanner}"
                      :style="{ 
                        color: getRoomSettings(room)?.useImageAsBanner ? '#ffffff' : getRoomNameColor(room) 
                      }"
                    >
                      {{ room.name }}
                    </h3>
                    <i 
                      v-if="room.password" 
                      class="pi pi-lock text-xs flex-shrink-0"
                      :style="{ 
                        color: getRoomSettings(room)?.useImageAsBanner ? '#ffffff' : getRoomNameColor(room) 
                      }"
                    ></i>
                  </div>
                  <!-- Users Count -->
                  <div 
                    class="flex items-center gap-1 !text-xs p-1 rounded btn-styled cursor-default !px-2" 
                    :style="{ 
                      boxShadow: 'none !important',
                      transform: 'none !important',
                      transition: 'none !important',
                      backgroundColor: 'var(--site-primary-color, #450924)',
                      color: '#ffffff'
                    }"
                  >
                    <i class="pi pi-users"></i>
                    <span>{{ room.users?.length || 0 }}/{{ room.max_count || 40 }}</span>
                  </div>
                </div>
                
                <!-- Description -->
                <p 
                  v-if="room.description" 
                  class="text-xs line-clamp-2 mb-2"
                  :style="{ 
                    color: getRoomSettings(room)?.useImageAsBanner ? '#ffffff' : getRoomDescriptionColor(room) 
                  }"
                >
                  {{ room.description }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Sidebar>

    <!-- Room Settings Modal -->
    <Dialog 
      v-model:visible="showRoomSettingsModal" 
      modal 
      header="إعدادات الغرفة" 
      :style="{ width: '600px' }"
      :draggable="false"
    >
      <form @submit.prevent="updateRoomSettings" class="space-y-3">
        <!-- Basic Settings -->
        <div class="border-b pb-3">
          <h4 class="font-semibold mb-2 text-sm" :style="{ color: 'var(--site-primary-color, #450924)' }">الإعدادات الأساسية</h4>
          
          <div class="grid grid-cols-2 gap-3">
            <div class="col-span-2">
              <label class="block text-xs font-medium mb-1">اسم الغرفة *</label>
              <InputText 
                v-model="roomSettingsForm.name" 
                placeholder="اسم الغرفة"
                class="w-full text-sm"
                size="small"
                required
              />
            </div>
            
            <div class="col-span-2">
              <label class="block text-xs font-medium mb-1">الوصف</label>
              <Textarea 
                v-model="roomSettingsForm.description" 
                placeholder="وصف الغرفة"
                class="w-full text-sm"
                rows="2"
              />
            </div>
            
            <div class="col-span-2">
              <label class="block text-xs font-medium mb-1">رسالة الترحيب</label>
              <Textarea 
                v-model="roomSettingsForm.welcome_message" 
                placeholder="رسالة ترحيب"
                class="w-full text-sm"
                rows="2"
              />
            </div>
            
            <div class="col-span-2">
              <label class="block text-xs font-medium mb-2">صورة الغرفة</label>
              
              <!-- Image Preview Card -->
              <Transition name="fade-scale">
                <div v-if="roomImagePreview" class="relative group mb-3">
                  <div class="relative overflow-hidden rounded-xl border-2 border-gray-200 shadow-lg transition-all duration-300 hover:shadow-xl hover:border-primary">
                    <img 
                      :src="roomImagePreview" 
                      alt="Room image preview" 
                      class="w-full h-48 object-cover transition-transform duration-300 group-hover:scale-105" 
                    />
                    <!-- Overlay on hover -->
                    <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center gap-2">
                      <Button 
                        icon="pi pi-eye" 
                        rounded
                        text
                        severity="info"
                        class="text-white hover:bg-white/20"
                        v-tooltip.top="'معاينة'"
                        @click.stop
                      />
                      <Button 
                        icon="pi pi-pencil" 
                        rounded
                        text
                        severity="warning"
                        class="text-white hover:bg-white/20"
                        v-tooltip.top="'تغيير الصورة'"
                        @click.stop="roomImageInput?.click()"
                      />
                      <Button 
                        icon="pi pi-trash" 
                        rounded
                        text
                        severity="danger"
                        class="text-white hover:bg-white/20"
                        v-tooltip.top="'حذف الصورة'"
                        @click.stop="removeRoomImage"
                      />
                    </div>
                    <!-- Badge showing image status -->
                    <div class="absolute top-2 left-2 px-2 py-1 bg-green-500 text-white text-xs font-medium rounded-full shadow-md flex items-center gap-1">
                      <i class="pi pi-check-circle text-xs"></i>
                      <span>صورة محدثة</span>
                    </div>
                  </div>
                </div>
              </Transition>

              <!-- Drag and Drop Zone - Only show when no image or when explicitly showing upload area -->
              <Transition name="fade-scale">
                <div
                  v-if="!roomImagePreview"
                  :class="[
                    'relative border-2 border-dashed rounded-xl p-6 transition-all duration-300 cursor-pointer',
                    isDraggingOver
                      ? 'border-primary bg-primary/5 scale-[1.02] shadow-lg'
                      : 'border-gray-300 hover:border-primary hover:bg-gray-50'
                  ]"
                  @click="roomImageInput?.click()"
                  @dragover.prevent="handleDragOver"
                  @dragleave.prevent="handleDragLeave"
                  @drop.prevent="handleDrop"
                >
                  <input 
                    type="file" 
                    ref="roomImageInput"
                    accept="image/*"
                    @change="handleRoomImageSelect"
                    class="hidden"
                  />
                  
                  <div class="flex flex-col items-center justify-center gap-3 text-center">
                    <!-- Icon with animation -->
                    <div 
                      class="relative"
                      :class="isDraggingOver ? 'animate-bounce' : ''"
                    >
                      <div class="w-16 h-16 rounded-full bg-gradient-to-br from-primary/20 to-primary/10 flex items-center justify-center">
                        <i 
                          :class="[
                            'pi text-2xl transition-colors duration-300',
                            isDraggingOver ? 'pi-cloud-upload text-primary' : 'pi-image text-gray-400'
                          ]"
                        ></i>
                      </div>
                      <!-- Pulse effect when dragging -->
                      <div 
                        v-if="isDraggingOver"
                        class="absolute inset-0 rounded-full bg-primary/30 animate-ping"
                      ></div>
                    </div>
                    
                    <!-- Text content -->
                    <div>
                      <p class="text-sm font-semibold text-gray-700 mb-1">
                        اسحب وأفلت الصورة هنا
                      </p>
                      <p class="text-xs text-gray-500">
                        أو <span class="text-primary font-medium">انقر للتصفح</span>
                      </p>
                    </div>
                    
                    <!-- File info -->
                    <div v-if="roomImageFile" class="mt-2 px-3 py-2 bg-blue-50 rounded-lg border border-blue-200 flex items-center gap-2">
                      <i class="pi pi-file text-blue-600"></i>
                      <span class="text-xs text-blue-700 font-medium">{{ roomImageFile.name }}</span>
                      <span class="text-xs text-blue-600">({{ formatFileSize(roomImageFile.size) }})</span>
                    </div>
                    
                    <!-- Supported formats -->
                    <div class="flex items-center gap-2 mt-2">
                      <span 
                        v-for="format in ['JPEG', 'PNG', 'GIF', 'WebP']" 
                        :key="format"
                        class="px-2 py-0.5 bg-gray-100 text-gray-600 text-[10px] font-medium rounded"
                      >
                        {{ format }}
                      </span>
                      <span class="text-[10px] text-gray-500">حد أقصى 2MB</span>
                    </div>
                  </div>
                </div>
              </Transition>
              
              <!-- Hidden input for when image exists (used by edit button) -->
              <input 
                v-if="roomImagePreview"
                type="file" 
                ref="roomImageInput"
                accept="image/*"
                @change="handleRoomImageSelect"
                class="hidden"
              />
              
              <!-- Banner Mode Toggle -->
              <div v-if="roomImagePreview" class="mt-3 flex items-center justify-between p-3 bg-gray-50 rounded-lg border border-gray-200">
                <div class="flex items-center gap-2">
                  <i class="pi pi-image text-primary"></i>
                  <div>
                    <label class="text-sm font-medium text-gray-700 block">استخدام الصورة كخلفية</label>
                    <p class="text-xs text-gray-500">عرض الصورة كخلفية تغطي كامل بطاقة الغرفة في القائمة</p>
                  </div>
                </div>
                <InputSwitch v-model="roomSettingsForm.useImageAsBanner" />
              </div>
            </div>
          </div>
        </div>
        
        <!-- Access Settings -->
        <div class="border-b pb-3">
          <h4 class="font-semibold mb-2 text-sm" :style="{ color: 'var(--site-primary-color, #450924)' }">إعدادات الوصول</h4>
          
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium mb-1">الحد الأقصى للأعضاء</label>
              <InputNumber 
                v-model="roomSettingsForm.max_count" 
                :min="2"
                :max="40"
                class="w-full text-sm"
                size="small"
              />
            </div>
            
            <div>
              <label class="block text-xs font-medium mb-1">اللايكات المطلوبة</label>
              <InputNumber 
                v-model="roomSettingsForm.required_likes" 
                :min="0"
                class="w-full text-sm"
                size="small"
                placeholder="0"
              />
            </div>
            
            <div class="col-span-2">
              <label class="block text-xs font-medium mb-1">هاشتاج الغرفة (1-100)</label>
              <InputNumber 
                v-model="roomSettingsForm.room_hashtag" 
                :min="1"
                :max="100"
                class="w-full text-sm"
                size="small"
                placeholder="توليد تلقائي"
              />
            </div>
            
            <div class="col-span-2">
              <label class="block text-xs font-medium mb-1">كلمة المرور (اختياري)</label>
              <Password 
                v-model="roomSettingsForm.password" 
                placeholder="اتركه فارغاً لإزالة كلمة المرور"
                class="w-full text-sm"
                :feedback="false"
                toggleMask
                size="small"
              />
            </div>
            
            <div class="col-span-2 flex items-center gap-2">
              <Checkbox 
                v-model="roomSettingsForm.is_public" 
                inputId="room_is_public"
                :binary="true"
              />
              <label for="room_is_public" class="text-xs">غرفة عامة</label>
            </div>
            
            <div class="col-span-2 flex items-center gap-2">
              <Checkbox 
                v-model="roomSettingsForm.is_staff_only" 
                inputId="room_is_staff_only"
                :binary="true"
              />
              <label for="room_is_staff_only" class="text-xs">للموظفين فقط</label>
            </div>
          </div>
        </div>
        
        <!-- Appearance Settings -->
        <div class="border-b pb-3">
          <h4 class="font-semibold mb-2 text-sm" :style="{ color: 'var(--site-primary-color, #450924)' }">إعدادات المظهر</h4>
          
          <div class="space-y-2">
            <div class="flex items-center gap-2">
              <ColorPicker 
                v-model="roomSettingsForm.roomNameColor" 
                format="hex" 
                class="border rounded-md"
                :pt="{ root: { style: 'width: 2rem; height: 2rem;' } }"
              />
              <label class="text-xs font-medium whitespace-nowrap flex-1">لون اسم الغرفة</label>
            </div>
            
            <div class="flex items-center gap-2">
              <ColorPicker 
                v-model="roomSettingsForm.roomBorderColor" 
                format="hex" 
                class="border rounded-md"
                :pt="{ root: { style: 'width: 2rem; height: 2rem;' } }"
              />
              <label class="text-xs font-medium whitespace-nowrap flex-1">لون حدود الغرفة</label>
            </div>
            
            <div class="flex items-center gap-2">
              <ColorPicker 
                v-model="roomSettingsForm.roomDescriptionColor" 
                format="hex" 
                class="border rounded-md"
                :pt="{ root: { style: 'width: 2rem; height: 2rem;' } }"
              />
              <label class="text-xs font-medium whitespace-nowrap flex-1">لون وصف الغرفة</label>
            </div>
            
            <div class="flex items-center gap-2">
              <ColorPicker 
                v-model="roomSettingsForm.backgroundColor" 
                format="hex" 
                class="border rounded-md"
                :pt="{ root: { style: 'width: 2rem; height: 2rem;' } }"
              />
              <label class="text-xs font-medium whitespace-nowrap flex-1">لون خلفية الغرفة</label>
            </div>
            
            <div class="flex items-center gap-2">
              <ColorPicker 
                v-model="roomSettingsForm.textColor" 
                format="hex" 
                class="border rounded-md"
                :pt="{ root: { style: 'width: 2rem; height: 2rem;' } }"
              />
              <label class="text-xs font-medium whitespace-nowrap flex-1">لون النص</label>
            </div>
            
          </div>
        </div>
        
        <!-- Features Settings -->
        <div class="pb-3">
          <h4 class="font-semibold mb-2 text-sm" :style="{ color: 'var(--site-primary-color, #450924)' }">الميزات</h4>
          
          <div class="space-y-2">
            <div class="flex items-center gap-2">
              <Checkbox 
                v-model="roomSettingsForm.enable_mic" 
                inputId="room_enable_mic"
                :binary="true"
              />
              <label for="room_enable_mic" class="text-xs">تفعيل الميكروفون</label>
            </div>
            
            <div class="flex items-center gap-2">
              <Checkbox 
                v-model="roomSettingsForm.disable_incognito" 
                inputId="room_disable_incognito"
                :binary="true"
              />
              <label for="room_disable_incognito" class="text-xs">تعطيل وضع التصفح الخاص</label>
            </div>
          </div>
        </div>
        
        <div class="flex gap-2 justify-end pt-3 border-t">
          <Button 
            label="إلغاء" 
            severity="secondary" 
            size="small"
            @click="showRoomSettingsModal = false"
            :disabled="updatingRoom"
          />
          <Button 
            label="حفظ" 
            icon="pi pi-check" 
            size="small"
            type="submit"
            :loading="updatingRoom"
          />
        </div>
      </form>
    </Dialog>

    <!-- Membership Designs Dialog -->
    <Dialog
      v-model:visible="showMembershipDesignsDialog"
      header="تصاميم العضوية"
      :style="{ width: '700px', maxWidth: '90vw' }"
      :modal="true"
      class="p-fluid"
      :draggable="false"
    >
      <div class="space-y-4 max-h-[70vh] overflow-y-auto px-2">
        <div>
          <label class="block text-sm font-medium mb-2">خلفية العضوية</label>
          <div v-if="membershipBackgrounds.length === 0" class="text-center py-8 text-gray-500 text-sm">
            جاري التحميل...
          </div>
          <div v-else class="grid grid-cols-2 gap-2 max-h-48 overflow-y-auto">
            <div
              v-for="design in membershipBackgrounds"
              :key="design.id"
              class="relative cursor-pointer border-2 rounded p-2"
              :class="selectedMembershipBackground?.id === design.id ? 'border-primary' : 'border-gray-300'"
              @click="selectMembershipBackground(design)"
            >
              <img
                :src="design.image_url"
                :alt="design.name"
                class="w-full h-20 object-cover rounded"
              />
              <div class="text-xs text-center mt-1 truncate">{{ design.name }}</div>
            </div>
          </div>
        </div>
        <div>
          <label class="block text-sm font-medium mb-2">إطار الصورة</label>
          <div v-if="membershipFrames.length === 0" class="text-center py-8 text-gray-500 text-sm">
            جاري التحميل...
          </div>
          <div v-else class="grid grid-cols-2 gap-2 max-h-48 overflow-y-auto">
            <div
              v-for="design in membershipFrames"
              :key="design.id"
              class="relative cursor-pointer border-2 rounded p-2"
              :class="selectedMembershipFrame?.id === design.id ? 'border-primary' : 'border-gray-300'"
              @click="selectMembershipFrame(design)"
            >
              <div class="relative w-full h-20 flex items-center justify-center">
                <Avatar
                  :image="authStore.user?.avatar_url || getDefaultUserImage()"
                  shape="circle"
                  class="w-16 h-16"
                />
                <img
                  :src="design.image_url"
                  :alt="design.name"
                  class="absolute inset-0 w-full h-full object-contain pointer-events-none"
                />
              </div>
              <div class="text-xs text-center mt-1 truncate">{{ design.name }}</div>
            </div>
          </div>
        </div>
      </div>
      <template #footer>
        <Button label="إلغاء" icon="pi pi-times" text @click="showMembershipDesignsDialog = false" />
        <Button
          label="حفظ"
          icon="pi pi-check"
          @click="saveMembershipDesigns"
          :loading="savingMembershipDesigns"
        />
      </template>
    </Dialog>

    <!-- Create Room Modal -->
    <Dialog 
      v-model:visible="showCreateRoomModal" 
      modal 
      header="إنشاء غرفة جديدة" 
      :style="{ width: '600px' }"
      :draggable="false"
    >
      <form @submit.prevent="createRoom" class="space-y-3">
        <!-- Basic Settings -->
        <div class="border-b pb-3">
          <h4 class="font-semibold mb-2 text-sm" :style="{ color: 'var(--site-primary-color, #450924)' }">الإعدادات الأساسية</h4>
          
          <div class="grid grid-cols-2 gap-3">
            <div class="col-span-2">
              <label class="block text-xs font-medium mb-1">اسم الغرفة *</label>
              <InputText 
                v-model="newRoom.name" 
                placeholder="اسم الغرفة"
                class="w-full text-sm"
                size="small"
                required
              />
            </div>
            
            <div class="col-span-2">
              <label class="block text-xs font-medium mb-1">الوصف</label>
              <Textarea 
                v-model="newRoom.description" 
                placeholder="وصف الغرفة"
                class="w-full text-sm"
                rows="2"
              />
            </div>
            
            <div class="col-span-2">
              <label class="block text-xs font-medium mb-1">رسالة الترحيب</label>
              <Textarea 
                v-model="newRoom.welcome_message" 
                placeholder="رسالة ترحيب"
                class="w-full text-sm"
                rows="2"
              />
            </div>
            
            <div class="col-span-2">
              <label class="block text-xs font-medium mb-1">صورة الغرفة</label>
              <div class="space-y-1">
                <div v-if="newRoomImagePreview" class="relative">
                  <img :src="newRoomImagePreview" alt="Room image preview" class="w-full h-20 object-cover rounded border" />
                  <Button 
                    icon="pi pi-times" 
                    severity="danger" 
                    rounded 
                    text
                    size="small"
                    class="absolute top-1 right-1"
                    @click="removeNewRoomImage"
                  />
                </div>
                <div class="flex gap-1">
                  <input 
                    type="file" 
                    ref="newRoomImageInput"
                    accept="image/*"
                    @change="handleNewRoomImageSelect"
                    class="hidden"
                  />
                  <Button 
                    label="اختر صورة" 
                    icon="pi pi-image" 
                    size="small"
                    @click="newRoomImageInput?.click()"
                    class="flex-1 text-xs"
                  />
                  <Button 
                    v-if="newRoomImagePreview"
                    icon="pi pi-trash" 
                    severity="danger"
                    size="small"
                    @click="removeNewRoomImage"
                  />
                </div>
                <small class="text-gray-500 text-[10px]">JPEG, PNG, GIF, WebP (حد أقصى 2MB)</small>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Access Settings -->
        <div class="border-b pb-3">
          <h4 class="font-semibold mb-2 text-sm" :style="{ color: 'var(--site-primary-color, #450924)' }">إعدادات الوصول</h4>
          
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-xs font-medium mb-1">الحد الأقصى للأعضاء</label>
              <InputNumber 
                v-model="newRoom.max_count" 
                :min="2"
                :max="40"
                class="w-full text-sm"
                size="small"
              />
            </div>
            
            <div>
              <label class="block text-xs font-medium mb-1">اللايكات المطلوبة</label>
              <InputNumber 
                v-model="newRoom.required_likes" 
                :min="0"
                class="w-full text-sm"
                size="small"
                placeholder="0"
              />
            </div>
            
            <div class="col-span-2">
              <label class="block text-xs font-medium mb-1">هاشتاج الغرفة (1-100)</label>
              <InputNumber 
                v-model="newRoom.room_hashtag" 
                :min="1"
                :max="100"
                class="w-full text-sm"
                size="small"
                placeholder="توليد تلقائي"
              />
            </div>
            
            <div class="col-span-2">
              <label class="block text-xs font-medium mb-1">كلمة المرور (اختياري)</label>
              <Password 
                v-model="newRoom.password" 
                placeholder="كلمة مرور للغرفة"
                class="w-full text-sm"
                :feedback="false"
                toggleMask
                size="small"
              />
            </div>
            
            <div class="col-span-2 flex items-center gap-2">
              <Checkbox 
                v-model="newRoom.is_public" 
                inputId="new_room_is_public"
                :binary="true"
              />
              <label for="new_room_is_public" class="text-xs">غرفة عامة</label>
            </div>
            
            <div class="col-span-2 flex items-center gap-2">
              <Checkbox 
                v-model="newRoom.is_staff_only" 
                inputId="new_room_is_staff_only"
                :binary="true"
              />
              <label for="new_room_is_staff_only" class="text-xs">للموظفين فقط</label>
            </div>
          </div>
        </div>
        
        <!-- Appearance Settings -->
        <div class="border-b pb-3">
          <h4 class="font-semibold mb-2 text-sm" :style="{ color: 'var(--site-primary-color, #450924)' }">إعدادات المظهر</h4>
          
          <div class="space-y-2">
            <div class="flex items-center gap-2">
              <ColorPicker 
                v-model="newRoom.roomNameColor" 
                format="hex" 
                class="border rounded-md"
                :pt="{ root: { style: 'width: 2rem; height: 2rem;' } }"
              />
              <label class="text-xs font-medium whitespace-nowrap flex-1">لون اسم الغرفة</label>
            </div>
            
            <div class="flex items-center gap-2">
              <ColorPicker 
                v-model="newRoom.roomBorderColor" 
                format="hex" 
                class="border rounded-md"
                :pt="{ root: { style: 'width: 2rem; height: 2rem;' } }"
              />
              <label class="text-xs font-medium whitespace-nowrap flex-1">لون حدود الغرفة</label>
            </div>
            
            <div class="flex items-center gap-2">
              <ColorPicker 
                v-model="newRoom.roomDescriptionColor" 
                format="hex" 
                class="border rounded-md"
                :pt="{ root: { style: 'width: 2rem; height: 2rem;' } }"
              />
              <label class="text-xs font-medium whitespace-nowrap flex-1">لون وصف الغرفة</label>
            </div>
            
            <div class="flex items-center gap-2">
              <ColorPicker 
                v-model="newRoom.backgroundColor" 
                format="hex" 
                class="border rounded-md"
                :pt="{ root: { style: 'width: 2rem; height: 2rem;' } }"
              />
              <label class="text-xs font-medium whitespace-nowrap flex-1">لون خلفية الغرفة</label>
            </div>
            
            <div class="flex items-center gap-2">
              <ColorPicker 
                v-model="newRoom.textColor" 
                format="hex" 
                class="border rounded-md"
                :pt="{ root: { style: 'width: 2rem; height: 2rem;' } }"
              />
              <label class="text-xs font-medium whitespace-nowrap flex-1">لون النص</label>
            </div>
            
          </div>
        </div>
        
        <!-- Features Settings -->
        <div class="pb-3">
          <h4 class="font-semibold mb-2 text-sm" :style="{ color: 'var(--site-primary-color, #450924)' }">الميزات</h4>
          
          <div class="space-y-2">
            <div class="flex items-center gap-2">
              <Checkbox 
                v-model="newRoom.enable_mic" 
                inputId="new_room_enable_mic"
                :binary="true"
              />
              <label for="new_room_enable_mic" class="text-xs">تفعيل الميكروفون</label>
            </div>
            
            <div class="flex items-center gap-2">
              <Checkbox 
                v-model="newRoom.disable_incognito" 
                inputId="new_room_disable_incognito"
                :binary="true"
              />
              <label for="new_room_disable_incognito" class="text-xs">تعطيل وضع التصفح الخاص</label>
            </div>
          </div>
        </div>
        
        <div class="flex gap-2 justify-end pt-3 border-t">
          <Button 
            label="إلغاء" 
            severity="secondary" 
            size="small"
            @click="showCreateRoomModal = false"
            :disabled="creatingRoom"
          />
          <Button 
            label="إنشاء" 
            icon="pi pi-check" 
            size="small"
            type="submit"
            :loading="creatingRoom"
          />
        </div>
      </form>
    </Dialog>

    <!-- Wall Sidebar -->
    <Sidebar v-model:visible="showWall" position="right" class="!w-64 md:!w-96">
      <template #header>
        <h2 class="font-bold" :style="{ color: 'var(--site-primary-color, #450924)' }">الحائط</h2>
      </template>
      <div class="flex flex-col h-full" style="height: calc(100vh - 130px);">
        <!-- Scrollable Content Area -->
        <div class="flex-1 overflow-y-auto custom-scrollbar" style="min-height: 0;">
          <!-- Wall Creator Banner -->
          <div 
            v-if="wallCreator" 
            @click="showWallCreatorsModal = true"
            class="cursor-pointer transition-all hover:opacity-90 hover:scale-[1.02] overflow-hidden"
          >
            <img 
              src="assets/images/banner.gif" 
              alt="مبدع الحائط" 
              class="w-full h-auto object-cover"
            />
          </div>

          <!-- YouTube Search Bar -->
          <div class="border p-3 my-2" :style="{ borderColor: 'var(--site-primary-color, #450924)' }">
            <div class="flex gap-2 mb-2">
              <InputText 
                v-model="youtubeSearchQuery" 
                placeholder="ابحث عن فيديو على YouTube..." 
                class="flex-1 text-sm"
                @keyup.enter="searchYouTube"
                @input="debounceSearch"
              />
              <Button 
                icon="pi pi-search" 
                @click="searchYouTube"
                :loading="searchingYouTube"
                :style="{ backgroundColor: 'var(--site-primary-color, #450924)' }"
                class="text-white"
              />
            </div>
            <div v-if="searchingYouTube" class="text-center py-4">
              <i class="pi pi-spin pi-spinner text-xl" :style="{ color: 'var(--site-primary-color, #450924)' }"></i>
            </div>
            <div v-else-if="youtubeSearchResults.length > 0" class="max-h-48 overflow-y-auto space-y-2 mt-2 custom-scrollbar">
              <div 
                v-for="video in youtubeSearchResults" 
                :key="video.id"
                class="flex gap-2 p-2 border rounded cursor-pointer hover:bg-gray-50 transition-colors"
                :style="{ borderColor: 'var(--site-primary-color, #450924)' }"
                @click="selectYouTubeVideo(video)"
              >
                <img :src="video.thumbnail" alt="" class="w-16 h-12 object-cover rounded" />
                <div class="flex-1 min-w-0">
                  <div class="text-xs font-medium line-clamp-2">{{ video.title }}</div>
                  <div class="text-xs text-gray-500 mt-1">{{ video.channelTitle }}</div>
                </div>
              </div>
            </div>
          </div>

        <!-- Emoji Panel for Wall Post -->
        <OverlayPanel ref="wallEmojiPanel" class="emoji-panel">
          <div class="w-[90vw] sm:w-80 max-w-80 max-h-96 overflow-y-auto">
            <div v-if="emojiList.length === 0" class="p-4 text-center text-gray-500">
              <p class="text-sm">لا توجد إيموجي متاحة</p>
            </div>
            <div v-else class="grid grid-cols-6 sm:grid-cols-8 gap-2 p-2">
              <button 
                v-for="emojiId in emojiList" 
                :key="emojiId" 
                @click="insertEmojiToWallPost(emojiId)"
                class="w-10 h-10 p-1 hover:bg-gray-100 rounded transition flex items-center justify-center touch-manipulation"
                type="button"
              >
                <img :src="getEmojiPath(emojiId)" :alt="`Emoji ${emojiId}`" class="w-full h-full object-contain" />
              </button>
            </div>
          </div>
        </OverlayPanel>

          <!-- Wall Posts -->
          <div v-if="loadingWallPosts" class="text-center py-8">
            <i class="pi pi-spin pi-spinner text-2xl"></i>
          </div>
          <div v-else-if="wallPosts.length === 0" class="text-center py-8 text-gray-500">
            لا توجد منشورات بعد
          </div>
          <div v-else class="space-y-0">
          <div v-for="post in wallPosts" :key="post.id" class="border-b overflow-hidden" 
               :style="{ backgroundColor: 'var(--site-secondary-color, #ffffff)' }">
            <!-- Header: User Image + Name + Time (like messages) -->
            <div class="flex items-start gap-2">
              <img 
                :src="post.user?.avatar_url || getDefaultUserImage()" 
                alt="Avatar" 
                class="w-12 h-12 object-cover flex-shrink-0"
                style="width: 48px; height: 48px;"
              />
              <div class="flex-1 flex flex-col min-w-0">
                <!-- Name and Time -->
                <div class="flex items-center justify-between mb-1">
                  <span 
                    :style="{ 
                      color: rgbToCss(post.user?.name_color || { r: 69, g: 9, b: 36 }), 
                      backgroundColor: post.user?.name_bg_color === null || post.user?.name_bg_color === undefined || post.user?.name_bg_color === 'transparent'
                        ? 'transparent'
                        : rgbToCss(post.user?.name_bg_color || 'transparent')
                    }"
                    class="text-sm font-medium"
                  >
                    {{ post.user?.name || post.user?.username }}
                  </span>
                  <span class="text-xs text-gray-500">{{ timeSinceArabic(post.created_at) }}</span>
                </div>
                
                <!-- Post Content Text (if exists) - under name like messages -->
                <p v-if="post.content" class="text-sm whitespace-pre-wrap text-gray-800 leading-relaxed">{{ post.content }}</p>
              </div>
            </div>

            <!-- Image OR YouTube Video (only one) -->
            <div v-if="post.image_url || post.youtube_video" class="py-1">
              <!-- YouTube Video -->
              <div 
                v-if="post.youtube_video" 
                class="overflow-hidden shadow-md hover:shadow-lg transition-shadow group relative rounded"
              >
                <div class="relative w-full" style="padding-bottom: 56.25%; min-height: 300px;">
                  <iframe
                    :src="`https://www.youtube.com/embed/${post.youtube_video.id}?rel=0`"
                    frameborder="0"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen
                    class="absolute top-0 left-0 w-full h-full z-10 rounded"
                    @click="playingVideos.add(post.id)"
                    style="min-height: 300px;"
                  ></iframe>
                  
                  <!-- Title and Link Overlay -->
                  <div 
                    class="absolute bottom-0 left-0 right-0 p-3 bg-gradient-to-t from-black/80 via-black/60 to-transparent transition-opacity duration-300 z-20"
                    :class="playingVideos.has(post.id) ? 'opacity-0 group-hover:opacity-100 pointer-events-none' : 'opacity-100'"
                    @click="playingVideos.add(post.id)"
                    style="pointer-events: none;"
                  >
                    <div class="text-sm font-semibold line-clamp-2 text-white mb-2">{{ post.youtube_video.title }}</div>
                    <a 
                      :href="`https://www.youtube.com/watch?v=${post.youtube_video.id}`" 
                      target="_blank"
                      class="text-xs text-white/80 hover:text-white mt-1 inline-flex items-center gap-1 transition-colors"
                      @click.stop
                      style="pointer-events: auto;"
                    >
                      <i class="pi pi-external-link"></i>
                      مشاهدة على YouTube
                    </a>
                  </div>
                </div>
              </div>

              <!-- Image -->
              <div v-else-if="post.image_url" class="overflow-hidden shadow-md hover:shadow-xl transition-shadow cursor-pointer group rounded"
                   :style="{ borderColor: 'var(--site-primary-color, #450924)' }">
                <img 
                  :src="post.image_url" 
                  alt="Post image" 
                  class="w-full max-h-96 object-contain group-hover:scale-105 transition-transform duration-300 rounded"
                  @click="viewWallPostImage(post.image_url)"
                />
              </div>
            </div>

            <!-- Footer: Delete + Like + Comment -->
            <div class="flex items-center justify-between" 
                 :style="{ borderTopColor: 'var(--site-primary-color, #450924)' }">
              <Button 
                v-if="post.user_id === authStore.user?.id || authStore.user?.all_permissions?.includes('delete_wall_post')"
                label="X"
                class="btn-styled text-xs !border-0"
                size="small"
                @click="deleteWallPost(post.id)"
              />
              
              <div class="flex items-center gap-2">
                <Button 
                  :icon="post.is_liked ? 'pi pi-heart-fill' : 'pi pi-heart'"
                  :label="String(post.likes_count || 0)"
                  text
                  size="small"
                  :class="post.is_liked ? '!text-red-500 hover:!bg-red-50' : 'hover:!bg-gray-100'"
                  @click="toggleWallPostLike(post)"
                />
                <Button 
                  icon="pi pi-comment"
                  :label="String(post.comments_count || 0)"
                  text
                  size="small"
                  class="hover:!bg-gray-100"
                  :style="{ color: 'var(--site-primary-color, #450924)' }"
                  @click="openCommentsModal(post)"
                />
              </div>
            </div>
          </div>
        </div>
        </div>

        <!-- Fixed Post Form at Bottom -->
        <div class="flex-shrink-0 border-t" :style="{ borderTopColor: 'var(--site-primary-color, #450924)' }">
          <!-- Preview Bubble (above input area) -->
          <div v-if="selectedYouTubeVideo || wallPostImagePreview" class="p-2 sm:p-3 border-b" 
               :style="{ borderBottomColor: 'var(--site-primary-color, #450924)', backgroundColor: 'rgba(69, 9, 36, 0.05)' }">
            <!-- Selected YouTube Video -->
            <div v-if="selectedYouTubeVideo" class="mb-2 p-1.5 sm:p-2 rounded flex items-center justify-between gap-2 bg-white"
                 :style="{ borderColor: 'var(--site-primary-color, #450924)' }">
              <div class="flex gap-1.5 sm:gap-2 flex-1 min-w-0">
                <img :src="selectedYouTubeVideo.thumbnail" alt="" class="w-12 h-9 sm:w-16 sm:h-12 object-cover rounded flex-shrink-0" />
                <div class="flex-1 min-w-0">
                  <div class="text-xs font-medium line-clamp-2">{{ selectedYouTubeVideo.title }}</div>
                  <div class="text-[10px] sm:text-xs text-gray-500 mt-0.5 sm:mt-1">{{ selectedYouTubeVideo.channelTitle || 'YouTube' }}</div>
                </div>
              </div>
              <Button 
                icon="pi pi-times" 
                text 
                rounded 
                size="small"
                @click="selectedYouTubeVideo = null"
                class="flex-shrink-0 !w-6 !h-6 sm:!w-8 sm:!h-8 !p-0"
              />
            </div>

            <!-- Selected Image Preview -->
            <div v-if="wallPostImagePreview" class="relative">
              <img :src="wallPostImagePreview" alt="Preview" class="w-full h-24 sm:h-32 object-cover rounded border" 
                   :style="{ borderColor: 'var(--site-primary-color, #450924)' }" />
              <Button 
                icon="pi pi-times" 
                text 
                rounded 
                severity="danger"
                class="absolute top-1 right-1 bg-white/90 !w-6 !h-6 sm:!w-8 sm:!h-8 !p-0"
                @click="wallPostImageFile = null; wallPostImagePreview = null"
              />
            </div>
          </div>

          <!-- Post Form -->
          <div class="flex items-center justify-between p-1.5 sm:p-2" :style="{ backgroundColor: 'var(--site-secondary-color, #ffffff)' }">
            <div class="flex items-center gap-0.5 sm:gap-1 w-full min-w-0">
              <Button 
                icon="pi pi-image" 
                label="" 
                text
                size="small"
                @click="wallPostImageInput?.click()"
                class="flex-shrink-0 !w-7 !h-7 sm:!w-8 sm:!h-8 !p-0"
                :style="{ color: 'var(--site-primary-color, #450924)' }"
                v-tooltip.top="'إضافة صورة'"
              />
              <input 
                ref="wallPostImageInput"
                type="file" 
                accept="image/*" 
                class="hidden" 
                @change="handleWallPostImageSelect"
              />
              <Button 
                ref="emojiButton" 
                type="button" 
                rounded 
                severity="secondary" 
                class="flex-shrink-0 !p-0 !w-7 !h-7 sm:!w-8 sm:!h-8"
                @click="wallEmojiPanel.toggle($event)"
                v-tooltip.top="'لوحة الإيموجي'"
              >
                <img 
                  :src="emojiList.length > 0 ? getEmojiPath(emojiList[0]) : getEmojiPath(0)" 
                  width="16" 
                  height="16" 
                  class="!p-0 sm:w-5 sm:h-5"
                  alt="Emoji"
                >
              </Button>
              <InputText 
                ref="wallPostInput" 
                v-model="wallPost" 
                placeholder="اكتب رسالتك هنا..."
                class="flex-1 min-w-0 text-xs sm:text-sm" 
                size="small" 
                :disabled="postingToWall" 
              />
              <Button 
                type="button"
                @click="postToWall" 
                text 
                label="إرسال" 
                :loading="postingToWall" 
                :disabled="(!wallPost.trim() && !wallPostImageFile && !selectedYouTubeVideo) || postingToWall"
                class="flex-shrink-0 btn-styled !text-white hover:!text-white border-0 !p-1.5 sm:!p-2 !text-xs sm:!text-sm" 
                :style="{ backgroundColor: 'var(--site-button-color, #450924)' }" 
                v-tooltip.top="'إرسال'" 
              />
            </div>
          </div>
        </div>
      </div>
    </Sidebar>

    <!-- Comments Modal -->
    <Dialog 
      v-model:visible="showCommentsModal" 
      modal 
      :style="{ width: '90vw', maxWidth: '600px' }"
      :header="`تعليقات (${currentPostComments.length})`"
    >
      <div v-if="selectedWallPost" class="space-y-4">
        <!-- Post Preview -->
        <div class="border rounded p-3" :style="{ 
          borderColor: 'var(--site-primary-color, #450924)', 
          backgroundColor: 'var(--site-secondary-color, #ffffff)' 
        }">
          <div class="flex items-center gap-2 mb-2">
            <Avatar :image="selectedWallPost.user?.avatar_url" shape="circle" class="w-6 h-6" />
            <div>
              <div class="text-xs font-medium">{{ selectedWallPost.user?.name || selectedWallPost.user?.username }}</div>
            </div>
          </div>
          <p v-if="selectedWallPost.content" class="text-sm">{{ selectedWallPost.content }}</p>
        </div>

        <!-- Comments List -->
        <div class="max-h-96 overflow-y-auto space-y-3 custom-scrollbar">
          <div v-if="loadingComments" class="text-center py-4">
            <i class="pi pi-spin pi-spinner" :style="{ color: 'var(--site-primary-color, #450924)' }"></i>
          </div>
          <div v-else-if="currentPostComments.length === 0" class="text-center py-4 text-gray-500 text-sm">
            لا توجد تعليقات بعد
          </div>
          <div v-else v-for="comment in currentPostComments" :key="comment.id" class="flex gap-2">
            <Avatar :image="comment.user?.avatar_url" shape="circle" class="w-8 h-8 flex-shrink-0" />
            <div class="flex-1">
              <div class="rounded-lg p-2" :style="{ backgroundColor: 'rgba(69, 9, 36, 0.05)' }">
                <div class="text-xs font-medium mb-1">{{ comment.user?.name || comment.user?.username }}</div>
                <div class="text-sm">{{ comment.content }}</div>
              </div>
              <div class="text-xs text-gray-500 mt-1">{{ formatTime(comment.created_at) }}</div>
            </div>
            <Button 
              v-if="comment.user_id === authStore.user?.id || authStore.user?.all_permissions?.delete_wall_posts"
              icon="pi pi-trash" 
              text 
              rounded 
              severity="danger"
              size="small"
              @click="deleteComment(comment.id)"
            />
          </div>
        </div>

        <!-- Add Comment Form -->
        <div class="flex gap-2 border-t pt-3" :style="{ borderTopColor: 'var(--site-primary-color, #450924)' }">
          <InputText 
            v-model="newComment" 
            placeholder="اكتب تعليق..." 
            class="flex-1"
            @keyup.enter="addComment"
          />
          <Button 
            icon="pi pi-send" 
            @click="addComment"
            :disabled="!newComment.trim() || postingComment"
            :loading="postingComment"
            class="btn-styled text-white border-0"
            :style="{ backgroundColor: 'var(--site-button-color, #3D0821)' }"
          />
        </div>
      </div>
    </Dialog>

    <!-- Image Viewer Dialog -->
    <Dialog 
      v-model:visible="showImageDialog" 
      modal 
      :style="{ width: '90vw', maxWidth: '800px' }"
      :pt="{ content: { class: 'p-0' } }"
    >
      <img v-if="viewingImageUrl" :src="viewingImageUrl" alt="" class="w-full" />
    </Dialog>

    <!-- Wall Creators Podium Modal -->
    <Dialog 
      v-model:visible="showWallCreatorsModal" 
      modal 
      :style="{ width: '90vw', maxWidth: '900px' }"
      :pt="{ content: { class: 'p-0' } }"
    >
      <template #header>
        <div class="flex items-center justify-between w-full">
          <h2 class="text-2xl font-bold flex items-center gap-2">
            <i class="pi pi-trophy text-yellow-500"></i>
            <span :style="{ color: 'var(--site-primary-color, #450924)' }">مبدع الحائط</span>
          </h2>
          <Button icon="pi pi-times" text rounded @click="showWallCreatorsModal = false" />
        </div>
      </template>
      
      <div class="p-6" :style="{ backgroundColor: 'var(--site-secondary-color, #ffffff)' }">
        <div v-if="loadingTopCreators" class="text-center py-12">
          <i class="pi pi-spin pi-spinner text-3xl" :style="{ color: 'var(--site-primary-color, #450924)' }"></i>
        </div>
        
        <div v-else-if="topCreators.length === 0" class="text-center py-12 text-gray-500">
          <i class="pi pi-trophy text-4xl mb-4 opacity-50"></i>
          <p class="text-lg">لا يوجد مبدعين بعد</p>
        </div>
        
        <!-- Podium Design -->
        <div v-else class="flex items-end justify-center gap-4 pb-8" 
             :class="{ 'justify-center': topCreators.length === 1, 'justify-around': topCreators.length === 2 }">
          <!-- 2nd Place (Left) -->
          <div v-if="topCreators[1]" class="flex flex-col items-center flex-1 max-w-[200px] order-1">
            <div class="relative mb-2">
              <Avatar 
                :image="topCreators[1].avatar_url" 
                shape="circle" 
                class="w-20 h-20 border-4 border-gray-300"
                :style="{ borderColor: '#C0C0C0' }"
              />
              <div class="absolute -top-2 -right-2 bg-gray-300 text-white rounded-full w-8 h-8 flex items-center justify-center font-bold text-sm">
                2
              </div>
            </div>
            <div class="text-center mb-2">
              <div class="font-bold text-sm">{{ topCreators[1].name || topCreators[1].username }}</div>
              <div class="text-xs text-gray-500">{{ topCreators[1].total_likes }} إعجاب</div>
            </div>
            <!-- Silver Podium -->
            <div class="w-full bg-gradient-to-t from-gray-300 to-gray-200 rounded-t-lg shadow-lg" 
                 style="height: 120px; border-top: 4px solid #C0C0C0;">
              <div class="h-full flex items-center justify-center">
                <i class="pi pi-trophy text-3xl text-gray-400"></i>
              </div>
            </div>
          </div>

          <!-- 1st Place (Center - Tallest) -->
          <div v-if="topCreators[0]" class="flex flex-col items-center flex-1 max-w-[250px] order-2">
            <div class="relative mb-2">
              <Avatar 
                :image="topCreators[0].avatar_url" 
                shape="circle" 
                class="w-24 h-24 border-4 border-yellow-400"
                :style="{ borderColor: '#FFD700' }"
              />
              <div class="absolute -top-2 -right-2 bg-yellow-500 text-white rounded-full w-10 h-10 flex items-center justify-center font-bold shadow-lg">
                1
              </div>
              <!-- Crown icon for 1st place -->
              <div class="absolute -top-4 left-1/2 transform -translate-x-1/2">
                <i class="pi pi-crown text-yellow-500 text-2xl"></i>
              </div>
            </div>
            <div class="text-center mb-2">
              <div class="font-bold text-base">{{ topCreators[0].name || topCreators[0].username }}</div>
              <div class="text-sm text-gray-600 font-semibold">{{ topCreators[0].total_likes }} إعجاب</div>
            </div>
            <!-- Gold Podium -->
            <div class="w-full bg-gradient-to-t from-yellow-400 to-yellow-300 rounded-t-lg shadow-2xl" 
                 style="height: 180px; border-top: 4px solid #FFD700;">
              <div class="h-full flex items-center justify-center">
                <i class="pi pi-trophy text-4xl text-yellow-600"></i>
              </div>
            </div>
          </div>

          <!-- 3rd Place (Right) -->
          <div v-if="topCreators[2]" class="flex flex-col items-center flex-1 max-w-[200px] order-3">
            <div class="relative mb-2">
              <Avatar 
                :image="topCreators[2].avatar_url" 
                shape="circle" 
                class="w-20 h-20 border-4 border-orange-300"
                :style="{ borderColor: '#CD7F32' }"
              />
              <div class="absolute -top-2 -right-2 bg-orange-400 text-white rounded-full w-8 h-8 flex items-center justify-center font-bold text-sm">
                3
              </div>
            </div>
            <div class="text-center mb-2">
              <div class="font-bold text-sm">{{ topCreators[2].name || topCreators[2].username }}</div>
              <div class="text-xs text-gray-500">{{ topCreators[2].total_likes }} إعجاب</div>
            </div>
            <!-- Bronze Podium -->
            <div class="w-full bg-gradient-to-t from-orange-300 to-orange-200 rounded-t-lg shadow-lg" 
                 style="height: 100px; border-top: 4px solid #CD7F32;">
              <div class="h-full flex items-center justify-center">
                <i class="pi pi-trophy text-3xl text-orange-500"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Additional creators list (if more than 3) -->
        <div v-if="topCreators.length > 3" class="mt-8 border-t pt-4">
          <h3 class="text-lg font-semibold mb-4" :style="{ color: 'var(--site-primary-color, #450924)' }">
            باقي المبدعين
          </h3>
          <div class="space-y-2">
            <div 
              v-for="(creator, index) in topCreators.slice(3)" 
              :key="creator.id"
              class="flex items-center gap-3 p-3 rounded-lg hover:bg-gray-50 transition-colors"
            >
              <div class="w-8 h-8 flex items-center justify-center font-bold text-gray-500">
                {{ index + 4 }}
              </div>
              <Avatar :image="creator.avatar_url" shape="circle" class="w-10 h-10" />
              <div class="flex-1">
                <div class="font-medium text-sm">{{ creator.name || creator.username }}</div>
                <div class="text-xs text-gray-500">{{ creator.total_likes }} إعجاب</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Dialog>

    <!-- Settings Sidebar -->
    <Sidebar v-model:visible="showSettings" position="right" class="!w-64 md:!w-96">
      <template #header>
        <div class="flex items-center justify-between w-full">
          <h2 class="text-lg font-bold">الإعدادات</h2>
          <Button 
            icon="pi pi-exclamation-triangle" 
            text 
            rounded 
            severity="warning"
            @click="openWarnings"
            v-tooltip.top="'فتح صندوق التحذيرات'"
            class="relative"
          >
            <Badge 
              v-if="userWarningsRef && userWarningsRef.unreadCount > 0"
              :value="userWarningsRef.unreadCount" 
              severity="danger"
              class="absolute -top-1 -right-1"
            />
          </Button>
        </div>
      </template>
      <div class="space-y-6 px-4 max-h-[calc(95vh-100px)] overflow-y-auto">
        <!-- Profile Section -->
        <div>
          <h3 class="text-lg font-semibold mb-3">الملف الشخصي</h3>
          <div class="space-y-3">
            <div>
              <label class="block text-sm font-medium mb-1">الاسم</label>
              <InputText v-model="profileForm.name" class="w-full" />
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">السيرة الذاتية</label>
              <InputText v-model="profileForm.bio" class="w-full" placeholder="أدخل سيرتك الذاتية" />
            </div>
            <div>
              <label class="block text-sm font-medium mb-2">رابط التواصل الاجتماعي</label>
              <div class="space-y-3">
                <!-- URL Input -->
                <div class="relative">
                  <InputText 
                    v-model="profileForm.social_media_url" 
                    placeholder="أدخل رابط YouTube، Instagram، TikTok، أو X" 
                    class="!text-xs"
                    :class="[
                      'w-full',
                      profileForm.social_media_type ? 'pl-24' : ''
                    ]"
                    @input="detectSocialMediaPlatform"
                    @blur="detectSocialMediaPlatform"
                  />
                  <!-- Detected Platform Badge -->
                  <div 
                    v-if="profileForm.social_media_type"
                    class="absolute left-2 top-1/2 -translate-y-1/2 flex items-center gap-1.5 px-2 py-1 bg-white rounded border border-gray-200 shadow-sm z-10"
                  >
                    <div 
                      class="flex-shrink-0"
                      v-html="getSocialMediaIcon(profileForm.social_media_type)?.svg"
                      :class="getSocialMediaIcon(profileForm.social_media_type)?.color"
                      style="width: 16px; height: 16px;"
                    ></div>
                    <span class="text-xs font-medium">{{ getSocialMediaPlatformName(profileForm.social_media_type) }}</span>
                  </div>
                </div>
                
                <!-- Clear Button -->
                <Button 
                  v-if="profileForm.social_media_type || profileForm.social_media_url"
                  label="حذف الرابط" 
                  icon="pi pi-trash" 
                  severity="danger" 
                  text 
                  size="small"
                  @click="clearSocialMedia" 
                />
              </div>
            </div>
            <!-- Profile Picture Section -->
            <div>
              <label class="block text-sm font-medium mb-2">الصورة الشخصية</label>
              <div class="flex items-center gap-4">
                <!-- Current Profile Picture -->
                <div class="relative group">
                  <div 
                    class="w-24 h-24 rounded-full overflow-hidden border-2 border-gray-300 cursor-pointer hover:border-primary transition-all shadow-md hover:shadow-lg"
                    @click="changeProfilePicture"
                  >
                    <img 
                      :src="authStore.user?.avatar_url || getDefaultUserImage()" 
                      alt="Profile Picture"
                      class="w-full h-full object-cover"
                    />
                  </div>
                  <!-- Overlay on hover -->
                  <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity rounded-full flex items-center justify-center cursor-pointer" @click="changeProfilePicture">
                    <i class="pi pi-camera text-white text-xl"></i>
                  </div>
                  <!-- Delete button -->
                  <button
                    v-if="authStore.user?.avatar_url"
                    @click.stop="deleteProfilePicture"
                    class="absolute -top-1 -right-1 w-6 h-6 bg-red-500 hover:bg-red-600 text-white rounded-full flex items-center justify-center shadow-md transition-all opacity-0 group-hover:opacity-100"
                    v-tooltip.top="'حذف الصورة'"
                  >
                    <i class="pi pi-times text-xs"></i>
                  </button>
                </div>
                <div class="flex-1">
                  <p class="text-xs text-gray-600 mb-1">انقر على الصورة لتغييرها</p>
                  <p class="text-xs text-gray-500">الصيغ المدعومة: JPG, PNG, GIF, WebP</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Appearance Section -->
        <div>
          <h3 class="text-lg font-semibold mb-3">المظهر</h3>
          <div class="space-y-3">
            <div class="flex items-center gap-3">
              <ColorPicker 
                :modelValue="settingsStore.nameColor"
                @update:modelValue="(value) => settingsStore.setNameColor(value)"
                format="rgb" 
                class="border rounded-md" 
              />
              <label class="text-sm font-medium whitespace-nowrap">لون الاسم</label>
              
            </div>
            <div class="flex items-center gap-3">
              <ColorPicker 
                :modelValue="settingsStore.messageColor"
                @update:modelValue="(value) => settingsStore.setMessageColor(value)"
                format="rgb" 
                class="border rounded-md" 
              />
              <label class="text-sm font-medium whitespace-nowrap">لون الرسائل</label>
              
            </div>
            <div class="flex items-center gap-3">
              <ColorPicker 
                :modelValue="settingsStore.nameBgColor === 'transparent' ? { r: 255, g: 255, b: 255 } : settingsStore.nameBgColor"
                @update:modelValue="(value) => {
                  if (value && typeof value === 'object' && 'r' in value && 'g' in value && 'b' in value) {
                    settingsStore.setNameBgColor(value)
                  }
                }"
                format="rgb" 
                class="border rounded-md" 
              />
              <label class="text-sm font-medium whitespace-nowrap">لون خلفية الاسم</label>
              
            </div>
            <div class="flex items-center gap-3">
              <ColorPicker 
                :modelValue="settingsStore.imageBorderColor"
                @update:modelValue="(value) => settingsStore.setImageBorderColor(value)"
                format="rgb" 
                class="border rounded-md" 
              />
              <label class="text-sm font-medium whitespace-nowrap">لون إطار الصورة</label>
              
            </div>
            <div class="flex items-center gap-3">
              <ColorPicker 
                :modelValue="settingsStore.bioColor"
                @update:modelValue="(value) => settingsStore.setBioColor(value)"
                format="rgb" 
                class="border rounded-md" 
              />
              <label class="text-sm font-medium whitespace-nowrap">لون السيرة الذاتية</label>
              
            </div>
            <div>
              <label class="block text-sm font-medium mb-1">حجم الخط في الغرفة: {{ settingsStore.roomFontSize
              }}px</label>
              <Slider :modelValue="settingsStore.roomFontSize" @update:modelValue="(value) => {
                const numValue = Array.isArray(value) ? value[0] : value
                if (typeof numValue === 'number') {
                  settingsStore.setRoomFontSize(numValue)
                }
              }" :min="10" :max="24" class="w-full" />
            </div>
          </div>
        </div>

        <!-- Membership Designs Button -->
        <div v-if="authStore.user?.designed_membership">
          <Button
            label="تصاميم العضوية"
            icon="pi pi-palette"
            @click="openMembershipDesignsDialog"
            class="w-full"
            severity="info"
          />
        </div>

        <!-- Privacy Section -->
        <div>
          <h3 class="text-lg font-semibold mb-3">الخصوصية</h3>
          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <label class="text-sm">تفعيل الرسائل الخاصة</label>
              <InputSwitch 
                :modelValue="settingsStore.privateMessagesEnabled" 
                @update:modelValue="(value: boolean) => settingsStore.setPrivateMessagesEnabled(value)" 
              />
            </div>
            <div v-if="hasPermission(authStore.user, 'incognito_mode')" class="flex items-center justify-between">
              <label class="text-sm">تفعيل وضع التخفي</label>
              <InputSwitch v-model="incognitoModeEnabled" />
            </div>
            <div class="flex items-center justify-between">
              <label class="text-sm">تفعيل الإشعارات</label>
              <InputSwitch 
                :modelValue="settingsStore.notificationsEnabled" 
                @update:modelValue="(value: boolean) => settingsStore.setNotificationsEnabled(value)" 
              />
            </div>
          </div>
        </div>

        <!-- Dashboard Navigation (only if user has dashboard permission) -->
        <div v-if="hasDashboardAccess">
          <h3 class="text-lg font-semibold mb-3">لوحة التحكم</h3>
          <Button 
            label="الذهاب إلى لوحة التحكم" 
            icon="pi pi-th-large" 
            @click="navigateToDashboard"
            class="w-full"
            severity="info"
          />
        </div>

        <!-- Room Management Section (only for admins/owners) -->
        <div v-if="canManageRoom">
          <h3 class="text-lg font-semibold mb-3">إدارة الغرفة</h3>
          <Button 
            label="إعدادات الغرفة" 
            icon="pi pi-cog" 
            @click="showRoomSettingsModal = true"
            class="w-full"
            severity="secondary"
          />
        </div>

        <!-- Actions -->
        <div class="flex gap-2 pb-6 border-t">
          <Button label="حفظ" icon="pi pi-check" @click="saveSettings" :loading="savingSettings" class="flex-1" />
          <Button label="خروج" icon="pi pi-sign-out" severity="danger" @click="handleLogout" class="flex-1" />
        </div>
      </div>
    </Sidebar>

    <!-- Warnings Modal -->
    <Dialog 
      v-model:visible="showWarningsModal" 
      :style="{ width: '90vw', maxWidth: '800px' }" 
      :modal="true" 
      :closable="true"
      :draggable="false"
      :dismissable-mask="true"
      class="p-fluid"
    >
      <template #header>
        <div class="flex items-center gap-3">
          <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-orange-500 to-orange-600 flex items-center justify-center shadow-lg">
            <i class="pi pi-exclamation-triangle text-white text-xl"></i>
          </div>
          <div>
            <h2 class="text-xl font-bold m-0">صندوق التحذيرات</h2>
            <p class="text-sm text-gray-600 m-0">عرض وإدارة التحذيرات الخاصة بك</p>
          </div>
        </div>
      </template>
      <div class="py-4">
        <UserWarnings ref="userWarningsRef" />
      </div>
    </Dialog>

    <!-- User Profile Modal -->
    <Dialog v-model:visible="showUserProfileModal" :style="{ width: '550px', maxWidth: '90vw' }" :modal="true" :closable="false" class="p-fluid">
      <template #header>
        <div class="flex items-center justify-between w-full">
          <div class="flex items-center gap-2">
            <img
              v-if="getRoleGroupBanner(selectedUser)"
              :src="getRoleGroupBanner(selectedUser)"
              :alt="getRoleGroupName(selectedUser)"
              class="h-5 w-auto object-contain"
            />
            <span class="text-lg font-bold">{{ selectedUser?.name || selectedUser?.username }}</span>
            <i v-if="selectedUser?.group_role" 
               :class="getRoleIcon(selectedUser.group_role)" 
               class="text-lg"
               :title="`Role: ${selectedUser.group_role}`"></i>
          </div>
          <Button icon="pi pi-times" text rounded @click="closeUserProfileModal" />
        </div>
      </template>

      <div class="flex flex-col gap-4">
        <!-- User Image -->
        <div class="flex justify-center relative w-full overflow-hidden" style="height: 256px;">
          <!-- Blurred background image -->
          <div
            v-if="selectedUser?.avatar_url || getDefaultUserImage()"
            class="absolute inset-0 w-full h-full"
            :style="{
              backgroundImage: `url(${selectedUser?.avatar_url || getDefaultUserImage()})`,
              backgroundSize: 'cover',
              backgroundPosition: 'center',
              filter: 'blur(20px)',
              transform: 'scale(1.1)',
            }"
          ></div>
          <!-- Main image -->
          <div class="relative z-10 flex items-center justify-center h-full">
            <div class="relative">
              <Avatar
                :image="selectedUser?.avatar_url || getDefaultUserImage()"
                shape="square"
                class="!w-64 !h-64 !object-cover"
                style="width: 256px !important; height: 256px !important;"
                size="large"
              />
              <!-- Social Media Icon Overlay -->
              <div
                v-if="selectedUser && selectedUser.social_media_type && selectedUser.social_media_url"
                @click.stop="openSocialMediaPopup(selectedUser)"
                class="absolute bottom-2 right-2 bg-white rounded-full shadow-lg cursor-pointer hover:scale-110 transition-transform z-20 flex items-center justify-center"
                :class="getSocialMediaIcon(selectedUser.social_media_type)?.color"
                style="width: 36px; height: 36px; padding: 6px;"
                v-html="getSocialMediaIcon(selectedUser.social_media_type)?.svg"
              ></div>
            </div>
          </div>
        </div>

        <!-- User Bio -->
        <div v-if="selectedUser?.bio" class="text-center text-sm text-gray-700">
          {{ selectedUser.bio }}
        </div>

        <!-- User Flag and Country -->
        <div v-if="selectedUser?.country_code" class="flex items-center justify-center gap-2">
          <img
            :src="`/flags/${selectedUser.country_code.toLowerCase()}.png`"
            :alt="selectedUser.country_code"
            class="w-8 h-6 object-contain"
            @error="(e: Event) => { const target = e.target as HTMLImageElement; if (target) target.style.display = 'none' }"
          />
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col gap-3">
          <!-- Communication Actions -->
          <div class="flex flex-wrap gap-2">
            <Button 
              label="رسالة خاصة" 
              icon="pi pi-send" 
              size="small"
              class="flex-1 min-w-[120px]"
              @click="openPrivateChat(selectedUser!)"
              :disabled="!selectedUser?.private_messages_enabled || selectedUser?.id === authStore.user?.id || !authStore.user?.private_messages_enabled"
              v-tooltip.top="!selectedUser?.private_messages_enabled ? 'المستخدم قام بإيقاف الرسائل الخاصة' : selectedUser?.id === authStore.user?.id ? 'لا يمكنك إرسال رسالة لنفسك' : !authStore.user?.private_messages_enabled ? 'الرسائل الخاصة معطلة في حسابك' : 'إرسال رسالة خاصة'"
            />
            <Button 
              label="إشعار" 
              icon="pi pi-bell" 
              size="small"
              severity="secondary"
              class="flex-1 min-w-[120px]"
              @click="notifyUser"
            />
            <Button 
              :label="`إعجاب (${selectedUser?.likes || 0})`" 
              icon="pi pi-heart" 
              size="small"
              severity="danger"
              class="flex-1 min-w-[120px]"
              @click="likeUser"
            />
            <Button 
              v-if="hasRole(20)"
              label="إرسال هدية" 
              icon="pi pi-gift" 
              size="small"
              severity="secondary"
              class="flex-1 min-w-[120px]"
              @click="sendGift"
            />
          </div>

          <!-- Profile Actions -->
          <div class="flex flex-wrap gap-2">
            <Button 
              label="حذف الصورة" 
              icon="pi pi-trash" 
              size="small"
              severity="danger"
              outlined
              class="flex-1 min-w-[120px]"
              @click="deleteUserPhoto"
            />
            <Button 
              label="تعيين بانر" 
              icon="pi pi-image" 
              size="small"
              severity="secondary"
              outlined
              class="flex-1 min-w-[120px]"
              @click="setUserBanner"
            />
          </div>

          <!-- Moderation Actions -->
          <div class="flex flex-wrap gap-2">
            <Button 
              label="طرد من الغرفة" 
              icon="pi pi-sign-out" 
              size="small"
              severity="warning"
              outlined
              class="flex-1 min-w-[140px]"
              @click="kickFromRoom"
            />
            <Button 
              label="طرد من الموقع" 
              icon="pi pi-ban" 
              size="small"
              severity="warning"
              outlined
              class="flex-1 min-w-[140px]"
              @click="kickFromSite"
            />
            <Button 
              label="حظر" 
              icon="pi pi-lock" 
              size="small"
              severity="danger"
              outlined
              class="flex-1 min-w-[120px]"
              @click="banUser"
            />
          </div>

          <!-- User Controls -->
          <div class="flex flex-wrap gap-2">
            <Button 
              label="بلاغ" 
              icon="pi pi-flag" 
              size="small"
              severity="secondary"
              outlined
              class="flex-1 min-w-[120px]"
              @click="reportUser"
            />
            <Button 
              label="كتم" 
              icon="pi pi-volume-off" 
              size="small"
              severity="secondary"
              outlined
              class="flex-1 min-w-[120px]"
              @click="muteUser"
            />
            <Button 
              label="كتم في كل الغرف" 
              icon="pi pi-volume-off" 
              size="small"
              severity="secondary"
              outlined
              class="flex-1 min-w-[140px]"
              @click="muteUserAllRooms"
            />
            <Button 
              label="تجاهل" 
              icon="pi pi-eye-slash" 
              size="small"
              severity="secondary"
              outlined
              class="flex-1 min-w-[120px]"
              @click="ignoreUser"
            />
          </div>
        </div>

        <!-- Admin Section -->
        <div v-if="canManageUser" class="border-t pt-4 space-y-4">
          <h3 class="font-semibold text-lg">إدارة المستخدم (للمشرفين)</h3>
          
          <!-- Name Edit -->
          <div class="field">
            <label for="admin-name" class="block text-sm font-medium mb-2">الاسم</label>
            <div class="flex gap-2">
              <InputText id="admin-name" v-model="adminEditForm.name" class="flex-1" />
              <Button 
                label="حفظ" 
                icon="pi pi-check" 
                @click="saveUserName" 
                :loading="savingUserData"
                size="small"
                severity="success"
              />
            </div>
          </div>

          <!-- Likes Edit -->
          <div class="field">
            <label for="admin-likes" class="block text-sm font-medium mb-2">الإعجابات</label>
            <div class="flex gap-2">
              <InputNumber id="admin-likes" v-model="adminEditForm.likes" :min="0" class="flex-1" />
              <Button 
                label="حفظ" 
                icon="pi pi-check" 
                @click="saveUserLikes" 
                :loading="savingUserData"
                size="small"
                severity="success"
              />
            </div>
          </div>

          <!-- Points Edit -->
          <div class="field">
            <label for="admin-points" class="block text-sm font-medium mb-2">النقاط</label>
            <div class="flex gap-2">
              <InputNumber id="admin-points" v-model="adminEditForm.points" :min="0" class="flex-1" />
              <Button 
                label="حفظ" 
                icon="pi pi-check" 
                @click="saveUserPoints" 
                :loading="savingUserData"
                size="small"
                severity="success"
              />
            </div>
          </div>

          <!-- Bio Edit -->
          <div class="field">
            <label for="admin-bio" class="block text-sm font-medium mb-2">السيرة الذاتية</label>
            <div class="flex gap-2">
              <InputText id="admin-bio" v-model="adminEditForm.bio" class="flex-1" />
              <Button 
                label="حفظ" 
                icon="pi pi-check" 
                @click="saveUserBio" 
                :loading="savingUserData"
                size="small"
                severity="success"
              />
            </div>
          </div>

          <!-- Role Group Selector -->
          <div class="field">
            <label for="admin-role-group" class="block text-sm font-medium mb-2">مجموعة الدور</label>
            <div class="flex flex-col gap-2">
              <Dropdown
                id="admin-role-group"
                v-model="adminEditForm.roleGroupId"
                :options="availableRoleGroups"
                optionLabel="name"
                optionValue="id"
                placeholder="اختر مجموعة الدور"
                class="w-full"
                :loading="loadingRoleGroups"
                :showClear="true"
                @change="onRoleGroupChange"
              >
                <template #option="slotProps">
                  <div class="flex items-center gap-2">
                    <img
                      v-if="slotProps.option.banner"
                      :src="slotProps.option.banner"
                      :alt="slotProps.option.name"
                      class="h-4 w-auto object-contain"
                    />
                    <span>{{ slotProps.option.name }}</span>
                  </div>
                </template>
                <template #value="slotProps">
                  <div v-if="slotProps.value" class="flex items-center gap-2">
                    <img
                      v-if="getRoleGroupBannerById(slotProps.value)"
                      :src="getRoleGroupBannerById(slotProps.value)"
                      :alt="getRoleGroupNameById(slotProps.value)"
                      class="h-4 w-auto object-contain"
                    />
                    <span>{{ getRoleGroupNameById(slotProps.value) }}</span>
                  </div>
                  <span v-else class="text-gray-400">اختر مجموعة الدور</span>
                </template>
              </Dropdown>
              
              <!-- Expiration Date for Role Group -->
              <div v-if="adminEditForm.roleGroupId" class="flex items-center gap-2 mt-2 p-3 bg-gray-50 rounded">
                <Checkbox
                  inputId="expires-role-group"
                  :binary="true"
                  :modelValue="adminEditForm.roleGroupExpiration !== null"
                  @update:modelValue="toggleExpiration($event)"
                />
                <label for="expires-role-group" class="text-sm text-gray-600">محدد المدة</label>
                <Calendar
                  v-if="adminEditForm.roleGroupExpiration !== null"
                  v-model="adminEditForm.roleGroupExpiration"
                  inputId="exp-date-role-group"
                  dateFormat="yy-mm-dd"
                  :showTime="true"
                  hourFormat="24"
                  :minDate="new Date()"
                  placeholder="تاريخ الانتهاء"
                  class="flex-1"
                  size="small"
                />
              </div>
              
              <Button 
                label="حفظ مجموعة الدور" 
                icon="pi pi-check" 
                @click="saveUserRoleGroup" 
                :loading="savingUserData"
                size="small"
                severity="success"
              />
            </div>
          </div>

          <!-- Room Selector -->
          <div class="field">
            <label for="admin-room" class="block text-sm font-medium mb-2">تغيير الغرفة</label>
            <div class="flex flex-col gap-2">
              <Dropdown 
                id="admin-room"
                v-model="adminEditForm.roomId" 
                :options="availableRooms" 
                optionLabel="name" 
                optionValue="id"
                placeholder="اختر الغرفة"
                class="w-full"
              />
              <div v-if="selectedRoomForMove?.password" class="flex gap-2">
                <InputText 
                  v-model="adminEditForm.roomPassword" 
                  type="password" 
                  placeholder="كلمة مرور الغرفة" 
                  class="flex-1"
                />
              </div>
              <Button 
                label="نقل المستخدم" 
                icon="pi pi-arrow-right" 
                @click="moveUserToRoom" 
                :loading="movingUser"
                size="small"
                severity="info"
              />
            </div>
          </div>
        </div>
      </div>
    </Dialog>

    <!-- Password Dialog for Locked Rooms -->
    <Dialog 
      v-model:visible="showPasswordDialog" 
      :style="{ width: '400px', maxWidth: '90vw' }" 
      :modal="true" 
      :closable="false"
      class="p-fluid"
    >
      <template #header>
        <div class="flex items-center gap-2">
          <i class="pi pi-lock text-xl"></i>
          <span class="text-lg font-semibold">غرفة محمية بكلمة مرور</span>
        </div>
      </template>
      
      <div class="space-y-4">
        <div>
          <p class="text-sm text-gray-600 mb-4">
            هذه الغرفة محمية بكلمة مرور. يرجى إدخال كلمة المرور للوصول إلى الغرفة:
          </p>
          <p class="font-medium mb-2" :style="{ color: 'var(--site-primary-color, #450924)' }">{{ passwordProtectedRoomName }}</p>
        </div>
        
        <div>
          <label class="block text-sm font-medium mb-2">كلمة المرور</label>
          <Password 
            v-model="roomPasswordInput" 
            placeholder="أدخل كلمة المرور"
            class="w-full"
            :feedback="false"
            toggleMask
            @keyup.enter="submitRoomPassword"
            :disabled="submittingPassword"
          />
          <small v-if="passwordError" class="p-error mt-1 block">{{ passwordError }}</small>
        </div>
      </div>
      
      <template #footer>
        <div class="flex justify-end gap-2">
          <Button 
            label="إلغاء" 
            severity="secondary"
            @click="cancelPasswordDialog"
            :disabled="submittingPassword"
          />
          <Button 
            label="دخول" 
            icon="pi pi-check"
            @click="submitRoomPassword"
            :loading="submittingPassword"
          />
        </div>
      </template>
    </Dialog>

    <!-- Report User Dialog - Lazy loaded -->
    <LazyReportUserDialog
      v-if="showReportDialog"
      :model-value="showReportDialog"
      @update:model-value="showReportDialog = $event"
      :reported-user="selectedUser"
      @reported="handleReported"
    />

    <!-- Social Media Popup -->
    <Dialog 
      v-model:visible="showSocialMediaPopup" 
      modal 
      :style="{ width: '90vw', maxWidth: '800px' }"
      :pt="{
        root: { class: 'social-media-dialog' },
        content: { class: 'p-0' }
      }"
      @hide="socialMediaUrl = null; socialMediaType = null"
    >
      <template #header>
        <div class="flex items-center justify-between w-full">
          <h3 class="text-lg font-semibold">
            {{ getSocialMediaPlatformName(socialMediaType) }}
          </h3>
        </div>
      </template>
      <div v-if="socialMediaUrl" class="w-full" style="height: 70vh;">
        <iframe 
          :src="getEmbedUrl(socialMediaUrl, socialMediaType)" 
          class="w-full h-full border-0"
          allowfullscreen
        ></iframe>
      </div>
    </Dialog>

    <!-- Private Message Modal -->
    <Dialog 
      v-model:visible="showPrivateMessageModal" 
      modal 
      :style="{ 
        width: '100vw', 
        maxWidth: '750px', 
        height: '50vh',
        maxHeight: '50vh'
      }"
      :closable="true"
      :pt="{
        root: { class: 'private-message-dialog' },
        content: { class: 'p-0 flex flex-col h-full' }
      }"
      class="p-fluid private-message-dialog-mobile"
      @hide="closePrivateMessageModal"
    >
      <template #header>
        <div class="flex items-center justify-between w-full px-2 sm:px-4">
          <div class="flex items-center gap-2 sm:gap-3 min-w-0 flex-1">
            <Avatar 
              :image="privateMessagesStore.currentConversation?.avatar_url || getDefaultUserImage()" 
              shape="circle" 
              class="w-8 h-8 sm:w-10 sm:h-10 flex-shrink-0" 
            />
            <div class="min-w-0 flex-1">
              <div class="font-bold text-sm sm:text-lg truncate">
                {{ privateMessagesStore.currentConversation?.name || privateMessagesStore.currentConversation?.username }}
              </div>
              <div v-if="privateMessagesStore.currentConversation?.bio" class="text-xs sm:text-sm text-gray-500 truncate hidden sm:block">
                {{ privateMessagesStore.currentConversation.bio }}
              </div>
            </div>
          </div>
        </div>
      </template>
      
      <div class="flex flex-col flex-1 min-h-0 private-message-content">
        <!-- Messages Container -->
        <div ref="privateMessagesContainer" class="flex-1 overflow-y-auto p-2 sm:p-4 space-y-3 sm:space-y-4 bg-gray-50">
          <div v-if="privateMessagesStore.currentConversationMessages.length === 0" class="text-center py-8 text-gray-500 text-sm sm:text-base">
            لا توجد رسائل. ابدأ المحادثة الآن!
          </div>
          <div v-for="message in privateMessagesStore.currentConversationMessages" :key="message.id"
            class="flex"
            :class="message.sender_id === authStore.user?.id ? 'justify-end' : 'justify-start'">
            <div class="max-w-[85%] sm:max-w-[70%] flex gap-1.5 sm:gap-2" :class="message.sender_id === authStore.user?.id ? 'flex-row-reverse' : 'flex-row'">
              <Avatar v-if="message.sender_id !== authStore.user?.id" 
                :image="message.sender?.avatar_url || getDefaultUserImage()" 
                shape="circle" 
                class="w-6 h-6 sm:w-8 sm:h-8 flex-shrink-0" />
              <div class="rounded-lg p-2 sm:p-3 shadow-sm"
                :class="message.sender_id === authStore.user?.id 
                  ? 'bg-blue-500 text-white' 
                  : 'bg-white text-gray-900 border border-gray-200'">
                <!-- Image if present -->
                <img 
                  v-if="message.meta?.image" 
                  :src="message.meta.image" 
                  :alt="message.meta.image_name || 'صورة'"
                  class="max-w-full max-h-48 sm:max-h-64 rounded mb-1 sm:mb-2"
                />
                <!-- Message content with emojis -->
                <div class="text-xs sm:text-sm whitespace-pre-wrap break-words">
                  <template v-for="(part, index) in parseMessageWithEmojis(message.content)" :key="index">
                    <img v-if="part.type === 'emoji' && part.emojiId !== undefined" 
                      :src="getEmojiPath(part.emojiId)"
                      :alt="`:${part.emojiId}:`" 
                      class="inline-block w-3 h-3 sm:w-4 sm:h-4 align-middle" />
                    <span v-else-if="part.type === 'text'" class="inline">
                      {{ part.text }}
                    </span>
                  </template>
                </div>
                <div class="text-[10px] sm:text-xs mt-1 flex items-center gap-1"
                  :class="message.sender_id === authStore.user?.id ? 'text-blue-100' : 'text-gray-500'">
                  {{ moment(message.created_at).format('HH:mm') }}
                  <i v-if="message.sender_id === authStore.user?.id && message.read_at" class="pi pi-check-double text-[10px] sm:text-xs"></i>
                  <i v-else-if="message.sender_id === authStore.user?.id" class="pi pi-check text-[10px] sm:text-xs"></i>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Message Input -->
        <div class="border-t p-2 sm:p-3 bg-white flex-shrink-0">
          <div class="flex gap-1 sm:gap-2 items-center min-w-0">
            <Button 
              ref="privateEmojiButton" 
              type="button" 
              rounded 
              severity="secondary" 
              class="flex-shrink-0 !p-0 w-8 h-8 sm:w-9 sm:h-9 md:w-10 md:h-10"
              @click="privateEmojiPanel.toggle($event)" 
              v-tooltip.top="'لوحة الإيموجي'"
            >
              <img 
                :src="emojiList.length > 0 ? getEmojiPath(emojiList[0]) : getEmojiPath(0)" 
                width="18" 
                height="18" 
                class="!p-0 sm:w-5 sm:h-5"
                alt="Emoji"
              >
            </Button>
            <InputText 
              ref="privateMessageInput"
              v-model="privateMessageContent" 
              placeholder="اكتب رسالة..."
              class="flex-1 min-w-0 text-sm sm:text-base"
              size="small"
              @keyup.enter="sendPrivateMessage"
              :disabled="sendingPrivateMessage"
            />
            <FileUpload
              mode="basic"
              accept="image/*"
              :maxFileSize="5000000"
              :auto="true"
              chooseLabel=""
              class="flex-shrink-0"
              :pt="{
                chooseButton: { class: '!w-8 !h-8 sm:!w-9 sm:!h-9 md:!w-10 md:!h-10 !p-0' }
              }"
              @select="onPrivateMessageFileSelect"
              v-tooltip.top="'إرسال صورة'"
            >
              <template #chooseicon>
                <i class="pi pi-image text-sm sm:text-base md:text-lg"></i>
              </template>
            </FileUpload>
            <Button 
              icon="pi pi-send" 
              @click="sendPrivateMessage"
              :loading="sendingPrivateMessage"
              :disabled="!privateMessageContent.trim() && !privateMessageImageFile"
              class="flex-shrink-0 !w-8 !h-8 sm:!w-9 sm:!h-9 md:!w-10 md:!h-10 !p-0"
            />
          </div>
          
          <!-- Emoji Panel Popup -->
          <OverlayPanel ref="privateEmojiPanel" class="emoji-panel">
            <div class="w-[90vw] sm:w-80 max-w-80 max-h-96 overflow-y-auto">
              <div v-if="emojiList.length === 0" class="p-4 text-center text-gray-500">
                <p class="text-sm">لا توجد إيموجي متاحة</p>
                <p class="text-xs mt-2">يرجى إضافة إيموجي من لوحة التحكم</p>
              </div>
              <div v-else class="grid grid-cols-6 sm:grid-cols-8 gap-2 p-2">
                <button v-for="emojiId in emojiList" :key="emojiId" @click="insertPrivateEmoji(emojiId)"
                  class="w-10 h-10 sm:w-10 sm:h-10 p-1 hover:bg-gray-100 rounded transition flex items-center justify-center touch-manipulation"
                  type="button">
                  <img :src="getEmojiPath(emojiId)" :alt="`Emoji ${emojiId}`" class="w-full h-full object-contain" />
                </button>
              </div>
            </div>
          </OverlayPanel>
        </div>
      </div>
    </Dialog>
  </div>
</template>

<script setup lang="ts">
// @ts-ignore - Nuxt auto-imports types
import moment from 'moment'
import { useToast } from 'primevue/usetoast'
// @ts-ignore
import type { Message, User, Room, RoleGroup, PrivateMessage, Conversation } from '~/types'
import { getFlagImageUrl } from '~~/app/utils/flagImage'

definePageMeta({
  middleware: 'auth',
})

const router = useRouter()
const authStore = useAuthStore()
const chatStore = useChatStore()
const privateMessagesStore = usePrivateMessagesStore()
const settingsStore = useSettingsStore()
const { getEcho, initEcho, disconnect } = useEcho()
const toast = useToast()
const { getDefaultUserImage, getSystemMessagesImage } = useSiteSettings()
const { initBootstrap, getBootstrap } = useBootstrap()

const roomId = computed(() => chatStore.currentRoomId?.toString() || '')
const messageContent = ref('')
const sending = ref(false)
const messagesContainer = ref<HTMLElement | null>(null)
const messageInput = ref()

// Sort all messages by created_at (oldest first) - show ALL messages from ALL rooms visited in this session
// Messages persist across room switches until browser refresh/close
const sortedMessages = computed(() => {
  // Show all messages from all rooms, sorted by date (oldest first)
  // Include system messages and welcome messages regardless of room
  const allMessages = [...chatStore.messages]
  
  if (import.meta.dev) {
    const systemCount = allMessages.filter((m: Message) => m.meta?.is_system || m.meta?.is_welcome_message).length
    const welcomeCount = allMessages.filter((m: Message) => m.meta?.is_welcome_message).length
  }
  
  return allMessages.sort((a: Message, b: Message) => {
    const dateA = new Date(a.created_at).getTime()
    const dateB = new Date(b.created_at).getTime()
    return dateA - dateB
  })
})

// Store current channel reference for cleanup
let currentChannel: any = null
let userPrivateChannel: any = null
let isUserChannelSubscribed = false

// Track previous room ID for the current user during navigation
let previousRoomId: string | null = null

// Track recent departures to detect moves reliably
const RECENT_DEPARTURE_TTL = 8000 // ms
const recentRoomDepartures = new Map<number, { roomId: number; timestamp: number }>()

const rememberUserLeftRoom = (userId: number, roomId: number) => {
  recentRoomDepartures.set(userId, { roomId, timestamp: Date.now() })
}

const consumeRecentDeparture = (userId: number) => {
  const info = recentRoomDepartures.get(userId)
  if (!info) {
    return null
  }
  if (Date.now() - info.timestamp > RECENT_DEPARTURE_TTL) {
    recentRoomDepartures.delete(userId)
    return null
  }
  recentRoomDepartures.delete(userId)
  return info
}

const peekRecentDeparture = (userId: number) => {
  const info = recentRoomDepartures.get(userId)
  if (!info) {
    return null
  }
  if (Date.now() - info.timestamp > RECENT_DEPARTURE_TTL) {
    recentRoomDepartures.delete(userId)
    return null
  }
  return info
}

// Flag to prevent duplicate subscriptions
let isSubscribed = false
const emojiButton = ref()
const emojiPanel = ref()
const privateEmojiButton = ref()
const privateEmojiPanel = ref()
const privateMessageInput = ref()
const privateMessageImageFile = ref<File | null>(null)
const replyingTo = ref<Message | null>(null)
const isManualDisconnect = ref(false) // Track if user manually disconnected
const wasDisconnected = ref(false) // Track if socket was disconnected (for reconnect detection)

// Use emojis from database via composable
const { fetchEmojis, getEmojiUrl, getEmojiList, getAllEmojis } = useEmojis()

// Emoji list from database (will be populated after fetch)
const emojiList = ref<number[]>([])

// Legacy emoji images for backward compatibility (fallback only)
const emojiImages = import.meta.glob('~/assets/emojis/*.{gif,png}', { eager: true, query: '?url', import: 'default' }) as Record<string, string>

// Get emoji image path - now uses database URLs
const getEmojiPath = (emojiId: number) => {
  // First try to get from database (new system)
  const dbUrl = getEmojiUrl(emojiId)
  if (dbUrl) {
    return dbUrl
  }

  // Fallback to legacy assets for backward compatibility
  // This handles old messages that might use numeric IDs (0-97)
  for (const [path, url] of Object.entries(emojiImages)) {
    if (path.includes(`/${emojiId}.gif`) || path.includes(`/${emojiId}.png`)) {
      return url
    }
  }

  // Final fallback: try public folder paths
  return `/emojis/${emojiId}.gif`
}

// Parse message content and replace emoji codes with emoji objects
// Supports both new database IDs and legacy numeric IDs (0-97) for backward compatibility
const parseMessageWithEmojis = (content: string) => {
  if (!content) return []

  const parts: Array<{ type: 'text' | 'emoji'; text?: string; emojiId?: number }> = []
  const emojiRegex = /:(\d+):/g
  let lastIndex = 0
  let match

  while ((match = emojiRegex.exec(content)) !== null) {
    // Add text before emoji
    if (match.index > lastIndex) {
      parts.push({
        type: 'text',
        text: content.substring(lastIndex, match.index),
      })
    }

    // Add emoji
    const emojiIdStr = match[1]
    if (!emojiIdStr) {
      // No emoji ID found, keep as text
      parts.push({
        type: 'text',
        text: match[0],
      })
      lastIndex = match.index + match[0].length
      continue
    }

    const emojiId = parseInt(emojiIdStr, 10)
    if (!isNaN(emojiId)) {
      // Check if it's a valid emoji ID (database ID or legacy numeric ID 0-97)
      // Database IDs are typically > 97, but we check both
      const emojiUrl = getEmojiUrl(emojiId)
      const isLegacyId = emojiId >= 0 && emojiId <= 97
      
      if (emojiUrl || isLegacyId) {
        // Valid emoji ID (either from database or legacy)
        parts.push({
          type: 'emoji',
          emojiId,
        })
      } else {
        // Invalid emoji ID, keep as text
        parts.push({
          type: 'text',
          text: match[0],
        })
      }
    } else {
      // Invalid emoji ID, keep as text
      parts.push({
        type: 'text',
        text: match[0],
      })
    }

    lastIndex = match.index + match[0].length
  }

  // Add remaining text after last emoji
  if (lastIndex < content.length) {
    parts.push({
      type: 'text',
      text: content.substring(lastIndex),
    })
  }

  // If no emojis found, return the whole content as text
  if (parts.length === 0) {
    parts.push({
      type: 'text',
      text: content,
    })
  }

  return parts
}

// Helper function to convert RGB object to CSS string
const getRoleIcon = (role: string): string => {
  const roleIcons: Record<string, string> = {
    'admin': 'pi pi-shield',
    'moderator': 'pi pi-user-edit',
    'vip': 'pi pi-star',
    'member': 'pi pi-user',
  }
  return roleIcons[role.toLowerCase()] || 'pi pi-user'
}

// Helper function to get role group banner
const getRoleGroupBanner = (user: any): string | undefined => {
  // First check if role_group_banner is directly available (from backend accessor)
  if (user?.role_group_banner) {
    return user.role_group_banner
  }
  // Fallback to checking role_groups array
  if (user?.role_groups && user.role_groups.length > 0) {
    // Get the highest priority role group (first one, already sorted by backend)
    const primaryRoleGroup = user.role_groups[0]
    return primaryRoleGroup?.banner || undefined
  }
  return undefined
}

// Helper function to get role group name
const getRoleGroupName = (user: any): string => {
  if (!user?.role_groups || user.role_groups.length === 0) {
    return ''
  }
  const primaryRoleGroup = user.role_groups[0]
  return primaryRoleGroup?.name || ''
}

// Helper function to check if user has permission
const hasPermission = (user: any, permission: string): boolean => {
  if (!user?.all_permissions) {
    return false
  }
  return user.all_permissions.includes(permission)
}

const rgbToCss = (color: any): string => {
  if (!color) return ''
  if (typeof color === 'string') {
    // If it's already a string (hex or rgb), return as is
    if (color.startsWith('rgb') || color.startsWith('#') || color === 'transparent') {
      return color
    }
    // Try to parse as stored JSON RGB object
    try {
      const parsed = JSON.parse(color)
      if (parsed && typeof parsed === 'object' && 'r' in parsed && 'g' in parsed && 'b' in parsed) {
        return `rgb(${parsed.r}, ${parsed.g}, ${parsed.b})`
      }
    } catch {
      return color
    }
    return color
  }
  if (typeof color === 'object' && 'r' in color && 'g' in color && 'b' in color) {
    return `rgb(${color.r}, ${color.g}, ${color.b})`
  }
  return ''
}

// Get room-specific styles from room settings
const getRoomStyles = (): Record<string, string> => {
  const styles: Record<string, string> = {
    fontSize: settingsStore.roomFontSize + 'px',
  }
  
  if (!chatStore.currentRoom?.settings) {
    styles.backgroundColor = 'white'
    return styles
  }
  
  const roomSettings = chatStore.currentRoom.settings
  
  // Apply text color if set
  if (roomSettings.textColor) {
    styles.color = roomSettings.textColor
  }
  
  // Apply custom CSS if provided
  if (roomSettings.customCSS) {
    // Parse and apply custom CSS (be careful with this)
    try {
      const customStyles = JSON.parse(roomSettings.customCSS)
      Object.assign(styles, customStyles)
    } catch {
      // If not JSON, treat as CSS string (less safe, but flexible)
      // Note: We can't directly apply CSS strings, so we'll skip this for now
      // In a real implementation, you might want to inject a <style> tag
    }
  }
  
  return styles
}

// Get room image URL (handles both full URLs and relative paths)
const getRoomImageUrl = (imagePath: string | null | undefined): string => {
  if (!imagePath) return ''
  
  // If it's already a full URL, return as is
  if (imagePath.startsWith('http://') || imagePath.startsWith('https://')) {
    return imagePath
  }
  
  // If it starts with /, it's a relative path from root
  if (imagePath.startsWith('/')) {
    const config = useRuntimeConfig()
    const apiBaseUrl = config.public.apiBaseUrl || ''
    return `${apiBaseUrl}${imagePath}`
  }
  
  // Otherwise, assume it's a storage path and prepend storage URL
  const config = useRuntimeConfig()
  const apiBaseUrl = config.public.apiBaseUrl || ''
  return `${apiBaseUrl}/storage/${imagePath}`
}

// Handle image loading errors
const handleImageError = (event: Event) => {
  const img = event.target as HTMLImageElement
  // Hide the image or show a placeholder if it fails to load
  img.style.display = 'none'
}

// Helper to parse settings (in case they come as JSON string)
const parseRoomSettings = (room: Room): any => {
  if (!room.settings) return null
  
  // If settings is already an object, return it
  if (typeof room.settings === 'object' && !Array.isArray(room.settings)) {
    return room.settings
  }
  
  // If settings is a string, try to parse it
  if (typeof room.settings === 'string') {
    try {
      return JSON.parse(room.settings)
    } catch {
      return null
    }
  }
  
  return null
}

// Get room settings helper
const getRoomSettings = (room: Room): any => {
  return parseRoomSettings(room)
}

// Get banner style for room card
const getRoomBannerStyle = (room: Room): Record<string, string> => {
  const roomSettings = parseRoomSettings(room)
  const styles: Record<string, string> = {}
  
  if (roomSettings?.useImageAsBanner && (room.room_cover || room.room_image || room.room_image_url)) {
    // Banner mode - ensure proper height and rounded corners
    styles.minHeight = '80px'
    styles.borderRadius = '8px'
  }
  
  return styles
}

// Get room card styles for sidebar list
const getRoomCardStyles = (room: Room): Record<string, string> => {
  const styles: Record<string, string> = {
    borderWidth: '1px',
    borderStyle: 'solid',
  }
  
  const roomSettings = parseRoomSettings(room)
  
  if (!roomSettings) {
    styles.borderColor = '#e5e7eb' // gray-200
    styles.backgroundColor = '#ffffff'
    return styles
  }
  
  // Apply background color if set
  if (roomSettings.backgroundColor) {
    // Ensure it's a valid color value
    let bgColor = String(roomSettings.backgroundColor).trim()
    // If it's a hex color without #, add the prefix
    if (bgColor && !bgColor.startsWith('#') && /^[0-9A-Fa-f]{6}$/.test(bgColor)) {
      bgColor = '#' + bgColor
    }
    if (bgColor && !bgColor.startsWith('#') && /^[0-9A-Fa-f]{3}$/.test(bgColor)) {
      bgColor = '#' + bgColor
    }
    if (bgColor && (bgColor.startsWith('#') || bgColor.startsWith('rgb') || bgColor.startsWith('rgba'))) {
      styles.backgroundColor = bgColor
    } else {
      styles.backgroundColor = '#ffffff'
    }
  } else {
    styles.backgroundColor = '#ffffff'
  }
  
  // Apply border color if set
  if (roomSettings.roomBorderColor) {
    // Ensure it's a valid color value
    let borderColor = String(roomSettings.roomBorderColor).trim()
    // If it's a hex color without #, add the prefix
    if (borderColor && !borderColor.startsWith('#') && /^[0-9A-Fa-f]{6}$/.test(borderColor)) {
      borderColor = '#' + borderColor
    }
    if (borderColor && !borderColor.startsWith('#') && /^[0-9A-Fa-f]{3}$/.test(borderColor)) {
      borderColor = '#' + borderColor
    }
    if (borderColor && (borderColor.startsWith('#') || borderColor.startsWith('rgb') || borderColor.startsWith('rgba'))) {
      styles.borderColor = borderColor
    } else {
      styles.borderColor = '#e5e7eb' // gray-200
    }
  } else {
    styles.borderColor = '#e5e7eb' // gray-200
  }
  
  return styles
}

// Get room name color
const getRoomNameColor = (room: Room): string => {
  if (!room) return '#1f2937'
  
  const roomSettings = parseRoomSettings(room)
  
  
  if (roomSettings?.roomNameColor) {
    let color = String(roomSettings.roomNameColor).trim()
    
    // Handle ColorPicker object format if it wasn't converted
    if (typeof roomSettings.roomNameColor === 'object' && roomSettings.roomNameColor.hex) {
      color = roomSettings.roomNameColor.hex
    }
    
    // If it's a hex color without #, add the prefix
    if (color && !color.startsWith('#') && /^[0-9A-Fa-f]{6}$/.test(color)) {
      color = '#' + color
    }
    // Handle 3-digit hex
    if (color && !color.startsWith('#') && /^[0-9A-Fa-f]{3}$/.test(color)) {
      color = '#' + color
    }
    
    // Validate color format
    if (color && (color.startsWith('#') || color.startsWith('rgb') || color.startsWith('rgba'))) {
      return color
    }
  }
  
  return '#1f2937' // gray-800 default
}

// Get room description color
const getRoomDescriptionColor = (room: Room): string => {
  const roomSettings = parseRoomSettings(room)
  if (roomSettings?.roomDescriptionColor) {
    let color = String(roomSettings.roomDescriptionColor).trim()
    // If it's a hex color without #, add the prefix
    if (color && !color.startsWith('#') && /^[0-9A-Fa-f]{6}$/.test(color)) {
      color = '#' + color
    }
    if (color && !color.startsWith('#') && /^[0-9A-Fa-f]{3}$/.test(color)) {
      color = '#' + color
    }
    if (color && (color.startsWith('#') || color.startsWith('rgb') || color.startsWith('rgba'))) {
      return color
    }
  }
  return '#6b7280' // gray-500 default
}

// Get room text color (for general text)
const getRoomTextColor = (room: Room): string => {
  const roomSettings = parseRoomSettings(room)
  if (roomSettings?.textColor) {
    let color = String(roomSettings.textColor).trim()
    // If it's a hex color without #, add the prefix
    if (color && !color.startsWith('#') && /^[0-9A-Fa-f]{6}$/.test(color)) {
      color = '#' + color
    }
    if (color && !color.startsWith('#') && /^[0-9A-Fa-f]{3}$/.test(color)) {
      color = '#' + color
    }
    if (color && (color.startsWith('#') || color.startsWith('rgb') || color.startsWith('rgba'))) {
      return color
    }
  }
  return '#4b5563' // gray-600 default
}

// Get room border color (for image border)
const getRoomBorderColor = (room: Room): string => {
  const roomSettings = parseRoomSettings(room)
  if (roomSettings?.roomBorderColor) {
    let color = String(roomSettings.roomBorderColor).trim()
    // If it's a hex color without #, add the prefix
    if (color && !color.startsWith('#') && /^[0-9A-Fa-f]{6}$/.test(color)) {
      color = '#' + color
    }
    if (color && !color.startsWith('#') && /^[0-9A-Fa-f]{3}$/.test(color)) {
      color = '#' + color
    }
    if (color && (color.startsWith('#') || color.startsWith('rgb') || color.startsWith('rgba'))) {
      return color
    }
  }
  return '#e5e7eb' // gray-200 default
}

// Get room background color (for placeholder when no image)
const getRoomBackgroundColor = (room: Room): string => {
  const roomSettings = parseRoomSettings(room)
  if (roomSettings?.backgroundColor) {
    let color = String(roomSettings.backgroundColor).trim()
    // If it's a hex color without #, add the prefix
    if (color && !color.startsWith('#') && /^[0-9A-Fa-f]{6}$/.test(color)) {
      color = '#' + color
    }
    if (color && !color.startsWith('#') && /^[0-9A-Fa-f]{3}$/.test(color)) {
      color = '#' + color
    }
    if (color && (color.startsWith('#') || color.startsWith('rgb') || color.startsWith('rgba'))) {
      return color
    }
  }
  return '#f3f4f6' // gray-100 default
}

function timeSinceArabic(dateString: string) {
  // Use currentTime ref to make it reactive and auto-update
  const now = moment(currentTime.value);
  const past = moment(dateString);
  const diffSeconds = Math.abs(now.diff(past, 'seconds'));
  const duration = moment.duration(diffSeconds, 'seconds');

  const days = Math.floor(duration.asDays());
  const hours = duration.hours();
  const minutes = duration.minutes();

  let parts = [];
  if (days) parts.push(`${days}ي`);
  if (hours) parts.push(`${hours}س`);
  if (minutes) parts.push(`${minutes}د`);
  // If less than a minute, show "0 د" instead of seconds
  if (!days && !hours && !minutes) parts.push('0 د');

  return parts.join(' ');
}

// Dialog/Sidebar visibility
const showUsersSidebar = ref(false)
const showPrivateMessages = ref(false)
const showRoomsList = ref(false)
const showWall = ref(false)
const showSettings = ref(false)
const hasNewWallPost = ref(false)
let newWallPostTimeout: ReturnType<typeof setTimeout> | null = null
const savingSettings = ref(false)
const showWarningsModal = ref(false)
const userWarningsRef = ref<any>(null)

// User Profile Modal
const showUserProfileModal = ref(false)
const selectedUser = ref<User | null>(null)
const savingUserData = ref(false)
const movingUser = ref(false)
const availableRoleGroups = ref<RoleGroup[]>([])
const loadingRoleGroups = ref(false)
const showReportDialog = ref(false)
const showSocialMediaPopup = ref(false)
const socialMediaUrl = ref<string | null>(null)
const socialMediaType = ref<'youtube' | 'instagram' | 'tiktok' | 'x' | null>(null)

// Password dialog for locked rooms
const showPasswordDialog = ref(false)
const roomPasswordInput = ref('')
const passwordError = ref('')
const submittingPassword = ref(false)
const passwordProtectedRoomId = ref<number | null>(null)
const passwordProtectedRoomName = ref('')
const isPasswordValidated = ref(false) // Track if password has been validated for current room
const adminEditForm = ref({
  name: '',
  likes: 0,
  points: 0,
  bio: '',
  roleGroupId: null as number | null,
  roleGroupExpiration: null as Date | null,
  roomId: null as number | null,
  roomPassword: ''
})

// Users sidebar
const userSearchQuery = ref('')

// Close other sidebars when one opens
watch(showUsersSidebar, async (isOpen) => {
  if (isOpen) {
    // Close other sidebars
    showPrivateMessages.value = false
    showRoomsList.value = false
    showWall.value = false
    showSettings.value = false
    
    try {
      await chatStore.fetchActiveUsers()
    } catch (error) {
      console.error('[Users] Error fetching active users:', error)
    }
  }
})

watch(showPrivateMessages, async (isOpen) => {
  if (isOpen) {
    // Close other sidebars
    showUsersSidebar.value = false
    showRoomsList.value = false
    showWall.value = false
    showSettings.value = false
    
    // Fetch conversations
    try {
      await privateMessagesStore.fetchConversations()
      await privateMessagesStore.fetchUnreadCount()
    } catch (error) {
      console.error('Error fetching conversations:', error)
    }
  }
})

watch(showRoomsList, (isOpen) => {
  if (isOpen) {
    // Close other sidebars
    showUsersSidebar.value = false
    showPrivateMessages.value = false
    showWall.value = false
    showSettings.value = false
  }
})

watch(showWall, (isOpen) => {
  if (isOpen) {
    // Close other sidebars
    showUsersSidebar.value = false
    showPrivateMessages.value = false
    showRoomsList.value = false
    showSettings.value = false
  }
})

watch(showSettings, (isOpen) => {
  if (isOpen) {
    // Close other sidebars
    showUsersSidebar.value = false
    showPrivateMessages.value = false
    showRoomsList.value = false
    showWall.value = false
  }
})

// Users in current room
const currentRoomUsers = computed(() => {
  const users = chatStore.currentRoom?.users || []
  if (!userSearchQuery.value) return users
  const query = userSearchQuery.value.toLowerCase()
  return users.filter((user: { name?: string; username?: string }) =>
    (user.name || user.username || '').toLowerCase().includes(query)
  )
})

// Users online in other rooms (exclude current room users)
const otherRoomsUsers = computed(() => {
  const currentRoomUserIds = new Set((chatStore.currentRoom?.users || []).map((u: User) => u.id))
  const allActiveUsers = chatStore.displayActiveUsers || []
  

  
  const otherUsers = allActiveUsers.filter((user: User) => !currentRoomUserIds.has(user.id))

  
  if (!userSearchQuery.value) return otherUsers
  const query = userSearchQuery.value.toLowerCase()
  return otherUsers.filter((user: { name?: string; username?: string }) =>
    (user.name || user.username || '').toLowerCase().includes(query)
  )
})

// Legacy filteredUsers for backward compatibility (stories section)
const filteredUsers = computed(() => currentRoomUsers.value)

// Premium entry notifications
interface PremiumEntryNotification {
  id: string
  user: any
  background: string | null
}
const premiumEntryNotifications = ref<PremiumEntryNotification[]>([])
let notificationIdCounter = 0

const showPremiumEntryNotification = (user: any) => {

  
  // Check if user has premium_entry - check both user object and authStore for current user
  const isCurrentUser = user.id === authStore.user?.id
  const hasPremiumEntry = user.premium_entry || (isCurrentUser && authStore.user?.premium_entry)

  
  if (hasPremiumEntry) {
    
    // Use authStore data for current user to ensure we have premium_entry_background
    let finalUser = user
    if (isCurrentUser && authStore.user) {
      finalUser = {
        id: authStore.user.id,
        name: authStore.user.name,
        username: authStore.user.username,
        avatar_url: authStore.user.avatar_url,
        premium_entry: authStore.user.premium_entry,
        premium_entry_background: authStore.user.premium_entry_background || null,
      }
    }
    
    // Ensure background URL is properly formatted
    let backgroundUrl = finalUser.premium_entry_background || null
    
    if (backgroundUrl && !backgroundUrl.startsWith('http') && !backgroundUrl.startsWith('/')) {
      // If it's a relative path, make it absolute
      const config = useRuntimeConfig()
      const baseUrl = config.public.apiBaseUrl || ''
      backgroundUrl = `${baseUrl}${backgroundUrl.startsWith('/') ? '' : '/'}${backgroundUrl}`
    }
    
    const notification: PremiumEntryNotification = {
      id: `premium-${notificationIdCounter++}-${finalUser.id}`,
      user: {
        id: finalUser.id,
        name: finalUser.name,
        username: finalUser.username,
        avatar_url: finalUser.avatar_url,
        premium_entry: true,
        premium_entry_background: finalUser.premium_entry_background,
      },
      background: backgroundUrl,
    }
    
    premiumEntryNotifications.value.push(notification)
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
      const index = premiumEntryNotifications.value.findIndex(n => n.id === notification.id)
      if (index !== -1) {
        premiumEntryNotifications.value.splice(index, 1)
      }
    }, 5000)
  }
}


// Membership Designs
interface MembershipDesign {
  id: number
  name: string
  type: 'background' | 'frame'
  image_url: string
  description?: string
  is_active: boolean
  priority: number
}

const membershipBackgrounds = ref<MembershipDesign[]>([])
const membershipFrames = ref<MembershipDesign[]>([])
const selectedMembershipBackground = ref<MembershipDesign | null>(null)
const selectedMembershipFrame = ref<MembershipDesign | null>(null)
const savingMembershipDesigns = ref(false)
const showMembershipDesignsDialog = ref(false)

const fetchMembershipDesigns = async () => {
  if (!authStore.user?.designed_membership) return
  
  try {
    const nuxtApp = useNuxtApp()
    const backgrounds = await nuxtApp.$api('/membership-designs?type=background&active=true')
    const frames = await nuxtApp.$api('/membership-designs?type=frame&active=true')
    membershipBackgrounds.value = backgrounds
    membershipFrames.value = frames
    
    // Set selected designs from user data
    if (authStore.user.membership_background) {
      selectedMembershipBackground.value = backgrounds.find((d: MembershipDesign) => d.id === authStore.user.membership_background.id) || null
    }
    if (authStore.user.membership_frame) {
      selectedMembershipFrame.value = frames.find((d: MembershipDesign) => d.id === authStore.user.membership_frame.id) || null
    }
  } catch (error: any) {
    console.error('Error fetching membership designs:', error)
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: 'فشل تحميل التصاميم',
      life: 3000,
    })
  }
}

const openMembershipDesignsDialog = async () => {
  showMembershipDesignsDialog.value = true
  // Fetch designs when opening the dialog
  await fetchMembershipDesigns()
}

const selectMembershipBackground = (design: MembershipDesign) => {
  selectedMembershipBackground.value = design
}

const selectMembershipFrame = (design: MembershipDesign) => {
  selectedMembershipFrame.value = design
}

// Premium Entry Background
const premiumEntryBackgroundFile = ref<File | null>(null)
const premiumEntryBackgroundPreview = ref<string | null>(null)
const uploadingPremiumEntryBackground = ref(false)
const deletingPremiumEntryBackground = ref(false)

const onPremiumEntryBackgroundSelect = (event: any) => {
  const file = event.files?.[0]
  if (!file) return

  if (!file.type.startsWith('image/')) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: 'الملف يجب أن يكون صورة',
      life: 3000,
    })
    return
  }

  if (file.size > 5120000) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: 'حجم الصورة يجب أن يكون أقل من 5MB',
      life: 3000,
    })
    return
  }

  premiumEntryBackgroundFile.value = file

  // Create preview
  const reader = new FileReader()
  reader.onload = (e) => {
    premiumEntryBackgroundPreview.value = e.target?.result as string
  }
  reader.readAsDataURL(file)
}

const removePremiumEntryBackground = () => {
  premiumEntryBackgroundFile.value = null
  premiumEntryBackgroundPreview.value = null
}

const uploadPremiumEntryBackground = async () => {
  if (!premiumEntryBackgroundFile.value) return
  
  uploadingPremiumEntryBackground.value = true
  try {
    const formData = new FormData()
    formData.append('image', premiumEntryBackgroundFile.value)
    
    const nuxtApp = useNuxtApp()
    const config = useRuntimeConfig()
    const baseUrl = config.public.apiBaseUrl
    
    const headers: HeadersInit = {
      'Accept': 'application/json',
    }
    
    if (authStore.token) {
      headers['Authorization'] = `Bearer ${authStore.token}`
    }
    
    const response = await fetch(`${baseUrl}/profile/premium-entry-background`, {
      method: 'POST',
      headers,
      body: formData,
    })
    
    if (!response.ok) {
      const error = await response.json().catch(() => ({ message: 'فشل رفع الصورة' }))
      throw new Error(error.message || 'فشل رفع الصورة')
    }
    
    const data = await response.json()
    
    // Update user in auth store
    if (authStore.user && data.user) {
      authStore.user.premium_entry_background = data.premium_entry_background
      // Update localStorage
      if (import.meta.client) {
        localStorage.setItem('auth_user', JSON.stringify(authStore.user))
      }
    }
    
    // Clear file and preview
    premiumEntryBackgroundFile.value = null
    premiumEntryBackgroundPreview.value = null
    
    toast.add({
      severity: 'success',
      summary: 'نجح',
      detail: 'تم رفع صورة الخلفية بنجاح',
      life: 3000,
    })
  } catch (error: any) {
    console.error('Error uploading premium entry background:', error)
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.message || 'فشل رفع الصورة',
      life: 3000,
    })
  } finally {
    uploadingPremiumEntryBackground.value = false
  }
}

const deletePremiumEntryBackground = async () => {
  deletingPremiumEntryBackground.value = true
  try {
    const nuxtApp = useNuxtApp()
    const updatedUser = await nuxtApp.$api('/profile/premium-entry-background', { method: 'DELETE' })
    
    // Update user in auth store
    if (authStore.user && updatedUser) {
      authStore.user.premium_entry_background = null
      // Update localStorage
      if (import.meta.client) {
        localStorage.setItem('auth_user', JSON.stringify(authStore.user))
      }
    }
    
    toast.add({
      severity: 'success',
      summary: 'نجح',
      detail: 'تم حذف صورة الخلفية بنجاح',
      life: 3000,
    })
  } catch (error: any) {
    console.error('Error deleting premium entry background:', error)
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.message || 'فشل حذف الصورة',
      life: 3000,
    })
  } finally {
    deletingPremiumEntryBackground.value = false
  }
}

const saveMembershipDesigns = async () => {
  if (!authStore.user?.designed_membership) return
  
  // Ensure user is authenticated
  if (!authStore.isAuthenticated || !authStore.token) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: 'يجب تسجيل الدخول أولاً',
      life: 3000,
    })
    return
  }
  
  savingMembershipDesigns.value = true
  try {
    const nuxtApp = useNuxtApp()
    await nuxtApp.$api('/profile/designs', {
      method: 'PUT',
      body: {
        membership_background_id: selectedMembershipBackground.value?.id || null,
        membership_frame_id: selectedMembershipFrame.value?.id || null,
      },
    })
    
    toast.add({
      severity: 'success',
      summary: 'نجح',
      detail: 'تم حفظ التصاميم بنجاح',
      life: 3000,
    })
    
    // Update user data
    if (authStore.user) {
      authStore.user.membership_background = selectedMembershipBackground.value
      authStore.user.membership_frame = selectedMembershipFrame.value
    }
    
    // Close the dialog
    showMembershipDesignsDialog.value = false
  } catch (error: any) {
    console.error('Error saving membership designs:', error)
    const errorMessage = error.message || error.data?.message || 'فشل حفظ التصاميم'
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: errorMessage,
      life: 3000,
    })
  } finally {
    savingMembershipDesigns.value = false
  }
}

// Rooms sidebar
const roomSearchQuery = ref('')
const showCreateRoomModal = ref(false)
const creatingRoom = ref(false)
const newRoomImageInput = ref<HTMLInputElement | null>(null)
const newRoomImageFile = ref<File | null>(null)
const newRoomImagePreview = ref<string | null>(null)
const newRoom = ref({
  name: '',
  description: '',
  welcome_message: '',
  required_likes: 0,
  room_hashtag: null as number | null,
  is_public: true,
  is_staff_only: false,
  enable_mic: false,
  disable_incognito: false,
  max_count: 20,
  password: '',
      backgroundColor: '',
      textColor: '',
      roomNameColor: '',
      roomBorderColor: '',
      roomDescriptionColor: '',
})

// Room settings
const showRoomSettingsModal = ref(false)
const updatingRoom = ref(false)
const roomImageInput = ref<HTMLInputElement | null>(null)
const roomImageFile = ref<File | null>(null)
const roomImagePreview = ref<string | null>(null)
const isDraggingOver = ref(false)
const roomSettingsForm = ref({
  name: '',
  description: '',
  welcome_message: '',
  required_likes: 0,
  room_hashtag: null as number | null,
  is_public: true,
  is_staff_only: false,
  enable_mic: false,
  disable_incognito: false,
  max_count: 20,
  password: '',
  room_image: '',
  backgroundColor: '',
  textColor: '',
  roomNameColor: '',
  roomBorderColor: '',
  roomDescriptionColor: '',
  useImageAsBanner: false,
})

// Check if current user can manage the room (is owner, admin, or has role 999)
const canManageRoom = computed(() => {
  if (!chatStore.currentRoom || !authStore.user) {
    return false
  }
  
  const room = chatStore.currentRoom
  const userId = authStore.user.id
  const userRole = authStore.user.group_role
  
  // Check if user has special role 999 (can edit all rooms)
  // Handle various formats: string '999', number 999, or any string containing '999'
  const roleStr = String(userRole || '').trim()
  if (roleStr === '999' || userRole === 999 || roleStr.includes('999')) {
    return true
  }
  
  // Check if user is the creator/owner
  if (room.created_by === userId) {
    return true
  }
  
  // Check if user has admin role in the room
  const currentUserInRoom = room.users?.find((u: any) => u.id === userId)
  if (currentUserInRoom) {
    // Check pivot role if available
    const pivot = (currentUserInRoom as any).pivot
    if (pivot && pivot.role === 'admin') {
      return true
    }
  }
  
  return false
})

// Check if user has dashboard access permission
const hasDashboardAccess = computed(() => {
  return hasPermission(authStore.user, 'dashboard')
})

// Navigate to dashboard
const navigateToDashboard = () => {
  router.push('/dashboard')
}

// Check if current user can manage other users (admin permissions)
const canManageUser = computed(() => {
  if (!authStore.user) return false
  
  // Check for manage_user_info permission from role groups
  if (hasPermission(authStore.user, 'manage_user_info')) {
    return true
  }
  
  // Fallback: Check for role 999 or admin (for backward compatibility)
  const userRole = authStore.user.group_role
  const roleStr = String(userRole || '').trim()
  return roleStr === '999' || userRole === 999 || roleStr.includes('999') || roleStr === 'admin'
})

// Check if user has required role
const hasRole = (requiredRole: number): boolean => {
  if (!authStore.user?.group_role) return false
  const userRole = authStore.user.group_role
  const roleNum = parseInt(String(userRole)) || 0
  return roleNum >= requiredRole
}

// Available rooms for admin room selector
const availableRooms = computed(() => {
  // Ensure rooms are loaded
  if (!chatStore.displayRooms || chatStore.displayRooms.length === 0) {
    // Fetch rooms if not loaded
    if (showUserProfileModal.value) {
      chatStore.fetchRooms()
    }
  }
  return chatStore.displayRooms || []
})

// Selected room for move (with password check)
const selectedRoomForMove = computed(() => {
  if (!adminEditForm.value.roomId) return null
  return availableRooms.value.find((r: Room) => r.id === adminEditForm.value.roomId)
})

// Check if user is currently in the room
const isUserInCurrentRoom = (userId: number | undefined): boolean => {
  if (!userId || !chatStore.currentRoom?.users) return false
  return chatStore.currentRoom.users.some((u: User) => u.id === userId)
}

// Handle user click from messages
const handleUserClick = (user: User | undefined, message: Message) => {
  if (!user || !user.id) return
  
  // Check if user is in current room - only allow click if they are
  if (!isUserInCurrentRoom(user.id)) {
    // User is not in current room, don't open modal
    return
  }
  
  openUserProfile(user)
}

// Open user profile modal
const openUserProfile = async (user: User) => {
  // Show modal immediately with provided user data (optimistic UI)
  selectedUser.value = user
  
  // Initialize admin form with available user data immediately
  const primaryRoleGroup = (user.role_groups || [])[0]
  let roleGroupId: number | null = null
  let roleGroupExpiration: Date | null = null
  
  if (primaryRoleGroup) {
    roleGroupId = primaryRoleGroup.id
    const pivot = (primaryRoleGroup as any).pivot
    if (pivot && pivot.expires_at) {
      roleGroupExpiration = new Date(pivot.expires_at)
    } else {
      roleGroupExpiration = null
    }
  }
  
  adminEditForm.value = {
    name: user.name || user.username || '',
    likes: (user as any).likes || 0,
    points: (user as any).points || 0,
    bio: user.bio || '',
    roleGroupId,
    roleGroupExpiration,
    roomId: null,
    roomPassword: ''
  }
  
  // Show modal immediately
  showUserProfileModal.value = true
  
  // Load data in background (non-blocking)
  ;(async () => {
    try {
      // Fetch full user data in background
      const { $api } = useNuxtApp()
      const fullUser = await ($api as any)(`/users/${user.id}`)
      
      // Update with full data
      selectedUser.value = fullUser
      
      // Update admin form with full data
      const updatedPrimaryRoleGroup = (fullUser.role_groups || [])[0]
      let updatedRoleGroupId: number | null = null
      let updatedRoleGroupExpiration: Date | null = null
      
      if (updatedPrimaryRoleGroup) {
        updatedRoleGroupId = updatedPrimaryRoleGroup.id
        const pivot = (updatedPrimaryRoleGroup as any).pivot
        if (pivot && pivot.expires_at) {
          updatedRoleGroupExpiration = new Date(pivot.expires_at)
        }
      }
      
      adminEditForm.value = {
        name: fullUser.name || fullUser.username || '',
        likes: (fullUser as any).likes || 0,
        points: (fullUser as any).points || 0,
        bio: fullUser.bio || '',
        roleGroupId: updatedRoleGroupId,
        roleGroupExpiration: updatedRoleGroupExpiration,
        roomId: null,
        roomPassword: ''
      }
    } catch (error) {
      console.warn('Failed to fetch full user data:', error)
      // Keep using provided data
    }
  })()
  
  // Fetch role groups in background if needed
  if (availableRoleGroups.value.length === 0) {
    fetchRoleGroups().catch(err => console.warn('Failed to fetch role groups:', err))
  }
  
  // Fetch rooms in background if needed
  if (canManageUser.value && (!chatStore.displayRooms || chatStore.displayRooms.length === 0)) {
    chatStore.fetchRooms().catch(err => console.warn('Failed to fetch rooms:', err))
  }
}

// Close user profile modal
const closeUserProfileModal = () => {
  showUserProfileModal.value = false
  selectedUser.value = null
  adminEditForm.value = {
    name: '',
    likes: 0,
    points: 0,
    bio: '',
    roleGroupId: null,
    roleGroupExpiration: null,
    roomId: null,
    roomPassword: ''
  }
}

// Handle role group change
const onRoleGroupChange = () => {
  // Reset expiration when role group changes
  adminEditForm.value.roleGroupExpiration = null
}

// Toggle expiration for the role group
const toggleExpiration = (hasExpiration: boolean) => {
  if (hasExpiration) {
    // Set default expiration to 30 days from now
    const defaultDate = new Date()
    defaultDate.setDate(defaultDate.getDate() + 30)
    adminEditForm.value.roleGroupExpiration = defaultDate
  } else {
    // Set to null for infinite/no expiration
    adminEditForm.value.roleGroupExpiration = null
  }
}

// Fetch available role groups
const fetchRoleGroups = async () => {
  loadingRoleGroups.value = true
  try {
    const { $api } = useNuxtApp()
    availableRoleGroups.value = await $api('/role-groups?is_active=true')
  } catch (error) {
    console.error('Error fetching role groups:', error)
  } finally {
    loadingRoleGroups.value = false
  }
}

// Helper functions for role groups
const getRoleGroupBannerById = (groupId: number): string | undefined => {
  const roleGroup = availableRoleGroups.value.find((rg: RoleGroup) => rg.id === groupId)
  return roleGroup?.banner || undefined
}

const getRoleGroupNameById = (groupId: number): string => {
  const roleGroup = availableRoleGroups.value.find((rg: RoleGroup) => rg.id === groupId)
  return roleGroup?.name || ''
}

// User action functions
const notifyUser = async () => {
  if (!selectedUser.value) return
  // TODO: Implement notification API call
}

const likeUser = async () => {
  if (!selectedUser.value) return
  
  // Check rate limit
  const { checkRateLimit } = useRateLimit()
  if (!checkRateLimit('like_user')) {
    return
  }
  
  try {
    const { $api } = useNuxtApp()
    await $api(`/users/${selectedUser.value.id}/like`, { method: 'POST' })
    // Refresh user data
    if (selectedUser.value) {
      (selectedUser.value as any).likes = ((selectedUser.value as any).likes || 0) + 1
    }
  } catch (error) {
    console.error('Error liking user:', error)
  }
}

const sendGift = async () => {
  if (!selectedUser.value) return
  // TODO: Implement gift sending
}

const deleteUserPhoto = async () => {
  if (!selectedUser.value) return
  try {
    const { $api } = useNuxtApp()
    await $api(`/users/${selectedUser.value.id}/avatar`, { method: 'DELETE' })
    if (selectedUser.value) {
      selectedUser.value.avatar_url = null
    }
  } catch (error) {
    console.error('Error deleting photo:', error)
  }
}

const setUserBanner = async () => {
  if (!selectedUser.value) return
  // TODO: Implement banner setting
}

const kickFromRoom = async () => {
  if (!selectedUser.value || !chatStore.currentRoom) return
  try {
    const { $api } = useNuxtApp()
    await $api(`/chat/${chatStore.currentRoom.id}/users/${selectedUser.value.id}`, { method: 'DELETE' })
  } catch (error) {
    console.error('Error kicking user:', error)
  }
}

const kickFromSite = async () => {
  if (!selectedUser.value) return
  try {
    const { $api } = useNuxtApp()
    await $api(`/users/${selectedUser.value.id}/kick`, { method: 'POST' })
  } catch (error) {
    console.error('Error kicking user from site:', error)
  }
}

const banUser = async () => {
  if (!selectedUser.value) return
  try {
    const { $api } = useNuxtApp()
    await $api(`/users/${selectedUser.value.id}/ban`, { method: 'POST' })
  } catch (error) {
    console.error('Error banning user:', error)
  }
}

const reportUser = () => {
  if (!selectedUser.value) return
  showReportDialog.value = true
}

const handleReported = () => {
  // Report was submitted successfully
  // Optionally close the user profile modal or show a success message
}

const muteUser = async () => {
  if (!selectedUser.value || !chatStore.currentRoom) return
  try {
    const { $api } = useNuxtApp()
    await $api(`/chat/${chatStore.currentRoom.id}/users/${selectedUser.value.id}/mute`, { method: 'POST' })
  } catch (error) {
    console.error('Error muting user:', error)
  }
}

const muteUserAllRooms = async () => {
  if (!selectedUser.value) return
  try {
    const { $api } = useNuxtApp()
    await $api(`/users/${selectedUser.value.id}/mute-all`, { method: 'POST' })
  } catch (error) {
    console.error('Error muting user in all rooms:', error)
  }
}

const ignoreUser = async () => {
  if (!selectedUser.value) return
  try {
    const { $api } = useNuxtApp()
    await $api(`/users/${selectedUser.value.id}/ignore`, { method: 'POST' })
  } catch (error) {
    console.error('Error ignoring user:', error)
  }
}

// Admin save functions
const saveUserName = async () => {
  if (!selectedUser.value) return
  savingUserData.value = true
  try {
    const { $api } = useNuxtApp()
    await $api(`/users/${selectedUser.value.id}`, {
      method: 'PUT',
      body: { name: adminEditForm.value.name }
    })
    if (selectedUser.value) {
      selectedUser.value.name = adminEditForm.value.name
    }
  } catch (error) {
    console.error('Error saving name:', error)
  } finally {
    savingUserData.value = false
  }
}

const saveUserLikes = async () => {
  if (!selectedUser.value) return
  savingUserData.value = true
  try {
    const { $api } = useNuxtApp()
    await $api(`/users/${selectedUser.value.id}`, {
      method: 'PUT',
      body: { likes: adminEditForm.value.likes }
    })
    if (selectedUser.value) {
      (selectedUser.value as any).likes = adminEditForm.value.likes
    }
  } catch (error) {
    console.error('Error saving likes:', error)
  } finally {
    savingUserData.value = false
  }
}

const saveUserPoints = async () => {
  if (!selectedUser.value) return
  savingUserData.value = true
  try {
    const { $api } = useNuxtApp()
    await $api(`/users/${selectedUser.value.id}`, {
      method: 'PUT',
      body: { points: adminEditForm.value.points }
    })
    if (selectedUser.value) {
      (selectedUser.value as any).points = adminEditForm.value.points
    }
  } catch (error) {
    console.error('Error saving points:', error)
  } finally {
    savingUserData.value = false
  }
}

const saveUserBio = async () => {
  if (!selectedUser.value) return
  savingUserData.value = true
  try {
    const { $api } = useNuxtApp()
    await $api(`/users/${selectedUser.value.id}`, {
      method: 'PUT',
      body: { bio: adminEditForm.value.bio }
    })
    if (selectedUser.value) {
      selectedUser.value.bio = adminEditForm.value.bio
    }
  } catch (error) {
    console.error('Error saving bio:', error)
  } finally {
    savingUserData.value = false
  }
}

const saveUserRoleGroup = async () => {
  if (!selectedUser.value) return
  savingUserData.value = true
  try {
    const { $api } = useNuxtApp()
    const { useToast } = await import('primevue/usetoast')
    const toast = useToast()
    
    // Get current role group ID (first/highest priority)
    const currentRoleGroupId = (selectedUser.value.role_groups || [])[0]?.id || null
    const newRoleGroupId = adminEditForm.value.roleGroupId
    
    // Prepare expiration date
    const expiration = adminEditForm.value.roleGroupExpiration
    const expiresAt = expiration ? expiration.toISOString() : null
    
    // Remove user from all current role groups first
    if (currentRoleGroupId) {
      await $api(`/role-groups/${currentRoleGroupId}/users`, {
        method: 'DELETE',
        body: { user_ids: [selectedUser.value.id] }
      })
    }
    
    // Add user to new role group if selected
    if (newRoleGroupId) {
      await $api(`/role-groups/${newRoleGroupId}/users`, {
        method: 'POST',
        body: {
          user_ids: [selectedUser.value.id],
          expires_at_per_user: {
            [selectedUser.value.id]: expiresAt
          }
        }
      })
    }
    
    // Refresh user data to get updated role groups
    if (selectedUser.value) {
      // Reload user with role groups
      const updatedUser = await $api(`/users?search=${encodeURIComponent(selectedUser.value.username || selectedUser.value.name || '')}`)
      const foundUser = Array.isArray(updatedUser) ? updatedUser.find((u: User) => u.id === selectedUser.value!.id) : updatedUser
      if (foundUser) {
        selectedUser.value.role_groups = foundUser.role_groups || []
        const primaryRoleGroup = (foundUser.role_groups || [])[0]
        
        if (primaryRoleGroup) {
          adminEditForm.value.roleGroupId = primaryRoleGroup.id
          const pivot = (primaryRoleGroup as any).pivot
          if (pivot && pivot.expires_at) {
            adminEditForm.value.roleGroupExpiration = new Date(pivot.expires_at)
          } else {
            adminEditForm.value.roleGroupExpiration = null
          }
        } else {
          adminEditForm.value.roleGroupId = null
          adminEditForm.value.roleGroupExpiration = null
        }
      }
    }
    
    toast.add({
      severity: 'success',
      summary: 'نجح',
      detail: 'تم تحديث مجموعة الدور بنجاح',
      life: 3000,
    })
  } catch (error: any) {
    const { useToast } = await import('primevue/usetoast')
    const toast = useToast()
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.message || 'فشل تحديث مجموعة الدور',
      life: 3000,
    })
    console.error('Error saving role group:', error)
  } finally {
    savingUserData.value = false
  }
}

const moveUserToRoom = async () => {
  if (!selectedUser.value || !adminEditForm.value.roomId) {
    console.warn('Cannot move user: missing user or room ID')
    return
  }
  
  movingUser.value = true
  try {
    const { $api } = useNuxtApp()
    const targetRoomId = adminEditForm.value.roomId
    const previousRoomId = chatStore.currentRoom?.id
    
    // Prepare request body - backend will handle removing user from previous room
    const body: any = { user_id: selectedUser.value.id }
    if (adminEditForm.value.roomPassword) {
      body.password = adminEditForm.value.roomPassword
    }
    // Pass previous room ID so backend can remove user from it and broadcast messages
    if (previousRoomId && previousRoomId !== targetRoomId) {
      body.previous_room_id = previousRoomId
    }
    
    try {
      // Send move request - backend will send event to user, user will navigate themselves
      await $api(`/chat/${targetRoomId}/users`, {
        method: 'POST',
        body
      })
      
      // Request sent successfully - the user will receive the event and navigate themselves
      // If user being moved is the current user, we can navigate immediately as fallback
      if (selectedUser.value.id === authStore.user?.id) {
        await navigateToRoom(targetRoomId)
      }
      
      // Close modal after successful request
      closeUserProfileModal()
    } catch (error: any) {
      // Extract error message from API response
      const errorMessage = error?.message || 'حدث خطأ أثناء نقل المستخدم'
      
      console.error('Error sending move request:', error)
      console.error('Error message:', errorMessage)
      
      // Show error to user
      throw new Error(errorMessage)
    }
  } catch (error: any) {
    console.error('Error moving user:', error)
    alert(error?.message || 'حدث خطأ أثناء نقل المستخدم')
  } finally {
    movingUser.value = false
  }
}

const filteredRoomsList = computed(() => {
  if (!roomSearchQuery.value.trim()) {
    return chatStore.displayRooms
  }
  
  const query = roomSearchQuery.value.toLowerCase().trim()
  
  return chatStore.displayRooms.filter((room: Room) => {
    // Search by name
    const nameMatch = room.name?.toLowerCase().includes(query)
    // Search by id
    const idMatch = String(room.id).includes(query)
    
    return nameMatch || idMatch
  })
})

const fetchRoomsList = async () => {
  try {
    const rooms = await chatStore.fetchRooms()
    // Debug: check first room's settings

  } catch (error) {
    console.error('Error fetching rooms:', error)
  }
}

// Watch for sidebar opening to fetch rooms
watch(showWall, (isOpen) => {
  if (isOpen && roomId.value) {
    fetchWallPosts()
    fetchWallCreator()
    // Clear notification when wall is opened
    if (hasNewWallPost.value) {
      hasNewWallPost.value = false
      if (newWallPostTimeout) {
        clearTimeout(newWallPostTimeout)
        newWallPostTimeout = null
      }
    }
  }
})

watch(showRoomsList, (isOpen) => {
  if (isOpen) {
    fetchRoomsList()
  }
})

// Watch for create room modal opening to reset form
watch(showCreateRoomModal, (isOpen) => {
  if (isOpen) {
    newRoom.value = {
      name: '',
      description: '',
      welcome_message: '',
      required_likes: 0,
      room_hashtag: null,
      is_public: true,
      is_staff_only: false,
      enable_mic: false,
      disable_incognito: false,
      max_count: 20,
      password: '',
      backgroundColor: '',
      textColor: '',
      roomNameColor: '',
      roomBorderColor: '',
      roomDescriptionColor: '',
    }
    newRoomImageFile.value = null
    newRoomImagePreview.value = null
  }
})

// Watch for room settings modal opening to load current room data
watch(showRoomSettingsModal, (isOpen) => {
  if (isOpen && chatStore.currentRoom) {
    const room = chatStore.currentRoom
    roomSettingsForm.value = {
      name: room.name || '',
      description: room.description || '',
      welcome_message: room.welcome_message || '',
      required_likes: room.required_likes || 0,
      room_hashtag: room.room_hashtag || null,
      is_public: room.is_public ?? true,
      is_staff_only: room.is_staff_only ?? false,
      enable_mic: room.enable_mic ?? false,
      disable_incognito: room.disable_incognito ?? false,
      max_count: room.max_count || 20,
      password: '', // Don't show existing password
      room_image: room.room_image || '',
      backgroundColor: room.settings?.backgroundColor || '',
      textColor: room.settings?.textColor || '',
      roomNameColor: room.settings?.roomNameColor || '',
      roomBorderColor: room.settings?.roomBorderColor || '',
      roomDescriptionColor: room.settings?.roomDescriptionColor || '',
      useImageAsBanner: room.settings?.useImageAsBanner ?? false,
    }
    
    
    // Reset image upload state
    roomImageFile.value = null
    // Use room_image_url if available (from Media Library), otherwise fallback to room_image
    roomImagePreview.value = room.room_image_url || room.room_image || null
  }
})

const handleRoomImageSelect = (event: Event) => {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (file) {
    processImageFile(file)
  }
}

const processImageFile = (file: File) => {
  // Validate file type
  const validTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp']
  if (!validTypes.includes(file.type)) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: 'نوع الملف غير مدعوم. يرجى اختيار صورة بصيغة JPEG, PNG, GIF, أو WebP',
      life: 3000,
    })
    return
  }
  
  // Validate file size (2MB max)
  const maxSize = 2 * 1024 * 1024 // 2MB in bytes
  if (file.size > maxSize) {
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: 'حجم الملف كبير جداً. الحد الأقصى هو 2MB',
      life: 3000,
    })
    return
  }
  
  roomImageFile.value = file
  // Create preview
  const reader = new FileReader()
  reader.onload = (e) => {
    roomImagePreview.value = e.target?.result as string
  }
  reader.readAsDataURL(file)
}

const handleDragOver = (event: DragEvent) => {
  event.preventDefault()
  isDraggingOver.value = true
}

const handleDragLeave = (event: DragEvent) => {
  event.preventDefault()
  isDraggingOver.value = false
}

const handleDrop = (event: DragEvent) => {
  event.preventDefault()
  isDraggingOver.value = false
  
  const files = event.dataTransfer?.files
  if (files && files.length > 0) {
    const file = files[0]
    if (file && file.type.startsWith('image/')) {
      processImageFile(file)
    } else {
      toast.add({
        severity: 'error',
        summary: 'خطأ',
        detail: 'يرجى إسقاط ملف صورة فقط',
        life: 3000,
      })
    }
  }
}

const formatFileSize = (bytes: number): string => {
  if (bytes === 0) return '0 Bytes'
  const k = 1024
  const sizes = ['Bytes', 'KB', 'MB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
}

const removeRoomImage = () => {
  roomImageFile.value = null
  roomImagePreview.value = null
  if (roomImageInput.value) {
    roomImageInput.value.value = ''
  }
}

const updateRoomSettings = async () => {
  if (!chatStore.currentRoom) return
  
  updatingRoom.value = true
  try {
    const { $api } = useNuxtApp()
    const config = useRuntimeConfig()
    const baseUrl = config.public.apiBaseUrl
    
    // Upload room image if a new file was selected
    if (roomImageFile.value) {
      try {
        const formData = new FormData()
        formData.append('image', roomImageFile.value)
        
        const headers: HeadersInit = {
          'Accept': 'application/json',
        }
        
        if (authStore.token) {
          headers['Authorization'] = `Bearer ${authStore.token}`
        }
        
        const imageResponse = await fetch(`${baseUrl}/chat/${chatStore.currentRoom.id}/image`, {
          method: 'POST',
          headers,
          body: formData,
        })
        
        if (!imageResponse.ok) {
          const error = await imageResponse.json().catch(() => ({ message: 'فشل رفع الصورة' }))
          throw new Error(error.message || 'فشل رفع الصورة')
        }
        
        const imageData = await imageResponse.json()
        // Update room_image with the new URL
        roomSettingsForm.value.room_image = imageData.room_image || ''
      } catch (error: any) {
        console.error('Error uploading room image:', error)
        alert(error.message || 'فشل رفع صورة الغرفة')
        updatingRoom.value = false
        return
      }
    }
    
    // Helper function to convert color value to string
    const getColorValue = (color: any): string | null => {
      if (!color) return null
      
      // Handle ColorPicker object format (PrimeVue ColorPicker with format="hex")
      if (typeof color === 'object') {
        // PrimeVue ColorPicker with format="hex" returns { hex: '#ffffff' } or { hex: 'ffffff' }
        if (color.hex) {
          let hexValue = String(color.hex).trim()
          // Ensure hex color has # prefix
          if (hexValue && !hexValue.startsWith('#')) {
            hexValue = '#' + hexValue
          }
          return hexValue
        }
        // RGB format
        if ('r' in color && 'g' in color && 'b' in color) {
          return `rgb(${color.r}, ${color.g}, ${color.b})`
        }
        // If it's an object but no recognized format, return null
        return null
      }
      
      // Handle string values
      if (typeof color === 'string') {
        const trimmed = color.trim()
        // If it's an empty string, return null
        if (trimmed === '') return null
        
        // If it's already a valid color format, return as is
        if (trimmed.startsWith('#') || trimmed.startsWith('rgb') || trimmed.startsWith('rgba')) {
          return trimmed
        }
        
        // If it's a hex color without # (e.g., "2f139e"), add the prefix
        if (/^[0-9A-Fa-f]{6}$/.test(trimmed)) {
          return '#' + trimmed
        }
        
        // If it's a 3-digit hex without # (e.g., "fff"), add the prefix
        if (/^[0-9A-Fa-f]{3}$/.test(trimmed)) {
          return '#' + trimmed
        }
        
        // If it doesn't look like a color, return null
        return null
      }
      
      return null
    }
    
    // Get existing settings to merge with
    const existingSettings = chatStore.currentRoom?.settings || {}
    
    // Prepare settings object - merge with existing and update with new values
    const settings: any = {
      ...existingSettings,
    }
    
    // Only update color fields if they have valid values
    const bgColor = getColorValue(roomSettingsForm.value.backgroundColor)
    if (bgColor !== null) {
      settings.backgroundColor = bgColor
    }
    
    const txtColor = getColorValue(roomSettingsForm.value.textColor)
    if (txtColor !== null) {
      settings.textColor = txtColor
    }
    
    const nameColor = getColorValue(roomSettingsForm.value.roomNameColor)
    if (nameColor !== null) {
      settings.roomNameColor = nameColor
    }
    
    const borderColor = getColorValue(roomSettingsForm.value.roomBorderColor)
    if (borderColor !== null) {
      settings.roomBorderColor = borderColor
    }
    
    const descColor = getColorValue(roomSettingsForm.value.roomDescriptionColor)
    if (descColor !== null) {
      settings.roomDescriptionColor = descColor
    }
    
    // Add banner mode setting
    settings.useImageAsBanner = roomSettingsForm.value.useImageAsBanner || false
    
    const updatedRoom = await $api(`/chat/${chatStore.currentRoom.id}`, {
      method: 'PUT',
      body: {
        name: roomSettingsForm.value.name.trim(),
        description: roomSettingsForm.value.description || null,
        welcome_message: roomSettingsForm.value.welcome_message || null,
        required_likes: roomSettingsForm.value.required_likes || 0,
        room_hashtag: roomSettingsForm.value.room_hashtag || null,
        is_public: roomSettingsForm.value.is_public,
        is_staff_only: roomSettingsForm.value.is_staff_only,
        enable_mic: roomSettingsForm.value.enable_mic,
        disable_incognito: roomSettingsForm.value.disable_incognito,
        max_count: roomSettingsForm.value.max_count || 20,
        password: roomSettingsForm.value.password || null,
        settings: Object.keys(settings).length > 0 ? settings : null,
      },
    })
    
    // Update current room in store
    chatStore.setCurrentRoom(updatedRoom)
    
    // Also update the room in the rooms list
    const roomIndex = chatStore.rooms.findIndex((r: Room) => r.id === updatedRoom.id)
    if (roomIndex !== -1) {
      chatStore.rooms[roomIndex] = updatedRoom
    }
    
    // Refresh rooms list to update sidebar with new styles
    await fetchRoomsList()
    
    // Reset image upload state
    roomImageFile.value = null
    
    // Close modal
    showRoomSettingsModal.value = false
    
    // Show success message (you could use a toast here)
    alert('تم تحديث إعدادات الغرفة بنجاح')
  } catch (error: any) {
    console.error('Error updating room:', error)
    alert(error?.message || 'حدث خطأ أثناء تحديث إعدادات الغرفة')
  } finally {
    updatingRoom.value = false
  }
}

const handleNewRoomImageSelect = (event: Event) => {
  const input = event.target as HTMLInputElement
  const file = input.files?.[0]
  if (file) {
    newRoomImageFile.value = file
    // Create preview
    const reader = new FileReader()
    reader.onload = (e) => {
      newRoomImagePreview.value = e.target?.result as string
    }
    reader.readAsDataURL(file)
  }
}

const removeNewRoomImage = () => {
  newRoomImageFile.value = null
  newRoomImagePreview.value = null
  if (newRoomImageInput.value) {
    newRoomImageInput.value.value = ''
  }
}

const createRoom = async () => {
  if (!newRoom.value.name.trim()) {
    return
  }
  
  creatingRoom.value = true
  try {
    const { $api } = useNuxtApp()
    const config = useRuntimeConfig()
    const baseUrl = config.public.apiBaseUrl
    
    // Helper function to convert color value to string
    const getColorValue = (color: any): string | null => {
      if (!color) return null
      if (typeof color === 'string') return color
      if (typeof color === 'object' && color.hex) return color.hex
      if (typeof color === 'object' && 'r' in color && 'g' in color && 'b' in color) {
        return `rgb(${color.r}, ${color.g}, ${color.b})`
      }
      return String(color)
    }
    
    // Prepare settings object - always include all color fields
    const settings: any = {
      backgroundColor: getColorValue(newRoom.value.backgroundColor) || null,
      textColor: getColorValue(newRoom.value.textColor) || null,
      roomNameColor: getColorValue(newRoom.value.roomNameColor) || null,
      roomBorderColor: getColorValue(newRoom.value.roomBorderColor) || null,
      roomDescriptionColor: getColorValue(newRoom.value.roomDescriptionColor) || null,
    }
    
    // Remove null values to clean up the settings object
    Object.keys(settings).forEach(key => {
      if (settings[key] === null) {
        delete settings[key]
      }
    })
    
    // Create room first
    const room = await $api('/chat', {
      method: 'POST',
      body: {
        name: newRoom.value.name.trim(),
        description: newRoom.value.description || null,
        welcome_message: newRoom.value.welcome_message || null,
        required_likes: newRoom.value.required_likes || 0,
        room_hashtag: newRoom.value.room_hashtag || null,
        is_public: newRoom.value.is_public,
        is_staff_only: newRoom.value.is_staff_only,
        enable_mic: newRoom.value.enable_mic,
        disable_incognito: newRoom.value.disable_incognito,
        max_count: newRoom.value.max_count || 20,
        password: newRoom.value.password || null,
        settings: Object.keys(settings).length > 0 ? settings : null,
      },
    })
    
    // Upload room image if a file was selected
    if (newRoomImageFile.value && room && room.id) {
      try {
        const formData = new FormData()
        formData.append('image', newRoomImageFile.value)
        
        const headers: HeadersInit = {
          'Accept': 'application/json',
        }
        
        if (authStore.token) {
          headers['Authorization'] = `Bearer ${authStore.token}`
        }
        
        await fetch(`${baseUrl}/chat/${room.id}/image`, {
          method: 'POST',
          headers,
          body: formData,
        })
      } catch (error: any) {
        console.error('Error uploading room image:', error)
        // Don't fail the room creation if image upload fails
      }
    }
    
    // Refresh rooms list
    await fetchRoomsList()
    
    // Reset form and close modal
    newRoom.value = {
      name: '',
      description: '',
      welcome_message: '',
      required_likes: 0,
      room_hashtag: null,
      is_public: true,
      is_staff_only: false,
      enable_mic: false,
      disable_incognito: false,
      max_count: 20,
      password: '',
      backgroundColor: '',
      textColor: '',
      roomNameColor: '',
      roomBorderColor: '',
      roomDescriptionColor: '',
    }
    newRoomImageFile.value = null
    newRoomImagePreview.value = null
    
    showCreateRoomModal.value = false
    
    // Navigate to the new room
    if (room && room.id) {
      navigateToRoom(room.id)
    }
  } catch (error: any) {
    console.error('Error creating room:', error)
    alert(error?.message || 'حدث خطأ أثناء إنشاء الغرفة')
  } finally {
    creatingRoom.value = false
  }
}

// Private messages
const privateMessageContent = ref('')
const sendingPrivateMessage = ref(false)
const showPrivateMessageModal = ref(false)
const privateMessagesContainer = ref<HTMLElement | null>(null)

// Wall
const wallPost = ref('')
const wallPosts = ref<any[]>([])

// Settings
const socialMediaOptions = [
  { label: 'YouTube', value: 'youtube' },
  { label: 'Instagram', value: 'instagram' },
  { label: 'TikTok', value: 'tiktok' },
  { label: 'X (Twitter)', value: 'x' },
]

const profileForm = ref({
  name: '',
  bio: '',
  social_media_type: null as 'youtube' | 'instagram' | 'tiktok' | 'x' | null,
  social_media_url: '' as string | null,
})

const roomUsers = computed(() => {
  return chatStore.currentRoom?.users || []
})

const onlineCount = computed(() => {
  return roomUsers.value.length || 0
})

const formatTime = (dateString: string) => {
  const date = new Date(dateString)
  return date.toLocaleTimeString('ar-SA', { hour: '2-digit', minute: '2-digit' })
}

// Reactive time for auto-updating relative time
const currentTime = ref(new Date())
let timeInterval: ReturnType<typeof setInterval> | null = null
let activityInterval: ReturnType<typeof setInterval> | null = null
let pingInterval: ReturnType<typeof setInterval> | null = null
let currentPresenceChannel: any = null // Room-specific presence channel
let globalPresenceChannel: any = null // Global presence channel for cross-room status updates
const scheduledMessageIntervals = ref<Map<number, ReturnType<typeof setInterval>>>(new Map())
const scheduledMessageCountdowns = ref<Map<number, ReturnType<typeof setInterval>>>(new Map())
const scheduledMessageNextSendTime = ref<Map<number, number>>(new Map())

// Helper function to send a local scheduled message (only visible to current user)
const sendLocalScheduledMessage = (scheduledMessage: any, roomId: number) => {
  const systemMessagesImage = getSystemMessagesImage()
  
  const localMessage = {
    id: `scheduled-${scheduledMessage.id}-${Date.now()}-${Math.random()}`,
    room_id: roomId,
    user_id: null,
    user: {
      id: null,
      name: scheduledMessage.title,
      username: 'system',
      avatar_url: systemMessagesImage || null,
      is_guest: false,
      role_groups: [],
    },
    content: scheduledMessage.message,
    meta: {
      is_scheduled: true,
      is_system: true,
      scheduled_message_id: scheduledMessage.id,
      type: scheduledMessage.type,
      is_local: true, // Mark as local so it's only visible to this user
    },
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString(),
  }
  
  chatStore.addMessage(localMessage)
  nextTick(() => {
    scrollToBottom()
  })
}

// Set up scheduled messages for the current room
const setupScheduledMessages = (roomId: number) => {
  
  // Clean up any existing intervals first
  cleanupScheduledMessages()
  
  
  if (!chatStore.currentRoom) {
    return
  }
  
  if (!chatStore.currentRoom.scheduled_messages || !Array.isArray(chatStore.currentRoom.scheduled_messages)) {

    return
  }
  
  const scheduledMessages = chatStore.currentRoom.scheduled_messages.filter((msg: any) => {
    const isActive = msg.is_active !== false && msg.is_active !== undefined
    return isActive
  })
  
  for (const msg of scheduledMessages) {
    
    if (msg.type === 'welcoming') {
      // Send welcoming messages after room welcome/system messages are sent (one-time only, does not recur)
      // Wait for welcome/system messages to be sent first
      setTimeout(() => {
        sendLocalScheduledMessage(msg, roomId)
      }, 1000) // Wait 1 second after room welcome messages
    } else if (msg.type === 'daily') {
      // Set up interval for daily messages based on time_span (recurring every time_span minutes)
      const intervalMs = msg.time_span * 60 * 1000 // Convert minutes to milliseconds
      
      // Store message ID and title for lookup later
      const messageId = msg.id
      const messageTitle = msg.title
      // Store a copy of the message data as fallback
      const originalMessage = { ...msg }
      
      // Send first message after room welcome/system messages are sent
      // Wait for welcome/system messages to be sent first
      setTimeout(() => {
        sendLocalScheduledMessage(msg, roomId)
        // Calculate next send time after first message is sent (accounting for the 1 second delay)
        const nextSendTime = Date.now() + intervalMs
        scheduledMessageNextSendTime.value.set(messageId, nextSendTime)
      }, 1000) // Wait 1 second after room welcome messages
      
      // Set initial next send time (will be updated when first message is sent)
      const initialNextSendTime = Date.now() + intervalMs + 1000
      scheduledMessageNextSendTime.value.set(messageId, initialNextSendTime)
      
      // Set up countdown timer to log remaining time
      const startCountdown = () => {
        // Clear existing countdown if any
        const existingCountdown = scheduledMessageCountdowns.value.get(messageId)
        if (existingCountdown) {
          clearInterval(existingCountdown)
        }
        
        const countdownId = setInterval(() => {
          const nextTime = scheduledMessageNextSendTime.value.get(messageId)
          if (!nextTime) {
            clearInterval(countdownId)
            scheduledMessageCountdowns.value.delete(messageId)
            return
          }
          
          const remaining = nextTime - Date.now()
          if (remaining <= 0) {
            clearInterval(countdownId)
            scheduledMessageCountdowns.value.delete(messageId)
            return
          }
          
          // Countdown timer - no logging needed
          // Just update the next send time tracking
        }, 1000) // Update every second
        
        scheduledMessageCountdowns.value.set(messageId, countdownId)
      }
      
      startCountdown()
      
      // Then set up interval to send repeatedly every time_span minutes
      const intervalId = setInterval(() => {
        // Check if we're still in the same room
        if (chatStore.currentRoom && String(chatStore.currentRoom.id) === String(roomId)) {
          // Look up the scheduled message from loaded room data
          const scheduledMessages = chatStore.currentRoom.scheduled_messages || []
          
          let currentMessage = scheduledMessages.find((m: any) => m.id === messageId && m.is_active)
          
          // Fallback to original message if not found in current room data (in case room data was refreshed)
          if (!currentMessage && originalMessage.is_active) {
            currentMessage = originalMessage
          }
          
          if (currentMessage && currentMessage.is_active) {
            sendLocalScheduledMessage(currentMessage, roomId)
            
            // Update next send time and restart countdown
            const newNextSendTime = Date.now() + intervalMs
            scheduledMessageNextSendTime.value.set(messageId, newNextSendTime)
            startCountdown()
          }
        } else {
          // Clean up if we've left the room
          clearInterval(intervalId)
          scheduledMessageIntervals.value.delete(messageId)
          const countdownId = scheduledMessageCountdowns.value.get(messageId)
          if (countdownId) {
            clearInterval(countdownId)
            scheduledMessageCountdowns.value.delete(messageId)
          }
          scheduledMessageNextSendTime.value.delete(messageId)
        }
      }, intervalMs)
      
      scheduledMessageIntervals.value.set(msg.id, intervalId)
    }
  }
  
  if (scheduledMessageIntervals.value.size === 0) {
    console.warn('⚠️ No recurring intervals were set up. Make sure you have "daily" type messages (not "welcoming") if you want messages to recur.')
  }
}

// Clean up scheduled message intervals
const cleanupScheduledMessages = () => {
  scheduledMessageIntervals.value.forEach((intervalId) => {
    clearInterval(intervalId)
  })
  scheduledMessageIntervals.value.clear()
  
  // Clean up countdown timers
  scheduledMessageCountdowns.value.forEach((countdownId) => {
    clearInterval(countdownId)
  })
  scheduledMessageCountdowns.value.clear()
  
  // Clear next send times
  scheduledMessageNextSendTime.value.clear()
}

// Update current time every minute for relative time display
// This will be set up in the main onMounted hook

// Update user activity every 2 minutes to keep them active in the room
const updateActivity = async () => {
  if (!authStore.isAuthenticated || !roomId.value) return
  
  try {
    const { $api } = useNuxtApp()
    // Just fetch the room to update last_activity (backend handles this)
    await ($api as any)(`/chat/${roomId.value}`)
  } catch (error) {
    // Ignore errors
  }
}

const formatRelativeTime = (dateString: string) => {
  const date = new Date(dateString)
  const now = currentTime.value
  const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000)

  if (diffInSeconds < 60) {
    return 'الآن'
  }

  const diffInMinutes = Math.floor(diffInSeconds / 60)
  if (diffInMinutes < 60) {
    return `${diffInMinutes}م`
  }

  const diffInHours = Math.floor(diffInMinutes / 60)
  if (diffInHours < 24) {
    return `${diffInHours}س`
  }

  const diffInDays = Math.floor(diffInHours / 24)
  if (diffInDays < 7) {
    return `${diffInDays}يوم`
  }

  const diffInWeeks = Math.floor(diffInDays / 7)
  if (diffInWeeks < 4) {
    return `${diffInWeeks}أسبوع`
  }

  const diffInMonths = Math.floor(diffInDays / 30)
  if (diffInMonths < 12) {
    return `${diffInMonths}شهر`
  }

  const diffInYears = Math.floor(diffInDays / 365)
  return `${diffInYears}سنة`
}

const scrollToBottom = () => {
  nextTick(() => {
    if (messagesContainer.value) {
      messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight
    }
  })
}

const addLocalMoveMessageForCurrentUser = (fromRoomId: string | number, toRoomId: string | number) => {
  if (!authStore.user) {
    return
  }

  const toRoomIdNumber = Number(toRoomId)
  const fromRoomIdNumber = Number(fromRoomId)

  if (!toRoomIdNumber || !fromRoomIdNumber || toRoomIdNumber === fromRoomIdNumber) {
    return
  }

  const movedMessage = {
    id: `system-moved-${Date.now()}-${Math.random()}`,
    room_id: toRoomIdNumber,
    user_id: authStore.user.id,
    user: {
      id: authStore.user.id,
      name: authStore.user.name,
      username: authStore.user.username,
      avatar_url: authStore.user.avatar_url,
      name_color: authStore.user.name_color || { r: 69, g: 9, b: 36 },
      message_color: authStore.user.message_color || { r: 69, g: 9, b: 36 },
      image_border_color: authStore.user.image_border_color || { r: 69, g: 9, b: 36 },
      name_bg_color: authStore.user.name_bg_color || 'transparent',
    },
    content: `${authStore.user.name || authStore.user.username} انتقل إلى الغرفة`,
    meta: {
      is_system: true,
      action: 'moved',
      room_id: toRoomIdNumber,
      room_name: chatStore.currentRoom?.name || `الغرفة ${toRoomIdNumber}`,
      previous_room_id: fromRoomIdNumber,
    },
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString(),
  }

  const existingMovedMessage = chatStore.messages.find((m: Message) =>
    m.meta?.is_system &&
    m.meta?.action === 'moved' &&
    m.user_id === authStore.user?.id &&
    m.room_id === toRoomIdNumber &&
    m.meta?.previous_room_id === fromRoomIdNumber &&
    new Date(m.created_at).getTime() > Date.now() - 5000
  )

  // if (existingMovedMessage) {
  //   return
  // }

  chatStore.addMessage(movedMessage)
  nextTick(() => {
    scrollToBottom()
  })
}

// System messages are now received from the backend via WebSocket events
// No need for local creation - they are broadcast to all users in the room

const scrollToMessage = (messageId: number) => {
  nextTick(() => {
    if (messagesContainer.value) {
      const messageElement = messagesContainer.value.querySelector(`[data-message-id="${messageId}"]`)
      if (messageElement) {
        messageElement.scrollIntoView({ behavior: 'smooth', block: 'center' })
        // Highlight the message briefly
        messageElement.classList.add('bg-yellow-100')
        setTimeout(() => {
          messageElement.classList.remove('bg-yellow-100')
        }, 2000)
      }
    }
  })
}

const insertEmoji = (emojiId: number) => {
  const emojiCode = `:${emojiId}:`
  messageContent.value += emojiCode
  emojiPanel.value?.hide()
  // Focus back on input
  nextTick(() => {
    if (messageInput.value && messageInput.value.$el) {
      const input = messageInput.value.$el.querySelector('input') || messageInput.value.$el
      if (input) {
        input.focus()
        // Move cursor to end
        const length = messageContent.value.length
        input.setSelectionRange(length, length)
      }
    }
  })
}

const insertPrivateEmoji = (emojiId: number) => {
  const emojiCode = `:${emojiId}:`
  privateMessageContent.value += emojiCode
  privateEmojiPanel.value?.hide()
  // Focus back on input
  nextTick(() => {
    if (privateMessageInput.value && privateMessageInput.value.$el) {
      const input = privateMessageInput.value.$el.querySelector('input') || privateMessageInput.value.$el
      if (input) {
        input.focus()
        // Move cursor to end
        const length = privateMessageContent.value.length
        input.setSelectionRange(length, length)
      }
    }
  })
}

const onPrivateMessageFileSelect = (event: any) => {
  const file = event.files?.[0]
  if (file) {
    privateMessageImageFile.value = file
    // Auto-send the image
    sendPrivateMessageWithImage()
  }
}

const focusMessageInput = () => {
  nextTick(() => {
    if (messageInput.value) {
      // PrimeVue InputText component
      if (messageInput.value.$el) {
        const input = messageInput.value.$el.querySelector('input') || messageInput.value.$el
        if (input && input.focus) {
          input.focus()
        }
      } else if (messageInput.value.focus) {
        // Direct focus method if available
        messageInput.value.focus()
      }
    }
  })
}

const setReplyTo = (message: Message) => {
  replyingTo.value = message
  focusMessageInput()
}

const cancelReply = () => {
  replyingTo.value = null
}

const getReplyUserName = (replyTo: { user_id: number; user?: User }) => {
  if (replyTo.user) {
    return replyTo.user.name || replyTo.user.username
  }
  // Try to find user in current room
  const user = chatStore.currentRoom?.users?.find((u: User) => u.id === replyTo.user_id)
  return user ? (user.name || user.username) : 'مستخدم'
}

const sendMessage = async () => {
  if (!messageContent.value.trim() || sending.value) return
  
  // Don't send if not in a room
  if (!chatStore.currentRoom || !roomId.value) {
    toast.add({
      severity: 'warn',
      summary: 'غير متاح',
      detail: 'يرجى الانضمام إلى غرفة أولاً',
      life: 3000,
    })
    return
  }

  // Check message-specific rate limit (allows bursts)
  const { checkMessageRateLimit } = useRateLimit()
  if (!checkMessageRateLimit()) {
    return
  }

  // Don't send messages if password dialog is open - wait for password validation first
  if (showPasswordDialog.value) {
    toast.add({
      severity: 'warn',
      summary: 'انتظر',
      detail: 'يرجى إدخال كلمة المرور أولاً',
      life: 3000,
    })
    return
  }

  // Check permission to send messages
  if (!hasPermission(authStore.user, 'sending_messages_in_chat')) {
    toast.add({
      severity: 'warn',
      summary: 'غير مصرح',
      detail: 'ليس لديك صلاحية لإرسال الرسائل',
      life: 3000,
    })
    return
  }

  const content = messageContent.value.trim()
  messageContent.value = '' // Clear input immediately for better UX
  
  // Prepare meta with reply info if replying
  const meta = replyingTo.value ? {
    reply_to: {
      id: replyingTo.value.id,
      content: replyingTo.value.content,
      user_id: replyingTo.value.user_id,
      user: replyingTo.value.user,
    }
  } : undefined
  
  // Clear reply
  const wasReplying = !!replyingTo.value
  const replyData = replyingTo.value ? { ...replyingTo.value } : null
  replyingTo.value = null
  
  // Create temporary message ID for optimistic update
  const tempMessageId = `temp-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`
  let tempMessage: any = null

  // Mark current user as active when sending a message
  if (authStore.user?.id) {
    markUserActiveOnSocket(authStore.user.id)
  }

  // Create optimistic message and add it immediately
  if (authStore.user) {
    tempMessage = {
      id: tempMessageId,
      room_id: Number(roomId.value),
      user_id: authStore.user.id,
      user: {
        id: authStore.user.id,
        name: authStore.user.name,
        username: authStore.user.username,
        avatar_url: authStore.user.avatar_url,
        name_color: authStore.user.name_color,
        message_color: authStore.user.message_color,
        name_bg_color: authStore.user.name_bg_color,
        image_border_color: authStore.user.image_border_color,
        bio_color: authStore.user.bio_color,
        role_groups: authStore.user.role_groups || [],
      },
      content: content,
      meta: meta || {},
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
      is_optimistic: true, // Flag to identify temporary messages
    }
    
    // Add message immediately for instant feedback
    chatStore.addMessage(tempMessage)
    scrollToBottom()
  }

  // Remove loading state immediately - don't wait for response
  sending.value = false
  focusMessageInput()

  // Send message to backend (will be stored in database) - non-blocking
  ;(async () => {
    try {
      const sentMessage = await chatStore.sendMessage(roomId.value, content, meta)

      // Remove temporary message if it exists
      if (tempMessage) {
        const tempIndex = chatStore.messages.findIndex((m: Message) => m.id === tempMessageId)
        if (tempIndex !== -1) {
          chatStore.messages.splice(tempIndex, 1)
        }
      }

      // Add the real message from backend
      if (sentMessage && sentMessage.id) {
        // Ensure the message has the correct room_id
        const messageWithRoom = {
          ...sentMessage,
          room_id: Number(roomId.value),
        }
        chatStore.addMessage(messageWithRoom)
        scrollToBottom()
      }
    } catch (error: any) {
      // Check if user is banned
      if (error?.data?.banned || (error?.status === 403 && error?.data?.message?.includes('banned'))) {
        // Remove temporary message if it exists
        if (tempMessage) {
          const tempIndex = chatStore.messages.findIndex((m: Message) => m.id === tempMessageId)
          if (tempIndex !== -1) {
            chatStore.messages.splice(tempIndex, 1)
          }
        }
        // Ban is handled by useApi composable, just return
        return
      }
      
      // Mark message as failed instead of removing it
      if (tempMessage) {
        const tempIndex = chatStore.messages.findIndex((m: Message) => m.id === tempMessageId)
        if (tempIndex !== -1) {
          // Update message to show error state
          chatStore.messages[tempIndex] = {
            ...chatStore.messages[tempIndex],
            is_optimistic: true,
            send_failed: true,
            error_message: error?.data?.message || error?.message || 'فشل إرسال الرسالة',
          }
        }
      }
      
      // Restore message content and reply state on error
      messageContent.value = content
      if (wasReplying && replyData) {
        replyingTo.value = replyData
      }
      console.error('Error sending message:', error)
    }
  })()
    
    // Premium entry notification only shows when joining/moving rooms (with 3 second delay)
}

// Don't load messages from database - only show messages received via real-time during the session
// Messages will clear on page refresh (store resets)

// Users sidebar functions
// Stories
const storyViewerRef = ref()
const storyCreatorRef = ref()
const selectedStoryUserId = ref<number | null>(null)
const usersWithStories = ref<Array<{
  user: { id: number; name?: string; username?: string; avatar_url?: string }
  stories: any[]
  has_unviewed: boolean
}>>([])

const viewUserStory = (userId: number) => {
  selectedStoryUserId.value = userId
  nextTick(() => {
    storyViewerRef.value?.open(userId)
  })
}

const openStoryCreator = () => {
  storyCreatorRef.value?.open()
}

const handleStoryCreated = () => {
  // Story will be added via socket event, no need to fetch
}

const fetchStories = async () => {
  try {
    const { api } = useApi()
    const response = await api('/stories')
    
    // Ensure response is an array
    if (!Array.isArray(response)) {
      console.error('Invalid stories response format:', response)
      usersWithStories.value = []
      return
    }
    
    // Validate and map the response
    const stories: Array<{
      user: { id: number; name?: string; username?: string; avatar_url?: string }
      stories: any[]
      has_unviewed: boolean
    }> = []
    
    for (const item of response) {
      // Ensure item has required structure
      if (item && typeof item === 'object' && item.user && Array.isArray(item.stories)) {
        stories.push({
          user: {
            id: item.user.id,
            name: item.user.name,
            username: item.user.username,
            avatar_url: item.user.avatar_url,
          },
          stories: item.stories,
          has_unviewed: Boolean(item.has_unviewed),
        })
      }
    }
    
    usersWithStories.value = stories
  } catch (error: any) {
    console.error('Error fetching stories:', error)
    usersWithStories.value = []
  }
}

const addStoryFromSocket = (storyData: any) => {
  // Find if user already has stories
  const existingUserIndex = usersWithStories.value.findIndex(
    (item) => item.user.id === storyData.user_id
  )

  const story = {
    id: storyData.id,
    media_url: storyData.media_url,
    media_type: storyData.media_type,
    caption: storyData.caption,
    expires_at: storyData.expires_at,
    created_at: storyData.created_at,
    is_viewed: storyData.is_viewed || false,
    views_count: storyData.views_count || 0,
  }

  if (existingUserIndex !== -1) {
    // User already has stories, add to their list
    const userStories = usersWithStories.value[existingUserIndex]
    if (userStories) {
      // Check if story already exists (avoid duplicates)
      const storyExists = userStories.stories.some((s: any) => s.id === story.id)
      if (!storyExists) {
        userStories.stories.unshift(story) // Add to beginning
        // Update has_unviewed if this is a new story from another user
        if (storyData.user_id !== authStore.user?.id) {
          userStories.has_unviewed = true
        }
      }
    }
  } else {
    // New user with stories, add them
    usersWithStories.value.push({
      user: {
        id: storyData.user.id,
        name: storyData.user.name,
        username: storyData.user.username,
        avatar_url: storyData.user.avatar_url,
      },
      stories: [story],
      has_unviewed: storyData.user_id !== authStore.user?.id, // Not unviewed if it's current user's story
    })
  }
}

const openPrivateChat = async (user: { id: number; name?: string; username?: string; avatar_url?: string }) => {
  // Close user profile modal if open
  if (showUserProfileModal.value) {
    closeUserProfileModal()
  }
  
  // Check if user has private messages enabled
  const fullUser = chatStore.activeUsers.find((u: User) => u.id === user.id) || user as User
  
  if (!fullUser.private_messages_enabled && fullUser.id !== authStore.user?.id) {
    toast.add({
      severity: 'warn',
      summary: 'غير متاح',
      detail: 'هذا المستخدم قام بإيقاف الرسائل الخاصة',
      life: 3000,
    })
    return
  }
  
  // Check if current user has private messages enabled
  if (!authStore.user?.private_messages_enabled) {
    toast.add({
      severity: 'warn',
      summary: 'غير متاح',
      detail: 'الرسائل الخاصة معطلة في حسابك',
      life: 3000,
    })
    return
  }
  
  // Set current conversation and show modal immediately (optimistic UI)
  privateMessagesStore.setCurrentConversation(fullUser)
  showPrivateMessageModal.value = true
  
  // Load messages in background (non-blocking)
  ;(async () => {
    try {
      // Fetch messages
      await privateMessagesStore.fetchMessages(user.id)
      
      // Mark as read
      await privateMessagesStore.markAsRead(user.id)
      
      // Scroll to bottom after messages load
      nextTick(() => {
        scrollPrivateMessagesToBottom()
      })
    } catch (error: any) {
      console.error('Error loading private messages:', error)
      toast.add({
        severity: 'error',
        summary: 'خطأ',
        detail: error?.message || 'فشل تحميل الرسائل',
        life: 3000,
      })
    }
  })()
}

const closePrivateMessageModal = () => {
  showPrivateMessageModal.value = false
  privateMessagesStore.setCurrentConversation(null)
  privateMessageContent.value = ''
  privateMessageImageFile.value = null
  privateEmojiPanel.value?.hide()
}

const scrollPrivateMessagesToBottom = () => {
  if (privateMessagesContainer.value) {
    privateMessagesContainer.value.scrollTop = privateMessagesContainer.value.scrollHeight
  }
}

const sendPrivateMessage = async () => {
  if ((!privateMessageContent.value.trim() && !privateMessageImageFile.value) || sendingPrivateMessage.value || !privateMessagesStore.currentConversation) return

  // Check rate limit
  const { checkRateLimit } = useRateLimit()
  if (!checkRateLimit('send_private_message')) {
    return
  }

  const content = privateMessageContent.value.trim()
  const imageFile = privateMessageImageFile.value
  
  // If there's an image, send it separately
  if (imageFile) {
    await sendPrivateMessageWithImage()
    return
  }
  
  privateMessageContent.value = ''
  
  sendingPrivateMessage.value = true

  try {
    const sentMessage = await privateMessagesStore.sendMessage(
      privateMessagesStore.currentConversation.id,
      content
    )

    if (sentMessage && sentMessage.id) {
      privateMessagesStore.addMessage(sentMessage)
    }

    scrollPrivateMessagesToBottom()
  } catch (error: any) {
    console.error('Error sending private message:', error)
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error?.message || 'فشل إرسال الرسالة',
      life: 3000,
    })
    // Restore message content on error
    privateMessageContent.value = content
  } finally {
    sendingPrivateMessage.value = false
  }
}

const sendPrivateMessageWithImage = async () => {
  if (!privateMessagesStore.currentConversation || sendingPrivateMessage.value) return

  const imageFile = privateMessageImageFile.value
  const content = privateMessageContent.value.trim()
  
  if (!imageFile) return
  
  sendingPrivateMessage.value = true

  try {
    const { $api } = useNuxtApp()
    const formData = new FormData()
    formData.append('image', imageFile)
    if (content) {
      formData.append('content', content)
    }

    // Upload image first, then send message with image URL
    const uploadResponse = await $api('/private-messages/upload-image', {
      method: 'POST',
      body: formData,
    })

    // Send message with image URL in meta
    const messageContent = content || 'صورة'
    const meta = {
      image_url: uploadResponse.image_url || uploadResponse.url,
      image_path: uploadResponse.path,
    }

    const sentMessage = await privateMessagesStore.sendMessage(
      privateMessagesStore.currentConversation.id,
      messageContent,
      meta
    )

    if (sentMessage && sentMessage.id) {
      privateMessagesStore.addMessage(sentMessage)
    }

    // Clear inputs
    privateMessageContent.value = ''
    privateMessageImageFile.value = null

    scrollPrivateMessagesToBottom()
  } catch (error: any) {
    console.error('Error sending private message with image:', error)
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error?.message || 'فشل إرسال الصورة',
      life: 3000,
    })
  } finally {
    sendingPrivateMessage.value = false
  }
}

// Wall functions
const loadingWallPosts = ref(false)
const postingToWall = ref(false)

const fetchWallPosts = async (force = false) => {
  if (!roomId.value) return
  
  // Try localStorage cache with background refresh
  if (!force && import.meta.client) {
    const { useLocalStorageCache } = await import('~~/app/composables/useLocalStorageCache')
    const cache = useLocalStorageCache()
    const cacheKey = `wall_posts_${roomId.value}`
    const cachedPosts = cache.getCachedData<any[]>(cacheKey)
    
    if (cachedPosts && Array.isArray(cachedPosts) && cachedPosts.length > 0) {
      // Show cached data immediately
      wallPosts.value = cachedPosts
      // Fetch fresh data in background
      fetchWallPostsInBackground()
      return
    }
  }
  
  // No cache or force refresh, fetch directly
  loadingWallPosts.value = true
  try {
    const { api } = useApi()
    const response = await api(`/chat/${roomId.value}/wall-posts`)
    // Handle paginated response
    const posts = response.data || response || []
    
    // Add image URLs to posts
    posts.forEach((post: any) => {
      if (post.image) {
        post.image_url = post.image.startsWith('http') ? post.image : `${useRuntimeConfig().public.apiBaseUrl.replace('/api', '')}/storage/${post.image}`
      }
    })
    
    wallPosts.value = posts
    
    // Cache it
    if (import.meta.client && roomId.value) {
      const { useLocalStorageCache } = await import('~~/app/composables/useLocalStorageCache')
      const cache = useLocalStorageCache()
      const cacheKey = `wall_posts_${roomId.value}`
      cache.setCachedData(cacheKey, posts, 120 * 60 * 1000) // 2 hours (120 minutes)
    }
    
    // Fetch wall creator
    await fetchWallCreator()
  } catch (error: any) {
    console.error('Error fetching wall posts:', error)
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: 'فشل تحميل منشورات الحائط',
      life: 3000,
    })
  } finally {
    loadingWallPosts.value = false
  }
}

const fetchWallPostsInBackground = async () => {
  if (!roomId.value) return
  
  try {
    const { api } = useApi()
    const response = await api(`/chat/${roomId.value}/wall-posts`)
    const posts = response.data || response || []
    
    // Add image URLs to posts
    posts.forEach((post: any) => {
      if (post.image) {
        post.image_url = post.image.startsWith('http') ? post.image : `${useRuntimeConfig().public.apiBaseUrl.replace('/api', '')}/storage/${post.image}`
      }
    })
    
    // Merge with existing posts, keeping unique posts by ID
    const existingPostIds = new Set(wallPosts.value.map((p: any) => p.id))
    const newPosts = posts.filter((p: any) => !existingPostIds.has(p.id))
    
    // Combine existing and new posts, then sort by created_at descending (newest first)
    const allPosts = [...wallPosts.value, ...newPosts].sort((a: any, b: any) => {
      const dateA = new Date(a.created_at).getTime()
      const dateB = new Date(b.created_at).getTime()
      return dateB - dateA // Descending order (newest first)
    })
    
    // Update state with merged and sorted posts
    wallPosts.value = allPosts
    
    // Update cache with merged posts
    if (import.meta.client && roomId.value) {
      const { useLocalStorageCache } = await import('~~/app/composables/useLocalStorageCache')
      const cache = useLocalStorageCache()
      const cacheKey = `wall_posts_${roomId.value}`
      cache.setCachedData(cacheKey, allPosts, 120 * 60 * 1000) // 2 hours
    }
    
    // Fetch wall creator in background
    await fetchWallCreator()
  } catch (error) {
    console.error('Error fetching wall posts in background:', error)
    // Keep showing cached data on error
  }
}

const postToWall = async () => {
  if ((!wallPost.value.trim() && !wallPostImageFile.value && !selectedYouTubeVideo.value) || !roomId.value) return
  
  // Check rate limit
  const { checkRateLimit } = useRateLimit()
  if (!checkRateLimit('post_to_wall')) {
    return
  }
  
  postingToWall.value = true
  try {
    const { api } = useApi()
    const formData = new FormData()
    
    if (wallPost.value.trim()) {
      formData.append('content', wallPost.value)
    }
    
    if (wallPostImageFile.value) {
      formData.append('image', wallPostImageFile.value)
    }
    
    if (selectedYouTubeVideo.value) {
      formData.append('youtube_video[id]', selectedYouTubeVideo.value.id)
      formData.append('youtube_video[title]', selectedYouTubeVideo.value.title)
      formData.append('youtube_video[thumbnail]', selectedYouTubeVideo.value.thumbnail)
    }
    
    const newPost = await api(`/chat/${roomId.value}/wall-posts`, {
      method: 'POST',
      body: formData,
    })
    
    // Add to local array (will also be added via real-time event)
    const existingPost = wallPosts.value.find((p: any) => p.id === newPost.id)
    if (!existingPost) {
      wallPosts.value.unshift(newPost)
      // Update cache with new post
      if (import.meta.client && roomId.value) {
        const { useLocalStorageCache } = await import('~~/app/composables/useLocalStorageCache')
        const cache = useLocalStorageCache()
        const cacheKey = `wall_posts_${roomId.value}`
        cache.setCachedData(cacheKey, wallPosts.value, 120 * 60 * 1000) // 2 hours
      }
    }
    
    // Reset form
    wallPost.value = ''
    wallPostImageFile.value = null
    wallPostImagePreview.value = null
    selectedYouTubeVideo.value = null
    if (wallPostImageInput.value) {
      wallPostImageInput.value.value = ''
    }
    
    // Refresh wall creator
    await fetchWallCreator()
  } catch (error: any) {
    console.error('Error posting to wall:', error)
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.data?.message || 'فشل نشر المنشور',
      life: 3000,
    })
  } finally {
    postingToWall.value = false
  }
}

const deleteWallPost = async (postId: number) => {
  if (!roomId.value) return
  
  try {
    const { api } = useApi()
    await api(`/chat/${roomId.value}/wall-posts/${postId}`, {
      method: 'DELETE',
    })
    
    // Remove from local array
    wallPosts.value = wallPosts.value.filter((p: any) => p.id !== postId)
    
    toast.add({
      severity: 'success',
      summary: 'نجح',
      detail: 'تم حذف المنشور',
      life: 3000,
    })
  } catch (error: any) {
    console.error('Error deleting wall post:', error)
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.data?.message || 'فشل حذف المنشور',
      life: 3000,
    })
  }
}

// Wall post enhancements
const wallPostImageFile = ref<File | null>(null)
const wallPostImagePreview = ref<string | null>(null)
const wallPostImageInput = ref<HTMLInputElement | null>(null)
const wallEmojiPanel = ref()
const wallPostInput = ref()
const selectedYouTubeVideo = ref<any>(null)
const youtubeSearchQuery = ref('')
const youtubeSearchResults = ref<any[]>([])
const searchingYouTube = ref(false)
const wallCreator = ref<any>(null)
const topCreators = ref<any[]>([])
const showWallCreatorsModal = ref(false)
const loadingTopCreators = ref(false)
const showCommentsModal = ref(false)
const selectedWallPost = ref<any>(null)
const currentPostComments = ref<any[]>([])
const loadingComments = ref(false)
const postingComment = ref(false)
const newComment = ref('')
const showImageDialog = ref(false)
const viewingImageUrl = ref<string | null>(null)
const playingVideos = ref<Set<number>>(new Set())

const handleWallPostImageSelect = (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (file) {
    // Clear YouTube video if image is selected (only one media allowed)
    if (selectedYouTubeVideo.value) {
      selectedYouTubeVideo.value = null
    }
    wallPostImageFile.value = file
    const reader = new FileReader()
    reader.onload = (e) => {
      wallPostImagePreview.value = e.target?.result as string
    }
    reader.readAsDataURL(file)
  }
}

const insertEmojiToWallPost = (emojiId: number) => {
  const emojiUrl = getEmojiUrl(emojiId)
  if (emojiUrl && wallPostInput.value) {
    const imgTag = `<img src="${emojiUrl}" alt="emoji" class="inline-block w-5 h-5" />`
    const textarea = wallPostInput.value.$el?.querySelector('textarea') || wallPostInput.value.$el
    if (textarea) {
      const start = textarea.selectionStart || 0
      const end = textarea.selectionEnd || 0
      const text = wallPost.value
      wallPost.value = text.substring(0, start) + imgTag + text.substring(end)
      nextTick(() => {
        textarea.focus()
        textarea.setSelectionRange(start + imgTag.length, start + imgTag.length)
      })
    }
  }
  wallEmojiPanel.value?.hide()
}

let youtubeSearchDebounceTimer: ReturnType<typeof setTimeout> | null = null

const debounceSearch = () => {
  if (youtubeSearchDebounceTimer) {
    clearTimeout(youtubeSearchDebounceTimer)
  }
  youtubeSearchDebounceTimer = setTimeout(() => {
    if (youtubeSearchQuery.value.trim()) {
      searchYouTube()
    } else {
      youtubeSearchResults.value = []
    }
  }, 500)
}

const searchYouTube = async () => {
  if (!youtubeSearchQuery.value.trim()) {
    youtubeSearchResults.value = []
    return
  }

  searchingYouTube.value = true
  try {
    const { api } = useApi()
    const response = await api(`/youtube/search?q=${encodeURIComponent(youtubeSearchQuery.value)}`)
    
    youtubeSearchResults.value = response.videos || []
    
    if (youtubeSearchResults.value.length === 0) {
      toast.add({
        severity: 'info',
        summary: 'لا توجد نتائج',
        detail: 'لم يتم العثور على فيديوهات',
        life: 2000,
      })
    }
  } catch (error: any) {
    console.error('Error searching YouTube:', error)
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.data?.message || 'فشل البحث في YouTube',
      life: 3000,
    })
    youtubeSearchResults.value = []
  } finally {
    searchingYouTube.value = false
  }
}

const selectYouTubeVideo = (video: any) => {
  // Clear image if YouTube video is selected (only one media allowed)
  if (wallPostImageFile.value || wallPostImagePreview.value) {
    wallPostImageFile.value = null
    wallPostImagePreview.value = null
    if (wallPostImageInput.value) {
      wallPostImageInput.value.value = ''
    }
  }
  selectedYouTubeVideo.value = video
  youtubeSearchResults.value = []
  youtubeSearchQuery.value = ''
}

const fetchWallCreator = async () => {
  if (!roomId.value) return
  
  try {
    const { api } = useApi()
    const response = await api(`/chat/${roomId.value}/wall-creator`)
    wallCreator.value = response.wall_creator ? {
      ...response.wall_creator,
      total_likes: response.total_likes,
    } : null
    
    // Store top creators for podium modal
    if (response.top_creators && Array.isArray(response.top_creators)) {
      topCreators.value = response.top_creators.map((creator: any) => ({
        ...creator,
        total_likes: creator.total_likes || 0,
      }))
    }
  } catch (error) {
    console.error('Error fetching wall creator:', error)
  }
}

const fetchTopCreators = async () => {
  if (!roomId.value) return
  
  loadingTopCreators.value = true
  try {
    const { api } = useApi()
    const response = await api(`/chat/${roomId.value}/wall-creator`)
    
    if (response.top_creators && Array.isArray(response.top_creators)) {
      topCreators.value = response.top_creators.map((creator: any) => ({
        ...creator,
        total_likes: creator.total_likes || 0,
      }))
    } else {
      topCreators.value = []
    }
  } catch (error) {
    console.error('Error fetching top creators:', error)
    topCreators.value = []
  } finally {
    loadingTopCreators.value = false
  }
}

// Watch for modal opening to fetch top creators
watch(showWallCreatorsModal, (isOpen) => {
  if (isOpen && roomId.value) {
    fetchTopCreators()
  }
})

const toggleWallPostLike = async (post: any) => {
  if (!roomId.value) return
  
  // Check rate limit
  const { checkRateLimit } = useRateLimit()
  if (!checkRateLimit('like_post')) {
    return
  }
  
  try {
    const { api } = useApi()
    const response = await api(`/chat/${roomId.value}/wall-posts/${post.id}/like`, {
      method: 'POST',
    })
    
    // Update post in local array
    const index = wallPosts.value.findIndex((p: any) => p.id === post.id)
    if (index !== -1) {
      wallPosts.value[index].is_liked = response.liked
      wallPosts.value[index].likes_count = response.likes_count
    }
  } catch (error: any) {
    console.error('Error toggling like:', error)
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.data?.message || 'فشل تحديث الإعجاب',
      life: 3000,
    })
  }
}

const openCommentsModal = async (post: any) => {
  selectedWallPost.value = post
  showCommentsModal.value = true
  await fetchComments(post.id)
}

const fetchComments = async (postId: number) => {
  if (!roomId.value) return
  
  loadingComments.value = true
  try {
    const { api } = useApi()
    const comments = await api(`/chat/${roomId.value}/wall-posts/${postId}/comments`)
    currentPostComments.value = comments || []
  } catch (error: any) {
    console.error('Error fetching comments:', error)
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: 'فشل تحميل التعليقات',
      life: 3000,
    })
  } finally {
    loadingComments.value = false
  }
}

const addComment = async () => {
  if (!newComment.value.trim() || !selectedWallPost.value || !roomId.value) return
  
  // Check rate limit
  const { checkRateLimit } = useRateLimit()
  if (!checkRateLimit('comment_post')) {
    return
  }
  
  postingComment.value = true
  try {
    const { api } = useApi()
    const comment = await api(`/chat/${roomId.value}/wall-posts/${selectedWallPost.value.id}/comments`, {
      method: 'POST',
      body: {
        content: newComment.value,
      } as any,
    })
    
    currentPostComments.value.push(comment)
    newComment.value = ''
    
    // Update comments count in post
    const index = wallPosts.value.findIndex((p: any) => p.id === selectedWallPost.value.id)
    if (index !== -1) {
      wallPosts.value[index].comments_count = (wallPosts.value[index].comments_count || 0) + 1
    }
  } catch (error: any) {
    console.error('Error adding comment:', error)
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.data?.message || 'فشل إضافة التعليق',
      life: 3000,
    })
  } finally {
    postingComment.value = false
  }
}

const deleteComment = async (commentId: number) => {
  if (!selectedWallPost.value || !roomId.value) return
  
  try {
    const { api } = useApi()
    await api(`/chat/${roomId.value}/wall-posts/${selectedWallPost.value.id}/comments/${commentId}`, {
      method: 'DELETE',
    })
    
    currentPostComments.value = currentPostComments.value.filter((c: any) => c.id !== commentId)
    
    // Update comments count in post
    const index = wallPosts.value.findIndex((p: any) => p.id === selectedWallPost.value.id)
    if (index !== -1) {
      wallPosts.value[index].comments_count = Math.max((wallPosts.value[index].comments_count || 1) - 1, 0)
    }
    
    toast.add({
      severity: 'success',
      summary: 'نجح',
      detail: 'تم حذف التعليق',
      life: 3000,
    })
  } catch (error: any) {
    console.error('Error deleting comment:', error)
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.data?.message || 'فشل حذف التعليق',
      life: 3000,
    })
  }
}

const viewWallPostImage = (imageUrl: string) => {
  viewingImageUrl.value = imageUrl
  showImageDialog.value = true
}

// Settings functions
const clearSocialMedia = () => {
  profileForm.value.social_media_type = null
  profileForm.value.social_media_url = null
}

const detectSocialMediaPlatform = () => {
  const url = profileForm.value.social_media_url
  if (!url || !url.trim()) {
    profileForm.value.social_media_type = null
    return
  }
  
  const urlLower = url.toLowerCase().trim()
  
  // YouTube detection
  if (urlLower.includes('youtube.com') || urlLower.includes('youtu.be')) {
    profileForm.value.social_media_type = 'youtube'
    return
  }
  
  // Instagram detection
  if (urlLower.includes('instagram.com') || urlLower.includes('instagr.am')) {
    profileForm.value.social_media_type = 'instagram'
    return
  }
  
  // TikTok detection
  if (urlLower.includes('tiktok.com')) {
    profileForm.value.social_media_type = 'tiktok'
    return
  }
  
  // X (Twitter) detection
  if (urlLower.includes('x.com') || urlLower.includes('twitter.com')) {
    profileForm.value.social_media_type = 'x'
    return
  }
  
  // If URL doesn't match any platform, clear the type
  profileForm.value.social_media_type = null
}

// Social Media Helper Functions
const getSocialMediaIcon = (type: string | null | undefined) => {
  if (!type) return null
  
  const icons: Record<string, { svg: string; color: string }> = {
    'youtube': {
      svg: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 100%; height: 100%; display: block;">
        <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
      </svg>`,
      color: 'text-red-600'
    },
    'instagram': {
      svg: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 100%; height: 100%; display: block;">
        <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>
      </svg>`,
      color: 'text-pink-600'
    },
    'tiktok': {
      svg: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 100%; height: 100%; display: block;">
        <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64 2.93 2.93 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 5 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1-.1z"/>
      </svg>`,
      color: 'text-black'
    },
    'x': {
      svg: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" style="width: 100%; height: 100%; display: block;">
        <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
      </svg>`,
      color: 'text-black'
    }
  }
  
  return icons[type] || null
}

const getSocialMediaPlatformName = (type: string | null | undefined): string => {
  if (!type) return 'رابط التواصل الاجتماعي'
  const names: Record<string, string> = {
    'youtube': 'YouTube',
    'instagram': 'Instagram',
    'tiktok': 'TikTok',
    'x': 'X (Twitter)',
  }
  return names[type] || 'رابط التواصل الاجتماعي'
}

// New status types: 'active', 'inactive_tab', 'private_disabled', 'away', 'incognito'
type UserStatus = 'active' | 'inactive_tab' | 'private_disabled' | 'away' | 'incognito'

const userConnectionStatus = ref<Record<number, UserStatus>>({})
const userLastActivity = ref<Record<number, number>>({})
const userTabVisibility = ref<Record<number, boolean>>({}) // Track if user's tab is visible
// Track users who left rooms - used to detect "moved" vs "joined" actions
// Note: "left" messages are ONLY sent on socket disconnect, not when users leave rooms
const usersLeavingRooms = ref<Record<number, { 
  roomId: number, 
  timestamp: number,
  lastActivity?: number, // Preserve activity timestamp when user leaves
  lastStatus?: UserStatus, // Preserve status when user leaves
  timeoutId: ReturnType<typeof setTimeout>
}>>({})

// Track current user's tab visibility
const isTabVisible = ref(true)

// Load last activity from localStorage if available
const getLastActivityKey = () => {
  return authStore.user?.id ? `user_${authStore.user.id}_last_activity` : null
}

const loadLastActivity = (): number => {
  if (!import.meta.client) return Date.now()
  const key = getLastActivityKey()
  if (!key) return Date.now()
  
  try {
    const saved = localStorage.getItem(key)
    if (saved) {
      const timestamp = parseInt(saved, 10)
      // Only use saved value if it's not too old (more than 1 hour ago, reset to now)
      const oneHour = 60 * 60 * 1000
      if (Date.now() - timestamp < oneHour) {
        return timestamp
      }
    }
  } catch (error) {
    console.warn('Error loading last activity from storage:', error)
  }
  return Date.now()
}

const saveLastActivity = (timestamp: number) => {
  if (!import.meta.client) return
  const key = getLastActivityKey()
  if (!key) return
  
  try {
    localStorage.setItem(key, String(timestamp))
  } catch (error) {
    console.warn('Error saving last activity to storage:', error)
  }
}

const currentUserLastActivity = ref(loadLastActivity())

// Track tab visibility using Page Visibility API
if (import.meta.client) {
  const handleVisibilityChange = () => {
    const wasVisible = isTabVisible.value
    isTabVisible.value = !document.hidden
    
    if (authStore.user?.id) {
      if (document.hidden) {
        // Tab is now hidden - immediately mark user as away (gray)
        // This happens when user switches to another browser tab
        setUserConnectionStatus(authStore.user.id, 'away')
        
        // Update tab visibility for other users via ping/pong
        // Send immediate ping to notify others that tab is hidden
        if (currentPresenceChannel && currentPresenceChannel.subscribed && typeof currentPresenceChannel.whisper === 'function') {
          try {
            currentPresenceChannel.whisper('ping', {
              user_id: authStore.user.id,
              timestamp: Date.now(),
              tab_visible: false,
              last_activity: currentUserLastActivity.value,
            })
          } catch (error) {
            console.warn('Error sending visibility ping:', error)
          }
        }
        
        // Force update the UI by triggering reactivity
        // The status indicator should update immediately
        nextTick(() => {
          // Ensure status is set to away
          if (userConnectionStatus.value[authStore.user.id] !== 'away') {
            setUserConnectionStatus(authStore.user.id, 'away')
          }
        })
      } else {
        // Tab is now visible - but don't update activity (tab visibility is not an interaction)
        // Just notify others that tab is visible via ping/pong
        const now = Date.now()
        
        // Update tab visibility for other users via ping/pong
        // Send immediate ping to notify others that tab is visible (but don't update activity)
        if (currentPresenceChannel && currentPresenceChannel.subscribed && typeof currentPresenceChannel.whisper === 'function') {
          try {
            currentPresenceChannel.whisper('ping', {
              user_id: authStore.user.id,
              timestamp: now,
              tab_visible: true,
              last_activity: currentUserLastActivity.value, // Use existing activity, don't update it
            })
          } catch (error) {
            console.warn('Error sending visibility ping:', error)
          }
        }
        
        // Recalculate status immediately (status will be yellow if no recent interaction)
        const user = authStore.user
        const newStatus = calculateUserStatus(user)
        setUserConnectionStatus(user.id, newStatus)
      }
    }
  }
  
  document.addEventListener('visibilitychange', handleVisibilityChange)
  
  // NOTE: Removed mouse/keyboard/scroll activity tracking
  // Activity should only be updated for actual interactions:
  // - Sending messages
  // - Changing rooms
  // - Reconnecting to socket
  
  // Save status before page unload (when user closes tab/refreshes)
  const handleBeforeUnload = async () => {
    if (authStore.user?.id && pendingStatusUpdate.value) {
      // Save status immediately (synchronous if possible)
      try {
        const { $api } = useNuxtApp()
        // Use sendBeacon for reliable delivery during page unload
        if (navigator.sendBeacon) {
          const blob = new Blob([JSON.stringify({
            status: pendingStatusUpdate.value.status,
            last_activity: pendingStatusUpdate.value.lastActivity,
          })], { type: 'application/json' })
          navigator.sendBeacon(
            `${useRuntimeConfig().public.apiBaseUrl}/user-status`,
            blob
          )
        } else {
          // Fallback: try synchronous fetch (may not complete)
          await ($api as any)('/user-status', {
            method: 'PUT',
            body: {
              status: pendingStatusUpdate.value.status,
              last_activity: pendingStatusUpdate.value.lastActivity,
            },
            keepalive: true, // Keep request alive during page unload
          })
        }
      } catch (error) {
        console.warn('Failed to save status on unload:', error)
      }
    }
  }
  
  window.addEventListener('beforeunload', handleBeforeUnload)
  
  // Cleanup on unmount
  onUnmounted(() => {
    document.removeEventListener('visibilitychange', handleVisibilityChange)
    window.removeEventListener('beforeunload', handleBeforeUnload)
    
    // Save status on component unmount
    if (authStore.user?.id && pendingStatusUpdate.value) {
      handleBeforeUnload()
    }
  })
}

// Debounced status update to backend (prevents too many API calls)
let statusUpdateTimeout: ReturnType<typeof setTimeout> | null = null
const pendingStatusUpdate = ref<{ userId: number; status: UserStatus; lastActivity: number | null } | null>(null)

const setUserConnectionStatus = (userId: number, status: UserStatus) => {
  // Only update local state - status updates happen via socket events only
  // No HTTP PUT requests - all status updates are handled through socket
  userConnectionStatus.value = {
    ...userConnectionStatus.value,
    [userId]: status,
  }
  
  // Status updates are now handled via socket events only
  // The backend receives status updates through presence channel whispers
  // No need for HTTP PUT requests
}

const markUserActiveOnSocket = (userId: number) => {
  if (!userId) return
  
  const isCurrentUser = userId === authStore.user?.id
  
  if (isCurrentUser) {
    // For current user, update currentUserLastActivity
    const now = Date.now()
    currentUserLastActivity.value = now
    // Save to localStorage for persistence
    saveLastActivity(now)
  } else {
    // For other users, update userLastActivity
    userLastActivity.value = {
      ...userLastActivity.value,
      [userId]: Date.now(),
    }
  }
  
  // Don't override special statuses (private_disabled, incognito) - they'll be recalculated
  const currentStatus = userConnectionStatus.value[userId]
  if (currentStatus !== 'private_disabled' && currentStatus !== 'incognito') {
    setUserConnectionStatus(userId, 'active')
  }
}

// Calculate user status based on rules
const calculateUserStatus = (user: any): UserStatus => {
  if (!user?.id) return 'away'
  
  // Check if user has incognito mode permission AND has it enabled
  // User must have the permission AND have enabled it in their settings
  if (hasPermission(user, 'incognito_mode') && user.incognito_mode_enabled === true) {
    return 'incognito'
  }
  
  // Check if user has disabled private messages
  // Check from user object (backend provides it)
  const settingsStore = useSettingsStore()
  const isCurrentUser = user.id === authStore.user?.id
  
  if (isCurrentUser) {
    // For current user, check settings store first, then user object
    if (settingsStore.privateMessagesEnabled === false || user.private_messages_enabled === false) {
      return 'private_disabled'
    }
  } else {
    // For other users, check if backend provides this info
    if (user.private_messages_enabled === false) {
      return 'private_disabled'
    }
  }
  
  // For current user, use currentUserLastActivity and isTabVisible
  // For other users, use userLastActivity and userTabVisibility
  const lastActivity = isCurrentUser 
    ? currentUserLastActivity.value 
    : userLastActivity.value[user.id]
  
  const isTabVisibleForUser = isCurrentUser
    ? isTabVisible.value
    : (userTabVisibility.value[user.id] !== false) // Default to true if not tracked
  
  // IMPORTANT: If tab is not visible (for current user), always return 'away' regardless of activity
  // This ensures that when user switches to another tab, they're immediately marked as away
  if (isCurrentUser && !isTabVisibleForUser) {
    return 'away'
  }
  
  const now = Date.now()
  const oneMinute = 60 * 1000
  
  // If no activity tracked, assume away
  if (!lastActivity) {
    return 'away'
  }
  
  const timeSinceActivity = now - lastActivity

  
  // If tab is visible and user was active within 1 minute: active (green)
  if (isTabVisibleForUser && timeSinceActivity < oneMinute) {
    return 'active'
  }
  
  // If tab is visible but inactive for 1+ minute: inactive_tab (yellow)
  if (isTabVisibleForUser && timeSinceActivity >= oneMinute) {
    return 'inactive_tab'
  }
  
  // If tab is not visible and inactive for 1+ minute: away (gray)
  if (!isTabVisibleForUser && timeSinceActivity >= oneMinute) {
    return 'away'
  }
  
  // Default to active if tab is visible
  return isTabVisibleForUser ? 'active' : 'away'
}

const getUserConnectionStatusClass = (user: any) => {
  if (!user?.id) return 'bg-gray-300'

  // For current user, if tab is hidden, always show away (gray) regardless of stored status
  const isCurrentUser = user.id === authStore.user?.id
  if (isCurrentUser && !isTabVisible.value) {
    return 'bg-gray-400' // away (gray)
  }

  const status = userConnectionStatus.value[user.id] || calculateUserStatus(user)

  switch (status) {
    case 'active':
      return 'bg-green-500'
    case 'inactive_tab':
      return 'bg-yellow-400'
    case 'private_disabled':
      return 'bg-red-500'
    case 'away':
      return 'bg-gray-400'
    case 'incognito':
      return 'bg-blue-500'
    default:
      return 'bg-gray-300'
  }
}

// Status check interval (1 minute)
let statusCheckInterval: ReturnType<typeof setInterval> | null = null

// Update status for all users globally (not room-specific)
const updateAllUsersStatus = () => {
  
  // Use activeUsers from chatStore (global, not room-specific)
  const allUsers = chatStore.activeUsers || []
  
  if (allUsers.length === 0) {
    return
  }
  
  
  allUsers.forEach((user: any) => {
    if (user?.id) {
      const newStatus = calculateUserStatus(user)
      const oldStatus = userConnectionStatus.value[user.id]
      setUserConnectionStatus(user.id, newStatus)
      
    }
  })
  
}

// Ping/Pong mechanism for global presence channel (cross-room status updates)
const setupGlobalPresencePingPong = (channel: any) => {
  if (!channel || typeof channel.listenForWhisper !== 'function') {
    return
  }

  // Listen for pong responses from other users on global channel
  channel.listenForWhisper('pong', (data: any) => {
    if (data && data.user_id && typeof data.user_id === 'number') {
      // Update user activity - use timestamp from data if provided, otherwise use current time
      const activityTime = data.last_activity || data.timestamp || Date.now()
      userLastActivity.value = {
        ...userLastActivity.value,
        [data.user_id]: activityTime,
      }
      
      // Update tab visibility if provided
      if (typeof data.tab_visible === 'boolean') {
        userTabVisibility.value = {
          ...userTabVisibility.value,
          [data.user_id]: data.tab_visible,
        }
      }
      
      // Recalculate status immediately (works globally, not room-specific)
      // Find user in activeUsers (global list)
      const user = chatStore.activeUsers.find((u: any) => u.id === data.user_id)
      if (user) {
        const newStatus = calculateUserStatus(user)
        setUserConnectionStatus(data.user_id, newStatus)
      }
    }
  })

  // Send ping on global channel every 30 seconds (separate from room channel ping)
  // This ensures status updates work across all rooms
  setInterval(() => {
    if (!globalPresenceChannel || !authStore.user?.id) {
      return
    }
    
    try {
      if (globalPresenceChannel.subscribed && typeof globalPresenceChannel.whisper === 'function') {
        globalPresenceChannel.whisper('ping', {
          user_id: authStore.user.id,
          timestamp: Date.now(),
          tab_visible: isTabVisible.value,
          last_activity: currentUserLastActivity.value,
        })
      }
    } catch (error: any) {
      console.warn('⚠️ [GLOBAL PING] Error sending ping on global channel:', error?.message || error)
    }
  }, 30000) // Ping every 30 seconds

  // Listen for ping events on global channel and respond with pong
  channel.listenForWhisper('ping', (data: any) => {
    if (data && data.user_id && typeof data.user_id === 'number' && authStore.user?.id) {
      // Update the ping sender's tab visibility if provided
      if (typeof data.tab_visible === 'boolean') {
        userTabVisibility.value = {
          ...userTabVisibility.value,
          [data.user_id]: data.tab_visible,
        }
      }
      
      // Respond with pong to indicate we're active (include our tab visibility)
      try {
        if (channel.subscribed && typeof channel.whisper === 'function') {
          channel.whisper('pong', {
            user_id: authStore.user.id,
            timestamp: Date.now(),
            tab_visible: isTabVisible.value,
            last_activity: currentUserLastActivity.value,
          })
        }
      } catch (error) {
        console.error('Error sending global pong:', error)
      }
    }
  })
}

// Ping/Pong mechanism for real-time activity tracking (room-specific)
const startPingPong = (channel: any) => {
  // Store the channel reference
  currentPresenceChannel = channel
  
  // Clear any existing ping interval
  if (pingInterval) {
    clearInterval(pingInterval)
    pingInterval = null
  }
  
  // Clear any existing status check interval
  if (statusCheckInterval) {
    clearInterval(statusCheckInterval)
    statusCheckInterval = null
  }

  // Listen for pong responses from other users
  if (channel && typeof channel.listenForWhisper === 'function') {
    channel.listenForWhisper('pong', (data: any) => {
      if (data && data.user_id && typeof data.user_id === 'number') {
        // Update user activity - use timestamp from data if provided, otherwise use current time
        const activityTime = data.last_activity || data.timestamp || Date.now()
        userLastActivity.value = {
          ...userLastActivity.value,
          [data.user_id]: activityTime,
        }
        
        // Update tab visibility if provided
        if (typeof data.tab_visible === 'boolean') {
          userTabVisibility.value = {
            ...userTabVisibility.value,
            [data.user_id]: data.tab_visible,
          }
        }
        
        // Recalculate status immediately (using global activeUsers, not room-specific)
        const user = chatStore.activeUsers.find((u: any) => u.id === data.user_id)
        if (user) {
          const newStatus = calculateUserStatus(user)
          setUserConnectionStatus(data.user_id, newStatus)
        }
      }
    })
    
  }

  // Send ping every 30 seconds to check user activity
  pingInterval = setInterval(() => {
    // Use the stored channel reference instead of the parameter
    const activeChannel = currentPresenceChannel
    
    if (!activeChannel || !authStore.user?.id) {
      return
    }
    
    try {
      // Check if channel is still valid and subscribed
      if (!activeChannel.subscribed) {
        console.warn('⚠️ [PING] Channel not subscribed, skipping ping')
        return
      }
      
      if (typeof activeChannel.whisper !== 'function') {
        console.warn('⚠️ [PING] Channel whisper function not available, skipping ping')
        return
      }
      
      // Check internal Pusher channel state to ensure it's valid
      // @ts-ignore - accessing internal pusher properties
      const pusherChannel = activeChannel.pusher || activeChannel._pusher || activeChannel.channel
      if (pusherChannel) {
        // @ts-ignore
        const channelName = pusherChannel.name || activeChannel.name
        // @ts-ignore
        const pusherInstance = pusherChannel.pusher || pusherChannel._pusher
        
        // Check if the channel exists in Pusher's channels registry
        if (pusherInstance?.channels?.channels) {
          // @ts-ignore
          if (!pusherInstance.channels.channels[channelName]) {
            console.warn('⚠️ [PING] Channel not found in Pusher channels registry, stopping ping interval')
            if (pingInterval) {
              clearInterval(pingInterval)
              pingInterval = null
            }
            currentPresenceChannel = null
            return
          }
        }
      }
      
      // Send ping via whisper to all members in the presence channel
      // Note: We don't mark current user as active here - only send ping data
      // Activity should only be updated from actual user interactions
      activeChannel.whisper('ping', {
        user_id: authStore.user.id,
        timestamp: Date.now(),
        tab_visible: isTabVisible.value,
        last_activity: currentUserLastActivity.value,
      })
    } catch (error: any) {
      // Channel might be disconnected or invalid
      const errorMsg = error?.message || String(error)
      console.warn('⚠️ [PING] Error sending ping:', errorMsg)
      
      // If channel is invalid (undefined trigger, channels, pusher errors), stop ping interval
      if (errorMsg.includes('undefined') || 
          errorMsg.includes('trigger') || 
          errorMsg.includes('channels') ||
          errorMsg.includes('pusher') ||
          errorMsg.includes('Cannot read')) {
        console.warn('⚠️ [PING] Channel appears disconnected, stopping ping interval')
        if (pingInterval) {
          clearInterval(pingInterval)
          pingInterval = null
        }
        // Clear the channel reference
        currentPresenceChannel = null
      }
    }
  }, 30000) // Ping every 30 seconds
  
  // Status check every 1 minute (60000ms)
  // Status updates now come ONLY via socket events (user.status.updated)
  // Removed periodic PUT requests - status is updated via socket only
  // statusCheckInterval removed - no more PUT requests every minute
}

// Stop ping/pong when leaving channel
const stopPingPong = () => {
  if (pingInterval) {
    clearInterval(pingInterval)
    pingInterval = null
  }
  if (statusCheckInterval) {
    clearInterval(statusCheckInterval)
    statusCheckInterval = null
  }
  currentPresenceChannel = null
}

const convertYouTubeToEmbed = (url: string): string => {
  // Handle various YouTube URL formats
  let videoId = null
  
  // Format: https://www.youtube.com/watch?v=VIDEO_ID
  const watchMatch = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\n?#]+)/)
  if (watchMatch) {
    videoId = watchMatch[1]
  }
  
  // Format: https://www.youtube.com/embed/VIDEO_ID
  const embedMatch = url.match(/youtube\.com\/embed\/([^&\n?#]+)/)
  if (embedMatch) {
    videoId = embedMatch[1]
  }
  
  // Format: https://youtube.com/@channelname (channel page, can't embed)
  if (url.includes('youtube.com/@') || url.includes('youtube.com/channel/') || url.includes('youtube.com/c/')) {
    // For channel pages, return the original URL (can't embed channels)
    return url
  }
  
  if (videoId) {
    return `https://www.youtube.com/embed/${videoId}`
  }
  
  // If we can't extract video ID, return original URL
  return url
}

const getEmbedUrl = (url: string | null, type: string | null | undefined): string => {
  if (!url) return ''
  
  // Convert YouTube URLs to embed format
  if (type === 'youtube') {
    return convertYouTubeToEmbed(url)
  }
  
  // For other platforms, return original URL
  return url
}

const openSocialMediaPopup = (user: User) => {
  if (user.social_media_url && user.social_media_type) {
    socialMediaUrl.value = user.social_media_url
    socialMediaType.value = user.social_media_type
    showSocialMediaPopup.value = true
  }
}

const changeProfilePicture = async () => {
  const input = document.createElement('input')
  input.type = 'file'
  input.accept = 'image/*'
  input.onchange = async (e: any) => {
    const file = e.target.files[0]
    if (file) {
      try {
        const formData = new FormData()
        formData.append('avatar', file)
        
        const { $api } = useNuxtApp()
        const config = useRuntimeConfig()
        const baseUrl = config.public.apiBaseUrl
        const url = `${baseUrl}/profile/avatar`
        
        const headers: HeadersInit = {
          'Accept': 'application/json',
        }
        
        // Add auth token
        if (authStore.token) {
          headers['Authorization'] = `Bearer ${authStore.token}`
        }
        
        const response = await fetch(url, {
          method: 'POST',
          headers,
          body: formData,
        })
        
        if (!response.ok) {
          const error = await response.json().catch(() => ({ message: 'فشل رفع الصورة' }))
          throw new Error(error.message || 'فشل رفع الصورة')
        }
        
        const data = await response.json()
        
        // Update user in auth store
        if (authStore.user && data.user) {
          authStore.user.avatar_url = data.user.avatar_url
          authStore.user.name = data.user.name
          authStore.user.bio = data.user.bio
          // Update localStorage
          if (import.meta.client) {
            localStorage.setItem('auth_user', JSON.stringify(authStore.user))
          }
        }
      } catch (error: any) {
        console.error('Error uploading profile picture:', error)
        alert(error.message || 'فشل رفع الصورة')
      }
    }
  }
  input.click()
}

const deleteProfilePicture = async () => {
  if (!authStore.user) return
  
  try {
    const nuxtApp = useNuxtApp()
    const updatedUser = await (nuxtApp.$api as any)('/profile/avatar', { method: 'DELETE' })
    
    // Update user in auth store
    if (authStore.user && updatedUser) {
      authStore.user.avatar_url = updatedUser.avatar_url || null
      // Update localStorage
      if (import.meta.client) {
        localStorage.setItem('auth_user', JSON.stringify(authStore.user))
      }
    }
  } catch (error: any) {
    console.error('Error deleting profile picture:', error)
    alert('فشل حذف الصورة')
  }
}

const saveSettings = async () => {
  if (savingSettings.value) return // Prevent double submission
  
  // Check rate limit
  const { checkRateLimit } = useRateLimit()
  if (!checkRateLimit('save_settings')) {
    return
  }
  
  savingSettings.value = true
  try {
    // Save profile changes (name, bio) and color settings together in one API call
    if (authStore.user) {
      const nuxtApp = useNuxtApp()
      const updatedUser = await (nuxtApp.$api as any)('/profile', {
        method: 'PUT',
        body: {
          name: profileForm.value.name,
          bio: profileForm.value.bio,
          social_media_type: profileForm.value.social_media_type || null,
          social_media_url: profileForm.value.social_media_url || null,
          room_font_size: settingsStore.roomFontSize,
          name_color: settingsStore.nameColor,
          message_color: settingsStore.messageColor,
          name_bg_color: settingsStore.nameBgColor === 'transparent' ? null : settingsStore.nameBgColor,
          image_border_color: settingsStore.imageBorderColor,
          bio_color: settingsStore.bioColor,
        },
      })
      
      // Update user in auth store
      if (updatedUser) {
        authStore.user.name = updatedUser.name
        authStore.user.bio = updatedUser.bio
        authStore.user.avatar_url = updatedUser.avatar_url
        
        // Update localStorage
        if (import.meta.client) {
          localStorage.setItem('auth_user', JSON.stringify(authStore.user))
        }
      }
    }
    
    showSettings.value = false
  } catch (error: any) {
    console.error('Error saving settings:', error)
    alert('فشل حفظ الإعدادات')
  } finally {
    savingSettings.value = false
  }
}

const handleLogout = async () => {
  // Save status before logout
  if (authStore.user?.id) {
    const currentStatus = userConnectionStatus.value[authStore.user.id] || 'away'
    try {
      const { $api } = useNuxtApp()
      await ($api as any)('/user-status', {
        method: 'PUT',
        body: {
          status: currentStatus,
          last_activity: currentUserLastActivity.value,
        },
      })
    } catch (error) {
      console.warn('Failed to save status on logout:', error)
    }
  }
  
  // Send "left" system message and leave event before logging out
  if (authStore.user && roomId.value && currentChannel) {
    const user = authStore.user
    
    const leftMessage = {
      id: `system-left-logout-${Date.now()}-${Math.random()}`,
      room_id: Number(roomId.value),
      user_id: user.id,
      user: {
        id: user.id,
        name: user.name,
        username: user.username,
        avatar_url: user.avatar_url || getSystemMessagesImage(),
        name_color: user.name_color || { r: 69, g: 9, b: 36 },
        message_color: user.message_color || { r: 69, g: 9, b: 36 },
        image_border_color: user.image_border_color || { r: 69, g: 9, b: 36 },
        name_bg_color: user.name_bg_color || 'transparent',
      },
      content: '(هذا المستخدم غادر الغرفة)',
      meta: {
        is_system: true,
        action: 'left',
      },
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    }
    
    try {
      // Send via whisper to ALL other users in the room (whisper sends to all members except sender)
      currentChannel.whisper('user.left', leftMessage)
      
      // Add a small delay to ensure whisper is sent before leaving channel
      await new Promise(resolve => setTimeout(resolve, 200))
    } catch (error) {
      console.error('Failed to send "left" message via socket on logout:', error)
    }
  }
  
  await authStore.logout()
  router.push('/')
}

const toggleSocketConnection = async () => {
  // Check rate limit
  const { checkRateLimit } = useRateLimit()
  if (!checkRateLimit('toggle_socket')) {
    return
  }
  
  const echo = getEcho()
  
  if (chatStore.connected) {
    // Manual disconnect
    isManualDisconnect.value = true
    
    // Send disconnect system message immediately (without listening to socket)
    if (roomId.value) {
      const addConnectionStateMessage = (state: 'connected' | 'disconnected' | 'error' | 'reconnecting', previousState?: string) => {
        if (!roomId.value) return
        
        const stateConfig = {
          connected: {
            content: 'تم الاتصال بالخادم',
            bgColor: '#d4edda',
            textColor: '#155724',
          },
          disconnected: {
            content: 'تم قطع الاتصال بالخادم',
            bgColor: '#f8d7da',
            textColor: '#721c24',
          },
          error: {
            content: 'حدث خطأ في الاتصال',
            bgColor: '#fff3cd',
            textColor: '#856404',
          },
          reconnecting: {
            content: 'جاري إعادة الاتصال...',
            bgColor: '#d1ecf1',
            textColor: '#0c5460',
          },
        }
        
        const config = stateConfig[state]
        if (!config) return
        
        const connectionMessage = {
          id: `connection-${state}-${Date.now()}-${Math.random()}`,
          room_id: Number(roomId.value),
          user_id: 0,
          user: {
            id: 0,
            name: 'النظام',
            username: 'system',
            avatar_url: getSystemMessagesImage() || null,
            name_color: { r: 100, g: 100, b: 100 },
            message_color: { r: 100, g: 100, b: 100 },
            image_border_color: { r: 100, g: 100, b: 100 },
            name_bg_color: 'transparent',
          },
          content: config.content,
          meta: {
            is_system: true,
            is_connection_state: true,
            connection_state: state,
            previous_state: previousState,
          },
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString(),
        }
        
        // Check if similar message already exists (within last 5 seconds)
        const existingMessage = chatStore.messages.find((m: Message) => 
          m.meta?.is_connection_state && 
          m.meta?.connection_state === state &&
          m.room_id === Number(roomId.value) &&
          Math.abs(new Date(m.created_at).getTime() - Date.now()) < 5000
        )
        
        if (!existingMessage) {
          chatStore.addMessage(connectionMessage)
          nextTick(() => {
            scrollToBottom()
          })
        }
      }
      
      addConnectionStateMessage('disconnected')
    }
    
    // Mark as disconnected
    wasDisconnected.value = true
    
    // Send "left" system message and leave event before disconnecting
    if (authStore.user && roomId.value && currentChannel) {
      const user = authStore.user
      
      const leftMessage = {
        id: `system-left-${Date.now()}-${Math.random()}`,
        room_id: Number(roomId.value),
        user_id: user.id,
        user: {
          id: user.id,
          name: user.name,
          username: user.username,
          avatar_url: user.avatar_url || getSystemMessagesImage(),
          name_color: user.name_color || { r: 69, g: 9, b: 36 },
          message_color: user.message_color || { r: 69, g: 9, b: 36 },
          image_border_color: user.image_border_color || { r: 69, g: 9, b: 36 },
          name_bg_color: user.name_bg_color || 'transparent',
        },
        content: '(هذا المستخدم غادر الغرفة)',
        meta: {
          is_system: true,
          action: 'left',
        },
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      }
      
      try {
        // Send via whisper to ALL other users in the room (whisper sends to all members except sender)
        currentChannel.whisper('user.left', leftMessage)
        
        // Add a small delay to ensure whisper is sent before leaving channel
        await new Promise(resolve => setTimeout(resolve, 100))
        
        // Also add it locally for the current user
        chatStore.addMessage(leftMessage)
        nextTick(() => {
          scrollToBottom()
        })
      } catch (error) {
        console.error('Failed to send "left" message via socket:', error)
        // Still add locally even if whisper fails
        chatStore.addMessage(leftMessage)
      }
    }
    
    // Disconnect
    if (echo) {
      // Leave channels after sending the whisper (with delay to ensure delivery)
      if (currentChannel) {
        stopPingPong() // Stop ping/pong when disconnecting
        // Small delay to ensure whisper is delivered before leaving
        await new Promise(resolve => setTimeout(resolve, 200))
        echo.leave(`room.${roomId.value}`)
        currentChannel = null
      }
      if (userPrivateChannel) {
        echo.leave(`user.${authStore.user?.id}`)
        userPrivateChannel = null
      }
      isSubscribed = false
      isUserChannelSubscribed = false
      
      disconnect()
      chatStore.setConnected(false)
      toast.add({
        severity: 'warn',
        summary: 'تم قطع الاتصال',
        detail: 'تم قطع الاتصال بالخادم',
        life: 3000,
      })
    }
  } else {
    // Manual connect - reset manual disconnect flag
    isManualDisconnect.value = false
    // Connect
    if (authStore.isAuthenticated && authStore.token) {
      initEcho()
      const newEcho = getEcho()
      if (newEcho) {
        try {
          // @ts-ignore - accessing internal connector property
          const pusher = newEcho.connector?.pusher || newEcho.pusher
          if (pusher && pusher.connection) {
            // Set up connection state tracking
            const updateConnectionState = () => {
              chatStore.setConnected(pusher.connection.state === 'connected')
            }
            
            const handleConnected = () => {
              chatStore.setConnected(true)
              
              // Mark current user as active when socket connects
              if (authStore.user?.id) {
                markUserActiveOnSocket(authStore.user.id)
              }
              
              toast.add({
                severity: 'success',
                summary: 'تم الاتصال',
                detail: 'تم الاتصال بالخادم بنجاح',
                life: 3000,
              })
              
              // Rejoin room channel after connection
              if (!currentChannel && roomId.value) {
                currentChannel = newEcho.join(`room.${roomId.value}`)
                setupChannelListeners(currentChannel, roomId.value)
                isSubscribed = true
              }
              
              // Rejoin user private channel
              if (!userPrivateChannel && authStore.user) {
                userPrivateChannel = newEcho.private(`user.${authStore.user.id}`)
                userPrivateChannel.subscribed(() => {
                  isUserChannelSubscribed = true
                })
              }
            }
            
            pusher.connection.bind('connected', handleConnected)
            
            pusher.connection.bind('disconnected', () => {
              chatStore.setConnected(false)
            })
            
            pusher.connection.bind('state_change', (states: any) => {
              // Prevent auto-reconnect if manually disconnected
              if (isManualDisconnect.value && states.current === 'connecting') {
                // Disconnect again to prevent auto-reconnect
                disconnect()
                return
              }
              
              chatStore.setConnected(states.current === 'connected')
              
              // Send reconnecting message when state changes to connecting (if not manual)
              if (!isManualDisconnect.value && states.current === 'connecting' && states.previous !== 'connecting') {
                if (roomId.value) {
                  const addConnectionStateMessage = (state: 'connected' | 'disconnected' | 'error' | 'reconnecting', previousState?: string) => {
                    if (!roomId.value) return
                    
                    const stateConfig = {
                      connected: {
                        content: 'تم الاتصال بالخادم',
                        bgColor: '#d4edda',
                        textColor: '#155724',
                      },
                      disconnected: {
                        content: 'تم قطع الاتصال بالخادم',
                        bgColor: '#f8d7da',
                        textColor: '#721c24',
                      },
                      error: {
                        content: 'حدث خطأ في الاتصال',
                        bgColor: '#fff3cd',
                        textColor: '#856404',
                      },
                      reconnecting: {
                        content: 'جاري إعادة الاتصال...',
                        bgColor: '#d1ecf1',
                        textColor: '#0c5460',
                      },
                    }
                    
                    const config = stateConfig[state]
                    if (!config) return
                    
                    const connectionMessage = {
                      id: `connection-${state}-${Date.now()}-${Math.random()}`,
                      room_id: Number(roomId.value),
                      user_id: 0,
                      user: {
                        id: 0,
                        name: 'النظام',
                        username: 'system',
                        avatar_url: getSystemMessagesImage() || null,
                        name_color: { r: 100, g: 100, b: 100 },
                        message_color: { r: 100, g: 100, b: 100 },
                        image_border_color: { r: 100, g: 100, b: 100 },
                        name_bg_color: 'transparent',
                      },
                      content: config.content,
                      meta: {
                        is_system: true,
                        is_connection_state: true,
                        connection_state: state,
                        previous_state: previousState,
                      },
                      created_at: new Date().toISOString(),
                      updated_at: new Date().toISOString(),
                    }
                    
                    // Check if similar message already exists (within last 5 seconds)
                    const existingMessage = chatStore.messages.find((m: Message) => 
                      m.meta?.is_connection_state && 
                      m.meta?.connection_state === state &&
                      m.room_id === Number(roomId.value) &&
                      Math.abs(new Date(m.created_at).getTime() - Date.now()) < 5000
                    )
                    
                    if (!existingMessage) {
                      chatStore.addMessage(connectionMessage)
                      nextTick(() => {
                        scrollToBottom()
                      })
                    }
                  }
                  
                  addConnectionStateMessage('reconnecting', states.previous)
                }
              }
            })
            
            updateConnectionState()
            
            // Try to connect if not already connected
            if (pusher.connection.state !== 'connected' && pusher.connection.state !== 'connecting') {
              pusher.connect()
            } else if (pusher.connection.state === 'connected') {
              // Already connected, trigger rejoin
              handleConnected()
            }
          }
        } catch (error) {
          console.error('Error connecting socket:', error)
          toast.add({
            severity: 'error',
            summary: 'خطأ في الاتصال',
            detail: 'فشل الاتصال بالخادم',
            life: 3000,
          })
        }
      }
    } else {
      // User is not authenticated - silently fail
      // Don't show toast as this page should be protected by auth middleware
      // If user reaches here without auth, they'll be redirected by middleware
      console.warn('Socket connection attempted without authentication')
    }
  }
}

const leaveRoom = async () => {
  if (!roomId.value || !authStore.user?.id) return
  
  try {
    const echo = getEcho()
    
    // Leave the room channel (but keep other channels like private messages)
    if (currentChannel && echo) {
      // Send "left" system message before leaving
      if (authStore.user && roomId.value && currentChannel) {
        const user = authStore.user
        
        const leftMessage = {
          id: `system-left-${Date.now()}-${Math.random()}`,
          room_id: Number(roomId.value),
          user_id: user.id,
          user: {
            id: user.id,
            name: user.name,
            username: user.username,
            avatar_url: user.avatar_url || getSystemMessagesImage(),
            name_color: user.name_color || { r: 69, g: 9, b: 36 },
            message_color: user.message_color || { r: 69, g: 9, b: 36 },
            image_border_color: user.image_border_color || { r: 69, g: 9, b: 36 },
            name_bg_color: user.name_bg_color || 'transparent',
          },
          content: '(هذا المستخدم غادر الغرفة)',
          meta: {
            is_system: true,
            action: 'left',
          },
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString(),
        }
        
        try {
          // Send via whisper to ALL other users in the room
          currentChannel.whisper('user.left', leftMessage)
          await new Promise(resolve => setTimeout(resolve, 100))
        } catch (error) {
          console.error('Failed to send "left" message via socket:', error)
        }
      }
      
      // Leave the room channel
      echo.leave(`room.${roomId.value}`)
      currentChannel = null
      isSubscribed = false
    }
    
    // Remove user from room via API
    const { $api } = useNuxtApp()
    await ($api as any)(`/chat/${roomId.value}/users/${authStore.user.id}`, { method: 'DELETE' })
    
    // Clear current room from store (but keep user on page)
    chatStore.setCurrentRoom(null)
    
    // Clear messages for this room
    const roomMessages = chatStore.messages.filter((m: Message) => String(m.room_id) === String(roomId.value))
    roomMessages.forEach((m: Message) => {
      const index = chatStore.messages.findIndex((msg: Message) => msg.id === m.id)
      if (index !== -1) {
        chatStore.messages.splice(index, 1)
      }
    })
    
    // Show success message
    toast.add({
      severity: 'success',
      summary: 'تم المغادرة',
      detail: 'تم مغادرة الغرفة بنجاح. يمكنك الآن الوصول إلى الرسائل الخاصة وقائمة الغرف.',
      life: 3000,
    })
  } catch (error: any) {
    console.error('Error leaving room:', error)
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error?.data?.message || error?.message || 'فشل مغادرة الغرفة',
      life: 3000,
    })
  }
}

const navigateToRoom = async (id: number) => {
  showRoomsList.value = false
  
  // If already in this room, don't do anything
  if (String(roomId.value) === String(id)) {
    return
  }
  
  // Check rate limit
  const { checkRateLimit } = useRateLimit()
  if (!checkRateLimit('click_room')) {
    return
  }
  
  // Don't navigate if password dialog is currently open
  if (showPasswordDialog.value) {
    return
  }
  
  // Check if room has password and user is not a member
  const targetRoom = chatStore.displayRooms.find((r: Room) => r.id === id)
  if (targetRoom?.password && authStore.user) {
    // Check if user is already a member
    const isMember = targetRoom.users?.some((u: User) => u.id === authStore.user?.id)
    
    if (!isMember) {
      // Show password dialog first before navigating
      passwordProtectedRoomId.value = id
      passwordProtectedRoomName.value = targetRoom.name || 'الغرفة'
      showPasswordDialog.value = true
      isPasswordValidated.value = false // Reset validation flag
      // Don't navigate yet - wait for password validation
      return
    }
  }
  
  // Store previous room ID before switching (for "moved" action)
  const oldRoomId = roomId.value
  previousRoomId = oldRoomId
  
  // Send "moved" message to old room via socket BEFORE navigating
  if (authStore.user && oldRoomId && currentChannel && String(oldRoomId) !== String(id)) {
    const user = authStore.user
    const newRoomName = targetRoom?.name || `الغرفة ${id}`
    
    const movedMessage = {
      id: `system-moved-${Date.now()}-${Math.random()}`,
      room_id: Number(oldRoomId),
      user_id: user.id,
      user: {
        id: user.id,
        name: user.name,
        username: user.username,
        avatar_url: user.avatar_url || getSystemMessagesImage(),
        name_color: user.name_color || { r: 69, g: 9, b: 36 },
        message_color: user.message_color || { r: 69, g: 9, b: 36 },
        image_border_color: user.image_border_color || { r: 69, g: 9, b: 36 },
        name_bg_color: user.name_bg_color || 'transparent',
      },
      content: `${user.name || user.username} انتقل إلى`,
      meta: {
        is_system: true,
        action: 'moved',
        room_id: Number(id),
        room_name: newRoomName,
        previous_room_id: Number(oldRoomId),
      },
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    }
    
    try {
      // Send via whisper to OTHER users in the old room (not to the moving user)
      currentChannel.whisper('user.moved', movedMessage)
      // Note: We don't add it locally - the moving user will see the normal message in the new room
    } catch (error) {
      console.error('Failed to send "moved" message via socket:', error)
    }
  }
  
  if (authStore.user && roomId.value) {
    rememberUserLeftRoom(authStore.user.id, Number(roomId.value))
  }
  
  // Reset password validation flag for new room
  isPasswordValidated.value = false
  
  // Update room ID - this will trigger the watch on roomId to handle channel switching
  chatStore.setCurrentRoomId(id)
}

// Password dialog handlers
const submitRoomPassword = async () => {
  if (!roomPasswordInput.value.trim()) {
    passwordError.value = 'كلمة المرور غير صحيحة'
    return
  }
  
  if (!passwordProtectedRoomId.value) {
    return
  }
  
  submittingPassword.value = true
  passwordError.value = ''
  
  try {
    // Validate password first by trying to fetch the room
    await chatStore.fetchRoom(passwordProtectedRoomId.value, roomPasswordInput.value)
    
    // Password is correct - mark as validated before proceeding
    isPasswordValidated.value = true
    
    // Password is correct - now navigate to the room
    const targetRoomId = passwordProtectedRoomId.value
    
    // Store previous room ID before switching (if not already set)
    if (!previousRoomId && roomId.value) {
      previousRoomId = roomId.value
      if (authStore.user && roomId.value) {
        rememberUserLeftRoom(authStore.user.id, Number(roomId.value))
      }
    }
    
    // Close dialog and clear password BEFORE navigation
    showPasswordDialog.value = false
    roomPasswordInput.value = ''
    passwordError.value = ''
    const targetId = passwordProtectedRoomId.value
    passwordProtectedRoomId.value = null
    
    // Set room ID after password validation
    // The watch handler will now proceed since password dialog is closed and validated
    chatStore.setCurrentRoomId(targetId)
    
    // Room setup will happen in the watch handler after navigation
  } catch (error: any) {
    // Show password incorrect error and stay on current room
    passwordError.value = 'كلمة المرور غير صحيحة'
    roomPasswordInput.value = ''
    isPasswordValidated.value = false // Keep validation flag false on error
    // Don't redirect - stay on current room, dialog stays open
  } finally {
    submittingPassword.value = false
  }
}

const cancelPasswordDialog = () => {
  showPasswordDialog.value = false
  roomPasswordInput.value = ''
  passwordError.value = ''
  passwordProtectedRoomId.value = null
  isPasswordValidated.value = false // Reset validation flag on cancel
  
  // Stay on current room - set back to previous room if available
  if (previousRoomId) {
    chatStore.setCurrentRoomId(Number(previousRoomId))
  }
  // If no previous room, just close dialog and stay where we are
}

// Extract channel setup logic to a reusable function
const setupChannelListeners = (channel: any, currentRoomId: string) => {
  // Listen for channel subscription events
  // Function to programmatically send system message and premium entry for current user
  const sendCurrentUserNotifications = () => {
    if (!authStore.user || !chatStore.currentRoom) {
      return
    }
    
    const user = authStore.user
    const currentRoom = chatStore.currentRoom
    
    
    // Show premium entry notification if enabled
    if (user.premium_entry) {
      const userData = {
        id: user.id,
        name: user.name,
        username: user.username,
        avatar_url: user.avatar_url,
        premium_entry: user.premium_entry,
        premium_entry_background: user.premium_entry_background || null,
      }
      showPremiumEntryNotification(userData)
    }
    
    // Create system message for current user
    // Determine action type: if previousRoomId is set, user moved from another room
    // Otherwise, it's a fresh join
    const action = previousRoomId ? 'moved' : 'joined'
    const previousRoomIdForMessage = previousRoomId ? Number(previousRoomId) : null
    
    let content = ''
    if (action === 'moved') {
      content = `هذا المستخدم انتقل إلى الغرفة`
    } else {
      content = 'هذا المستخدم انضم إلى'
    }
    
    const systemMessage = {
      id: `system-${Date.now()}-${Math.random()}`,
      room_id: Number(currentRoomId),
      user_id: user.id,
      user: {
        id: user.id,
        name: user.name,
        username: user.username,
        avatar_url: user.avatar_url || getSystemMessagesImage(),
        name_color: user.name_color || { r: 69, g: 9, b: 36 },
        message_color: user.message_color || { r: 69, g: 9, b: 36 },
        image_border_color: user.image_border_color || { r: 69, g: 9, b: 36 },
        name_bg_color: user.name_bg_color || 'transparent',
      },
      content: content,
      meta: {
        is_system: true,
        action: action,
        room_id: Number(currentRoomId),
        room_name: currentRoom?.name || `الغرفة ${currentRoomId}`,
        previous_room_id: previousRoomIdForMessage,
      },
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    }
    
    // Check for duplicate messages
    const existingMessage = chatStore.messages.find((m: Message) => 
      m.meta?.is_system && 
      m.user_id === user.id && 
      m.meta?.action === action &&
      m.room_id === Number(currentRoomId) &&
      new Date(m.created_at).getTime() > Date.now() - 5000
    )
    
    if (!existingMessage) {
      chatStore.addMessage(systemMessage)
      nextTick(() => {
        scrollToBottom()
      })
    } 
  }

  channel.subscribed(async () => {
    
    // Store channel reference for ping/pong
    currentPresenceChannel = channel
    
    // Start ping/pong mechanism for activity tracking
    startPingPong(channel)
    
    // If user reconnected after being disconnected, show "joined" message
    if (wasDisconnected.value && authStore.user && String(currentRoomId) === String(roomId.value)) {
      // Reset the flag
      wasDisconnected.value = false
      
      // No delay - channel is ready when subscribed callback fires
      
      const user = authStore.user
      const joinedMessage = {
        id: `system-joined-reconnect-${Date.now()}-${Math.random()}`,
        room_id: Number(currentRoomId),
        user_id: user.id,
        user: {
          id: user.id,
          name: user.name,
          username: user.username,
          avatar_url: user.avatar_url || getSystemMessagesImage(),
          name_color: user.name_color || { r: 69, g: 9, b: 36 },
          message_color: user.message_color || { r: 69, g: 9, b: 36 },
          image_border_color: user.image_border_color || { r: 69, g: 9, b: 36 },
          name_bg_color: user.name_bg_color || 'transparent',
        },
        content: `هذا المستخدم انضم إلى`,
        meta: {
          room_name: chatStore.currentRoom?.name || `الغرفة ${currentRoomId}`,
          is_system: true,
          action: 'joined',
        },
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      }
      
      // Add the message locally for the reconnecting user
      const existingMessage = chatStore.messages.find((m: Message) => 
        m.meta?.is_system && 
        m.user_id === user.id && 
        m.meta?.action === 'joined' &&
        m.room_id === Number(currentRoomId) &&
        new Date(m.created_at).getTime() > Date.now() - 5000
      )
      
      if (!existingMessage) {
        chatStore.addMessage(joinedMessage)
        nextTick(() => {
          scrollToBottom()
        })
      }
    }
  })
  
  // Use presence channel events for join/leave notifications
  channel.here((users: any[]) => {
    // Update room users immediately (non-blocking)
    if (chatStore.currentRoom) {
      chatStore.currentRoom.users = users
    }

    // Load statuses from backend in background (non-blocking, don't await)
    const userIds = users.filter(u => u?.id).map(u => u.id)
    if (userIds.length > 0) {
      // Don't await - load in background
      Promise.resolve().then(async () => {
        try {
          const { $api } = useNuxtApp()
          const statusResponse = await ($api as any)('/user-status/multiple', {
            method: 'POST',
            body: {
              user_ids: userIds,
            },
          })
          
          // Update statuses from backend response
          if (statusResponse?.statuses) {
            Object.entries(statusResponse.statuses).forEach(([userId, statusData]: [string, any]) => {
              const uid = parseInt(userId)
              if (statusData.last_activity) {
                userLastActivity.value = {
                  ...userLastActivity.value,
                  [uid]: statusData.last_activity,
                }
              }
              if (statusData.status) {
                setUserConnectionStatus(uid, statusData.status)
              }
            })
          }
        } catch (error) {
          console.warn('Failed to load user statuses from backend:', error)
          // Continue with local calculation as fallback
        }
      })
    }

    // Initialize status for all users currently in room
    users.forEach((u: any) => {
      if (u && typeof u.id === 'number') {
        // Use status from backend if available, otherwise calculate
        if (u.status && userConnectionStatus.value[u.id] === undefined) {
          // Status came from backend (presence channel)
          setUserConnectionStatus(u.id, u.status)
          if (u.last_activity) {
            userLastActivity.value = {
              ...userLastActivity.value,
              [u.id]: u.last_activity,
            }
          }
        } else {
          // Fallback: calculate status locally
          // Initialize activity tracking
          if (!userLastActivity.value[u.id]) {
            userLastActivity.value = {
              ...userLastActivity.value,
              [u.id]: u.last_activity || Date.now(),
            }
          }
          // Default tab visibility to true (will be updated via ping/pong)
          userTabVisibility.value = {
            ...userTabVisibility.value,
            [u.id]: true,
          }
          // Calculate and set initial status if not already set
          if (!userConnectionStatus.value[u.id]) {
            const initialStatus = calculateUserStatus(u)
            setUserConnectionStatus(u.id, initialStatus)
          }
        }
      }
    })
    
    // Send programmatic notifications for current user after room is loaded
    sendCurrentUserNotifications()
    
    // Welcome message logic for current user (only in here() callback)
    // Check immediately - no delay
    if (authStore.user && chatStore.currentRoom) {
      // Use nextTick to ensure room data is available, but don't delay
      nextTick(() => {
        const existingWelcomeMessage = chatStore.messages.find((m: Message) => 
          m.meta?.is_system && 
          m.meta?.is_welcome_message &&
          m.room_id === Number(currentRoomId) &&
          new Date(m.created_at).getTime() > Date.now() - 10000
        )
        
        if (!existingWelcomeMessage && authStore.user && chatStore.currentRoom?.welcome_message) {
          const room = chatStore.currentRoom
          const roomNameColor = getRoomNameColor(room)
          const roomTextColor = getRoomTextColor(room)
          const roomBorderColor = getRoomBorderColor(room)
          
          const hexToRgb = (hex: string): { r: number; g: number; b: number } | null => {
            if (!hex) return null
            const cleanHex = hex.replace('#', '')
            if (cleanHex.length === 6) {
              return {
                r: parseInt(cleanHex.substring(0, 2), 16),
                g: parseInt(cleanHex.substring(2, 4), 16),
                b: parseInt(cleanHex.substring(4, 6), 16),
              }
            }
            return null
          }
          
          const nameColorRgb = hexToRgb(roomNameColor) || { r: 100, g: 100, b: 100 }
          const messageColorRgb = hexToRgb(roomTextColor) || { r: 100, g: 100, b: 100 }
          const borderColorRgb = hexToRgb(roomBorderColor) || { r: 100, g: 100, b: 100 }
          
          const roomImageUrl = room.room_image_url || room.room_image || room.room_cover || null
          
          const welcomeMessage = {
            id: `welcome-${Date.now()}-${Math.random()}`,
            room_id: Number(currentRoomId),
            user_id: 0,
            user: {
              id: 0,
              name: room.name,
              username: 'room',
              avatar_url: roomImageUrl ? getRoomImageUrl(roomImageUrl) : (getSystemMessagesImage() || null),
              name_color: nameColorRgb,
              message_color: messageColorRgb,
              image_border_color: borderColorRgb,
              name_bg_color: 'transparent',
            },
            content: room.welcome_message,
            meta: {
              is_system: true,
              is_welcome_message: true,
              action: 'welcome',
            },
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString(),
          }
          
          chatStore.addMessage(welcomeMessage)
          nextTick(() => {
            scrollToBottom()
          })
        }
      })
    }

  })
  
  channel.joining(async (user: any) => {
    
    // Skip current user - we'll handle them programmatically
    const isCurrentUser = user && user.id === authStore.user?.id
    if (isCurrentUser) {
      return
    }
    
    if (user && typeof user.id === 'number') {
      // Use status from backend if available (from presence channel)
      if (user.status && user.last_activity !== undefined) {
        // Status came from backend - use it directly
        setUserConnectionStatus(user.id, user.status)
        userLastActivity.value = {
          ...userLastActivity.value,
          [user.id]: user.last_activity || Date.now(),
        }
      } else {
        // Check if this user was tracked as leaving another room (moved action)
        const leavingInfo = usersLeavingRooms.value[user.id]
        
        if (leavingInfo && leavingInfo.lastActivity) {
          // User moved from another room - update activity (moving is an interaction)
          // But preserve their status if it was good (not away)
          const now = Date.now()
          userLastActivity.value = {
            ...userLastActivity.value,
            [user.id]: now, // Update activity since moving rooms is an interaction
          }
          
          // Clear the leaving tracking since they've joined a new room
          if (leavingInfo.timeoutId) {
            clearTimeout(leavingInfo.timeoutId)
          }
          delete usersLeavingRooms.value[user.id]
          
          // Use preserved status if it was good, otherwise recalculate
          const preservedStatus = leavingInfo.lastStatus
          if (preservedStatus && preservedStatus !== 'away') {
            // Preserve good status (active/inactive_tab) when moving rooms
            setUserConnectionStatus(user.id, preservedStatus)
          } else {
            // Recalculate status with new activity (should be active since they just moved)
            const initialStatus = calculateUserStatus(user)
            setUserConnectionStatus(user.id, initialStatus)
          }
        } else {
          // User is joining fresh (not moving from another room)
          // Initialize user status tracking with current time
          userLastActivity.value = {
            ...userLastActivity.value,
            [user.id]: Date.now(),
          }
          // Calculate and set initial status
          const initialStatus = calculateUserStatus(user)
          setUserConnectionStatus(user.id, initialStatus)
        }
      }
      
      // Default tab visibility to true (will be updated via ping/pong)
      userTabVisibility.value = {
        ...userTabVisibility.value,
        [user.id]: true,
      }
    }

    if (chatStore.currentRoom?.users && user && user.id) {
      const existingUser = chatStore.currentRoom.users.find((u: any) => u.id === user.id)
      if (!existingUser) {
        chatStore.currentRoom.users.push(user)
      }
    }
    
    // Show premium entry notification for other users
    if (user && user.premium_entry) {
      const userData = {
        ...user,
        premium_entry: user.premium_entry || false,
        premium_entry_background: user.premium_entry_background || null,
      }
      showPremiumEntryNotification(userData)
    }
    
    // Create system message for other users
    if (user && user.id) {
      const currentRoom = chatStore.currentRoom
      const userId = user.id
      
      // Check if this user was tracked as leaving another room recently
      const leavingInfo = usersLeavingRooms.value[userId]
      let action: 'moved' | 'joined' = 'joined'
      let previousRoomIdForMessage: number | null = null
      
      if (leavingInfo && leavingInfo.roomId !== Number(currentRoomId)) {
        // User left another room and joined this one - it's a "moved" action
        action = 'moved'
        previousRoomIdForMessage = leavingInfo.roomId
        const oldRoomId = leavingInfo.roomId
        
        // Clear the cleanup timeout since user joined another room
        if (leavingInfo.timeoutId) {
          clearTimeout(leavingInfo.timeoutId)
        }
        
        // Add "moved" message to the old room locally
        // Note: This message will only be visible to the current user
        // Other users in the old room won't see it (no backend broadcast)
        const newRoomName = currentRoom?.name || `الغرفة ${currentRoomId}`
        const oldRoomMovedMessage = {
          id: `system-moved-from-${Date.now()}-${Math.random()}`,
          room_id: oldRoomId,
          user_id: userId,
          user: {
            ...user,
            avatar_url: user.avatar_url || getSystemMessagesImage(),
            name_color: user.name_color || { r: 69, g: 9, b: 36 },
            message_color: user.message_color || { r: 69, g: 9, b: 36 },
            image_border_color: user.image_border_color || { r: 69, g: 9, b: 36 },
            name_bg_color: user.name_bg_color || 'transparent',
          },
          content: `${user.name || user.username} انتقل إلى`,
          meta: {
            is_system: true,
            action: 'moved',
            room_id: Number(currentRoomId),
            room_name: newRoomName,
            previous_room_id: oldRoomId,
          },
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString(),
        }
        chatStore.addMessage(oldRoomMovedMessage)
        
        // Clean up tracking
        delete usersLeavingRooms.value[userId]
        
      }
      
      let content = ''
      if (action === 'moved') {
        content = 'هذا المستخدم انتقل إلى الغرفة'
      } else {
        content = 'هذا المستخدم انضم إلى'
      }
      
      const systemMessage = {
        id: `system-${Date.now()}-${Math.random()}`,
        room_id: Number(currentRoomId),
        user_id: user.id,
        user: {
          ...user,
          avatar_url: user.avatar_url || getSystemMessagesImage(),
          name_color: user.name_color || { r: 69, g: 9, b: 36 },
          message_color: user.message_color || { r: 69, g: 9, b: 36 },
          image_border_color: user.image_border_color || { r: 69, g: 9, b: 36 },
          name_bg_color: user.name_bg_color || 'transparent',
        },
        content: content,
        meta: {
          is_system: true,
          action: action,
          room_id: Number(currentRoomId),
          room_name: currentRoom?.name || `الغرفة ${currentRoomId}`,
          previous_room_id: previousRoomIdForMessage,
        },
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      }
      
      const existingMessage = chatStore.messages.find((m: Message) => 
        m.meta?.is_system && 
        m.user_id === user.id && 
        m.meta?.action === action &&
        m.room_id === Number(currentRoomId) &&
        new Date(m.created_at).getTime() > Date.now() - 5000
      )
      
      if (!existingMessage) {
        chatStore.addMessage(systemMessage)
        nextTick(() => {
          scrollToBottom()
        })
      }
    }
  })
  
  channel.leaving((user: any) => {
    
    if (chatStore.currentRoom?.users && user && user.id) {
      chatStore.currentRoom.users = chatStore.currentRoom.users.filter((u: any) => u.id !== user.id)
    }
    
    // Track that this user left the room - we'll use this to detect "moved" vs "joined" when they join another room
    // We do NOT send "left" messages here - only on socket disconnect
    if (user && user.id) {
      const userId = user.id
      const roomId = Number(currentRoomId)
      
      // Preserve user's last activity and status when they leave (they might be moving to another room)
      // Store their current activity timestamp and status before they leave
      const currentActivity = userLastActivity.value[userId]
      const currentStatus = userConnectionStatus.value[userId]
      
      // Clear any existing tracking for this user
      if (usersLeavingRooms.value[userId]?.timeoutId) {
        clearTimeout(usersLeavingRooms.value[userId].timeoutId)
      }
      
      // Track this user leaving (for detecting "moved" when they join another room)
      // Preserve their activity and status so we can restore it if they're moving rooms
      usersLeavingRooms.value[userId] = {
        roomId: roomId,
        timestamp: Date.now(),
        lastActivity: currentActivity, // Preserve activity timestamp
        lastStatus: currentStatus, // Preserve status
        timeoutId: setTimeout(() => {
          // Only mark as away if user hasn't joined another room within 5 seconds
          // This gives time for them to join another room (moved action)
          if (usersLeavingRooms.value[userId]) {
            // User didn't join another room, mark as away
            setUserConnectionStatus(userId, 'away')
            // Clean up tracking
            delete usersLeavingRooms.value[userId]
          }
        }, 5000) as ReturnType<typeof setTimeout> // Reduced to 5 seconds for faster detection
      }
      
      // Don't immediately mark as away - wait to see if they join another room
      // Keep their current status for now (will be updated when they join another room or after timeout)
    }
  })
  
  channel.error((error: any) => {
    console.error('Channel subscription error:', error)
  })

  // Listen for new messages (use as activity signal for AFK/online)
  channel.listen('.message.sent', async (data: any) => {
    if (String(data.room_id) === String(currentRoomId)) {
      // Mark sender as active on this socket event
      const senderId = data.user?.id || data.user_id
      if (senderId) {
        markUserActiveOnSocket(senderId)
      }

      const existingMessage = chatStore.messages.find((m: Message) => m.id === data.id)
      if (!existingMessage) {
        chatStore.addMessage(data)
        await nextTick()
        scrollToBottom()
      }
    }
  })

  // Listen for system messages from backend (for "joined" on reconnect)
  channel.listen('.system.message', (data: any) => {
    if (data && data.meta?.is_system && String(data.room_id) === String(currentRoomId)) {
      // Only handle "joined" messages for the current user (reconnect scenario)
      if (data.meta?.action === 'joined' && data.user_id === authStore.user?.id) {
        // Check if message already exists to avoid duplicates
        const existingMessage = chatStore.messages.find((m: Message) => 
          m.id === data.id || 
          (m.meta?.is_system && 
           m.user_id === data.user_id && 
           m.meta?.action === 'joined' &&
           m.room_id === data.room_id &&
           Math.abs(new Date(m.created_at).getTime() - new Date(data.created_at).getTime()) < 2000)
        )
        
        if (!existingMessage) {
          chatStore.addMessage(data)
          nextTick(() => {
            scrollToBottom()
          })
        }
      }
      // For other users' "joined" messages, they're handled by channel.joining() event
      // For "moved" messages, they're handled separately
    }
  })

  // Listen for new wall posts
  channel.listen('.wall.post.sent', (data: any) => {
    // Check if this post is for the current room
    const postRoomId = String(data.room_id || data.room?.id || '')
    const currentRoomIdStr = String(roomId.value || currentRoomId || '')
    
    if (postRoomId === currentRoomIdStr && postRoomId) {
      const existingPost = wallPosts.value.find((p: any) => p.id === data.id)
      if (!existingPost) {
        // Add image URL if exists
        if (data.image) {
          data.image_url = data.image.startsWith('http') ? data.image : `${useRuntimeConfig().public.apiBaseUrl.replace('/api', '')}/storage/${data.image}`
        }
        // Add to beginning of array (newest first)
        wallPosts.value.unshift(data)
        // Update cache with new post (non-blocking)
        if (import.meta.client && roomId.value) {
          import('~~/app/composables/useLocalStorageCache').then(({ useLocalStorageCache }) => {
            const cache = useLocalStorageCache()
            const cacheKey = `wall_posts_${roomId.value}`
            // Cache for 2 hours (120 minutes)
            cache.setCachedData(cacheKey, wallPosts.value, 120 * 60 * 1000)
          }).catch(() => {})
        }
        // Refresh wall creator
        fetchWallCreator()
        
        // Show notification overlay if wall sidebar is not open
        if (!showWall.value) {
          hasNewWallPost.value = true
          
          // Clear any existing timeout
          if (newWallPostTimeout) {
            clearTimeout(newWallPostTimeout)
          }
          
          // Auto-clear notification after 30 seconds
          newWallPostTimeout = setTimeout(() => {
            hasNewWallPost.value = false
            newWallPostTimeout = null
          }, 30000)
        }
      }
    }
  })


  // Listen for profile updates
  channel.listen('.profile.updated', (data: any) => {
    if (data.user && authStore.user && data.user.id === authStore.user.id) {
      Object.assign(authStore.user, data.user)
      if (import.meta.client) {
        localStorage.setItem('auth_user', JSON.stringify(authStore.user))
      }
      settingsStore.loadFromUser(data.user)
      // Recalculate status for current user when privacy settings change
      const newStatus = calculateUserStatus(authStore.user)
      setUserConnectionStatus(authStore.user.id, newStatus)
    }
    
    chatStore.messages.forEach((msg: Message) => {
      if (msg.user_id === data.user.id) {
        if (msg.user) {
          Object.assign(msg.user, data.user)
        }
      }
    })
    
    if (chatStore.currentRoom?.users) {
      const userIndex = chatStore.currentRoom.users.findIndex((u: any) => u.id === data.user.id)
      if (userIndex !== -1) {
        Object.assign(chatStore.currentRoom.users[userIndex], data.user)
        // Recalculate status for other users when their privacy settings change
        const updatedUser = chatStore.currentRoom.users[userIndex]
        const newStatus = calculateUserStatus(updatedUser)
        setUserConnectionStatus(updatedUser.id, newStatus)
      }
    }
  })

  // Listen for "joined" events from other users via whisper
  channel.listenForWhisper('user.joined', (data: any) => {
    if (data && data.meta?.is_system && data.meta?.action === 'joined') {
      // Don't add the message if it's from the current user (they're the one joining)
      if (data.user_id === authStore.user?.id) {
        return
      }
      
      // Only add if it's for the current room
      if (String(data.room_id) === String(currentRoomId)) {
        // Check if message already exists to avoid duplicates
        const existingMessage = chatStore.messages.find((m: Message) => 
          m.id === data.id || 
          (m.meta?.is_system && 
           m.user_id === data.user_id && 
           m.meta?.action === 'joined' &&
           m.room_id === data.room_id &&
           Math.abs(new Date(m.created_at).getTime() - new Date(data.created_at).getTime()) < 2000)
        )
        
        if (!existingMessage) {
          chatStore.addMessage(data)
          nextTick(() => {
            scrollToBottom()
          })
        }
      }
    }
  })

  // Listen for "left" events from other users via whisper
  channel.listenForWhisper('user.left', (data: any) => {
    if (data && data.meta?.is_system && data.meta?.action === 'left') {
      // Don't add the message if it's from the current user (they're the one leaving)
      if (data.user_id === authStore.user?.id) {
        return
      }
      
      // Only add if it's for the current room
      if (String(data.room_id) === String(currentRoomId)) {
        // Check if message already exists to avoid duplicates
        const existingMessage = chatStore.messages.find((m: Message) => 
          m.id === data.id || 
          (m.meta?.is_system && 
           m.user_id === data.user_id && 
           m.meta?.action === 'left' &&
           m.room_id === data.room_id &&
           Math.abs(new Date(m.created_at).getTime() - new Date(data.created_at).getTime()) < 2000)
        )
        
        if (!existingMessage) {
          chatStore.addMessage(data)
          nextTick(() => {
            scrollToBottom()
          })
        }
      }
    }
  })

  // Listen for "moved" events from other users via whisper
  channel.listenForWhisper('user.moved', (data: any) => {
    if (data && data.meta?.is_system && data.meta?.action === 'moved') {
      // Don't add the message if it's from the current user (they're the one moving)
      if (data.user_id === authStore.user?.id) {
        return
      }
      
      // The message is for the old room (where we received the whisper)
      // data.room_id is the NEW room the user moved to
      // data.meta.previous_room_id is the OLD room (current room)
      // We should add it if previous_room_id matches current room
      const isForCurrentRoom = String(data.meta?.previous_room_id) === String(currentRoomId) || 
                                String(data.room_id) === String(currentRoomId)
      
      if (isForCurrentRoom) {
        // Check if message already exists to avoid duplicates
        const existingMessage = chatStore.messages.find((m: Message) => 
          m.id === data.id || 
          (m.meta?.is_system && 
           m.user_id === data.user_id && 
           m.meta?.action === 'moved' &&
           (String(m.room_id) === String(data.room_id) || String(m.meta?.previous_room_id) === String(currentRoomId)) &&
           Math.abs(new Date(m.created_at).getTime() - new Date(data.created_at).getTime()) < 2000)
        )
        
        if (!existingMessage) {
          chatStore.addMessage(data)
          nextTick(() => {
            scrollToBottom()
          })
        }
      }
    }
  })

  // Listen for ping events and respond with pong (including tab visibility)
  channel.listenForWhisper('ping', (data: any) => {
    if (data && data.user_id && typeof data.user_id === 'number' && authStore.user?.id) {
      // Update the ping sender's tab visibility if provided
      if (typeof data.tab_visible === 'boolean') {
        userTabVisibility.value = {
          ...userTabVisibility.value,
          [data.user_id]: data.tab_visible,
        }
        
        // Recalculate status immediately for the ping sender
        const user = chatStore.currentRoom?.users?.find((u: any) => u.id === data.user_id) ||
                     chatStore.activeUsers.find((u: any) => u.id === data.user_id)
        if (user) {
          const newStatus = calculateUserStatus(user)
          setUserConnectionStatus(data.user_id, newStatus)
        }
      }
      
      // Respond with pong to indicate we're active (include our tab visibility)
      try {
        if (channel.subscribed && typeof channel.whisper === 'function') {
          channel.whisper('pong', {
            user_id: authStore.user.id,
            timestamp: Date.now(),
            tab_visible: isTabVisible.value,
            last_activity: currentUserLastActivity.value,
          })
          // Mark the ping sender as active
          if (data.user_id !== authStore.user.id) {
            markUserActiveOnSocket(data.user_id)
          }
        }
      } catch (error) {
        console.error('Error sending pong:', error)
      }
    }
  })

  // Listen for user presence (online/offline/afk) from socket
  channel.listen('.user.presence', (data: any) => {
    if (data.room_id && String(data.room_id) !== String(currentRoomId)) {
      return
    }
    
    // Skip current user - we handle them programmatically
    const isCurrentUser = data.user && data.user.id === authStore.user?.id
    if (isCurrentUser) {
      return
    }
    
    if (data.status === 'offline' && data.user) {
      // Mark user as away when they go offline
      setUserConnectionStatus(data.user.id, 'away')
      // Remove last activity tracking
      const { [data.user.id]: _removed, ...rest } = userLastActivity.value
      userLastActivity.value = rest

      if (chatStore.currentRoom?.users) {
        chatStore.currentRoom.users = chatStore.currentRoom.users.filter((u: any) => u.id !== data.user.id)
      }
    } else if (data.status === 'online' && data.user) {
      // Mark user as online on presence join
      markUserActiveOnSocket(data.user.id)

      // Show premium entry notification for other users only
      if (data.user.premium_entry) {
        const userData = {
          ...data.user,
          premium_entry: data.user.premium_entry || false,
          premium_entry_background: data.user.premium_entry_background || null,
        }
        showPremiumEntryNotification(userData)
      }
      
      if (chatStore.currentRoom?.users) {
        const existingUser = chatStore.currentRoom.users.find((u: any) => u.id === data.user.id)
        if (!existingUser) {
          chatStore.currentRoom.users.push(data.user)
        }
      }
    } else if (data.status === 'afk' && data.user) {
      // Optional AFK support if backend sends it via socket
      // Recalculate status based on current rules
      const newStatus = calculateUserStatus(data.user)
      setUserConnectionStatus(data.user.id, newStatus)
    }
  })
}

// Helper function to deep compare color objects
const colorsEqual = (a: any, b: any): boolean => {
  if (a === b) return true
  if (typeof a === 'string' || typeof b === 'string') {
    return a === b
  }
  if (typeof a === 'object' && typeof b === 'object' && a !== null && b !== null) {
    return a.r === b.r && a.g === b.g && a.b === b.b
  }
  return false
}

const openSettings = () => {
  // Load current user profile data when opening settings
  if (authStore.user) {
    profileForm.value.name = authStore.user.name || ''
    profileForm.value.bio = authStore.user.bio || ''
    profileForm.value.social_media_type = authStore.user.social_media_type || null
    profileForm.value.social_media_url = authStore.user.social_media_url || null
    // Load social links if stored (could be in user meta or separate field)
    // For now, we'll keep it empty as it's not in the User model
  }
  // Original settings will be stored by the watcher when showSettings becomes true
  showSettings.value = true
  // Warnings are now loaded only when user clicks to view them
}

const openWarnings = async () => {
  // Open the warnings modal
  showWarningsModal.value = true
  
  // Wait for modal to open and component to be mounted
  await nextTick()
  await new Promise(resolve => setTimeout(resolve, 300))
  
  // Trigger warnings to load and automatically show them
  if (userWarningsRef.value) {
    await userWarningsRef.value.forceShowWarnings()
  }
}

// Removed saveSettingsOnClose - now using submit button approach

onMounted(async () => {
  // Background authentication FIRST - refresh token and check user status BEFORE any API calls or socket connections
  // This ensures we have a valid, fresh token before making any authenticated requests
  if (authStore.isAuthenticated) {
    try {
      await authStore.backgroundAuth()
      
      // If Echo was already initialized (by plugin) with an expired token, disconnect and reinitialize it
      // This ensures Echo uses the fresh token from backgroundAuth
      const existingEcho = getEcho()
      if (existingEcho) {
        disconnect()
        // Small delay to ensure disconnect completes
        await new Promise(resolve => setTimeout(resolve, 100))
      }
    } catch (error) {
      // If background auth fails (user banned, etc.), it will handle redirect/logout internally
      // Don't proceed with any operations if auth fails
      console.error('Background auth error:', error)
      return
    }
  }
  
  // Initialize room ID if not set - default to room 1 (general room)
  // This happens AFTER backgroundAuth to ensure we have a valid token
  if (!chatStore.currentRoomId) {
    // Try to find general room from cached rooms first
    const cachedRooms = chatStore.displayRooms
    if (cachedRooms && cachedRooms.length > 0) {
      const generalRoom = cachedRooms.find((room: Room) => 
        room.is_public && (room.name?.toLowerCase().includes('general') || room.id === 1)
      ) || cachedRooms[0]
      if (generalRoom?.id) {
        chatStore.setCurrentRoomId(generalRoom.id)
      } else {
        chatStore.setCurrentRoomId(1) // Default to room 1
      }
    } else {
      // Fetch general room or default to room 1
      // This will now use the refreshed token from backgroundAuth
      try {
        const generalRoom = await chatStore.fetchGeneralRoom()
        if (generalRoom?.id) {
          chatStore.setCurrentRoomId(generalRoom.id)
        } else {
          chatStore.setCurrentRoomId(1)
        }
      } catch {
        chatStore.setCurrentRoomId(1) // Default to room 1
      }
    }
  }
  
  // Initialize Echo/socket connection AFTER authentication is complete
  // This ensures socket connects with a valid, fresh token
  if (authStore.isAuthenticated && authStore.token) {
    // Ensure Echo is initialized (plugin might have done it, but ensure it's ready)
    initEcho()
    const echo = getEcho()
    
    if (echo && roomId.value) {
      // Subscribe to room channel IMMEDIATELY (don't wait for ANYTHING)
      if (!currentChannel) {
        currentChannel = echo.join(`room.${roomId.value}`)
        // Set up listeners immediately - they'll work once channel subscribes
        setupChannelListeners(currentChannel, roomId.value)
        isSubscribed = true
      }
      
      // Subscribe to user private channel immediately
      if (!userPrivateChannel && authStore.user) {
        userPrivateChannel = echo.private(`user.${authStore.user.id}`)
        userPrivateChannel.subscribed(() => {
          isUserChannelSubscribed = true
        })
      }
      
      // Subscribe to global presence channel immediately
      if (!globalPresenceChannel) {
        globalPresenceChannel = echo.join('presence')
      }
      
      // Subscribe to stories channel for real-time updates
      const storiesChannel = echo.channel('stories')
      storiesChannel.listen('.story.created', (data: any) => {
        // Add story directly from socket event (no API call needed)
        addStoryFromSocket(data)
      })
      
      // Track connection state immediately
      try {
        // @ts-ignore
        const pusher = echo.connector?.pusher || echo.pusher
        if (pusher && pusher.connection) {
          const initialState = pusher.connection.state === 'connected' || pusher.connection.state === 'connecting'
          chatStore.setConnected(initialState)
        }
      } catch (error) {
        // Ignore errors, connection will be tracked later
      }
    }
  }

  // Load last activity from localStorage for immediate status calculation
  if (authStore.user?.id) {
    const loadedActivity = loadLastActivity()
    currentUserLastActivity.value = loadedActivity
    
    // Immediately calculate and set status based on loaded activity
    const user = authStore.user
    const initialStatus = calculateUserStatus(user)
    setUserConnectionStatus(user.id, initialStatus)
  }
  
  // Fetch emojis from database (non-blocking, parallel)
  fetchEmojis().then(() => {
    emojiList.value = getEmojiList()
  }).catch((error) => {
    console.error('Error loading emojis:', error)
    emojiList.value = []
  })

  // Fetch settings from API (non-blocking, parallel)
  if (authStore.isAuthenticated) {
    settingsStore.fetchFromAPI().catch((error) => {
      console.error('Error fetching settings:', error)
    })
  }

  // Set up time interval for relative time updates
  timeInterval = setInterval(() => {
    currentTime.value = new Date()
  }, 60000) // Update every minute

  // Set up activity heartbeat to keep user active in room
  updateActivity() // Update immediately
  activityInterval = setInterval(() => {
    updateActivity()
  }, 120000) // Update every 2 minutes

  // Local AFK detection based purely on socket activity timestamps
  // Status updates happen ONLY via socket events - no periodic HTTP requests

  // Load user profile data
  if (authStore.user) {
    profileForm.value.name = authStore.user.name || ''
    profileForm.value.bio = authStore.user.bio || ''
    profileForm.value.social_media_type = authStore.user.social_media_type || null
    profileForm.value.social_media_url = authStore.user.social_media_url || null
  }

  // Check if password dialog is open - if so, don't proceed with setup
  if (showPasswordDialog.value) {
    return
  }

  // Background authentication FIRST - refresh token before fetching room
  // This ensures we have a valid, fresh token for the API call
  if (authStore.isAuthenticated) {
    try {
      await authStore.backgroundAuth()
    } catch (error) {
      // If background auth fails, still try to fetch room (might work with existing token)
      console.error('Background auth error:', error)
    }
  }
  
  // Load room (blocking - needed for room data)
  try {
    await chatStore.fetchRoom(roomId.value)
    // Password validation successful - reset flag
    isPasswordValidated.value = true
    
    // Focus on message input immediately after room is loaded
    focusMessageInput()
    
    // Load bootstrap data first (synchronously from cache, then async refresh)
    // This ensures we show cached data immediately
    const bootstrap = getBootstrap()
    if (bootstrap) {
      // Apply cached bootstrap data immediately
      const { loadFromBootstrap } = useSiteSettings()
      loadFromBootstrap(bootstrap.site_settings)
      
      if (bootstrap.rooms && bootstrap.rooms.length > 0) {
        chatStore.loadRoomsFromBootstrap(bootstrap.rooms)
      }
    }
    
    // Load all other data in parallel (non-blocking)
    Promise.allSettled([
      // Bootstrap data (site settings, rooms) - refresh with fresh data
      initBootstrap().then(() => {
        const freshBootstrap = getBootstrap()
        if (freshBootstrap) {
          const { loadFromBootstrap } = useSiteSettings()
          loadFromBootstrap(freshBootstrap.site_settings)
          
          if (freshBootstrap.rooms && freshBootstrap.rooms.length > 0) {
            chatStore.loadRoomsFromBootstrap(freshBootstrap.rooms)
          }
        }
      }).catch((error) => {
        console.error('Error loading bootstrap data:', error)
      }),
      
      // Active users
      chatStore.fetchActiveUsers().then(() => {
        const allActiveUsers = chatStore.displayActiveUsers || []
        allActiveUsers.forEach((u: User) => {
          if (u && typeof u.id === 'number') {
            markUserActiveOnSocket(u.id)
          }
        })
      }).catch((error) => {
        console.error('Error loading active users:', error)
      }),
      
      // Private messages unread count
      privateMessagesStore.fetchUnreadCount().catch((error) => {
        console.error('Error loading private messages unread count:', error)
      }),
      
      // Wall posts
      fetchWallPosts().catch((error) => {
        console.error('Error loading wall posts:', error)
      }),
    ]).finally(() => {
      // Load stories AFTER all other work is finished
      fetchStories().catch((error) => {
        console.error('Error loading stories:', error)
      })
    })
  } catch (error: any) {

    
    // Check if room requires password
    if (error.data?.requires_password || error.status === 403 || error.message?.includes('password')) {
      passwordProtectedRoomId.value = Number(roomId.value)
      passwordProtectedRoomName.value = error.data?.room_name || 'الغرفة'
      showPasswordDialog.value = true
      isPasswordValidated.value = false // Reset validation flag
      chatStore.loading = false
      // Set back to previous room if available, otherwise stay on current room
      if (previousRoomId) {
        chatStore.setCurrentRoomId(Number(previousRoomId))
      } else if (chatStore.currentRoom?.id) {
        // If we have a current room, set it
        chatStore.setCurrentRoomId(chatStore.currentRoom.id)
      }
      return
    }
    // Check if user is banned
    if (error?.data?.banned || (error?.status === 403 && error?.data?.message?.includes('banned'))) {
      // Ban is handled by useApi composable, just return
      return
    }
    
    // Other errors - show error and redirect
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.message || 'فشل تحميل الغرفة',
      life: 3000,
    })
    router.push('/chat')
    return
  }
  
  // Don't clear messages - keep all messages from all rooms in store
  // Don't load messages from database - only show messages received via real-time during the session
  // Messages will clear on page refresh (store resets)
  
  // Set loading to false
  chatStore.loading = false

  // Set up scheduled messages for this room (non-blocking, with small delay)
  // Welcome messages are sent in channel.subscribed() with 500ms delay
  nextTick().then(() => {
    setTimeout(() => {
      if (chatStore.currentRoom) {
        setupScheduledMessages(Number(roomId.value))
      }
    }, 1000) // Reduced from 1.5s to 1s
  })

  // Track connection state (Echo already initialized and subscribed above)
  const echo = getEcho()
  
  if (echo) {
    try {
      // @ts-ignore - accessing internal connector property
      const pusher = echo.connector?.pusher || echo.pusher
      if (pusher && pusher.connection) {
        // Set initial connection state
        const initialState = pusher.connection.state === 'connected' || pusher.connection.state === 'connecting'
        chatStore.setConnected(initialState)
        
        // Listen for connection events
        // Function to add connection state system message
        const addConnectionStateMessage = (state: 'connected' | 'disconnected' | 'error' | 'reconnecting', previousState?: string) => {
          if (!roomId.value) return
          
          const stateConfig = {
            connected: {
              content: 'تم الاتصال بالخادم',
              bgColor: '#d4edda', // Light green
              textColor: '#155724', // Dark green
            },
            disconnected: {
              content: 'تم قطع الاتصال بالخادم',
              bgColor: '#f8d7da', // Light red
              textColor: '#721c24', // Dark red
            },
            error: {
              content: 'حدث خطأ في الاتصال',
              bgColor: '#fff3cd', // Light yellow
              textColor: '#856404', // Dark yellow
            },
            reconnecting: {
              content: 'جاري إعادة الاتصال...',
              bgColor: '#d1ecf1', // Light blue
              textColor: '#0c5460', // Dark blue
            },
          }
          
          const config = stateConfig[state]
          if (!config) return
          
          const connectionMessage = {
            id: `connection-${state}-${Date.now()}-${Math.random()}`,
            room_id: Number(roomId.value),
            user_id: 0,
            user: {
              id: 0,
              name: 'النظام',
              username: 'system',
              avatar_url: getSystemMessagesImage() || null,
              name_color: { r: 100, g: 100, b: 100 },
              message_color: { r: 100, g: 100, b: 100 },
              image_border_color: { r: 100, g: 100, b: 100 },
              name_bg_color: 'transparent',
            },
            content: config.content,
            meta: {
              is_system: true,
              is_connection_state: true,
              connection_state: state,
              previous_state: previousState,
            },
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString(),
          }
          
          // Check if similar message already exists (within last 5 seconds)
          const existingMessage = chatStore.messages.find((m: Message) => 
            m.meta?.is_connection_state && 
            m.meta?.connection_state === state &&
            m.room_id === Number(roomId.value) &&
            Math.abs(new Date(m.created_at).getTime() - Date.now()) < 5000
          )
          
          if (!existingMessage) {
            chatStore.addMessage(connectionMessage)
            nextTick(() => {
              scrollToBottom()
            })
          }
        }
        
        pusher.connection.bind('connected', async () => {
          chatStore.setConnected(true)
          
          // Reset manual disconnect flag when connection is established
          isManualDisconnect.value = false
          
          // Mark current user as active when socket connects
          if (authStore.user?.id) {
            markUserActiveOnSocket(authStore.user.id)
          }
          
          // When socket reconnects, treat network as restored:
          // Status will be updated via socket events - no need to recalculate via HTTP
          // updateAllUsersStatus() removed - status updates via socket only
          
          // Add connection message (only if not manually disconnected)
          if (!isManualDisconnect.value) {
            addConnectionStateMessage('connected')
          }
          
          // Note: "joined" message is sent in channel.subscribed() callback when wasDisconnected is true
        })
        
        pusher.connection.bind('disconnected', () => {
          chatStore.setConnected(false)
          
          // Mark as disconnected for reconnect detection
          wasDisconnected.value = true
          
          // Only add disconnection message and update status if NOT manually disconnected
          // If manually disconnected, we already sent the message and don't want auto-reconnect
          if (!isManualDisconnect.value) {
            // Mark all known users as "away" when socket disconnects
            const updated: typeof userConnectionStatus.value = {}
            Object.keys(userConnectionStatus.value).forEach((id) => {
              updated[Number(id)] = 'away'
            })
            userConnectionStatus.value = updated
            
            // Add disconnection message (automatic disconnect)
            addConnectionStateMessage('disconnected')
          }
        })
        
        pusher.connection.bind('error', () => {
          chatStore.setConnected(false)
          
          // Add error message
          addConnectionStateMessage('error')
        })
        
        pusher.connection.bind('state_change', (states: any) => {
          chatStore.setConnected(states.current === 'connected')
          
          // Reset manual disconnect flag when connection is established
          if (states.current === 'connected') {
            isManualDisconnect.value = false
          }
          
          // Add connection state message based on new state (only if not manually disconnected)
          if (!isManualDisconnect.value) {
            if (states.current === 'connecting' && states.previous !== 'connecting') {
              // Send reconnecting message immediately when state changes to connecting
              addConnectionStateMessage('reconnecting', states.previous)
            } else if (states.current === 'connected' && states.previous !== 'connected') {
              addConnectionStateMessage('connected', states.previous)
            } else if (states.current === 'disconnected' && states.previous !== 'disconnected') {
              addConnectionStateMessage('disconnected', states.previous)
            } else if (states.current === 'failed' || states.current === 'unavailable') {
              addConnectionStateMessage('error', states.previous)
            }
          }
        })
      } else {
        chatStore.setConnected(false)
      }
    } catch (error) {
      console.error('Error accessing Pusher connection:', error)
      chatStore.setConnected(false)
    }
  } else {
    chatStore.setConnected(false)
  }
  
  // Socket already subscribed at the beginning of onMounted
  // Only handle edge cases here if needed
  if (echo && !isSubscribed && roomId.value) {
    // Fallback: if somehow not subscribed, subscribe now
    if (!currentChannel) {
      currentChannel = echo.join(`room.${roomId.value}`)
      setupChannelListeners(currentChannel, roomId.value)
      isSubscribed = true
    }
  }

  // Global presence channel already subscribed at the beginning
  // Only set up listeners if not already done
  if (echo && authStore.user && globalPresenceChannel) {
    try {
      
      globalPresenceChannel.subscribed(() => {
        
        // Set up ping/pong on global channel for cross-room status tracking
        setupGlobalPresencePingPong(globalPresenceChannel)
        
        // Listen for user status updates from global channel
        globalPresenceChannel.listen('.user.status.updated', (data: any) => {
          if (data && data.user && data.user.id) {
            const userId = data.user.id
            const newStatus = data.status
            
            // Update user status in the global activeUsers list
            const user = chatStore.activeUsers.find((u: any) => u.id === userId)
            if (user) {
              // Update user object with new status and privacy settings
              if (data.user.incognito_mode_enabled !== undefined) {
                user.incognito_mode_enabled = data.user.incognito_mode_enabled
              }
              if (data.user.private_messages_enabled !== undefined) {
                user.private_messages_enabled = data.user.private_messages_enabled
              }
              
              // Update status
              setUserConnectionStatus(userId, newStatus)
              
              // Update last activity if provided
              if (data.user.last_activity) {
                userLastActivity.value = {
                  ...userLastActivity.value,
                  [userId]: data.user.last_activity,
                }
              }
              
            }
          }
        })
      })
      
      globalPresenceChannel.error((error: any) => {
        console.error('❌ Error subscribing to global presence channel:', error)
      })
    } catch (error) {
      console.error('❌ Failed to subscribe to global presence channel:', error)
    }
  }

  // Subscribe to user's private channel for move requests (only once)
  if (echo && authStore.user && !isUserChannelSubscribed) {
    try {
      userPrivateChannel = echo.private(`user.${authStore.user.id}`)
      
      userPrivateChannel.subscribed(() => {
        isUserChannelSubscribed = true
        
        // Set up listener after subscription is confirmed
        userPrivateChannel.listen('.user.move.request', async (data: any) => {
          
          if (data.target_room_id) {
            const targetRoomId = data.target_room_id
            const previousRoomId = data.previous_room_id
            
            // Navigate to the target room
            try {
              await navigateToRoom(targetRoomId)
            } catch (error) {
              console.error('❌ Error navigating to room:', error)
            }
          }
        })

        // Listen for ban event (inside subscribed callback)
        userPrivateChannel.listen('.user.banned', async (data: any) => {
          
          try {
            // Clear auth and disconnect Echo
            authStore.clearAuth()
            disconnect()
            
            // Show ban message
            const { useToast } = await import('primevue/usetoast')
            const toast = useToast()
            toast.add({
              severity: 'error',
              summary: 'تم حظر حسابك',
              detail: data.message || 'تم حظر حسابك من الموقع',
              life: 10000,
            })
            
            // Redirect to home page
            await router.push('/')
          } catch (error) {
            console.error('❌ Error handling ban event:', error)
            // Fallback: force redirect
            window.location.href = '/'
          }
        })

        // Listen for private messages
        userPrivateChannel.listen('.private-message.sent', async (data: any) => {
          try {
            if (data && data.id) {
              // Add message to store
              privateMessagesStore.addMessage(data)
              
              // Update conversation list
              if (data.sender && data.sender.id !== authStore.user?.id) {
                // This is a message received from another user
                const conversation: Conversation = {
                  user: data.sender,
                  last_message: {
                    id: data.id,
                    content: data.content,
                    sender_id: data.sender_id,
                    created_at: data.created_at,
                    read_at: data.read_at,
                  },
                  unread_count: 1,
                  message_count: 1,
                  last_message_at: data.created_at,
                }
                privateMessagesStore.updateConversation(conversation)
                
                // Update unread count
                await privateMessagesStore.fetchUnreadCount()
                
                // Mark as read if modal is open
                if (showPrivateMessageModal.value && privateMessagesStore.currentConversation?.id === data.sender_id) {
                  await privateMessagesStore.markAsRead(data.sender_id)
                }
              } else if (data.recipient && data.recipient.id !== authStore.user?.id) {
                // This is a message sent to another user (confirmation)
                // Update conversation
                const conversation: Conversation = {
                  user: data.recipient,
                  last_message: {
                    id: data.id,
                    content: data.content,
                    sender_id: data.sender_id,
                    created_at: data.created_at,
                    read_at: data.read_at,
                  },
                  unread_count: 0,
                  message_count: 1,
                  last_message_at: data.created_at,
                }
                privateMessagesStore.updateConversation(conversation)
              }
              
              // Scroll to bottom if modal is open
              if (showPrivateMessageModal.value && 
                  (data.sender_id === privateMessagesStore.currentConversation?.id || 
                   data.recipient_id === privateMessagesStore.currentConversation?.id)) {
                nextTick(() => {
                  scrollPrivateMessagesToBottom()
                })
              }
            }
          } catch (error) {
            console.error('❌ Error handling private message:', error)
          }
        })
      })

      // Also set up listener immediately (in case subscribed() doesn't fire)
      userPrivateChannel.listen('.user.move.request', async (data: any) => {
        
        if (data.target_room_id) {
          const targetRoomId = data.target_room_id
          const previousRoomId = data.previous_room_id
          
          // Navigate to the target room
          try {
            await navigateToRoom(targetRoomId)
          } catch (error) {
            console.error('❌ Error navigating to room:', error)
          }
        }
      })

      // Listen for ban event (immediate listener)
      userPrivateChannel.listen('.user.banned', async (data: any) => {
        
        try {
          // Clear auth and disconnect Echo
          authStore.clearAuth()
          disconnect()
          
          // Show ban message
          const { useToast } = await import('primevue/usetoast')
          const toast = useToast()
          toast.add({
            severity: 'error',
            summary: 'تم حظر حسابك',
            detail: data.message || 'تم حظر حسابك من الموقع',
            life: 10000,
          })
          
          // Redirect to home page
          await router.push('/')
        } catch (error) {
          console.error('❌ Error handling ban event:', error)
          // Fallback: force redirect
          window.location.href = '/'
        }
      })

      userPrivateChannel.error((error: any) => {
        console.error('❌ Error subscribing to private user channel:', error)
        isUserChannelSubscribed = false
      })
    } catch (error) {
      console.error('❌ Failed to set up private user channel:', error)
    }
  }
})

onUnmounted(() => {
  // Clear time interval
  if (timeInterval) {
    clearInterval(timeInterval)
    timeInterval = null
  }
  
  // Clear activity interval
  if (activityInterval) {
    clearInterval(activityInterval)
    activityInterval = null
  }
  
  // Clear wall post notification timeout
  if (newWallPostTimeout) {
    clearTimeout(newWallPostTimeout)
    newWallPostTimeout = null
  }
  
  // Clean up scheduled message intervals
  cleanupScheduledMessages()

  // Unsubscribe from Echo channel first
  const echo = getEcho()
  if (echo && currentChannel) {
    echo.leave(`room.${roomId.value}`)
    currentChannel = null
    isSubscribed = false
  }

  // Note: We don't unsubscribe from user private channel here because it should persist
  // across page navigations. The user channel is for receiving move requests and other
  // user-specific events, so it should stay active.

  // Don't clear messages - keep all messages in store for all rooms
  // Don't clear current room either, to preserve state
})

watch(() => chatStore.messages.length, () => {
  scrollToBottom()
})

// Watch for room changes and handle channel subscription without clearing messages
// This watch handles room switching without page reload
watch(() => roomId.value, async (newRoomId, oldRoomId) => {
  // Skip if no new room or same room
  if (!newRoomId || String(newRoomId) === String(oldRoomId)) {
    return
  }
  
  // Check if password dialog is open - if so, don't proceed with setup
  if (showPasswordDialog.value) {
    return
  }
  
  
  // Clear wall post notification when switching rooms
  if (hasNewWallPost.value) {
    hasNewWallPost.value = false
    if (newWallPostTimeout) {
      clearTimeout(newWallPostTimeout)
      newWallPostTimeout = null
    }
  }
  
  // Store previous room ID for "moved" action detection
  if (oldRoomId) {
    previousRoomId = oldRoomId
  }
  
  const echo = getEcho()
  if (!echo) {
    console.warn('Echo not available for room switch')
    return
  }
  
  // Clean up scheduled messages from previous room
  cleanupScheduledMessages()
  
  // Leave previous room channel if we have one
  // Note: We don't create a "left" message here because it's a "moved" event, not a "left" event
  if (oldRoomId && currentChannel) {
    echo.leave(`room.${oldRoomId}`)
    currentChannel = null
    isSubscribed = false
  }
  
  // Fetch the new room (this updates currentRoom but doesn't clear messages)
  // setCurrentRoom already doesn't clear messages, so this is safe
  try {
    await chatStore.fetchRoom(newRoomId)
    // Password validation successful - reset flag
    isPasswordValidated.value = true
    // Fetch wall posts for the new room
    await fetchWallPosts()
    
    // Focus on message input after switching rooms
    focusMessageInput()
  } catch (error: any) {
    
    // Check if room requires password
    if (error.data?.requires_password || error.status === 403 || error.message?.includes('password')) {
      passwordProtectedRoomId.value = Number(newRoomId)
      passwordProtectedRoomName.value = error.data?.room_name || 'الغرفة'
      showPasswordDialog.value = true
      isPasswordValidated.value = false // Reset validation flag
      // Set back to previous room to stay on current room
      if (oldRoomId) {
        chatStore.setCurrentRoomId(Number(oldRoomId))
      }
      // Don't proceed with room setup if password is required
      return
    }
    
    // Check if user is banned
    if (error?.data?.banned || (error?.status === 403 && error?.data?.message?.includes('banned'))) {
      // Ban is handled by useApi composable, just return
      return
    }
    
    // Other errors - show error and redirect to previous room
    toast.add({
      severity: 'error',
      summary: 'خطأ',
      detail: error.message || 'فشل تحميل الغرفة',
      life: 3000,
    })
    // Redirect back to previous room if available
    if (oldRoomId) {
      await router.replace(`/chat/${oldRoomId}`)
    }
    return
  }
  
  // Ensure currentRoom is set before proceeding
  if (!chatStore.currentRoom || String(chatStore.currentRoom.id) !== String(newRoomId)) {
    console.warn('Watch handler: currentRoom not set correctly after fetchRoom, waiting...')
    await new Promise(resolve => setTimeout(resolve, 100))
    // Try fetching again if still not set
    if (!chatStore.currentRoom || String(chatStore.currentRoom.id) !== String(newRoomId)) {
      try {
        await chatStore.fetchRoom(newRoomId)
        isPasswordValidated.value = true
      } catch (error: any) {
        if (error.data?.requires_password || error.status === 403 || error.message?.includes('password')) {
          passwordProtectedRoomId.value = Number(newRoomId)
          passwordProtectedRoomName.value = error.data?.room_name || 'الغرفة'
          showPasswordDialog.value = true
          isPasswordValidated.value = false // Reset validation flag
          return
        }
      }
    }
  }
  
  // Don't load messages from database - only show messages received via real-time during the session
  // Messages will clear on page refresh (store resets)
  
  // Set up scheduled messages for the new room
  // Wait for room data to be loaded and for welcome/system messages to be sent first
  // Welcome messages are sent in channel.subscribed() with 500ms delay, so wait longer
  await nextTick()
  await new Promise(resolve => setTimeout(resolve, 1500)) // Wait 1.5 seconds for welcome/system messages
  
  if (chatStore.currentRoom) {
    setupScheduledMessages(Number(newRoomId))
  }
  
  // Subscribe to new room channel
  currentChannel = echo.join(`room.${newRoomId}`)
  
  // Mark current user as active when changing rooms (this is an interaction)
  if (authStore.user?.id) {
    markUserActiveOnSocket(authStore.user.id)
  }
  
  // Set up all channel listeners
  setupChannelListeners(currentChannel, newRoomId)
  isSubscribed = true

  if (oldRoomId && authStore.user) {
    setTimeout(() => {
      addLocalMoveMessageForCurrentUser(oldRoomId, newRoomId)
    }, 300)
  }
  
}, { immediate: false })

// Store original settings when opening the panel to compare on close
const originalSettings = ref<{
  roomFontSize: number
  nameColor: any
  messageColor: any
  nameBgColor: any
  imageBorderColor: any
  bioColor: any
} | null>(null)

// No need to watch for panel close anymore - user will click submit button to save

// Incognito mode enabled state
const incognitoModeEnabled = computed({
  get: () => authStore.user?.incognito_mode_enabled || false,
  set: async (value: boolean) => {
    if (!authStore.user?.id) return
    
    // Check rate limit
    const { checkRateLimit } = useRateLimit()
    if (!checkRateLimit('toggle_incognito')) {
      return
    }
    
    try {
      const { $api } = useNuxtApp()
      const updatedUser = await ($api as any)('/profile', {
        method: 'PUT',
        body: {
          incognito_mode_enabled: value,
        },
      })
      
      // Update user in auth store
      if (updatedUser && authStore.user) {
        authStore.user.incognito_mode_enabled = updatedUser.incognito_mode_enabled
        if (import.meta.client) {
          localStorage.setItem('auth_user', JSON.stringify(authStore.user))
        }
        
        // Recalculate status immediately
        const newStatus = calculateUserStatus(authStore.user)
        setUserConnectionStatus(authStore.user.id, newStatus)
      }
    } catch (error) {
      console.error('Error saving incognito mode setting:', error)
    }
  }
})

watch(() => settingsStore.privateMessagesEnabled, (enabled) => {
  // Skip if currently updating to prevent recursive calls
  if (settingsStore.isUpdatingPrivateMessages) {
    return
  }
  settingsStore.setPrivateMessagesEnabled(enabled).then(() => {
    // Recalculate status after saving
    if (authStore.user) {
      const newStatus = calculateUserStatus(authStore.user)
      setUserConnectionStatus(authStore.user.id, newStatus)
    }
  })
})

watch(() => settingsStore.notificationsEnabled, (enabled) => {
  // Skip if currently updating to prevent recursive calls
  if (settingsStore.isUpdatingNotifications) {
    return
  }
  settingsStore.setNotificationsEnabled(enabled)
})
</script>

<style scoped>
:deep(.p-inputtext) {
  font-size: 0.875rem;
}

:deep(.p-button) {
  padding: 0.5rem;
}

:deep(.p-button-icon) {
  font-size: 1rem;
}

:deep(.p-sidebar) {
  background: white;
  /* Constrain sidebar to not extend below message input and bottom nav */
  /* Message input area: ~60-70px, bottom nav: ~50-60px, total: ~120-130px */
  /* Set bottom position to account for these elements */
  bottom: 130px !important;
  top: 0 !important;
  height: calc(100vh - 130px) !important;
  max-height: calc(100vh - 130px) !important;
}

/* Sidebar content area - make it scrollable */
:deep(.p-sidebar .p-sidebar-content) {
  height: 100%;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

/* Sidebar header - fixed height, no shrink */
:deep(.p-sidebar .p-sidebar-header) {
  flex-shrink: 0;
  border-bottom: 1px solid #e5e7eb;
}

/* Sidebar body/content wrapper - scrollable */
:deep(.p-sidebar .p-sidebar-body),
:deep(.p-sidebar .p-sidebar-content > div:not(.p-sidebar-header)) {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  min-height: 0;
  -webkit-overflow-scrolling: touch;
}

/* Fade-scale transition for image preview */
.fade-scale-enter-active {
  transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
}

.fade-scale-leave-active {
  transition: all 0.2s ease-in;
}

.fade-scale-enter-from {
  opacity: 0;
  transform: scale(0.9) translateY(-10px);
}

.fade-scale-leave-to {
  opacity: 0;
  transform: scale(0.9) translateY(-10px);
}

/* Premium Entry Notification Animations */
.premium-entry-enter-active {
  transition: all 0.3s ease-out;
}

.premium-entry-leave-active {
  transition: all 0.3s ease-in;
}

.premium-entry-enter-from {
  opacity: 0;
  transform: translateX(-100%);
}

.premium-entry-leave-to {
  opacity: 0;
  transform: translateX(-100%);
}

.premium-entry-notification {
  backdrop-filter: blur(4px);
  border: 2px solid rgba(255, 215, 0, 0.5);
}

/* Bottom Navigation Bar - Mobile Scroll */
.border-t.overflow-x-auto {
  -webkit-overflow-scrolling: touch;
  scrollbar-width: none; /* Firefox */
  -ms-overflow-style: none; /* IE and Edge */
}

.border-t.overflow-x-auto::-webkit-scrollbar {
  display: none; /* Chrome, Safari, Opera */
}


/* Ensure buttons are visible on mobile */
@media (max-width: 640px) {
  .btn-styled {
    font-size: 0.6875rem;
    padding: 0.375rem 0.75rem;
  }
}

/* Private Message Dialog - Mobile Support */
:deep(.private-message-dialog) {
  margin: 0 !important;
  display: flex !important;
  align-items: flex-start !important;
  justify-content: center !important;
  padding-top: 0 !important;
}

/* Target the dialog wrapper/mask on mobile */
@media (max-width: 640px) {
  :deep(.p-dialog-mask.private-message-dialog),
  :deep(.private-message-dialog.p-dialog-mask) {
    align-items: flex-start !important;
    padding-top: 0 !important;
    justify-content: center !important;
  }
  
  :deep(.private-message-dialog) {
    align-items: flex-start !important;
    padding-top: 0 !important;
  }
  
  /* Target the wrapper that contains the dialog */
  :deep(.p-dialog-mask) {
    align-items: flex-start !important;
  }
}

:deep(.private-message-dialog .p-dialog) {
  width: 100vw !important;
  max-width: 100vw !important;
  height: 50vh !important;
  max-height: 50vh !important;
  margin: 0 !important;
  margin-top: 0 !important;
  top: 0 !important;
  transform: none !important;
  border-radius: 0 !important;
  border-bottom-left-radius: 0.5rem !important;
  border-bottom-right-radius: 0.5rem !important;
  position: fixed !important;
}

/* Ensure on mobile it's at the top */
@media (max-width: 640px) {
  :deep(.private-message-dialog .p-dialog) {
    top: 0 !important;
    margin-top: 0 !important;
    transform: none !important;
    position: fixed !important;
  }
}

:deep(.private-message-dialog .p-dialog-content) {
  padding: 0 !important;
  height: 100% !important;
  display: flex !important;
  flex-direction: column !important;
  overflow: hidden !important;
}

:deep(.private-message-dialog .p-dialog-header) {
  padding: 0.75rem 1rem !important;
  flex-shrink: 0 !important;
  border-bottom: 1px solid #e5e7eb !important;
}

@media (min-width: 641px) {
  :deep(.private-message-dialog .p-dialog) {
    width: 90vw !important;
    max-width: 750px !important;
    height: 50vh !important;
    max-height: 50vh !important;
    margin: 0 !important;
    margin-top: 0 !important;
    top: 0 !important;
    transform: none !important;
    border-radius: 0 !important;
    border-bottom-left-radius: 0.5rem !important;
    border-bottom-right-radius: 0.5rem !important;
  }
  
  :deep(.private-message-dialog .p-dialog-content) {
    height: calc(50vh - 60px) !important;
  }
}

/* Touch-friendly buttons on mobile */
@media (max-width: 640px) {
  :deep(.private-message-dialog .p-button) {
    min-width: 32px !important;
    min-height: 32px !important;
  }
  
  :deep(.private-message-dialog .p-inputtext) {
    font-size: 16px !important; /* Prevents zoom on iOS */
    min-width: 0 !important;
    width: 100% !important;
  }
  
  /* Ensure input area fits */
  :deep(.private-message-dialog .p-inputtext input) {
    min-width: 0 !important;
    width: 100% !important;
  }
}

/* Ensure input container fits properly */
:deep(.private-message-dialog .p-inputtext) {
  min-width: 0 !important;
  flex: 1 1 0% !important;
}

/* Ensure buttons don't shrink */
:deep(.private-message-dialog .p-button),
:deep(.private-message-dialog .p-fileupload-choose) {
  flex-shrink: 0 !important;
}

/* Smooth scrolling for messages */
.private-message-dialog :deep([ref="privateMessagesContainer"]) {
  -webkit-overflow-scrolling: touch;
  scroll-behavior: smooth;
}

/* Content area height calculation */
.private-message-content {
  height: calc(50vh - 120px) !important;
}

@media (min-width: 641px) {
  .private-message-content {
    height: calc(50vh - 120px) !important;
  }
}

/* Wall Post Input - Mobile Support */
@media (max-width: 640px) {
  /* Ensure input field fits properly */
  :deep(.p-inputtext) {
    min-width: 0 !important;
  }
  
  /* Ensure buttons are touch-friendly */
  :deep(.p-button) {
    min-width: 28px !important;
    min-height: 28px !important;
  }
  
  /* Wall post input container - ensure proper flex behavior */
  :deep(.p-inputtext input) {
    min-width: 0 !important;
    width: 100% !important;
    font-size: 16px !important; /* Prevents zoom on iOS */
  }
}
</style>
