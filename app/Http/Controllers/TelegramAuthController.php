<?php

namespace App\Http\Controllers;

use App\Services\Telegram\TelegramMiniAppAuthService;
use App\Services\Telegram\TelegramMiniAppUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TelegramAuthController extends Controller
{
    public function __construct(
        protected TelegramMiniAppAuthService $telegramAuthService,
        protected TelegramMiniAppUserService $telegramUserService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $initData = $request->header('X-Telegram-Init-Data') ?? $request->input('init_data');
        $startParam = $request->input('start_param');

        if (! $initData) {
            return response()->json([
                'ok' => false,
                'message' => 'Telegram initData не передан.',
            ], 403);
        }

        $validated = $this->telegramAuthService->validate(
            initData: $initData,
            botToken: (string) config('services.telegram.bot_token')
        );

        if (! $validated) {
            return response()->json([
                'ok' => false,
                'message' => 'Telegram initData не прошёл проверку.',
            ], 403);
        }

        $telegramUser = $this->telegramAuthService->extractUser($validated);

        if (! $telegramUser || ! isset($telegramUser['id'])) {
            return response()->json([
                'ok' => false,
                'message' => 'Telegram не передал пользователя.',
            ], 403);
        }

        $user = $this->telegramUserService->findOrCreate($telegramUser);

        if (! $user->is_active || $user->status !== 'approved') {
            return response()->json([
                'ok' => false,
                'message' => 'Доступ к приложению ограничен.',
            ], 403);
        }

        Auth::login($user, true);
        $request->session()->regenerate();

        return response()->json([
            'ok' => true,
            'redirect' => $this->redirectByStartParam($startParam),
        ]);
    }

    protected function redirectByStartParam(?string $startParam): string
    {
        if (! $startParam) {
            return route('page-wishlists');
        }

        if ($startParam === 'create') {
            return route('page-wishlist-create');
        }

        if ($startParam === 'notifications') {
            return route('page-notification-settings');
        }

        if (str_starts_with($startParam, 'invite_')) {
            return route('page-wishlist-invite', [
                'token' => substr($startParam, strlen('invite_')),
            ]);
        }

        if (str_starts_with($startParam, 'wishlist_')) {
            return route('page-wishlist-show', [
                'wishlist' => (int) substr($startParam, strlen('wishlist_')),
            ]);
        }

        return route('page-wishlists');
    }
}