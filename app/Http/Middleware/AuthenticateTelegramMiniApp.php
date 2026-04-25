<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Telegram\TelegramMiniAppAuthService;
use App\Services\Telegram\TelegramMiniAppUserService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateTelegramMiniApp
{
    public function __construct(
        protected TelegramMiniAppAuthService $telegramAuthService,
        protected TelegramMiniAppUserService $telegramMiniAppUserService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local') && $request->has('dev_login')) {
            $user = User::query()->updateOrCreate(
                ['email' => 'dev@example.com'],
                [
                    'name' => 'Dev User',
                    'password' => Hash::make('password'),
                    'telegram_id' => 999999999,
                    'telegram_username' => 'dev_user',
                    'status' => 'approved',
                    'is_active' => true,
                    'approved_at' => now(),
                    'last_login_at' => now(),
                ]
            );

            Auth::login($user, true);
            $request->session()->regenerate();

            return $next($request);
        }

        if (Auth::check()) {
            return $next($request);
        }

        $initData = $request->header('X-Telegram-Init-Data')
            ?? $request->input('init_data')
            ?? $request->cookie('tg_init_data');

        if (! $initData) {
            abort(403, 'Telegram init data missing.');
        }

        $validated = $this->telegramAuthService->validate(
            $initData,
            (string) config('services.telegram.bot_token')
        );

        if (! $validated) {
            abort(403, 'Telegram init data invalid.');
        }

        $telegramUser = $this->telegramAuthService->extractUser($validated);

        if (! $telegramUser || ! isset($telegramUser['id'])) {
            abort(403, 'Telegram user missing.');
        }

        $user = $this->telegramMiniAppUserService->findOrCreate($telegramUser);

        Auth::login($user, true);
        $request->session()->regenerate();

        return $next($request);
    }
}