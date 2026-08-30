<?php

use App\Http\Controllers\SecureAssetController;
use App\Http\Controllers\Site\WebController;
use Illuminate\Support\Facades\Route;

// 注意：本文件的路由动作必须是控制器方法，不能使用闭包，否则 php artisan route:cache 无法生成生产路由缓存。
Route::get('/', [WebController::class, 'index']);

Route::get('/api/secure-assets/view', [SecureAssetController::class, 'show'])
    ->middleware('signed:relative')
    ->name('secure-assets.show');

Route::get('/client/register', [WebController::class, 'registerRedirect'])
    ->name('client.register.redirect');
