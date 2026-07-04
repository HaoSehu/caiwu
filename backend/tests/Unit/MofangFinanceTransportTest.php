<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Supplier;
use App\Services\Integrations\Plugins\PluginFileLoader;
use App\Services\Integrations\Plugins\PluginScanner;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiTransport;
use Caiwu\Plugins\Servers\MofangFinance\Lib\MofangAuthManager;
use Caiwu\Plugins\Servers\MofangFinance\Lib\MofangFinanceTransport;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MofangFinanceTransportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PluginFileLoader::class)->ensureLoaded(
            app(PluginScanner::class)->requireManifest('upstream', 'mofang_finance')
        );
    }

    public function test_auth_manager_caches_jwt_under_mofang_provider_key(): void
    {
        config(['mofang.finance_api.jwt_cache_store' => 'array']);

        $supplier = (new Supplier)->forceFill([
            'id' => 321,
            'interface_type' => 'mofang_finance_api',
            'api_url' => 'https://mofang.example.test',
            'api_username' => 'demo',
            'api_key' => 'secret',
        ]);

        $transport = new class extends HostingPanelApiTransport
        {
            public array $captured = [];

            public function request(
                Supplier $supplier,
                string $method,
                string $uri,
                array|string $payload = [],
                ?string $jwt = null,
                array $headers = [],
                array $query = []
            ): array {
                $this->captured[] = compact('method', 'uri', 'payload', 'jwt', 'headers', 'query');

                return ['status' => 200, 'jwt' => 'mofang-plugin-jwt'];
            }
        };

        $auth = new MofangAuthManager($transport);
        $jwt = $auth->login($supplier);

        $this->assertSame('mofang-plugin-jwt', $jwt);
        $this->assertSame('mofang-plugin-jwt', Cache::store('array')->get($auth->jwtCacheKey($supplier)));
        $this->assertSame('/v1/login_api', $transport->captured[0]['uri']);
        $this->assertSame('demo', $transport->captured[0]['payload']['account']);
    }

    public function test_transport_forgets_mofang_jwt_cache_on_unauthorized_response(): void
    {
        config(['mofang.finance_api.jwt_cache_store' => 'array']);

        $supplier = (new Supplier)->forceFill([
            'id' => 654,
            'interface_type' => 'mofang_finance_api',
            'api_url' => 'https://mofang.example.test',
            'api_username' => 'demo',
            'api_key' => 'secret',
        ]);

        $legacyTransport = new class extends HostingPanelApiTransport
        {
            public function request(
                Supplier $supplier,
                string $method,
                string $uri,
                array|string $payload = [],
                ?string $jwt = null,
                array $headers = [],
                array $query = []
            ): array {
                return ['status' => 401, 'data' => []];
            }
        };

        $auth = new MofangAuthManager($legacyTransport);
        Cache::store('array')->put($auth->jwtCacheKey($supplier), 'stale-jwt', now()->addMinutes(5));

        $transport = new MofangFinanceTransport($legacyTransport, $auth);
        $transport->get($supplier, '/v1/user', 'stale-jwt');

        $this->assertNull(Cache::store('array')->get($auth->jwtCacheKey($supplier)));
    }
}
