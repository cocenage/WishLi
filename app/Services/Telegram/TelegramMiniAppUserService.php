<?php

namespace App\Services\Telegram;

use App\Models\User;

class TelegramMiniAppUserService
{
    public function findOrCreate(array $telegramUser): User
    {
        $name = trim(
            ($telegramUser['first_name'] ?? '') . ' ' . ($telegramUser['last_name'] ?? '')
        );

        return User::query()->updateOrCreate(
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
    }
}