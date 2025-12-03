// Composable for accessing application settings
// These settings control various limits and features in the app

export const useAppSettings = () => {
  const { getSetting } = useSiteSettings()

  // Message character limits
  const wallMessageChars = computed(() => {
    return parseInt(getSetting('wall_message_chars', '0')) || 0
  })

  const privateMessageChars = computed(() => {
    return parseInt(getSetting('private_message_chars', '0')) || 0
  })

  const publicMessageChars = computed(() => {
    return parseInt(getSetting('public_message_chars', '0')) || 0
  })

  // Message timing intervals (in minutes)
  const dailyMessagesInterval = computed(() => {
    return parseInt(getSetting('daily_messages_interval', '0')) || 0
  })

  const wallMessagesInterval = computed(() => {
    return parseInt(getSetting('wall_messages_interval', '0')) || 0
  })

  // Like requirements
  const adLikesRequired = computed(() => {
    return parseInt(getSetting('ad_likes_required', '0')) || 0
  })

  const wallLikesRequired = computed(() => {
    return parseInt(getSetting('wall_likes_required', '0')) || 0
  })

  const privateContactLikesRequired = computed(() => {
    return parseInt(getSetting('private_contact_likes_required', '0')) || 0
  })

  const micLikesRequired = computed(() => {
    return parseInt(getSetting('mic_likes_required', '0')) || 0
  })

  const createStoryLikesRequired = computed(() => {
    return parseInt(getSetting('create_story_likes_required', '0')) || 0
  })

  const changeNameLikesRequired = computed(() => {
    return parseInt(getSetting('change_name_likes_required', '0')) || 0
  })

  const changePictureLikesRequired = computed(() => {
    return parseInt(getSetting('change_picture_likes_required', '0')) || 0
  })

  const changeYoutubeLinkLikesRequired = computed(() => {
    return parseInt(getSetting('change_youtube_link_likes_required', '0')) || 0
  })

  const sendPrivateMessageLikesRequired = computed(() => {
    return parseInt(getSetting('send_private_message_likes_required', '0')) || 0
  })

  const writeInRoomLikesRequired = computed(() => {
    return parseInt(getSetting('write_in_room_likes_required', '0')) || 0
  })

  const sendFilePrivateLikesRequired = computed(() => {
    return parseInt(getSetting('send_file_private_likes_required', '0')) || 0
  })

  const sendFileWallLikesRequired = computed(() => {
    return parseInt(getSetting('send_file_wall_likes_required', '0')) || 0
  })

  // Membership settings
  const maxMembershipsPerUser = computed(() => {
    return parseInt(getSetting('max_memberships_per_user', '0')) || 0
  })

  const maxRegistrationsPerUser = computed(() => {
    return parseInt(getSetting('max_registrations_per_user', '0')) || 0
  })

  const visitorNameChars = computed(() => {
    return parseInt(getSetting('visitor_name_chars', '0')) || 0
  })

  const membershipRegistrationChars = computed(() => {
    return parseInt(getSetting('membership_registration_chars', '0')) || 0
  })

  // Feature toggles
  const disableWallPin = computed(() => {
    const value = getSetting('disable_wall_pin', '0')
    return value === '1' || value === 'true' || value === true
  })

  const disableVisitorEntry = computed(() => {
    const value = getSetting('disable_visitor_entry', '0')
    return value === '1' || value === 'true' || value === true
  })

  const disableMembershipRegistration = computed(() => {
    const value = getSetting('disable_membership_registration', '0')
    return value === '1' || value === 'true' || value === true
  })

  const pinMemberships = computed(() => {
    const value = getSetting('pin_memberships', '0')
    return value === '1' || value === 'true' || value === true
  })

  const enableRoomReply = computed(() => {
    const value = getSetting('enable_room_reply', '1')
    return value === '1' || value === 'true' || value === true
  })

  const enableWallComments = computed(() => {
    const value = getSetting('enable_wall_comments', '1')
    return value === '1' || value === 'true' || value === true
  })

  const enablePrivateCall = computed(() => {
    const value = getSetting('enable_private_call', '1')
    return value === '1' || value === 'true' || value === true
  })

  const enablePrivateFingerprint = computed(() => {
    const value = getSetting('enable_private_fingerprint', '1')
    return value === '1' || value === 'true' || value === true
  })

  const enableWallYoutubeSearch = computed(() => {
    const value = getSetting('enable_wall_youtube_search', '1')
    return value === '1' || value === 'true' || value === true
  })

  const enableWallStories = computed(() => {
    const value = getSetting('enable_wall_stories', '1')
    return value === '1' || value === 'true' || value === true
  })

  const enableWallCreator = computed(() => {
    const value = getSetting('enable_wall_creator', '1')
    return value === '1' || value === 'true' || value === true
  })

  const blockVpn = computed(() => {
    const value = getSetting('block_vpn', '0')
    return value === '1' || value === 'true' || value === true
  })

  // Helper functions to check limits
  const checkMessageLength = (message: string, type: 'wall' | 'private' | 'public'): { valid: boolean; maxLength: number } => {
    let maxLength = 0
    if (type === 'wall') {
      maxLength = wallMessageChars.value
    } else if (type === 'private') {
      maxLength = privateMessageChars.value
    } else if (type === 'public') {
      maxLength = publicMessageChars.value
    }

    // If maxLength is 0, there's no limit
    if (maxLength === 0) {
      return { valid: true, maxLength: 0 }
    }

    return {
      valid: message.length <= maxLength,
      maxLength,
    }
  }

  const checkLikesRequired = (action: string, userLikes: number): { valid: boolean; required: number } => {
    let required = 0

    switch (action) {
      case 'ad':
        required = adLikesRequired.value
        break
      case 'wall':
        required = wallLikesRequired.value
        break
      case 'private_contact':
        required = privateContactLikesRequired.value
        break
      case 'mic':
        required = micLikesRequired.value
        break
      case 'create_story':
        required = createStoryLikesRequired.value
        break
      case 'change_name':
        required = changeNameLikesRequired.value
        break
      case 'change_picture':
        required = changePictureLikesRequired.value
        break
      case 'change_youtube_link':
        required = changeYoutubeLinkLikesRequired.value
        break
      case 'send_private_message':
        required = sendPrivateMessageLikesRequired.value
        break
      case 'write_in_room':
        required = writeInRoomLikesRequired.value
        break
      case 'send_file_private':
        required = sendFilePrivateLikesRequired.value
        break
      case 'send_file_wall':
        required = sendFileWallLikesRequired.value
        break
    }

    // If required is 0, no limit
    if (required === 0) {
      return { valid: true, required: 0 }
    }

    return {
      valid: userLikes >= required,
      required,
    }
  }

  return {
    // Message character limits
    wallMessageChars,
    privateMessageChars,
    publicMessageChars,
    
    // Message timing
    dailyMessagesInterval,
    wallMessagesInterval,
    
    // Like requirements
    adLikesRequired,
    wallLikesRequired,
    privateContactLikesRequired,
    micLikesRequired,
    createStoryLikesRequired,
    changeNameLikesRequired,
    changePictureLikesRequired,
    changeYoutubeLinkLikesRequired,
    sendPrivateMessageLikesRequired,
    writeInRoomLikesRequired,
    sendFilePrivateLikesRequired,
    sendFileWallLikesRequired,
    
    // Membership settings
    maxMembershipsPerUser,
    maxRegistrationsPerUser,
    visitorNameChars,
    membershipRegistrationChars,
    
    // Feature toggles
    disableWallPin,
    disableVisitorEntry,
    disableMembershipRegistration,
    pinMemberships,
    enableRoomReply,
    enableWallComments,
    enablePrivateCall,
    enablePrivateFingerprint,
    enableWallYoutubeSearch,
    enableWallStories,
    enableWallCreator,
    blockVpn,
    
    // Helper functions
    checkMessageLength,
    checkLikesRequired,
  }
}






