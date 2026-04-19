<?php

use Illuminate\Support\Facades\Route;
 Route::livewire('/wishlists', 'page-wishlists')->name('page-wishlists');
Route::middleware(['auth'])->group(function () {

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