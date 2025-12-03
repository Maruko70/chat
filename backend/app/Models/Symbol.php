<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Symbol extends Model
{
    protected $fillable = [
        'name',
        'type',
        'path',
        'url',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Get the URL attribute, ensuring it includes the backend port.
     */
    public function getUrlAttribute($value): ?string
    {
        if (empty($value) && !empty($this->attributes['path'])) {
            // If URL is empty but path exists, generate URL from path
            $value = Storage::url($this->attributes['path']);
        }
        
        if (empty($value)) {
            return null;
        }
        
        // If URL is already absolute, return as is
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }
        
        // Convert relative URL to absolute URL with backend port
        return url($value);
    }

    // Symbol types
    public const TYPE_EMOJI = 'emoji';
    public const TYPE_ROLE_GROUP_BANNER = 'role_group_banner';
    public const TYPE_GIFT_BANNER = 'gift_banner';
    public const TYPE_NAME_BANNER = 'name_banner';
    public const TYPE_USER_FRAME = 'user_frame';

    public static function getTypes(): array
    {
        return [
            self::TYPE_EMOJI => 'إيموجي',
            self::TYPE_ROLE_GROUP_BANNER => 'بانر مجموعة الدور',
            self::TYPE_GIFT_BANNER => 'بانر الهدية',
            self::TYPE_NAME_BANNER => 'بانر الاسم',
            self::TYPE_USER_FRAME => 'إطار الصورة الشخصية',
        ];
    }
}
