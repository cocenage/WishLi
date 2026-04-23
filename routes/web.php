<?php

use Illuminate\Support\Facades\Route;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

Route::middleware(['auth'])->group(function () {
    Route::livewire('/wishlists', 'page-wishlists')->name('page-wishlists');
    Route::livewire('/wishlists/create', 'page-wishlist-create')->name('page-wishlist-create');
    Route::livewire('/wishlists/{wishlist}', 'page-wishlist-show')->name('page-wishlist-show');
    Route::livewire('/wishlists/{wishlist}/edit', 'page-wishlist-edit')->name('page-wishlist-edit');
    Route::livewire('/wishlists/{wishlist}/items/create', 'page-wishlist-item-create')->name('page-wishlist-item-create');
    Route::livewire('/wishlists/{wishlist}/items/{item}/edit', 'page-wishlist-item-edit')->name('page-wishlist-item-edit');
    Route::livewire('/wishlist-invites/{token}', 'page-wishlist-invite')->name('page-wishlist-invite');
});
Route::get('/php-test', function () {
    return [
        'php_version' => phpversion(),
        'binary' => PHP_BINARY,
    ];
});

Route::get('/dev-login', function () {
    abort_unless(app()->environment('local'), 404);

    $user = User::query()->firstOrCreate(
        ['email' => 'dev@example.com'],
        [
            'name' => 'Dev User',
            'password' => bcrypt('password'),
        ]
    );

    Auth::login($user, true);

    return redirect()->route('page-wishlists');
})->name('dev-login');