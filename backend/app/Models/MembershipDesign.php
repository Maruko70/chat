<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MembershipDesign extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'image_url',
        'description',
        'is_active',
        'priority',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Get users who have selected this design as their background.
     */
    public function usersWithBackground(): HasMany
    {
        return $this->hasMany(User::class, 'membership_background_id');
    }

    /**
     * Get users who have selected this design as their frame.
     */
    public function usersWithFrame(): HasMany
    {
        return $this->hasMany(User::class, 'membership_frame_id');
    }

    /**
     * Scope to get only active designs.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get designs by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
}






