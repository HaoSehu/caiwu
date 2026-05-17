<?php

use App\Http\Controllers\SecureAssetController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['message' => '创欧云 API'];
});

Route::get('/api/secure-assets/view', [SecureAssetController::class, 'show'])
    ->middleware('signed:relative')
    ->name('secure-assets.show');

Route::get('/client/register', function () {
    $frontendUrl = rtrim((string) config('app.frontend_url', ''), '/');
    $currentRoot = rtrim(request()->getSchemeAndHttpHost(), '/');

    if ($frontendUrl === '' || $frontendUrl === $currentRoot) {
        abort(404);
    }

    $queryString = request()->getQueryString();
    $target = $frontendUrl.'/client/register';

    if ($queryString) {
        $target .= '?'.$queryString;
    }

    return redirect()->away($target);
});
