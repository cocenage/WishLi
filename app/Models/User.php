<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'telegram_id',
        'telegram_username',
        'telegram_first_name',
        'telegram_last_name',
        'telegram_photo_url',
        'telegram_last_auth_at',
        'name',
        'email',
        'password',
        'role',
        'status',
        'is_active',
        'approved_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'telegram_last_auth_at' => 'datetime',
            'approved_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function ownedWishlists()
    {
        return $this->hasMany(Wishlist::class, 'owner_id');
    }

    public function wishlistMemberships()
    {
        return $this->hasMany(WishlistMember::class);
    }

    public function notificationSettings()
    {
        return $this->hasOne(UserNotificationSetting::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}