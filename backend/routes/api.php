<?php

use App\Http\Controllers\SiteConfigController;
use App\Http\Controllers\SiteContentController;
use App\Http\Controllers\SiteHomeController;
use App\Http\Controllers\SiteProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 主路由 - 注册子路由
|--------------------------------------------------------------------------
*/

Route::get('/site/config', [SiteConfigController::class, 'index']);
Route::get('/site/home', [SiteHomeController::class, 'index']);
Route::get('/site/home-hero', [SiteHomeController::class, 'hero']);
Route::get('/site/products/init', [SiteProductController::class, 'init']);
Route::get('/site/product-types', [SiteProductController::class, 'productTypes']);
Route::get('/site/product-groups', [SiteProductController::class, 'productGroups']);
Route::get('/site/product-groups/{groupId}/children', [SiteProductController::class, 'childGroups']);
Route::get('/site/product-groups/{groupId}/catalog', [SiteProductController::class, 'groupCatalog']);
Route::get('/site/product-categories', [SiteProductController::class, 'productGroups']);
Route::get('/site/product-categories/{groupId}/children', [SiteProductController::class, 'childGroups']);
Route::get('/site/product-categories/{groupId}/catalog', [SiteProductController::class, 'groupCatalog']);
Route::get('/site/products', [SiteProductController::class, 'index']);
Route::get('/site/products/{productId}/stock', [SiteProductController::class, 'stock']);
Route::get('/site/products/{productId}', [SiteProductController::class, 'show']);
Route::post('/site/products/{productId}/quote', [SiteProductController::class, 'quote'])->middleware('throttle:60,1');
Route::get('/site/content/overview', [SiteContentController::class, 'overview']);
Route::get('/site/notices', [SiteContentController::class, 'notices']);
Route::get('/site/notices/{articleId}', [SiteContentController::class, 'noticeDetail']);
Route::get('/site/help-articles', [SiteContentController::class, 'helpArticles']);
Route::get('/site/help-articles/{articleId}', [SiteContentController::class, 'helpDetail']);
// 健康检查
Route::get('/health', function () {
    return response()->json([
        'code' => 0,
        'message' => 'ok',
        'data' => ['version' => '1.0.0'],
        'timestamp' => time(),
    ]);
});
