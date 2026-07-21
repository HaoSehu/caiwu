<?php

use App\Http\Controllers\FrontendEntryController;
use App\Http\Controllers\SecureAssetController;
use Illuminate\Support\Facades\Route;

Route::get('/api/secure-assets/view', [SecureAssetController::class, 'show'])
    ->middleware('signed:relative')
    ->name('secure-assets.show');

Route::get('/client/{path?}', [FrontendEntryController::class, 'client'])
    ->where('path', '.*');

Route::get('/admin/{path?}', [FrontendEntryController::class, 'admin'])
    ->where('path', '.*');

Route::get('/{path?}', [FrontendEntryController::class, 'site'])
    ->where('path', '^(?!(?:api|sanctum|uploads|media|vnc|ws|zjmf)(?:/|$)).*');
