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
                    <span v-if="message.user?.is_guest" class="text-xs text-gray-400">(زائر)</span>
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

              <!-- Date (at the end) -->
              <div class="flex-shrink-0 mx-2 text-xs text-gray-400 whitespace-nowrap">
                {{ timeSinceArabic(message.created_at) }}
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
              :class="[
                'flex-shrink-0',
                chatStore.connected 
                  ? '!text-green-600 hover:!bg-green-50' 
                  : '!text-orange-600 hover:!bg-orange-50'
              ]"
              @click="toggleSocketConnection" 
              v-tooltip.top="chatStore.connected ? 'قطع الاتصال' : 'اتصال'" 
            >
              <template #icon>
                <Icon 
                  :name="chatStore.connected ? 'solar:link-linear' : 'solar:link-broken-linear'" 
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
              class="flex-1 text-xs sm:text-sm" size="small" :disabled="sending || showPasswordDialog" />
            <Button type="submit" text label="إرسال" :loading="sending" :disabled="!messageContent.trim() || showPasswordDialog"
              class="flex-shrink-0 btn-styled !text-white hover:!text-white border-0 !p-2 sm:!p-2" :style="{ backgroundColor: 'var(--site-button-color, #450924)' }" v-tooltip.top="'إرسال'" />
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
              class="btn-styled flex items-center text-white border border-white px-1.5 sm:px-4 py-0.5 sm:py-1 rounded-tr rounded-bl hover:text-gray-200 transition flex-shrink-0 whitespace-nowrap">
              <i class="pi pi-comment text-xs sm:text-sm mx-0.5 sm:mx-2"></i>
              <span class="text-[12px] sm:text-[14px] font-medium">خاص</span>
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
    <Sidebar v-model:visible="showUsersSidebar" position="right" class="w-80">
      <template #header>
        <div class="flex items-start justify-between w-full">
          <h2 class="text-lg font-bold">المتواجدين</h2>
        </div>
      </template>
      <div>
        <!-- Stories Section -->
        <div class="px-2 mb-2">
          <div class="flex gap-2 overflow-x-auto">
            <div v-for="user in filteredUsers" :key="user.id" class="flex flex-col items-center gap-1 flex-shrink-0">
              <Avatar
                :image="user.avatar_url || getDefaultUserImage()"
                shape="square" class="w-12 h-12 cursor-pointer border-2 border-primary" @click="viewUserStory(user)" />
              <span class="text-xs text-center max-w-[60px] truncate">{{ user.name || user.username }}</span>
            </div>
          </div>
        </div>

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
                      :src="getFlagImageUrl(user.country_code)"
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
                        :src="getFlagImageUrl(user.country_code)"
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
    <Sidebar v-model:visible="showPrivateMessages" position="right" class="w-80">
      <template #header>
        <div class="flex items-center justify-between w-full">
          <h2 class="text-lg font-bold">الرسائل الخاصة</h2>
          <Button icon="pi pi-times" text rounded @click="showPrivateMessages = false" />
        </div>
      </template>
      <div class="space-y-2">
        <div v-if="privateChats.length === 0" class="text-center py-8 text-gray-500">
          لا توجد رسائل خاصة
        </div>
        <div v-for="chat in privateChats" :key="chat.id"
          class="flex items-center gap-3 p-3 hover:bg-gray-100 rounded cursor-pointer"
          @click="openPrivateChat(chat.user as { id: number; name?: string; username?: string; avatar_url?: string })">
          <Avatar :image="chat.user.avatar_url" shape="circle" class="w-10 h-10" />
          <div class="flex-1">
            <div class="font-medium">{{ chat.user.name || chat.user.username }}</div>
            <div class="text-sm text-gray-500 truncate">{{ chat.lastMessage }}</div>
          </div>
          <div class="text-xs text-gray-400">{{ chat.time }}</div>
        </div>
      </div>
    </Sidebar>

    <!-- Rooms List Sidebar -->
    <Sidebar v-model:visible="showRoomsList" position="right" class="w-80">
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
    <Sidebar v-model:visible="showWall" position="right" class="w-96">
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
            class="cursor-pointer transition-all hover:opacity-90 hover:scale-[1.02] rounded-lg overflow-hidden"
          >
            <img 
              src="assets/images/banner.gif" 
              alt="مبدع الحائط" 
              class="w-full h-auto object-cover"
            />
          </div>

          <!-- YouTube Search Bar -->
          <div class="border rounded-lg p-3" :style="{ borderColor: 'var(--site-primary-color, #450924)' }">
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
          <div class="w-80 max-h-96 overflow-y-auto">
            <div v-if="emojiList.length === 0" class="p-4 text-center text-gray-500">
              <p class="text-sm">لا توجد إيموجي متاحة</p>
            </div>
            <div v-else class="grid grid-cols-8 gap-2 p-2">
              <button 
                v-for="emojiId in emojiList" 
                :key="emojiId" 
                @click="insertEmojiToWallPost(emojiId)"
                class="w-10 h-10 p-1 hover:bg-gray-100 rounded transition flex items-center justify-center"
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
            <div v-if="post.image_url || post.youtube_video" class="pb-3">
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
              <div v-else></div>
              
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
          <div v-if="selectedYouTubeVideo || wallPostImagePreview" class="p-3 border-b" 
               :style="{ borderBottomColor: 'var(--site-primary-color, #450924)', backgroundColor: 'rgba(69, 9, 36, 0.05)' }">
            <!-- Selected YouTube Video -->
            <div v-if="selectedYouTubeVideo" class="mb-2 p-2 rounded flex items-center justify-between bg-white"
                 :style="{ borderColor: 'var(--site-primary-color, #450924)' }">
              <div class="flex gap-2 flex-1 min-w-0">
                <img :src="selectedYouTubeVideo.thumbnail" alt="" class="w-16 h-12 object-cover rounded" />
                <div class="flex-1 min-w-0">
                  <div class="text-xs font-medium line-clamp-2">{{ selectedYouTubeVideo.title }}</div>
                  <div class="text-xs text-gray-500 mt-1">{{ selectedYouTubeVideo.channelTitle || 'YouTube' }}</div>
                </div>
              </div>
              <Button 
                icon="pi pi-times" 
                text 
                rounded 
                size="small"
                @click="selectedYouTubeVideo = null"
                class="flex-shrink-0"
              />
            </div>

            <!-- Selected Image Preview -->
            <div v-if="wallPostImagePreview" class="relative">
              <img :src="wallPostImagePreview" alt="Preview" class="w-full h-32 object-cover rounded border" 
                   :style="{ borderColor: 'var(--site-primary-color, #450924)' }" />
              <Button 
                icon="pi pi-times" 
                text 
                rounded 
                severity="danger"
                class="absolute top-1 right-1 bg-white/90"
                @click="wallPostImageFile = null; wallPostImagePreview = null"
              />
            </div>
          </div>

          <!-- Post Form -->
          <div class="flex items-center justify-between p-2" :style="{ backgroundColor: 'var(--site-secondary-color, #ffffff)' }">
            <div class="flex items-center justify-between gap-1 w-full">
              <Button 
                icon="pi pi-image" 
                label="" 
                text
                size="small"
                @click="wallPostImageInput?.click()"
                :style="{ color: 'var(--site-primary-color, #450924)' }"
              />
              <input 
                ref="wallPostImageInput"
                type="file" 
                accept="image/*" 
                class="hidden" 
                @change="handleWallPostImageSelect"
              />
              <Button ref="emojiButton" type="button" rounded severity="secondary" class="flex-shrink-0 !p-0 w-8 h-8 sm:w-7 sm:h-7"
                @click="wallEmojiPanel.toggle($event)">
                <img 
                  :src="emojiList.length > 0 ? getEmojiPath(emojiList[0]) : getEmojiPath(0)" 
                  width="16" 
                  height="16" 
                  class="!p-0 md:w-6 md:h-6 sm:w-5 sm:h-5"
                  alt="Emoji"
                >
              </Button>
              <InputText ref="messageInput" v-model="wallPost" placeholder="اكتب رسالتك هنا..."
                class="flex-1 text-xs sm:text-sm" size="small" :disabled="postingToWall" />
              <Button 
                type="button"
                @click="postToWall" 
                text 
                label="إرسال" 
                :loading="postingToWall" 
                :disabled="(!wallPost.trim() && !wallPostImageFile && !selectedYouTubeVideo) || postingToWall"
                class="flex-shrink-0 btn-styled !text-white hover:!text-white border-0 !p-2 sm:!p-2" 
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
    <Sidebar v-model:visible="showSettings" position="right" class="w-96">
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
      <div class="space-y-6 max-h-[calc(100vh-100px)] overflow-y-auto">
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
              <InputSwitch v-model="settingsStore.privateMessagesEnabled" />
            </div>
            <div class="flex items-center justify-between">
              <label class="text-sm">تفعيل الإشعارات</label>
              <InputSwitch v-model="settingsStore.notificationsEnabled" />
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
        <div class="flex gap-2 pt-4 border-t">
          <Button label="حفظ" icon="pi pi-check" @click="saveSettings" :loading="savingSettings" class="flex-1" />
          <Button label="تسجيل الخروج" icon="pi pi-sign-out" severity="danger" @click="handleLogout" class="flex-1" />
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
            :src="getFlagImageUrl(selectedUser.country_code)"
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
              v-if="hasRole(20)"
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

    <!-- Report User Dialog -->
    <ReportUserDialog
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
  </div>
</template>

<script setup lang="ts">
// @ts-ignore - Nuxt auto-imports types
import moment from 'moment'
import { useToast } from 'primevue/usetoast'
// @ts-ignore
import type { Message, User, Room, RoleGroup } from '~/types'
import { getFlagImageUrl } from '~~/app/utils/flagImage'

definePageMeta({
  middleware: 'auth',
})

const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()
const chatStore = useChatStore()
const settingsStore = useSettingsStore()
const { getEcho, initEcho, disconnect } = useEcho()
const toast = useToast()
const { getDefaultUserImage, getSystemMessagesImage } = useSiteSettings()
const { initBootstrap, getBootstrap } = useBootstrap()

const roomId = computed(() => route.params.id as string)
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
    console.log('[sortedMessages] Total messages:', allMessages.length, 'System:', systemCount, 'Welcome:', welcomeCount)
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
  
  // Debug: log first few rooms to see what's happening
  if (room.id && room.id <= 3) {
    console.log(`Room ${room.id} getRoomNameColor:`, {
      hasSettings: !!room.settings,
      settingsType: typeof room.settings,
      parsedSettings: roomSettings,
      roomNameColor: roomSettings?.roomNameColor,
    })
  }
  
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
      if (room.id && room.id <= 3) {
        console.log(`Room ${room.id} returning color:`, color)
      }
      return color
    } else {
      if (room.id && room.id <= 3) {
        console.log(`Room ${room.id} invalid color format:`, color)
      }
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
      console.log('[Users Sidebar] Fetched active users:', chatStore.displayActiveUsers.length)
    } catch (error) {
      console.error('[Users Sidebar] Error fetching active users:', error)
    }
  }
})

watch(showPrivateMessages, (isOpen) => {
  if (isOpen) {
    // Close other sidebars
    showUsersSidebar.value = false
    showRoomsList.value = false
    showWall.value = false
    showSettings.value = false
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
  
  // Debug logging
  if (import.meta.dev) {
    console.log('[Users Sidebar] Current room user IDs:', Array.from(currentRoomUserIds))
    console.log('[Users Sidebar] All active users:', allActiveUsers.length)
    console.log('[Users Sidebar] Active users:', allActiveUsers.map((u: User) => ({ id: u.id, name: u.name || u.username })))
  }
  
  const otherUsers = allActiveUsers.filter((user: User) => !currentRoomUserIds.has(user.id))
  
  if (import.meta.dev) {
    console.log('[Users Sidebar] Other rooms users:', otherUsers.length)
    console.log('[Users Sidebar] Other rooms users list:', otherUsers.map((u: User) => ({ id: u.id, name: u.name || u.username })))
  }
  
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
    console.log('✅ [PREMIUM ENTRY] User has premium_entry enabled, proceeding...')
    
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
      console.log('👤 [PREMIUM ENTRY] Using authStore data for current user:', finalUser)
    }
    
    // Ensure background URL is properly formatted
    let backgroundUrl = finalUser.premium_entry_background || null
    console.log('🎨 [PREMIUM ENTRY] backgroundUrl before formatting:', backgroundUrl)
    
    if (backgroundUrl && !backgroundUrl.startsWith('http') && !backgroundUrl.startsWith('/')) {
      // If it's a relative path, make it absolute
      const config = useRuntimeConfig()
      const baseUrl = config.public.apiBaseUrl || ''
      backgroundUrl = `${baseUrl}${backgroundUrl.startsWith('/') ? '' : '/'}${backgroundUrl}`
      console.log('🎨 [PREMIUM ENTRY] backgroundUrl after formatting:', backgroundUrl)
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
    
    console.log('✨ [PREMIUM ENTRY] Creating notification object:', notification)
    console.log('📋 [PREMIUM ENTRY] Current notifications array length:', premiumEntryNotifications.value.length)
    console.log('📋 [PREMIUM ENTRY] Current notifications:', JSON.stringify(premiumEntryNotifications.value))
    premiumEntryNotifications.value.push(notification)
    console.log('✅ [PREMIUM ENTRY] Notification added! New array length:', premiumEntryNotifications.value.length)
    console.log('📋 [PREMIUM ENTRY] Full notifications array after push:', JSON.stringify(premiumEntryNotifications.value))
    
    // Auto-remove after 5 seconds
    setTimeout(() => {
      console.log('⏰ [PREMIUM ENTRY] Removing notification after 5 seconds:', notification.id)
      const index = premiumEntryNotifications.value.findIndex(n => n.id === notification.id)
      if (index !== -1) {
        premiumEntryNotifications.value.splice(index, 1)
        console.log('🗑️ [PREMIUM ENTRY] Notification removed. Remaining:', premiumEntryNotifications.value.length)
      }
    }, 5000)
  } else {
    console.log('❌ [PREMIUM ENTRY] User does not have premium_entry enabled')
    console.log('❌ [PREMIUM ENTRY] user.premium_entry:', user.premium_entry)
    console.log('❌ [PREMIUM ENTRY] authStore.user?.premium_entry:', authStore.user?.premium_entry)
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
  // Fetch full user data to ensure we have all fields including social media
  let fullUserData: User = user
  try {
    const { $api } = useNuxtApp()
    const fullUser = await ($api as any)(`/users/${user.id}`)
    fullUserData = fullUser
    selectedUser.value = fullUser
  } catch (error) {
    // Fallback to provided user data if fetch fails
    console.warn('Failed to fetch full user data, using provided data:', error)
    selectedUser.value = user
  }
  
  // Initialize admin form with user data
  // Get the first (highest priority) role group
  const primaryRoleGroup = (fullUserData.role_groups || [])[0]
  let roleGroupId: number | null = null
  let roleGroupExpiration: Date | null = null
  
  // Initialize expiration date from user's role group (if available in pivot)
  if (primaryRoleGroup) {
    roleGroupId = primaryRoleGroup.id
    const pivot = (primaryRoleGroup as any).pivot
    if (pivot && pivot.expires_at) {
      roleGroupExpiration = new Date(pivot.expires_at)
    } else {
      roleGroupExpiration = null // Infinite/no expiration
    }
  }
  
  adminEditForm.value = {
    name: fullUserData.name || fullUserData.username || '',
    likes: (fullUserData as any).likes || 0,
    points: (fullUserData as any).points || 0,
    bio: fullUserData.bio || '',
    roleGroupId,
    roleGroupExpiration,
    roomId: null,
    roomPassword: ''
  }
  
  // Fetch role groups if not already loaded
  if (availableRoleGroups.value.length === 0) {
    await fetchRoleGroups()
  }
  
  // Fetch rooms if not already loaded (for admin room selector)
  if (canManageUser.value && (!chatStore.displayRooms || chatStore.displayRooms.length === 0)) {
    await chatStore.fetchRooms()
  }
  
  showUserProfileModal.value = true
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
  console.log('Notify user:', selectedUser.value.id)
}

const likeUser = async () => {
  if (!selectedUser.value) return
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
  console.log('Send gift to user:', selectedUser.value.id)
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
  console.log('Set banner for user:', selectedUser.value.id)
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
    console.log('Fetched rooms:', rooms?.length, 'rooms')
    // Debug: check first room's settings
    if (rooms && rooms.length > 0) {
      console.log('First room settings:', {
        roomId: rooms[0].id,
        roomName: rooms[0].name,
        hasSettings: !!rooms[0].settings,
        settings: rooms[0].settings,
        settingsType: typeof rooms[0].settings,
      })
    }
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
    
    console.log('Loading room settings:', {
      roomSettings: room.settings,
      formValues: roomSettingsForm.value,
    })
    
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
    
    console.log('Form values:', {
      backgroundColor: roomSettingsForm.value.backgroundColor,
      textColor: roomSettingsForm.value.textColor,
      roomNameColor: roomSettingsForm.value.roomNameColor,
      roomBorderColor: roomSettingsForm.value.roomBorderColor,
      roomDescriptionColor: roomSettingsForm.value.roomDescriptionColor,
    })
    
    console.log('Converted color values:', {
      backgroundColor: bgColor,
      textColor: txtColor,
      roomNameColor: nameColor,
      roomBorderColor: borderColor,
      roomDescriptionColor: descColor,
    })
    
    console.log('Saving settings:', settings) // Debug log
    
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
    
    console.log('Updated room response:', updatedRoom) // Debug log
    console.log('Updated room settings:', updatedRoom.settings) // Debug log
    
    // Update current room in store
    chatStore.setCurrentRoom(updatedRoom)
    
    // Also update the room in the rooms list
    const roomIndex = chatStore.rooms.findIndex((r: Room) => r.id === updatedRoom.id)
    if (roomIndex !== -1) {
      chatStore.rooms[roomIndex] = updatedRoom
      console.log('Updated room in rooms list:', updatedRoom.id, updatedRoom.settings)
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
const privateChats = ref<any[]>([])

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
let currentPresenceChannel: any = null
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
  console.log('🔵 setupScheduledMessages called for room:', roomId)
  
  // Clean up any existing intervals first
  cleanupScheduledMessages()
  
  console.log('🔵 Current room:', chatStore.currentRoom)
  console.log('🔵 Scheduled messages:', chatStore.currentRoom?.scheduled_messages)
  console.log('🔵 Scheduled messages type:', typeof chatStore.currentRoom?.scheduled_messages)
  console.log('🔵 Scheduled messages is array:', Array.isArray(chatStore.currentRoom?.scheduled_messages))
  
  if (!chatStore.currentRoom) {
    console.warn('⚠️ No current room, cannot set up scheduled messages')
    return
  }
  
  if (!chatStore.currentRoom.scheduled_messages || !Array.isArray(chatStore.currentRoom.scheduled_messages)) {
    console.warn('⚠️ No scheduled messages found in room data')
    console.warn('⚠️ scheduled_messages value:', chatStore.currentRoom.scheduled_messages)
    return
  }
  
  console.log('🔵 All scheduled messages before filter:', chatStore.currentRoom.scheduled_messages)
  const scheduledMessages = chatStore.currentRoom.scheduled_messages.filter((msg: any) => {
    const isActive = msg.is_active !== false && msg.is_active !== undefined
    console.log(`🔵 Message ${msg.id} (${msg.title}): is_active=${msg.is_active}, will include=${isActive}`)
    return isActive
  })
  console.log('✅ Active scheduled messages:', scheduledMessages.length)
  
  for (const msg of scheduledMessages) {
    console.log('🟢 Processing scheduled message:', msg.id, msg.type, msg.title)
    
    if (msg.type === 'welcoming') {
      // Send welcoming messages after room welcome/system messages are sent (one-time only, does not recur)
      // Wait for welcome/system messages to be sent first
      setTimeout(() => {
        console.log('📤 Sending welcoming scheduled message:', msg.title, '(one-time only, will not recur)')
        sendLocalScheduledMessage(msg, roomId)
      }, 1000) // Wait 1 second after room welcome messages
    } else if (msg.type === 'daily') {
      // Set up interval for daily messages based on time_span (recurring every time_span minutes)
      const intervalMs = msg.time_span * 60 * 1000 // Convert minutes to milliseconds
      console.log('⏰ Setting up daily message interval:', msg.title, 'every', msg.time_span, 'minutes (', intervalMs, 'ms)')
      
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
        console.log(`🔄 Interval fired for message ID: ${messageId}, roomId: ${roomId}`)
        console.log(`🔄 Current room ID: ${chatStore.currentRoom?.id}, matches: ${chatStore.currentRoom && String(chatStore.currentRoom.id) === String(roomId)}`)
        
        // Check if we're still in the same room
        if (chatStore.currentRoom && String(chatStore.currentRoom.id) === String(roomId)) {
          // Look up the scheduled message from loaded room data
          const scheduledMessages = chatStore.currentRoom.scheduled_messages || []
          console.log(`🔄 Looking for message ${messageId} in ${scheduledMessages.length} scheduled messages`)
          
          let currentMessage = scheduledMessages.find((m: any) => m.id === messageId && m.is_active)
          
          // Fallback to original message if not found in current room data (in case room data was refreshed)
          if (!currentMessage && originalMessage.is_active) {
            console.log(`⚠️ Message not found in room data, using original message data for ID: ${messageId}`)
            currentMessage = originalMessage
          }
          
          if (currentMessage && currentMessage.is_active) {
            console.log(`✅ [Recurring Message] Sending "${currentMessage.title}" (repeats every ${currentMessage.time_span} minutes)`)
            sendLocalScheduledMessage(currentMessage, roomId)
            
            // Update next send time and restart countdown
            const newNextSendTime = Date.now() + intervalMs
            scheduledMessageNextSendTime.value.set(messageId, newNextSendTime)
            console.log(`✅ [Recurring Message] Next "${currentMessage.title}" will be sent in ${currentMessage.time_span} minutes`)
            startCountdown()
          } else {
            console.warn(`⚠️ Scheduled message ${messageId} not found or inactive. Available messages:`, scheduledMessages.map((m: any) => ({ id: m.id, title: m.title, is_active: m.is_active })))
            console.warn(`⚠️ Original message active status: ${originalMessage.is_active}`)
            // Don't clean up - keep trying in case the room data gets refreshed
            // The message might be temporarily unavailable but could come back
          }
        } else {
          // Clean up if we've left the room
          console.log(`⚠️ Cleaning up interval - left room. Current: ${chatStore.currentRoom?.id}, Expected: ${roomId}`)
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
      console.log('✅ Interval set up for message:', msg.id, 'Interval ID:', intervalId)
      console.log(`⏰ Next "${msg.title}" message will be sent in ${msg.time_span} minutes`)
    }
  }
  
  console.log('✅ Total intervals set up:', scheduledMessageIntervals.value.size)
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

const setReplyTo = (message: Message) => {
  replyingTo.value = message
  // Focus on input
  nextTick(() => {
    if (messageInput.value && messageInput.value.$el) {
      const input = messageInput.value.$el.querySelector('input') || messageInput.value.$el
      if (input) {
        input.focus()
      }
    }
  })
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
  replyingTo.value = null
  
  sending.value = true

  try {
    // Mark current user as active when sending a message
    if (authStore.user?.id) {
      markUserActiveOnSocket(authStore.user.id)
    }

    // Send message to backend (will be stored in database)
    const sentMessage = await chatStore.sendMessage(roomId.value, content, meta)

    // Message will be added via Echo broadcast, but we can also add it optimistically
    // if the backend returns the message immediately
    if (sentMessage && sentMessage.id) {
      // Ensure the message has the correct room_id
      const messageWithRoom = {
        ...sentMessage,
        room_id: Number(roomId.value),
      }
      chatStore.addMessage(messageWithRoom)
    }

    scrollToBottom()
    
    // Premium entry notification only shows when joining/moving rooms (with 3 second delay)
  } catch (error: any) {
    // Check if user is banned
    if (error?.data?.banned || (error?.status === 403 && error?.data?.message?.includes('banned'))) {
      // Ban is handled by useApi composable, just return
      return
    }
    
    // Restore message content and reply state on error
    messageContent.value = content
    if (wasReplying) {
      // Try to restore reply if we can find the message
      const replyId = replyingTo.value?.id
      if (replyId) {
        const originalMessage = chatStore.messages.find((m: Message) => m.id === replyId)
        if (originalMessage) {
          replyingTo.value = originalMessage
        }
      }
    }
    console.error('Error sending message:', error)
    // You could show a toast notification here
  } finally {
    sending.value = false
  }
}

// Don't load messages from database - only show messages received via real-time during the session
// Messages will clear on page refresh (store resets)

// Users sidebar functions
const viewUserStory = (user: { id: number; name?: string; username?: string }) => {
  // TODO: Implement story viewing
  console.log('View story for:', user)
}

const openPrivateChat = (user: { id: number; name?: string; username?: string; avatar_url?: string }) => {
  showUsersSidebar.value = false
  showPrivateMessages.value = true
  // TODO: Open private chat with user
  console.log('Open private chat with:', user)
}

// Wall functions
const loadingWallPosts = ref(false)
const postingToWall = ref(false)

const fetchWallPosts = async () => {
  if (!roomId.value) return
  
  loadingWallPosts.value = true
  try {
    const { api } = useApi()
    const response = await api(`/chat/${roomId.value}/wall-posts`)
    // Handle paginated response
    wallPosts.value = response.data || response || []
    
    // Add image URLs to posts
    wallPosts.value.forEach((post: any) => {
      if (post.image) {
        post.image_url = post.image.startsWith('http') ? post.image : `${useRuntimeConfig().public.apiBaseUrl.replace('/api', '')}/storage/${post.image}`
      }
    })
    
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

const postToWall = async () => {
  if ((!wallPost.value.trim() && !wallPostImageFile.value && !selectedYouTubeVideo.value) || !roomId.value) return
  
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

const userConnectionStatus = ref<Record<number, 'online' | 'afk' | 'disconnected' | 'internet_disconnected'>>({})
const userLastActivity = ref<Record<number, number>>({})
// Track users who left rooms - used to detect "moved" vs "joined" actions
// Note: "left" messages are ONLY sent on socket disconnect, not when users leave rooms
const usersLeavingRooms = ref<Record<number, { 
  roomId: number, 
  timestamp: number, 
  timeoutId: ReturnType<typeof setTimeout>
}>>({})

const setUserConnectionStatus = (userId: number, status: 'online' | 'afk' | 'disconnected' | 'internet_disconnected') => {
  userConnectionStatus.value = {
    ...userConnectionStatus.value,
    [userId]: status,
  }
}

const markUserActiveOnSocket = (userId: number) => {
  if (!userId) return
  userLastActivity.value = {
    ...userLastActivity.value,
    [userId]: Date.now(),
  }
  setUserConnectionStatus(userId, 'online')
}

const getUserConnectionStatusClass = (user: any) => {
  if (!user?.id) return 'bg-gray-300'

  const status = userConnectionStatus.value[user.id]

  switch (status) {
    case 'online':
      return 'bg-green-500'
    case 'afk':
      return 'bg-yellow-400'
    case 'disconnected':
      return 'bg-red-500'
    case 'internet_disconnected':
      return 'bg-gray-400'
    default:
      return 'bg-gray-300'
  }
}

// Ping/Pong mechanism for real-time activity tracking
const startPingPong = (channel: any) => {
  // Clear any existing ping interval
  if (pingInterval) {
    clearInterval(pingInterval)
    pingInterval = null
  }

  // Listen for pong responses from other users
  channel.listenForWhisper('pong', (data: any) => {
    if (data && data.user_id && typeof data.user_id === 'number') {
      // Mark user as active when they respond to ping
      markUserActiveOnSocket(data.user_id)
    }
  })

  // Send ping every 30 seconds to check user activity
  pingInterval = setInterval(() => {
    if (channel && authStore.user?.id) {
      try {
        // Send ping via whisper to all members in the presence channel
        channel.whisper('ping', {
          user_id: authStore.user.id,
          timestamp: Date.now(),
        })
        
        // Mark current user as active when sending ping
        markUserActiveOnSocket(authStore.user.id)
      } catch (error) {
        console.error('Error sending ping:', error)
      }
    }
  }, 30000) // Ping every 30 seconds
}

// Stop ping/pong when leaving channel
const stopPingPong = () => {
  if (pingInterval) {
    clearInterval(pingInterval)
    pingInterval = null
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
      console.log(`Sent "left" message to ALL users in room ${roomId.value} via socket whisper (on logout)`)
      
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
        console.log(`Sent "left" message to ALL users in room ${roomId.value} via socket whisper`)
        
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
                console.log('Preventing auto-reconnect - user manually disconnected')
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
      toast.add({
        severity: 'warn',
        summary: 'غير مصرح',
        detail: 'يجب تسجيل الدخول أولاً',
        life: 3000,
      })
    }
  }
}

const navigateToRoom = async (id: number) => {
  showRoomsList.value = false
  
  // If already in this room, don't do anything
  if (String(roomId.value) === String(id)) {
    return
  }
  
  // Don't navigate if password dialog is currently open
  if (showPasswordDialog.value) {
    console.log('Password dialog is open, cannot navigate to another room')
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
      console.log(`Sent "moved" message to old room ${oldRoomId} via socket (for other users only)`)
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
  
  // Update route - router.replace won't cause a full page reload in Nuxt
  // The route change will trigger the watch on roomId to handle channel switching
  await router.replace(`/chat/${id}`)
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
    
    // Navigate to the room after password validation
    // The watch handler will now proceed since password dialog is closed and validated
    await router.replace(`/chat/${targetId}`)
    
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
  
  // Stay on current room - redirect back to previous room if available
  if (previousRoomId) {
    router.replace(`/chat/${previousRoomId}`)
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
    
    console.log('📤 [PROGRAMMATIC] Sending notifications for current user:', user.id)
    
    // Show premium entry notification if enabled
    if (user.premium_entry) {
      console.log('🎯 [PREMIUM ENTRY] Current user has premium_entry, showing notification programmatically')
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
      console.log('📤 [PROGRAMMATIC] Adding system message for current user:', systemMessage)
      console.log('📤 [PROGRAMMATIC] System message room_id:', systemMessage.room_id, 'Current room ID:', currentRoomId)
      chatStore.addMessage(systemMessage)
      nextTick(() => {
        scrollToBottom()
        console.log('📤 [PROGRAMMATIC] Total messages after adding:', chatStore.messages.length)
        console.log('📤 [PROGRAMMATIC] System messages count:', chatStore.messages.filter((m: Message) => m.meta?.is_system).length)
      })
    } else {
      console.log('📤 [PROGRAMMATIC] Duplicate system message found, skipping')
    }
  }

  channel.subscribed(async () => {
    console.log('Successfully subscribed to presence channel:', `room.${currentRoomId}`)
    
    // Store channel reference for ping/pong
    currentPresenceChannel = channel
    
    // Start ping/pong mechanism for activity tracking
    startPingPong(channel)
    
    // Send "joined" message to all users when reconnecting (only if socket was previously disconnected)
    // Only send on reconnect (not initial join) - check if there was a previous disconnect
    if (wasDisconnected.value && authStore.user && String(currentRoomId) === String(roomId.value)) {
      // Reset the flag after sending
      wasDisconnected.value = false
      // Small delay to ensure channel is fully ready
      await new Promise(resolve => setTimeout(resolve, 300))
      
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
        content: `${user.name || user.username} انضم إلى الغرفة`,
        meta: {
          is_system: true,
          action: 'joined',
        },
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      }
      
      try {
        // Send via whisper to ALL other users in the room (not to the current user)
        channel.whisper('user.joined', joinedMessage)
        console.log(`Sent "joined" message to ALL other users in room ${currentRoomId} via socket whisper (on reconnect)`)
        // Note: We don't add it locally - the reconnecting user will see the normal message in the room
      } catch (error) {
        console.error('Failed to send "joined" message via socket:', error)
        // Don't add locally even if whisper fails - only other users should see it
      }
    }
  })
  
  // Use presence channel events for join/leave notifications
  channel.here((users: any[]) => {
    console.log('Users currently in room:', users)
    if (chatStore.currentRoom) {
      chatStore.currentRoom.users = users
    }

    // Mark all users currently in room as online from socket presence
    users.forEach((u: any) => {
      if (u && typeof u.id === 'number') {
        markUserActiveOnSocket(u.id)
      }
    })
    
    // Send programmatic notifications for current user after room is loaded
    sendCurrentUserNotifications()
    
    // Welcome message logic for current user (only in here() callback)
    if (authStore.user && chatStore.currentRoom) {
      setTimeout(() => {
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
          
          console.log('Channel.here: Adding welcome message:', welcomeMessage)
          console.log('Channel.here: Welcome message room_id:', welcomeMessage.room_id, 'Current room ID:', currentRoomId)
          chatStore.addMessage(welcomeMessage)
          nextTick(() => {
            scrollToBottom()
            console.log('Channel.here: Total messages after adding welcome:', chatStore.messages.length)
          })
        }
      }, 500)
    }

  })
  
  channel.joining(async (user: any) => {
    console.log('Channel.joining: User joining room:', user, 'Current room ID:', currentRoomId, 'Previous room ID:', previousRoomId)
    
    // Skip current user - we'll handle them programmatically
    const isCurrentUser = user && user.id === authStore.user?.id
    if (isCurrentUser) {
      console.log('Channel.joining: Skipping current user, will handle programmatically')
      return
    }
    
    if (user && typeof user.id === 'number') {
      // Mark newly joined user as online from socket presence
      markUserActiveOnSocket(user.id)
    }

    if (chatStore.currentRoom?.users && user && user.id) {
      const existingUser = chatStore.currentRoom.users.find((u: any) => u.id === user.id)
      if (!existingUser) {
        chatStore.currentRoom.users.push(user)
      }
    }
    
    // Show premium entry notification for other users
    if (user && user.premium_entry) {
      console.log('👥 [PREMIUM ENTRY] Other user joining with premium entry:', user)
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
        
        console.log(`User ${userId} moved from room ${oldRoomId} to room ${currentRoomId}`)
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
        console.log('Channel.joining: Adding system message for other user:', systemMessage)
        console.log('Channel.joining: System message room_id:', systemMessage.room_id, 'Current room ID:', currentRoomId)
        chatStore.addMessage(systemMessage)
        nextTick(() => {
          scrollToBottom()
          console.log('Channel.joining: Total messages after adding:', chatStore.messages.length)
        })
      }
    }
  })
  
  channel.leaving((user: any) => {
    console.log('User leaving room:', user)
    
    if (chatStore.currentRoom?.users && user && user.id) {
      chatStore.currentRoom.users = chatStore.currentRoom.users.filter((u: any) => u.id !== user.id)
    }
    
    // Track that this user left the room - we'll use this to detect "moved" vs "joined" when they join another room
    // We do NOT send "left" messages here - only on socket disconnect
    if (user && user.id) {
      const userId = user.id
      const roomId = Number(currentRoomId)
      
      // Clear any existing tracking for this user
      if (usersLeavingRooms.value[userId]?.timeoutId) {
        clearTimeout(usersLeavingRooms.value[userId].timeoutId)
      }
      
      // Track this user leaving (for detecting "moved" when they join another room)
      // We'll clean this up when they join another room or after a reasonable time
      usersLeavingRooms.value[userId] = {
        roomId: roomId,
        timestamp: Date.now(),
        timeoutId: setTimeout(() => {
          // Clean up old tracking after 10 seconds if user hasn't joined another room
          // This is just cleanup, we don't send "left" messages here
          delete usersLeavingRooms.value[userId]
        }, 10000) as ReturnType<typeof setTimeout>
      }
      
      // Mark user as disconnected when they leave presence channel (for status indicator only)
      setUserConnectionStatus(user.id, 'disconnected')
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

  // Listen for new wall posts
  channel.listen('.wall.post.sent', (data: any) => {
    if (String(data.room_id) === String(currentRoomId)) {
      const existingPost = wallPosts.value.find((p: any) => p.id === data.id)
      if (!existingPost) {
        // Add image URL if exists
        if (data.image) {
          data.image_url = data.image.startsWith('http') ? data.image : `${useRuntimeConfig().public.apiBaseUrl.replace('/api', '')}/storage/${data.image}`
        }
        wallPosts.value.unshift(data)
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
      }
    }
  })

  // Listen for "joined" events from other users via whisper
  channel.listenForWhisper('user.joined', (data: any) => {
    console.log('Received "user.joined" whisper event:', data)
    if (data && data.meta?.is_system && data.meta?.action === 'joined') {
      // Don't add the message if it's from the current user (they're the one joining)
      if (data.user_id === authStore.user?.id) {
        console.log('Skipping "joined" message - it\'s from the current user (joining user)')
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
          console.log('Adding "joined" message from whisper event (for other user):', data)
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
    console.log('Received "user.left" whisper event:', data)
    if (data && data.meta?.is_system && data.meta?.action === 'left') {
      // Don't add the message if it's from the current user (they're the one leaving)
      if (data.user_id === authStore.user?.id) {
        console.log('Skipping "left" message - it\'s from the current user (leaving user)')
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
          console.log('Adding "left" message from whisper event (for other user):', data)
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
    console.log('Received "user.moved" whisper event:', data)
    if (data && data.meta?.is_system && data.meta?.action === 'moved') {
      // Don't add the message if it's from the current user (they're the one moving)
      if (data.user_id === authStore.user?.id) {
        console.log('Skipping "moved" message - it\'s from the current user (moving user)')
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
          console.log('Adding "moved" message from whisper event (for other user):', data)
          chatStore.addMessage(data)
          nextTick(() => {
            scrollToBottom()
          })
        }
      }
    }
  })

  // Listen for ping events and respond with pong
  channel.listenForWhisper('ping', (data: any) => {
    if (data && data.user_id && typeof data.user_id === 'number' && authStore.user?.id) {
      // Respond with pong to indicate we're active
      try {
        channel.whisper('pong', {
          user_id: authStore.user.id,
          timestamp: Date.now(),
        })
        // Mark the ping sender as active
        markUserActiveOnSocket(data.user_id)
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
      console.log('Channel.presence: Skipping current user, handled programmatically')
      return
    }
    
    if (data.status === 'offline' && data.user) {
      // Mark user as disconnected in status map based purely on socket presence
      setUserConnectionStatus(data.user.id, 'disconnected')
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
        console.log('👥 [PREMIUM ENTRY] Other user coming online with premium entry:', data.user)
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
      setUserConnectionStatus(data.user.id, 'afk')
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
  // Fetch emojis from database (cached for quick loading)
  try {
    await fetchEmojis()
    emojiList.value = getEmojiList()
  } catch (error) {
    console.error('Error loading emojis:', error)
    // Fallback to empty list - legacy emojis will still work via getEmojiPath fallback
    emojiList.value = []
  }

  // Fetch settings from API (user-scoped)
  if (authStore.isAuthenticated) {
    await settingsStore.fetchFromAPI()
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
  setInterval(() => {
    const now = Date.now()
    const AFK_THRESHOLD_MS = 5 * 60 * 1000 // 5 minutes

    Object.entries(userLastActivity.value).forEach(([id, ts]) => {
      const userId = Number(id)
      if (!userId || !ts) return

      const diff = now - ts
      const currentStatus = userConnectionStatus.value[userId]

      if (diff >= AFK_THRESHOLD_MS && currentStatus === 'online') {
        setUserConnectionStatus(userId, 'afk')
      } else if (diff < AFK_THRESHOLD_MS && (currentStatus === 'afk' || !currentStatus)) {
        setUserConnectionStatus(userId, 'online')
      }
    })
  }, 60000) // check every minute

  // Load user profile data
  if (authStore.user) {
    profileForm.value.name = authStore.user.name || ''
    profileForm.value.bio = authStore.user.bio || ''
    profileForm.value.social_media_type = authStore.user.social_media_type || null
    profileForm.value.social_media_url = authStore.user.social_media_url || null
  }

  // Check if password dialog is open - if so, don't proceed with setup
  if (showPasswordDialog.value) {
    console.log('Password dialog is open, waiting for validation before proceeding')
    return
  }

  // Load room first, then messages from database
  try {
    await chatStore.fetchRoom(roomId.value)
    // Password validation successful - reset flag
    isPasswordValidated.value = true
    
    // Load bootstrap data after room is successfully loaded (non-blocking)
    // This loads site settings, rooms, and shortcuts in one request
    try {
      await initBootstrap()
      const bootstrap = getBootstrap()
      
      if (bootstrap) {
        // Load site settings from bootstrap
        const { loadFromBootstrap } = useSiteSettings()
        loadFromBootstrap(bootstrap.site_settings)
        
        // Load rooms from bootstrap (if not already loaded)
        if (bootstrap.rooms && bootstrap.rooms.length > 0) {
          chatStore.loadRoomsFromBootstrap(bootstrap.rooms)
        }
        
        console.log('Bootstrap data loaded after joining room:', {
          settings: Object.keys(bootstrap.site_settings).length,
          rooms: bootstrap.rooms.length,
          shortcuts: bootstrap.shortcuts.length,
        })
      }
    } catch (bootstrapError) {
      console.error('Error loading bootstrap data:', bootstrapError)
      // Non-critical - continue without bootstrap data
    }
    
    // Fetch active users after room is loaded (non-blocking)
    try {
      await chatStore.fetchActiveUsers()
      console.log('Active users loaded after joining room:', chatStore.displayActiveUsers.length)

      // Mark all active users (in any room) as online based on socket/global activity
      const allActiveUsers = chatStore.displayActiveUsers || []
      allActiveUsers.forEach((u: User) => {
        if (u && typeof u.id === 'number') {
          markUserActiveOnSocket(u.id)
        }
      })
    } catch (error) {
      console.error('Error loading active users:', error)
      // Non-critical - continue without active users
    }
    
    // Fetch wall posts for the room
    await fetchWallPosts()
  } catch (error: any) {
    console.log('Error fetching room:', error)
    console.log('Error data:', error.data)
    console.log('Error status:', error.status)
    
    // Check if room requires password
    if (error.data?.requires_password || error.status === 403 || error.message?.includes('password')) {
      passwordProtectedRoomId.value = Number(roomId.value)
      passwordProtectedRoomName.value = error.data?.room_name || 'الغرفة'
      showPasswordDialog.value = true
      isPasswordValidated.value = false // Reset validation flag
      chatStore.loading = false
      // Redirect back to previous room if available, otherwise stay on current route
      if (previousRoomId) {
        await router.replace(`/chat/${previousRoomId}`)
      } else if (chatStore.currentRoom?.id) {
        // If we have a current room, redirect to it
        await router.replace(`/chat/${chatStore.currentRoom.id}`)
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

  // Set up scheduled messages for this room (local, user-specific)
  // Wait for room data to be loaded and for welcome/system messages to be sent first
  // Welcome messages are sent in channel.subscribed() with 500ms delay, so wait longer
  await nextTick()
  await new Promise(resolve => setTimeout(resolve, 1500)) // Wait 1.5 seconds for welcome/system messages
  
  if (chatStore.currentRoom) {
    console.log('Room loaded, setting up scheduled messages. Room ID:', chatStore.currentRoom.id)
    console.log('Scheduled messages in room:', chatStore.currentRoom.scheduled_messages)
    setupScheduledMessages(Number(roomId.value))
  } else {
    console.warn('Current room not set after fetchRoom')
  }

  // Subscribe to Echo channel for real-time updates
  // Only subscribe if we don't already have a channel for this room
  const echo = getEcho()
  
  // Track connection state
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
          console.log('✅ Socket connected in chat page')
          chatStore.setConnected(true)
          
          // Reset manual disconnect flag when connection is established
          isManualDisconnect.value = false
          
          // Mark current user as active when socket connects
          if (authStore.user?.id) {
            markUserActiveOnSocket(authStore.user.id)
          }
          
          // When socket reconnects, treat network as restored:
          // clear any global "internet_disconnected" markers
          const updated: typeof userConnectionStatus.value = {}
          Object.entries(userConnectionStatus.value).forEach(([id, status]) => {
            updated[Number(id)] = status === 'internet_disconnected' ? 'online' : status
          })
          userConnectionStatus.value = updated
          
          // Add connection message (only if not manually disconnected)
          if (!isManualDisconnect.value) {
            addConnectionStateMessage('connected')
          }
          
          // Note: "joined" message is sent in channel.subscribed() callback when wasDisconnected is true
        })
        
        pusher.connection.bind('disconnected', () => {
          console.log('❌ Socket disconnected in chat page')
          chatStore.setConnected(false)
          
          // Mark as disconnected for reconnect detection
          wasDisconnected.value = true
          
          // Only add disconnection message and update status if NOT manually disconnected
          // If manually disconnected, we already sent the message and don't want auto-reconnect
          if (!isManualDisconnect.value) {
            // Mark all known users as "internet_disconnected" based on socket state only
            const updated: typeof userConnectionStatus.value = {}
            Object.keys(userConnectionStatus.value).forEach((id) => {
              updated[Number(id)] = 'internet_disconnected'
            })
            userConnectionStatus.value = updated
            
            // Add disconnection message (automatic disconnect)
            addConnectionStateMessage('disconnected')
          }
        })
        
        pusher.connection.bind('error', () => {
          console.log('❌ Socket connection error in chat page')
          chatStore.setConnected(false)
          
          // Add error message
          addConnectionStateMessage('error')
        })
        
        pusher.connection.bind('state_change', (states: any) => {
          console.log('🔄 Socket state changed in chat page:', states.previous, '->', states.current)
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
  
  if (echo && !isSubscribed) {
    // Check if we need to leave a previous room
    if (currentChannel && previousRoomId && String(previousRoomId) !== String(roomId.value)) {
      echo.leave(`room.${previousRoomId}`)
      currentChannel = null
    }
    
    // Only subscribe if we don't have a channel for the current room
    if (!currentChannel || String(previousRoomId) !== String(roomId.value)) {
      currentChannel = echo.join(`room.${roomId.value}`)
      
      // Debug: Log channel subscription
      console.log('Subscribing to presence channel:', `room.${roomId.value}`)
      
      // Set up all channel listeners using the extracted function
      setupChannelListeners(currentChannel, roomId.value)
      isSubscribed = true
    }
  }

  // Subscribe to user's private channel for move requests (only once)
  if (echo && authStore.user && !isUserChannelSubscribed) {
    try {
      userPrivateChannel = echo.private(`user.${authStore.user.id}`)
      
      userPrivateChannel.subscribed(() => {
        console.log('✅ Subscribed to private user channel:', `user.${authStore.user?.id}`)
        isUserChannelSubscribed = true
        
        // Set up listener after subscription is confirmed
        userPrivateChannel.listen('.user.move.request', async (data: any) => {
          console.log('📢 Received move request event:', data)
          
          if (data.target_room_id) {
            const targetRoomId = data.target_room_id
            const previousRoomId = data.previous_room_id
            
            // Navigate to the target room
            try {
              await navigateToRoom(targetRoomId)
              console.log('✅ Successfully navigated to room:', targetRoomId)
            } catch (error) {
              console.error('❌ Error navigating to room:', error)
            }
          }
        })

        // Listen for ban event (inside subscribed callback)
        userPrivateChannel.listen('.user.banned', async (data: any) => {
          console.log('🚫 Received ban event (subscribed callback):', data)
          
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
      })

      // Also set up listener immediately (in case subscribed() doesn't fire)
      userPrivateChannel.listen('.user.move.request', async (data: any) => {
        console.log('📢 Received move request event (immediate listener):', data)
        
        if (data.target_room_id) {
          const targetRoomId = data.target_room_id
          const previousRoomId = data.previous_room_id
          
          // Navigate to the target room
          try {
            await navigateToRoom(targetRoomId)
            console.log('✅ Successfully navigated to room (immediate):', targetRoomId)
          } catch (error) {
            console.error('❌ Error navigating to room:', error)
          }
        }
      })

      // Listen for ban event (immediate listener)
      userPrivateChannel.listen('.user.banned', async (data: any) => {
        console.log('🚫 Received ban event (immediate listener):', data)
        
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
    console.log('Password dialog is open, waiting for validation before proceeding with room switch')
    return
  }
  
  console.log('Room changed from', oldRoomId, 'to', newRoomId, '- Messages preserved:', chatStore.messages.length)
  
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
    console.log('Leaving previous room channel:', `room.${oldRoomId}`)
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
  } catch (error: any) {
    console.log('Error fetching room in watch:', error)
    console.log('Error data:', error.data)
    console.log('Error status:', error.status)
    
    // Check if room requires password
    if (error.data?.requires_password || error.status === 403 || error.message?.includes('password')) {
      passwordProtectedRoomId.value = Number(newRoomId)
      passwordProtectedRoomName.value = error.data?.room_name || 'الغرفة'
      showPasswordDialog.value = true
      isPasswordValidated.value = false // Reset validation flag
      // Redirect back to previous room to stay on current room
      if (oldRoomId) {
        await router.replace(`/chat/${oldRoomId}`)
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
    console.log('Room switched, setting up scheduled messages. Room ID:', chatStore.currentRoom.id)
    console.log('Scheduled messages in room:', chatStore.currentRoom.scheduled_messages)
    setupScheduledMessages(Number(newRoomId))
  } else {
    console.warn('Current room not set after fetchRoom in watch handler')
  }
  
  // Subscribe to new room channel
  console.log('Joining new room channel:', `room.${newRoomId}`)
  currentChannel = echo.join(`room.${newRoomId}`)
  
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

watch(() => settingsStore.privateMessagesEnabled, (enabled) => {
  settingsStore.setPrivateMessagesEnabled(enabled)
})

watch(() => settingsStore.notificationsEnabled, (enabled) => {
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
</style>
