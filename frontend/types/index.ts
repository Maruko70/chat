export interface User {
  id: number
  username: string
  name: string
  email?: string | null
  phone?: string | null
  country_flag?: string | null
  country_code?: string | null
  bio?: string | null
  social_media_type?: 'youtube' | 'instagram' | 'tiktok' | 'x' | null
  social_media_url?: string | null
  avatar_url?: string | null
  is_guest: boolean
  premium_entry?: boolean
  designed_membership?: boolean
  verify_membership?: boolean
  is_blocked?: boolean
  incognito_mode_enabled?: boolean
  private_messages_enabled?: boolean
  status?: 'active' | 'inactive_tab' | 'private_disabled' | 'away' | 'incognito'
  last_activity?: number | null
  role_groups?: (RoleGroup & { pivot?: { expires_at?: string | null } })[]
  role_group_banner?: string | null
  all_permissions?: string[]
  email_verified_at?: string | null
  ip_address?: string | null
  country?: string | null
  created_at?: string
  updated_at?: string
}

export interface Room {
  id: number
  name: string
  slug: string
  description?: string | null
  welcome_message?: string | null
  required_likes?: number
  room_hashtag?: number | null
  room_cover?: string | null
  room_image?: string | null
  room_image_url?: string | null
  is_public: boolean
  is_staff_only?: boolean
  enable_mic?: boolean
  disable_incognito?: boolean
  max_count?: number
  password?: string | null
  created_by?: number | null
  settings?: {
    backgroundColor?: string
    textColor?: string
    bannerHeight?: string
    roomNameColor?: string
    roomBorderColor?: string
    roomDescriptionColor?: string
    customCSS?: string
    [key: string]: any
  } | null
  users?: (User & { pivot?: { role?: string } })[]
  scheduled_messages?: ScheduledMessage[]
  created_at?: string
  updated_at?: string
}

export interface Message {
  id: number
  room_id: number
  user_id: number
  user?: User
  content: string
  meta?: {
    reply_to?: {
      id: number
      content: string
      user_id: number
      user?: User
    }
  }
  created_at: string
  updated_at: string
}

export interface PrivateMessage {
  id: number
  sender_id: number
  recipient_id: number
  sender?: User
  recipient?: User
  content: string
  meta?: any
  read_at?: string | null
  created_at: string
  updated_at: string
}

export interface Conversation {
  user: User
  last_message?: {
    id: number
    content: string
    sender_id: number
    created_at: string
    read_at?: string | null
  } | null
  unread_count: number
  message_count: number
  last_message_at: string
}

export interface RoleGroup {
  id: number
  name: string
  slug: string
  banner?: string | null
  priority: number
  permissions?: string[] | null
  is_active: boolean
  users_count?: number
  users?: User[]
  created_at?: string
  updated_at?: string
}

export interface Symbol {
  id: number
  name: string
  type: 'emoji' | 'role_group_banner' | 'gift_banner' | 'name_banner' | 'user_frame'
  url: string
  path: string
  priority: number
  is_active: boolean
  created_at?: string
  updated_at?: string
}

export interface ScheduledMessage {
  id: number
  type: 'daily' | 'welcoming'
  time_span: number
  title: string
  message: string
  room_id?: number | null
  room?: Room | null
  is_active: boolean
  last_sent_at?: string | null
  created_at?: string
  updated_at?: string
}

export const SYMBOL_TYPES = [
  { value: 'emoji', label: 'إيموجي' },
  { value: 'role_group_banner', label: 'بانر مجموعة الدور' },
  { value: 'gift_banner', label: 'بانر الهدية' },
  { value: 'name_banner', label: 'بانر الاسم' },
  { value: 'user_frame', label: 'إطار الصورة الشخصية' },
] as const

export const PERMISSIONS_LIST = [
  { key: 'kick', label: 'طرد المستخدم' },
  { key: 'delete_wall_post', label: 'حذف منشور الحائط' },
  { key: 'notifications', label: 'الإشعارات' },
  { key: 'change_name', label: 'تغيير الاسم' },
  { key: 'ban', label: 'حظر' },
  { key: 'advertisement', label: 'الإعلانات' },
  { key: 'toggle_private_message_for_user', label: 'تفعيل/إلغاء الرسائل الخاصة للمستخدم' },
  { key: 'move_user_from_room', label: 'نقل المستخدم من الغرفة' },
  { key: 'manage_rooms', label: 'إدارة الغرف' },
  { key: 'create_rooms', label: 'إنشاء الغرف' },
  { key: 'exceed_max_room_count', label: 'تجاوز الحد الأقصى لعدد الغرف' },
  { key: 'manage_user_info', label: 'إدارة معلومات المستخدم' },
  { key: 'mute_user', label: 'كتم المستخدم' },
  { key: 'set_likes', label: 'تعيين الإعجابات' },
  { key: 'filter', label: 'الفلتر ' },
  { key: 'subscriptions', label: 'الإشتراكات ' },
  { key: 'shortcuts', label: 'الاختصارات' },
  { key: 'sending_messages_in_chat', label: 'إرسال الرسائل في الدردشة' },
  { key: 'watch_filter', label: 'مشاهدة الفلتر' },
  { key: 'gifts', label: 'الهدايا' },
  { key: 'edit_permissions_on_role_groups', label: 'تعديل الصلاحيات على مجموعات الأدوار' },
  { key: 'dashboard', label: 'لوحة التحكم' },
  { key: 'join_full_or_closed_rooms', label: 'الانضمام للغرف الممتلئة أو المغلقة' },
  { key: 'delete_other_user_photo', label: 'حذف صورة مستخدم آخر' },
  { key: 'delete_public_messages_in_room', label: 'حذف الرسائل العامة في الغرفة' },
  { key: 'incognito_mode', label: 'وضع التخفي' },
  { key: 'give_banner', label: 'إعطاء بانر' },
  { key: 'site_administration', label: 'إدارة الموقع' },
] as const
