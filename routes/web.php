<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\BrandingSettingsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\QuotePaymentController;
use App\Http\Controllers\TelegramLinkController;
use App\Http\Controllers\UserManagementController;
use App\Support\AppPermissions;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/login');

Route::get('/dashboard', DashboardController::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/cotizaciones', [QuoteController::class, 'index'])
        ->middleware('permission:'.AppPermissions::QUOTES_VIEW)
        ->name('cotizaciones.index');
    Route::get('/cotizaciones/create', [QuoteController::class, 'create'])
        ->middleware('permission:'.AppPermissions::QUOTES_CREATE)
        ->name('cotizaciones.create');
    Route::post('/cotizaciones', [QuoteController::class, 'store'])
        ->middleware('permission:'.AppPermissions::QUOTES_CREATE)
        ->name('cotizaciones.store');
    Route::get('/cotizaciones/{quote}/pdf', [QuoteController::class, 'pdf'])
        ->middleware('permission:'.AppPermissions::QUOTES_VIEW)
        ->name('cotizaciones.pdf');
    Route::get('/cotizaciones/{quote}', [QuoteController::class, 'view'])
        ->middleware('permission:'.AppPermissions::QUOTES_VIEW)
        ->name('cotizaciones.view');

    Route::get('/cotizaciones/{quote}/edit', [QuoteController::class, 'edit'])
        ->middleware('permission:'.AppPermissions::QUOTES_EDIT)
        ->name('cotizaciones.edit');
    Route::put('/cotizaciones/{quote}', [QuoteController::class, 'update'])
        ->middleware('permission:'.AppPermissions::QUOTES_EDIT)
        ->name('cotizaciones.update');
    Route::post('/cotizaciones/{quote}/anticipos', [QuotePaymentController::class, 'store'])
        ->middleware('permission:'.AppPermissions::QUOTES_EDIT)
        ->name('cotizaciones.anticipos.store');
    Route::get('/cotizaciones/{quote}/anticipos/{payment}/edit', [QuotePaymentController::class, 'edit'])
        ->middleware('permission:'.AppPermissions::QUOTES_EDIT)
        ->name('cotizaciones.anticipos.edit');
    Route::put('/cotizaciones/{quote}/anticipos/{payment}', [QuotePaymentController::class, 'update'])
        ->middleware('permission:'.AppPermissions::QUOTES_EDIT)
        ->name('cotizaciones.anticipos.update');
    Route::delete('/cotizaciones/{quote}/anticipos/{payment}', [QuotePaymentController::class, 'destroy'])
        ->middleware('permission:'.AppPermissions::QUOTES_EDIT)
        ->name('cotizaciones.anticipos.destroy');

    Route::prefix('/usuarios')
        ->name('usuarios.')
        ->middleware('permission:'.AppPermissions::USERS_MANAGE)
        ->group(function (): void {
            Route::get('/', [UserManagementController::class, 'index'])->name('index');
            Route::get('/create', [UserManagementController::class, 'create'])->name('create');
            Route::post('/', [UserManagementController::class, 'store'])->name('store');
            Route::get('/{user}/edit', [UserManagementController::class, 'edit'])->name('edit');
            Route::put('/{user}', [UserManagementController::class, 'update'])->name('update');
            Route::delete('/{user}', [UserManagementController::class, 'destroy'])->name('destroy');
            Route::delete('/{user}/telegram', [UserManagementController::class, 'revokeTelegram'])->name('telegram.revoke');
        });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/telegram/generar-codigo', [TelegramLinkController::class, 'generateCode'])
        ->name('profile.telegram.generate-code');
    Route::get('/marca', [BrandingSettingsController::class, 'edit'])
        ->middleware('permission:'.AppPermissions::BRANDING_MANAGE)
        ->name('branding.edit');
    Route::patch('/marca', [BrandingSettingsController::class, 'update'])
        ->middleware('permission:'.AppPermissions::BRANDING_MANAGE)
        ->name('branding.update');
});

require __DIR__.'/auth.php';
