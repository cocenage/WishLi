<?php

namespace App\Services\Telegram;

use App\Models\User;

class TelegramMiniAppUserService
{
    public function findOrCreate(array $telegramUser): User
    {
        return User::query()->updateOrCreate(
            ['telegram_id' => $telegramUser['id']],
            [
                'name' => trim(($telegramUser['first_name'] ?? '') . ' ' . ($telegramUser['last_name'] ?? '')) ?: 'Telegram User',
                'telegram_username' => $telegramUser['username'] ?? null,
                'telegram_photo_url' => $telegramUser['photo_url'] ?? null,
                'is_active' => true,
            ]
        );
    }
}