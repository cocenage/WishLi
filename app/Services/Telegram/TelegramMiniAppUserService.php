<?php

namespace App\Services\Telegram;

use App\Models\User;
use App\Models\UserNotificationSetting;

class TelegramMiniAppUserService
{
    public function findOrCreate(array $telegramUser): User
    {
        $name = trim(
            ($telegramUser['first_name'] ?? '') . ' ' . ($telegramUser['last_name'] ?? '')
        );

        $user = User::query()->updateOrCreate(
            [
                'telegram_id' => $telegramUser['id'],
            ],
            [
                'name' => $name ?: 'Telegram User',
                'telegram_username' => $telegramUser['username'] ?? null,
                'telegram_first_name' => $telegramUser['first_name'] ?? null,
                'telegram_last_name' => $telegramUser['last_name'] ?? null,
                'telegram_photo_url' => $telegramUser['photo_url'] ?? null,
                'telegram_last_auth_at' => now(),
                'status' => 'approved',
                'is_active' => true,
                'approved_at' => now(),
            ]
        );

        UserNotificationSetting::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'wishlist_joined' => true,
                'item_claimed' => true,
                'item_unclaimed' => true,
                'wishlist_updated' => true,
                'event_reminders' => true,
                'marketing' => false,
                'reminder_days' => [7, 3, 1],
            ]
        );

        return $user;
    }
}