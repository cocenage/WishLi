<?php

use App\Http\Controllers\TelegramAuthController;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('telegram.login'))->name('home');
Route::get('/login', fn () => redirect()->route('telegram.login'))->name('login');

Route::get('/tg-login', fn () => view('telegram-login'))->name('telegram.login');

Route::post('/telegram/auth', TelegramAuthController::class)
    ->name('telegram.auth');

Route::get('/dev-login', function () {
    abort_unless(app()->environment('local'), 404);

    $user = User::query()->updateOrCreate(
        ['email' => 'dev@example.com'],
        [
            'name' => 'Dev User',
            'password' => Hash::make('password'),
            'telegram_id' => 999999999,
            'telegram_username' => 'dev_user',
            'telegram_first_name' => 'Dev',
            'telegram_last_name' => 'User',
            'status' => 'approved',
            'is_active' => true,
            'approved_at' => now(),
            'telegram_last_auth_at' => now(),
        ]
    );

    Auth::login($user, true);
    request()->session()->regenerate();

    return redirect()->route('page-wishlists');
})->name('dev-login');

$middleware = app()->environment('local') ? ['auth'] : ['tg.auth'];

Route::middleware($middleware)->group(function () {
    Route::livewire('/wishlists', 'page-wishlists')
        ->name('page-wishlists');

    Route::livewire('/wishlists/create', 'page-wishlist-create')
        ->name('page-wishlist-create');

    Route::livewire('/wishlists/{wishlist}', 'page-wishlist-show')
        ->name('page-wishlist-show');

    Route::livewire('/wishlists/{wishlist}/edit', 'page-wishlist-edit')
        ->name('page-wishlist-edit');

    Route::livewire('/wishlists/{wishlist}/items/create', 'page-wishlist-item-create')
        ->name('page-wishlist-item-create');

    Route::livewire('/wishlists/{wishlist}/items/{item}/edit', 'page-wishlist-item-edit')
        ->name('page-wishlist-item-edit');

    Route::livewire('/wishlist-invites/{token}', 'page-wishlist-invite')
        ->name('page-wishlist-invite');

    Route::livewire('/notification-settings', 'page-notification-settings')
        ->name('page-notification-settings');
});

Route::fallback(fn () => redirect()->route('telegram.login'));