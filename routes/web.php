<?php

use App\Http\Controllers\AdminUsageController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\PixelforgeWebhookController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WalletController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::post('/webhooks/pixelforge', PixelforgeWebhookController::class)->name('webhooks.pixelforge');

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('generations.index');
    }

    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        return redirect()->route('generations.index');
    })->name('dashboard');

    Route::get('/generations', [GenerationController::class, 'index'])->name('generations.index');
    Route::get('/generations/create', [GenerationController::class, 'create'])->name('generations.create');
    Route::post('/generations', [GenerationController::class, 'store'])->name('generations.store');
    Route::post('/generations/estimate', [GenerationController::class, 'estimate'])->name('generations.estimate');
    Route::get('/generations/{generation}/poll', [GenerationController::class, 'poll'])->name('generations.poll');

    Route::post('/wallet/top-up', [WalletController::class, 'topUp'])->name('wallet.top-up');
    Route::get('/wallet/balance', [WalletController::class, 'getBalance'])->name('wallet.balance');

    Route::get('/admin/usage', [AdminUsageController::class, 'index'])->name('admin.usage');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
