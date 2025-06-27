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

    // Account-specific inboxes with a clearer URL structure
    Route::prefix('accounts')->group(function () {
        // Info account routes
        Route::get('info', [InboxController::class, 'index'])->defaults('account', 'info')->name('inbox.info');
        Route::get('info/emails/{id}', [InboxController::class, 'show'])->defaults('account', 'info')->name('inbox.info.show');
        Route::post('info/emails/{id}/generate-reply', [InboxController::class, 'generateReply'])->defaults('account', 'info')->name('inbox.info.generate-reply');
        Route::post('info/emails/{id}/send-reply', [InboxController::class, 'sendReply'])->defaults('account', 'info')->name('inbox.info.send-reply');
        
        // Damian account routes
        Route::get('damian', [InboxController::class, 'index'])->defaults('account', 'damian')->name('inbox.damian');
        Route::get('damian/emails/{id}', [InboxController::class, 'show'])->defaults('account', 'damian')->name('inbox.damian.show');
        Route::post('damian/emails/{id}/generate-reply', [InboxController::class, 'generateReply'])->defaults('account', 'damian')->name('inbox.damian.generate-reply');
        Route::post('damian/emails/{id}/send-reply', [InboxController::class, 'sendReply'])->defaults('account', 'damian')->name('inbox.damian.send-reply');
    });
    
    // Default inbox routes (for lucasmbaldauf@myitjob.ch)
    Route::get('inbox', [InboxController::class, 'index'])->name('inbox.index');
    Route::get('inbox/{id}', [InboxController::class, 'show'])->name('inbox.show');
    Route::post('inbox/{id}/generate-reply', [InboxController::class, 'generateReply'])->name('inbox.generate-reply');
    Route::post('inbox/{id}/send-reply', [InboxController::class, 'sendReply'])->name('inbox.send-reply');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
