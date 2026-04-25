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
        protected TelegramMiniAppUserService $telegramMiniAppUserService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $initData = $request->header('X-Telegram-Init-Data')
            ?? $request->input('init_data');

        if (! $initData) {
            return response()->json([
                'ok' => false,
                'message' => 'Telegram init data missing.',
            ], 403);
        }

        $validated = $this->telegramAuthService->validate(
            $initData,
            (string) config('services.telegram.bot_token')
        );

        if (! $validated) {
            return response()->json([
                'ok' => false,
                'message' => 'Telegram init data invalid.',
            ], 403);
        }

        $telegramUser = $this->telegramAuthService->extractUser($validated);

        if (! $telegramUser || ! isset($telegramUser['id'])) {
            return response()->json([
                'ok' => false,
                'message' => 'Telegram user missing.',
            ], 403);
        }

        $user = $this->telegramMiniAppUserService->findOrCreate($telegramUser);

        Auth::login($user, true);
        $request->session()->regenerate();

        return response()->json([
            'ok' => true,
            'redirect' => route('page-wishlists'),
        ]);
    }
}