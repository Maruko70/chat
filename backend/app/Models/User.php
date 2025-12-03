<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class User extends Authenticatable implements HasMedia
{
    use HasFactory, Notifiable, HasApiTokens, InteractsWithMedia;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'username',
        'name',
        'email',
        'password',
        'phone',
        'country_flag',
        'bio',
        'social_media_type',
        'social_media_url',
        'avatar_url',
        'is_guest',
        'premium_entry',
        'premium_entry_background',
        'designed_membership',
        'membership_background_id',
        'membership_frame_id',
        'verify_membership',
        'is_blocked',
        'name_color',
        'message_color',
        'name_bg_color',
        'image_border_color',
        'bio_color',
        'room_font_size',
        'gifts',
        'ip_address',
        'country',
        'country_code',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'role_group_banner',
        'all_permissions',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'name_color' => 'array',
            'message_color' => 'array',
            'name_bg_color' => 'array', // Can be null for transparent
            'image_border_color' => 'array',
            'bio_color' => 'array',
            'room_font_size' => 'integer',
            'gifts' => 'array',
            'premium_entry' => 'boolean',
            'designed_membership' => 'boolean',
            'verify_membership' => 'boolean',
            'is_blocked' => 'boolean',
        ];
    }
    
    /**
     * Get name_bg_color attribute, returning null for transparent.
     */
    public function getNameBgColorAttribute($value)
    {
        if ($value === null) {
            return null; // transparent
        }
        return $value;
    }

    /**
     * Get the rooms that the user belongs to.
     */
    public function rooms(): BelongsToMany
    {
        return $this->belongsToMany(Room::class, 'room_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Get the messages sent by the user.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * Get the warnings issued to the user.
     */
    public function warnings(): HasMany
    {
        return $this->hasMany(UserWarning::class, 'user_id');
    }

    /**
     * Get the warning count for this user.
     */
    public function getWarningCountAttribute(): int
    {
        return $this->warnings()->count();
    }

    /**
     * Get the role groups that the user belongs to.
     */
    public function roleGroups(): BelongsToMany
    {
        return $this->belongsToMany(RoleGroup::class, 'role_group_user')
            ->where('role_groups.is_active', true)
            ->where(function ($query) {
                $query->whereNull('role_group_user.expires_at')
                      ->orWhere('role_group_user.expires_at', '>', now());
            })
            ->orderBy('role_groups.priority', 'desc')
            ->withPivot('expires_at')
            ->withTimestamps();
    }

    /**
     * Get the primary role group (highest priority).
     */
    public function getPrimaryRoleGroupAttribute()
    {
        return $this->roleGroups()->first();
    }

    /**
     * Get the role group banner URL.
     */
    public function getRoleGroupBannerAttribute(): ?string
    {
        // If role groups are already loaded, use them
        if ($this->relationLoaded('roleGroups')) {
            $primaryRoleGroup = $this->roleGroups->first();
            return $primaryRoleGroup?->banner;
        }
        
        // Otherwise, get the primary role group
        $primaryRoleGroup = $this->primaryRoleGroup;
        return $primaryRoleGroup?->banner;
    }

    /**
     * Check if user has a specific permission.
     */
    public function hasPermission(string $permission): bool
    {
        $roleGroups = $this->roleGroups()->get();
        
        foreach ($roleGroups as $roleGroup) {
            $permissions = $roleGroup->permissions ?? [];
            if (in_array($permission, $permissions)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get all permissions for the user (from all role groups).
     */
    public function getAllPermissionsAttribute(): array
    {
        // If role groups are already loaded, use them
        if ($this->relationLoaded('roleGroups')) {
            $roleGroups = $this->roleGroups;
        } else {
            $roleGroups = $this->roleGroups()->get();
        }
        
        $permissions = [];
        
        foreach ($roleGroups as $roleGroup) {
            $rolePermissions = $roleGroup->permissions ?? [];
            $permissions = array_merge($permissions, $rolePermissions);
        }
        
        return array_unique($permissions);
    }

    /**
     * Register media collections for the user.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')
            ->singleFile()
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
    }

    /**
     * Get the avatar URL attribute.
     */
    public function getAvatarUrlAttribute(): ?string
    {
        $media = $this->getFirstMedia('avatar');
        if (!$media) {
            // Return default user image from site settings if available
            $defaultUserImage = \App\Models\SiteSettings::getValue('default_user_image');
            return $defaultUserImage ?: null;
        }
        
        // Get the relative URL from media
        $relativeUrl = $media->getUrl();
        
        // Use url() helper which respects APP_URL including port in development
        return url($relativeUrl);
    }

    /**
     * Get the membership background design.
     */
    public function membershipBackground(): BelongsTo
    {
        return $this->belongsTo(MembershipDesign::class, 'membership_background_id');
    }

    /**
     * Get the membership frame design.
     */
    public function membershipFrame(): BelongsTo
    {
        return $this->belongsTo(MembershipDesign::class, 'membership_frame_id');
    }

    /**
     * Get the active ban for this user.
     */
    public function activeBan(): HasMany
    {
        return $this->hasMany(\App\Models\BannedUser::class)->active();
    }

    /**
     * Check if user is banned.
     */
    public function isBanned(): bool
    {
        return $this->activeBan()->exists();
    }
}
