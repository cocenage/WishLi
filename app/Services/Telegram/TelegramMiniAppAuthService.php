<?php

namespace App\Services\Telegram;

use Illuminate\Support\Arr;

class TelegramMiniAppAuthService
{
    public function validate(string $initData, string $botToken): ?array
    {
        parse_str($initData, $data);

        $hash = Arr::pull($data, 'hash');

        if (! $hash) {
            return null;
        }

        ksort($data);

        $dataCheckString = collect($data)
            ->map(fn ($value, $key) => "{$key}={$value}")
            ->implode("\n");

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (! hash_equals($calculatedHash, $hash)) {
            return null;
        }

        if (isset($data['auth_date']) && (time() - (int) $data['auth_date']) > 86400) {
            return null;
        }

        return $data;
    }

    public function extractUser(array $validatedData): ?array
    {
        if (! isset($validatedData['user'])) {
            return null;
        }

        $user = json_decode($validatedData['user'], true);

        return is_array($user) ? $user : null;
    }
}