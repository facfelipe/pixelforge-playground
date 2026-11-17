<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\StudioController;
use App\Http\Controllers\Webhooks\PixelforgeWebhookController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/studio');

Route::post('/webhooks/pixelforge', PixelforgeWebhookController::class)
    ->middleware('pixelforge.webhook')
    ->name('webhooks.pixelforge');

Route::middleware('auth')->group(function () {
    Route::get('/studio', [StudioController::class, 'index'])->name('studio');
    Route::post('/studio', [StudioController::class, 'store'])->name('studio.store');
    Route::post('/studio/estimate', [StudioController::class, 'estimate'])->name('studio.estimate');

    Route::get('/credits', [CreditController::class, 'index'])->name('credits.index');
    Route::post('/credits', [CreditController::class, 'store'])->name('credits.store');

    Route::get('/admin', ReportController::class)->name('admin.report');
});

require __DIR__.'/auth.php';
