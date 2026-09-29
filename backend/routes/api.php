<?php

use App\Http\Controllers\Site\V2\ContentController as V2SiteContentController;
use App\Http\Controllers\Site\V2\HomeController as V2SiteHomeController;
use App\Http\Controllers\Site\V2\ProductController as V2SiteProductController;
use App\Http\Controllers\Site\V2\ProductGroupController as V2SiteProductGroupController;
use App\Http\Controllers\System\HealthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 主路由 - 注册子路由
|--------------------------------------------------------------------------
*/

Route::get('/v2/site/product-groups', [V2SiteProductGroupController::class, 'index']);
Route::get('/v2/site/product-groups/{group}/children', [V2SiteProductGroupController::class, 'children']);
// 商品目录/详情/库存为公开端点且联动上游实时库存拉取，统一按 IP 限流防止刷量放大上游压力。
Route::get('/v2/site/product-groups/{group}/products', [V2SiteProductGroupController::class, 'products'])->middleware('throttle:site-product-browse');
Route::get('/v2/site/product-groups/{group}/catalog', [V2SiteProductGroupController::class, 'catalog']);
Route::get('/v2/site/product-types', [V2SiteProductController::class, 'types']);
Route::get('/v2/site/products', [V2SiteProductController::class, 'index'])->middleware('throttle:site-product-browse');
Route::get('/v2/site/products/{product}/stock', [V2SiteProductController::class, 'stock'])->middleware('throttle:site-product-browse');
Route::post('/v2/site/products/{product}/quote', [V2SiteProductController::class, 'quote'])->middleware('throttle:product-quote');
Route::get('/v2/site/products/{product}', [V2SiteProductController::class, 'show'])->middleware('throttle:site-product-browse');
Route::get('/v2/site/product-purchase-context', [V2SiteProductController::class, 'purchaseContext']);
Route::get('/v2/site/config', [V2SiteHomeController::class, 'config']);
Route::get('/v2/site/home', [V2SiteHomeController::class, 'home']);
Route::get('/v2/site/home-hero', [V2SiteHomeController::class, 'hero']);
Route::get('/v2/site/content/overview', [V2SiteContentController::class, 'overview']);
Route::get('/v2/site/notices', [V2SiteContentController::class, 'notices'])->middleware('throttle:30,1');
Route::get('/v2/site/notices/{article}', [V2SiteContentController::class, 'noticeDetail']);
Route::get('/v2/site/help-articles', [V2SiteContentController::class, 'helpArticles'])->middleware('throttle:30,1');
Route::get('/v2/site/help-articles/{article}', [V2SiteContentController::class, 'helpDetail']);
Route::get('/health', [HealthController::class, 'live']);
Route::get('/ready', [HealthController::class, 'ready']);
