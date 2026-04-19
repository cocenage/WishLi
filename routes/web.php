<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::livewire('/home', 'page-home')->name('page-home');
    Route::livewire('/show', 'page-show')->name('page-show');
    Route::livewire('/create', 'page-create')->name('page-create');
});