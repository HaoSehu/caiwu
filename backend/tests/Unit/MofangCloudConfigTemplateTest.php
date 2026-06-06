<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Integrations\Mofang\Adapters\MofangFinanceAdapter;
use App\Integrations\Mofang\Support\MofangCloudConfigTemplate;
use App\Models\Supplier;
use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiTransport;
use PHPUnit\Framework\TestCase;

class MofangCloudConfigTemplateTest extends TestCase
{
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
        $supplier = new Supplier;
        $transport = $this->createMock(HostingPanelApiTransport::class);
        $transport
            ->method('getProductCatalog')
            ->with($supplier)
            ->willReturn([
                'products' => [
                    [
                        'id' => 1001,
                        'name' => '香港 CN2 云服务器',
                        'type' => 'dcimcloud',
                        'description' => 'CPU：2核<br>内存：4G<br>带宽：20M',
                    ],
                ],
            ]);
        $transport
            ->method('fetchRealConfigOptions')
            ->with($supplier, 1001)
            ->willReturn([]);

        $template = (new MofangFinanceAdapter($transport, new MofangCloudConfigTemplate))
            ->getProductConfigTemplate($supplier, 1001);

        $optionsByField = collect($template['config_options'])->keyBy('field');

        $this->assertSame('2|2 核心', $optionsByField->get('cpu')['parameter']);
        $this->assertSame('4096|4G', $optionsByField->get('memory')['parameter']);
        $this->assertSame(['cpu', 'memory', 'bw'], $template['auto_filled_fields']);
    }

    public function test_mofang_adapter_labels_mofang_cloud_product_types(): void
    {
        $supplier = new Supplier;
        $transport = $this->createMock(HostingPanelApiTransport::class);
        $transport
            ->method('getProductCatalog')
            ->with($supplier)
            ->willReturn([
                'products' => [
                    [
                        'id' => 1001,
                        'name' => '香港 CN2 云服务器',
                        'type' => 'dcimcloud',
                        'type_label' => 'dcimcloud',
                        'description' => 'CPU：2核',
                    ],
                ],
            ]);
        $transport
            ->method('fetchRealConfigOptions')
            ->with($supplier, 1001)
            ->willReturn([]);

        $template = (new MofangFinanceAdapter($transport, new MofangCloudConfigTemplate))
            ->getProductConfigTemplate($supplier, 1001);

        $this->assertSame('云服务器', $template['product']['type_label']);
    }

    public function test_mofang_adapter_labels_mofang_cloud_product_types_in_catalog(): void
    {
        $supplier = new Supplier;
        $transport = $this->createMock(HostingPanelApiTransport::class);
        $transport
            ->method('getProductCatalog')
            ->with($supplier)
            ->willReturn([
                'groups' => [
                    [
                        'key' => 'group-cloud',
                        'label' => '云服务器',
                        'items' => [
                            [
                                'id' => 1001,
                                'name' => '香港 CN2 云服务器',
                                'type' => 'dcimcloud',
                                'type_label' => 'dcimcloud',
                            ],
                        ],
                    ],
                ],
                'products' => [
                    [
                        'id' => 1001,
                        'name' => '香港 CN2 云服务器',
                        'type' => 'dcimcloud',
                        'type_label' => 'dcimcloud',
                    ],
                ],
            ]);

        $catalog = (new MofangFinanceAdapter($transport, new MofangCloudConfigTemplate))
            ->getProductCatalog($supplier);

        $this->assertSame('云服务器', $catalog['products'][0]['type_label']);
        $this->assertSame('云服务器', $catalog['groups'][0]['items'][0]['type_label']);
    }
}
