<?php

use App\Http\Controllers\SecureAssetController;
use App\Http\Controllers\System\InstallerController;
use App\Services\Installer\InstallerStateService;
use App\Support\PublicUrl;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! app(InstallerStateService::class)->isInstalled()) {
        return redirect('/install/');
    }

    return ['message' => '创欧云 API'];
});

Route::get('/install', fn () => redirect('/install/index.html'));
Route::get('/install/api/status', [InstallerController::class, 'status']);
Route::prefix('install/api')->middleware(['ensure.installer', 'throttle:10,1'])->group(function (): void {
    Route::get('/environment', [InstallerController::class, 'environment']);
    Route::post('/database/verify', [InstallerController::class, 'verifyDatabase']);
    Route::post('/install', [InstallerController::class, 'install'])->middleware('throttle:3,1');
});

Route::get('/api/secure-assets/view', [SecureAssetController::class, 'show'])
    ->middleware('signed:relative')
    ->name('secure-assets.show');

Route::get('/client/register', function () {
    $frontendUrl = PublicUrl::website();
    $currentRoot = rtrim(request()->getSchemeAndHttpHost(), '/');

    if ($frontendUrl === '' || $frontendUrl === $currentRoot) {
        abort(404);
    }

    $queryString = request()->getQueryString();
    $target = PublicUrl::website('/client/register');

    if ($queryString) {
        $target .= '?'.$queryString;
    }

    return redirect()->away($target);
});
