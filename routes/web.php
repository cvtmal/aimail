<?php

declare(strict_types=1);

use App\Http\Controllers\InboxController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard');
    })->name('dashboard');

    Route::get('inbox', [InboxController::class, 'index'])->name('inbox.index');
    Route::get('inbox/{id}', [InboxController::class, 'show'])->name('inbox.show');
    Route::post('inbox/{id}/generate-reply', [InboxController::class, 'generateReply'])->name('inbox.generate-reply');
    Route::post('inbox/{id}/send-reply', [InboxController::class, 'sendReply'])->name('inbox.send-reply');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
