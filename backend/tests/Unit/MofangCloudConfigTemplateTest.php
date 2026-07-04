<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Supplier;
use App\Services\Integrations\Plugins\PluginFileLoader;
use App\Services\Integrations\Plugins\PluginScanner;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiTransport;
use Caiwu\Plugins\Servers\MofangFinance\Lib\MofangCloudConfigTemplate;
use Caiwu\Plugins\Servers\MofangFinance\Lib\MofangFinanceAdapter;
use Tests\TestCase;

class MofangCloudConfigTemplateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(PluginFileLoader::class)->ensureLoaded(
            app(PluginScanner::class)->requireManifest('upstream', 'mofang_finance')
        );
    }

    public function test_it_builds_cloud_config_template_from_product_description(): void
    {
        $template = new MofangCloudConfigTemplate;

        $configOptions = $template->build([
            'name' => '香港 CN2 云服务器',
            'type' => 'dcimcloud',
            'description' => 'CPU：2核<br>内存：4G<br>带宽：20M<br>流量：1T<br>硬盘：60G',
        ]);

        $optionsByField = collect($configOptions)->keyBy('field');

        $this->assertSame('mofang_api', $optionsByField->get('cpu')['source']);
        $this->assertSame('2|2 核心', $optionsByField->get('cpu')['parameter']);
        $this->assertSame('4096|4G', $optionsByField->get('memory')['parameter']);
        $this->assertSame('20|20Mbps', $optionsByField->get('bw')['parameter']);
        $this->assertSame('1024|1T', $optionsByField->get('flow_limit')['parameter']);
        $this->assertSame('60|系统盘', $optionsByField->get('system_disk_size')['parameter']);
    }

    public function test_it_exposes_supported_cloud_product_types(): void
    {
        $template = new MofangCloudConfigTemplate;

        $this->assertTrue($template->supports(['type' => 'dcimcloud']));
        $this->assertTrue($template->supports(['type' => 'cloud']));
        $this->assertTrue($template->supports(['type' => 'vps']));
        $this->assertFalse($template->supports(['type' => 'server']));
    }

    public function test_mofang_adapter_falls_back_to_cloud_template_when_remote_options_are_empty(): void
    {
        $supplier = $this->makeSupplier();
        $transport = $this->makeCatalogTransport([
            [
                'id' => 1001,
                'name' => '香港 CN2 云服务器',
                'type' => 'dcimcloud',
                'description' => 'CPU：2核<br>内存：4G<br>带宽：20M',
            ],
        ]);

        $template = (new MofangFinanceAdapter($transport, new MofangCloudConfigTemplate))
            ->getProductConfigTemplate($supplier, 1001);

        $optionsByField = collect($template['config_options'])->keyBy('field');

        $this->assertSame('2|2 核心', $optionsByField->get('cpu')['parameter']);
        $this->assertSame('4096|4G', $optionsByField->get('memory')['parameter']);
        $this->assertSame(['cpu', 'memory', 'bw'], $template['auto_filled_fields']);
    }

    public function test_mofang_adapter_labels_mofang_cloud_product_types(): void
    {
        $supplier = $this->makeSupplier();
        $transport = $this->makeCatalogTransport([
            [
                'id' => 1001,
                'name' => '香港 CN2 云服务器',
                'type' => 'dcimcloud',
                'type_label' => 'dcimcloud',
                'description' => 'CPU：2核',
            ],
        ]);

        $template = (new MofangFinanceAdapter($transport, new MofangCloudConfigTemplate))
            ->getProductConfigTemplate($supplier, 1001);

        $this->assertSame('云服务器', $template['product']['type_label']);
    }

    public function test_mofang_adapter_labels_mofang_cloud_product_types_in_catalog(): void
    {
        $supplier = $this->makeSupplier();
        $transport = $this->makeCatalogTransport([
            [
                'id' => 1001,
                'name' => '香港 CN2 云服务器',
                'type' => 'dcimcloud',
                'type_label' => 'dcimcloud',
            ],
        ]);

        $catalog = (new MofangFinanceAdapter($transport, new MofangCloudConfigTemplate))
            ->getProductCatalog($supplier);

        $this->assertSame('云服务器', $catalog['products'][0]['type_label']);
        $this->assertSame('云服务器', $catalog['groups'][0]['items'][0]['type_label']);
    }

    private function makeSupplier(): Supplier
    {
        return (new Supplier)->forceFill([
            'id' => 1,
            'interface_type' => 'mofang_finance_api',
            'api_url' => 'https://mofang.example.test',
            'api_username' => 'demo',
            'api_key' => 'secret',
        ]);
    }

    private function makeCatalogTransport(array $products): HostingPanelApiTransport
    {
        return new class($products) extends HostingPanelApiTransport
        {
            public function __construct(private readonly array $products) {}

            public function request(
                Supplier $supplier,
                string $method,
                string $uri,
                array|string $payload = [],
                ?string $jwt = null,
                array $headers = [],
                array $query = []
            ): array {
                if ($uri === '/v1/login_api') {
                    return ['status' => 200, 'jwt' => 'mofang-jwt'];
                }

                if ($uri === '/v1/products') {
                    return [
                        'status' => 200,
                        'data' => [
                            'first_group' => [
                                [
                                    'name' => '云服务器',
                                    'group' => [
                                        [
                                            'name' => '云服务器',
                                            'products' => $this->products,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ];
                }

                if ($uri === '/v1/productsconfig') {
                    return ['status' => 200, 'data' => ['first_group' => []]];
                }

                if (str_contains($uri, '/cart/get_product_config')) {
                    return ['status' => 200, 'data' => ['config_groups' => []]];
                }

                return ['status' => 200, 'data' => []];
            }
        };
    }
}
