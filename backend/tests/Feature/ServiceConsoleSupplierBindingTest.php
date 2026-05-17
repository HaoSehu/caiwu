<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Service;
use App\Models\Supplier;
use App\Services\ClientServiceConsole\ServiceDetailService;
use App\Services\ClientServiceConsole\ServiceResolverService;
use App\Services\ClientServiceConsole\ServiceTransformService;
use App\Services\System\OperationLogService;
use App\Services\Upstream\ProviderResolver;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ServiceConsoleSupplierBindingTest extends TestCase
{
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
    public function service_console_product_relations_select_real_product_type_column(): void
    {
        $source = file_get_contents(base_path('app/Services/ClientServiceConsole/ServiceSecurityGroupService.php'));

        $this->assertStringNotContainsString('product:id,name,type,', $source);
        $this->assertStringContainsString('product:id,product_type,product_group_id,supplier_id,provision_module', $source);
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
}
