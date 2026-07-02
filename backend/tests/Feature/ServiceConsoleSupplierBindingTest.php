<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Service;
use App\Models\Supplier;
use App\Services\ClientServiceConsole\ServiceDetailService;
use App\Services\ClientServiceConsole\ServiceResolverService;
use App\Services\ClientServiceConsole\ServiceTransformService;
use App\Services\Integrations\Plugins\PluginInstaller;
use App\Services\Integrations\Plugins\PluginScanner;
use App\Services\System\OperationLogService;
use App\Services\Upstream\ProviderRegistry;
use App\Services\Upstream\ProviderResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ServiceConsoleSupplierBindingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureHostingPanelPluginEnabled();
    }

    #[Test]
    public function it_prefers_the_service_bound_supplier_over_the_product_supplier(): void
    {
        $boundSupplier = new Supplier;
        $boundSupplier->id = 9;
        $boundSupplier->interface_type = 'hosting_panel_api';

        $productSupplier = new Supplier;
        $productSupplier->id = 3;
        $productSupplier->interface_type = 'hosting_panel_api';

        $product = new Product;
        $product->supplier_id = 3;
        $product->setRelation('supplier', $productSupplier);

        $service = new Service;
        $service->provision_data = [
            'supplier_id' => 9,
            'upstream_host_id' => 456,
        ];
        $service->setRelation('product', $product);

        $detailService = $this->makeDetailService($boundSupplier);

        [$resolvedSupplier, $hostId] = $detailService->resolveManagedSupplierAndHost($service);

        $this->assertSame($boundSupplier, $resolvedSupplier);
        $this->assertSame(456, $hostId);
    }

    #[Test]
    public function it_requires_a_supplier_binding_before_marking_service_as_manageable(): void
    {
        $transformService = new ServiceTransformService(new ServiceResolverService);

        $service = new Service;
        $service->provision_data = [
            'provider' => 'hosting_panel_api',
            'upstream_host_id' => 456,
        ];

        $this->assertFalse($transformService->canManageService($service));

        $service->provision_data = [
            'provider' => 'hosting_panel_api',
            'upstream_host_id' => 456,
            'supplier_id' => 9,
        ];

        $this->assertTrue($transformService->canManageService($service));
    }

    #[Test]
    public function service_console_product_relations_select_new_group_hierarchy_columns(): void
    {
        $source = file_get_contents(base_path('app/Services/ClientServiceConsole/ServiceSecurityGroupService.php'));

        $this->assertStringNotContainsString('product:id,name,type,', $source);
        $this->assertStringNotContainsString('product:id,product_type,product_group_id,supplier_id,provision_module', $source);
        $this->assertStringContainsString('product:id,product_type,service_type_code,first_product_group_id,second_product_group_id,third_product_group_id,supplier_id,provision_module', $source);
    }

    private function makeDetailService(?Supplier $resolvedSupplier): ServiceDetailService
    {
        return new class($resolvedSupplier, $this->createMock(OperationLogService::class), $this->createMock(ServiceResolverService::class), $this->createMock(ServiceTransformService::class)) extends ServiceDetailService
        {
            public function __construct(
                private readonly ?Supplier $resolvedSupplier,
                OperationLogService $operationLogService,
                ServiceResolverService $resolverService,
                ServiceTransformService $transformService,
            ) {
                parent::__construct(app(ProviderResolver::class), $operationLogService, $resolverService, $transformService);
            }

            protected function findSupplierById(int $supplierId): ?Supplier
            {
                return $this->resolvedSupplier;
            }
        };
    }

    private function ensureHostingPanelPluginEnabled(): void
    {
        $this->ensurePluginTables();

        $scanner = app(PluginScanner::class);
        $installer = app(PluginInstaller::class);
        $scanner->requireManifest('upstream', 'hosting_panel_api');
        $plugin = $installer->install('upstream', 'hosting_panel_api');
        $installer->enable($plugin);

        $this->app->forgetInstance(ProviderRegistry::class);
        $this->app->forgetInstance(ProviderResolver::class);
    }

    private function ensurePluginTables(): void
    {
        if (! Schema::hasTable('integration_plugins')) {
            Schema::create('integration_plugins', function (Blueprint $table): void {
                $table->id();
                $table->string('domain', 32);
                $table->string('slug', 120);
                $table->string('plugin_key', 120);
                $table->string('name', 120);
                $table->string('version', 32)->default('1.0.0');
                $table->string('provider_class', 255)->nullable();
                $table->string('entry_class', 255);
                $table->json('capabilities_json')->nullable();
                $table->json('config_schema_json')->nullable();
                $table->unsignedTinyInteger('status')->default(0);
                $table->timestamp('installed_at')->nullable();
                $table->timestamps();
                $table->unique(['domain', 'slug']);
                $table->unique(['domain', 'plugin_key']);
            });
        }

        if (! Schema::hasTable('integration_plugin_configs')) {
            Schema::create('integration_plugin_configs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('plugin_id');
                $table->json('config_json')->nullable();
                $table->longText('secret_json')->nullable();
                $table->json('has_secret_json')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->unique('plugin_id');
            });
        }
    }
}
