<?php

namespace App\Providers;

use App\Services\Auth\LegacyPasswordVerifier;
use App\Services\Sms\SmsDriverManager;
use App\Services\System\UploadedAssetReferenceService;
use App\Services\Verification\VerificationDriverManager;
use Carbon\CarbonInterface;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $mysqlConnection = config('database.connections.mysql', []);

        config([
            'database.default' => 'mysql',
            'database.connections' => [
                'mysql' => $mysqlConnection,
            ],
        ]);

        $this->app->singleton(UploadedAssetReferenceService::class);
        $this->app->singleton(SmsDriverManager::class);
        $this->app->singleton(VerificationDriverManager::class);
        $this->app->singleton(
            LegacyPasswordVerifier::class,
            fn (): LegacyPasswordVerifier => new LegacyPasswordVerifier($this->app->tagged('auth.legacy_password_verifiers'))
        );
    }

    public function boot(): void
    {
        Sanctum::authenticateAccessTokensUsing(function (PersonalAccessToken $accessToken, bool $isValid): bool {
            if (! $isValid) {
                return false;
            }

            $idleTimeout = max((int) config('sanctum.idle_timeout', 0), 0);
            if ($idleTimeout <= 0) {
                return true;
            }

            $lastActiveAt = $accessToken->last_used_at ?? $accessToken->created_at;
            if (! $lastActiveAt instanceof CarbonInterface) {
                return true;
            }

            if ($lastActiveAt->lt(now()->subSeconds($idleTimeout))) {
                $accessToken->delete();

                return false;
            }

            return true;
        });
    }
}
