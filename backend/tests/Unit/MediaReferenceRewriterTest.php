<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Content\Storage\MediaReferenceRewriter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 引用替换的键序与命中即停语义（生产 settings 存在完整 URL 与相对路径两种引用形态）。
 *
 * 迁移服务与外链订正命令共用 MediaReferenceRewriter，这里直测该类，
 * 保证两条链路的替换语义只有一份实现、一份验收。
 */
class MediaReferenceRewriterTest extends TestCase
{
    private MediaReferenceRewriter $rewriter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->rewriter = app(MediaReferenceRewriter::class);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function referenceFormProvider(): array
    {
        return [
            '完整 URL 引用（生产 settings 主形态）' => ['https://api.coyjs.cn/media/7.mp4'],
            '相对路径引用' => ['/media/7.mp4'],
            'JSON 内嵌相对路径' => ['{"video":"/media/7.mp4"}'],
        ];
    }

    #[DataProvider('referenceFormProvider')]
    public function test_to_remote_replacement_has_no_double_prefix(string $referenceForm): void
    {
        $map = $this->rewriter->buildPathMap('/media/7.mp4', 'https://api.coyjs.cn/media/7.mp4', 'http://ftp.example.com/media/7.mp4');

        if ($referenceForm === '{"video":"/media/7.mp4"}') {
            $updated = $this->rewriter->applyPathMap($referenceForm, $map);

            $this->assertSame('{"video":"http://ftp.example.com/media/7.mp4"}', $updated);

            return;
        }

        $updated = $this->rewriter->applyPathMap($referenceForm, $map);

        $this->assertSame('http://ftp.example.com/media/7.mp4', $updated);
        $this->assertStringNotContainsString('http://ftp.example.comhttp://', $updated, '不得出现双重前缀');
        $this->assertStringNotContainsString('https://api.coyjs.cnhttp://', $updated, '域名前缀不得残留');
    }

    public function test_to_local_replacement_restores_local_url(): void
    {
        // 迁回本地：path===url 同值，仅一个键。
        $map = $this->rewriter->buildPathMap('http://ftp.example.com/media/7.mp4', 'http://ftp.example.com/media/7.mp4', 'https://api.coyjs.cn/media/7.mp4');

        $this->assertSame(['http://ftp.example.com/media/7.mp4' => 'https://api.coyjs.cn/media/7.mp4'], $map);

        $updated = $this->rewriter->applyPathMap('http://ftp.example.com/media/7.mp4', $map);

        $this->assertSame('https://api.coyjs.cn/media/7.mp4', $updated);
    }

    public function test_build_path_map_orders_long_key_first(): void
    {
        $map = $this->rewriter->buildPathMap('/media/a.jpg', 'https://api.coyjs.cn/media/a.jpg', 'http://cdn.example.com/media/a.jpg');

        $this->assertSame([
            'https://api.coyjs.cn/media/a.jpg' => 'http://cdn.example.com/media/a.jpg',
            '/media/a.jpg' => 'http://cdn.example.com/media/a.jpg',
        ], $map, '完整 URL（长键）必须排在相对路径（短键）之前');
    }

    public function test_unrelated_value_passes_through_unchanged(): void
    {
        $map = $this->rewriter->buildPathMap('/media/a.jpg', 'https://api.coyjs.cn/media/a.jpg', 'http://cdn.example.com/media/a.jpg');

        $updated = $this->rewriter->applyPathMap('https://other.example.com/picture.png', $map);

        $this->assertSame('https://other.example.com/picture.png', $updated);
    }

    public function test_same_scheme_domain_change_rewrites_complete_url(): void
    {
        // HTTP → HTTPS 换域名的真实场景：协议与主机同时变化，路径段保持不变。
        $map = $this->rewriter->buildPathMap(
            'http://192.0.2.10/media/img_155342_g76d3jXT.jpg',
            'http://192.0.2.10/media/img_155342_g76d3jXT.jpg',
            'https://media.example.com/media/img_155342_g76d3jXT.jpg'
        );

        $this->assertSame(
            'https://media.example.com/media/img_155342_g76d3jXT.jpg',
            $this->rewriter->applyPathMap('http://192.0.2.10/media/img_155342_g76d3jXT.jpg', $map)
        );
    }
}
