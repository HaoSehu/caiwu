<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\Upstream\Drivers\HostingPanelApi\HostingPanelApiTransport;
use App\Services\Upstream\Support\UpstreamConfigOptionNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 上游配置项归一化单一实现护栏（D3B-04 回归）：
 * 平台驱动 Concern 与 zjmf_finance 插件已共同收敛到 UpstreamConfigOptionNormalizer，
 * 平台侧委托方法的输出必须与 normalizer 直接调用完全一致，防止两侧再次漂移。
 */
class UpstreamConfigOptionNormalizerTest extends TestCase
{
    public static function rawConfigOptionSamplesProvider(): array
    {
        return [
            'IPv4 数量型（映射表 + 范围区间）' => [[
                ['id' => 11, 'option_type' => 4, 'option_name' => 'ip_num|IP 数量', 'qty_minimum' => 1, 'qty_maximum' => 5, 'qty_stage' => 1],
            ]],
            '系统盘范围型（展示名关键词推断）' => [[
                ['id' => 13, 'option_type' => 14, 'option_name' => '系统盘', 'unit' => 'G', 'qty_minimum' => 20, 'qty_maximum' => 100],
            ]],
            '带宽范围型（中文关键词推断）' => [[
                ['id' => 10, 'option_type' => 10, 'option_name' => '下行带宽', 'unit' => 'M', 'qty_minimum' => 5, 'qty_maximum' => 200],
            ]],
            '操作系统子项（^ 分段展示标签）' => [[
                [
                    'id' => 21,
                    'option_type' => 5,
                    'option_name' => 'os|操作系统',
                    'sub' => [
                        ['id' => 1, 'option_name' => 'centos7.9|CentOS 7.9^推荐^20240618', 'pricing' => ['monthly' => '0']],
                        ['id' => 2, 'option_name' => 'debian12', 'pricing' => [['monthly' => '1.5', 'onetime' => '10']]],
                    ],
                ],
            ]],
            'CPU/内存子项与多周期计费' => [[
                [
                    'id' => 31,
                    'option_type' => 6,
                    'option_name' => 'cpu|CPU',
                    'sub' => [
                        ['id' => 5, 'option_name' => '2|2 核', 'pricing' => ['monthly' => 10, 'quarterly' => '27', 'hour' => '']],
                        ['id' => 6, 'option_name' => '4|4 核', 'pricing' => ['monthly' => 18, 'onetime' => '50', 'unknown_cycle' => '9']],
                    ],
                ],
            ]],
            '自定义 field 前缀优先于关键词推断' => [[
                ['id' => 41, 'option_type' => 1, 'option_name' => 'system_disk_size|额外磁盘', 'required' => 1, 'hidden' => 0, 'upgrade' => 1],
            ]],
        ];
    }

    #[DataProvider('rawConfigOptionSamplesProvider')]
    public function test_platform_trait_delegate_matches_normalizer_output(array $rawConfigOptions): void
    {
        $viaTransport = $this->invokeTransportNormalizer($rawConfigOptions);
        $viaNormalizer = UpstreamConfigOptionNormalizer::normalizeRemoteConfigOptions($rawConfigOptions);

        $this->assertSame($viaNormalizer, $viaTransport, '平台 Concern 委托输出与 normalizer 直接调用不一致');
    }

    public function test_normalizer_output_contract(): void
    {
        $normalized = UpstreamConfigOptionNormalizer::normalizeRemoteConfigOptions([
            [
                'id' => 21,
                'option_type' => 5,
                'option_name' => 'os|操作系统',
                'sub' => [
                    ['id' => 1, 'option_name' => 'centos7.9|CentOS 7.9^推荐^20240618', 'pricing' => ['monthly' => '10', 'onetime' => '0']],
                    ['id' => 2, 'option_name' => 'debian12', 'pricing' => [['monthly' => '1.5', 'onetime' => '10']]],
                ],
            ],
            [
                'id' => 11,
                'option_type' => 4,
                'option_name' => 'ip_num|IP 数量',
                'qty_minimum' => 2,
                'qty_maximum' => 9,
            ],
        ]);

        $os = $normalized[0];
        $this->assertSame('os', $os['field']);
        $this->assertSame('操作系统', $os['name']);
        // option_type=5 不做 ^ 分段，保留完整标签
        $this->assertSame('CentOS 7.9^推荐^20240618', $os['sub'][0]['version']);
        $this->assertSame('10.00', $os['sub'][0]['pricing']['monthly']);
        // 子项计费取第一层对象，one_time 键名双写
        $this->assertSame('1.50', $os['sub'][1]['pricing']['monthly']);
        $this->assertSame('10.00', $os['sub'][1]['pricing']['onetime']);
        $this->assertSame('centos7.9|CentOS 7.9^推荐^20240618,debian12|debian12', $os['parameter']);

        $ip = $normalized[1];
        // 范围型：无 parameter、保留 qty 区间
        $this->assertSame('ip_num', $ip['field']);
        $this->assertSame('', $ip['parameter']);
        $this->assertSame(2, $ip['qty_minimum']);
        $this->assertSame(9, $ip['qty_maximum']);
    }

    /**
     * 通过平台 transport 实例反射调用 Concern 内的私有委托方法。
     *
     * @param  array<int, mixed>  $rawConfigOptions
     * @return array<int, array<string, mixed>>
     */
    private function invokeTransportNormalizer(array $rawConfigOptions): array
    {
        $transport = new HostingPanelApiTransport;
        $method = (new \ReflectionClass($transport))
            ->getMethod('normalizeRemoteConfigOptions');

        return $method->invoke($transport, $rawConfigOptions);
    }
}
