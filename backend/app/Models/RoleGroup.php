<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class RoleGroup extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'banner',
        'priority',
        'permissions',
        'is_active',
    ];

    protected $casts = [
        'permissions' => 'array',
        'is_active' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($roleGroup) {
            if (empty($roleGroup->slug)) {
                $roleGroup->slug = Str::slug($roleGroup->name);
            }
        });
    }

    /**
     * Get the users that belong to this role group.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_group_user')
            ->withPivot('expires_at')
            ->withTimestamps();
    }
}
