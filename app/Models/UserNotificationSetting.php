<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserNotificationSetting extends Model
{
    protected $fillable = [
        'user_id',
        'wishlist_joined',
        'item_claimed',
        'item_unclaimed',
        'wishlist_updated',
        'event_reminders',
        'marketing',
        'reminder_days',
    ];

    protected function casts(): array
    {
        return [
            'wishlist_joined' => 'boolean',
            'item_claimed' => 'boolean',
            'item_unclaimed' => 'boolean',
            'wishlist_updated' => 'boolean',
            'event_reminders' => 'boolean',
            'marketing' => 'boolean',
            'reminder_days' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}