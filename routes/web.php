<?php

use App\Http\Controllers\ProjectFileDownloadController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

// The to-do dashboard is the home page; guests are sent on to the login page.
Route::redirect('/', '/dashboard')->name('home');

Volt::route('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Volt::route('projects', 'projects.index')->name('projects.index');
    Volt::route('projects/{project}', 'projects.show')->name('projects.show');
    Route::get('projects/{project}/files/{file}', ProjectFileDownloadController::class)
        ->scopeBindings()
        ->name('projects.files.download');
    Volt::route('trash', 'trash')->name('trash');
});

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__.'/auth.php';
