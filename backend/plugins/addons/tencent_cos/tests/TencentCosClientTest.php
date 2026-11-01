<?php

declare(strict_types=1);

namespace Tests\Unit;

use Caiwu\Plugins\Addons\TencentCos\Lib\TencentCosClient;
use Caiwu\Plugins\Addons\TencentCos\Lib\TencentCosSigner;
use Caiwu\Plugins\Addons\TencentCos\Lib\TencentCosSupport;
use Caiwu\Plugins\Addons\TencentCos\TencentCos;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// 插件类由运行时的 PluginFileLoader 按 require 加载，测试中需手动引入
require_once __DIR__.'/../lib/TencentCosClient.php';
require_once __DIR__.'/../lib/TencentCosSigner.php';
require_once __DIR__.'/../lib/TencentCosSupport.php';
require_once __DIR__.'/../logic/TencentCos.php';

/**
 * 腾讯云 COS 插件契约回归（不发真实网络请求）。
 *
 * 覆盖四块：
 * 1. 签名纯函数：固定时间戳下 Authorization 各段与官方六步算法一致；
 * 2. Support 校验：object_key 规则、local_path/target_dir 越界拦截、外链拼接公式；
 * 3. 传输桩全链路：五个动作的请求形状（方法/URL/参与签名的头）与返回结构归一；
 * 4. 安全：密钥不进返回结构，ACL 头参与签名。
 */
class TencentCosClientTest extends TestCase
{
    /** 测试用占位密钥（非真实凭据） */
    private const SECRET_ID = 'test-ak';

    private const SECRET_KEY = 'test-sk';

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function config(array $overrides = []): array
    {
        return array_merge([
            'secret_id' => self::SECRET_ID,
            'secret_key' => self::SECRET_KEY,
            'bucket' => 'testbucket-1250000000',
            'region' => 'ap-beijing',
            'acl' => 'none',
            'public_domain' => '',
            'object_prefix' => 'media',
            'timeout' => 30,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function client(array $config, callable $transport): TencentCosClient
    {
        return new TencentCosClient($config, $transport);
    }

    /**
     * 签名形状：固定时间戳，六段拼接与参与签名的头列表。
     */
    public function test_authorization_shape_matches_official_six_steps(): void
    {
        $authorization = TencentCosSigner::authorization(
            self::SECRET_ID,
            self::SECRET_KEY,
            'put',
            '/media/img_20300101_abc.jpg',
            [
                'host' => 'testbucket-1250000000.cos.ap-beijing.myqcloud.com',
                'content-type' => 'image/jpeg',
                'content-length' => '1024',
            ],
            [],
            1700000000,
        );

        // 六段固定顺序拼接
        $this->assertSame(
            'q-sign-algorithm=sha1&q-ak=test-ak&q-sign-time=1699999940;1700003600&q-key-time=1699999940;1700003600'
            .'&q-header-list=content-length;content-type;host&q-url-param-list=&q-signature=',
            substr($authorization, 0, strlen($authorization) - 40),
        );
        // 签名体为 40 位小写十六进制
        $this->assertMatchesRegularExpression('/^[0-9a-f]{40}$/', substr($authorization, -40));

        // 同输入可复现，换密钥即变
        $again = TencentCosSigner::authorization(
            self::SECRET_ID,
            self::SECRET_KEY,
            'PUT',
            '/media/img_20300101_abc.jpg',
            [
                'content-type' => 'image/jpeg',
                'host' => 'testbucket-1250000000.cos.ap-beijing.myqcloud.com',
                'content-length' => '1024',
            ],
            [],
            1700000000,
        );
        $this->assertSame($authorization, $again);
        $other = TencentCosSigner::authorization(
            self::SECRET_ID,
            'another-key',
            'put',
            '/media/img_20300101_abc.jpg',
            ['host' => 'testbucket-1250000000.cos.ap-beijing.myqcloud.com'],
            [],
            1700000000,
        );
        $this->assertNotSame($authorization, $other);
    }

    /**
     * object_key 校验规则：非空、≤500、无前导 /、段内无 ./..、字符集受限。
     */
    public function test_normalize_object_key_rules(): void
    {
        $this->assertSame('media/img.jpg', TencentCosSupport::normalizeObjectKey(' media/img.jpg '));
        $this->assertNull(TencentCosSupport::normalizeObjectKey(''));
        $this->assertNull(TencentCosSupport::normalizeObjectKey('/media/img.jpg'));
        $this->assertNull(TencentCosSupport::normalizeObjectKey('media//img.jpg'));
        $this->assertNull(TencentCosSupport::normalizeObjectKey('media/../img.jpg'));
        $this->assertNull(TencentCosSupport::normalizeObjectKey('media/img name.jpg'));
        $this->assertNull(TencentCosSupport::normalizeObjectKey(str_repeat('a', 501)));
    }

    /**
     * local_path 越界拦截：必须在 public/media 或 storage/app/tmp 之内。
     */
    public function test_resolve_local_file_rejects_paths_outside_allowed_roots(): void
    {
        $inside = storage_path('app/tmp').DIRECTORY_SEPARATOR.'cos_allowed_'.bin2hex(random_bytes(4)).'.txt';
        file_put_contents($inside, 'x');
        $this->assertSame((string) realpath($inside), (string) TencentCosSupport::resolveLocalFile($inside));
        unlink($inside);

        // 系统临时目录（storage/app/tmp 之外）必须拒绝
        $outside = sys_get_temp_dir().'/cos_denied_'.bin2hex(random_bytes(4)).'.txt';
        file_put_contents($outside, 'x');
        $this->assertNull(TencentCosSupport::resolveLocalFile($outside));
        unlink($outside);

        $this->assertNull(TencentCosSupport::resolveLocalFile(__FILE__));
        $this->assertNull(TencentCosSupport::resolveLocalFile(null));
    }

    /**
     * target_dir 校验：必须位于 storage/app/tmp 之内。
     */
    public function test_resolve_target_dir_rejects_paths_outside_tmp(): void
    {
        $this->assertSame(
            (string) realpath(storage_path('app/tmp')),
            (string) TencentCosSupport::resolveTargetDir(storage_path('app/tmp')),
        );
        $this->assertNull(TencentCosSupport::resolveTargetDir(sys_get_temp_dir()));
        $this->assertNull(TencentCosSupport::resolveTargetDir(public_path('media')));
    }

    /**
     * 外链拼接：public_domain 优先，否则桶默认域名；路径段编码。
     */
    public function test_build_public_url_formula(): void
    {
        $this->assertSame(
            'https://testbucket-1250000000.cos.ap-beijing.myqcloud.com/media/img.jpg',
            TencentCosSupport::buildPublicUrl('media/img.jpg', 'testbucket-1250000000', 'ap-beijing', null),
        );
        $this->assertSame(
            'https://cdn.example.com/media/img.jpg',
            TencentCosSupport::buildPublicUrl('media/img.jpg', 'testbucket-1250000000', 'ap-beijing', 'https://cdn.example.com/'),
        );
        // 路径段编码：空格 → %20，斜杠保留
        $this->assertSame(
            'media/a%20b.jpg',
            TencentCosSupport::encodeKeyPath('media/a b.jpg'),
        );
    }

    /**
     * put 全链路（传输桩）：PUT /{key}，签名头含 host/content-type/content-length，
     * public-read 时 x-cos-acl 参与签名；返回结构带 object_key/url/size。
     */
    public function test_put_request_shape_and_result(): void
    {
        $localPath = storage_path('app/tmp').'/cos_put_'.bin2hex(random_bytes(4)).'.jpg';
        file_put_contents($localPath, 'jpeg-bytes');

        $captured = [];
        $transport = function (string $method, string $url, array $options) use (&$captured): array {
            $captured = ['method' => $method, 'url' => $url, 'headers' => $options['headers']];

            return ['status' => 200, 'headers' => ['etag' => '"d41d8cd98f00b204e9800998ecf8427e"'], 'body' => ''];
        };

        $config = $this->config(['acl' => 'public-read']);
        $client = $this->client($config, $transport);
        $result = $client->putObject('media/img.jpg', $localPath, 'image/jpeg');

        $this->assertSame('PUT', $captured['method']);
        $this->assertSame('https://testbucket-1250000000.cos.ap-beijing.myqcloud.com/media/img.jpg', $captured['url']);
        $auth = $captured['headers']['Authorization'];
        // x-cos-acl 参与签名（出现在 q-header-list）
        $this->assertStringContainsString('q-header-list=content-length;content-type;host;x-cos-acl', $auth);
        $this->assertStringContainsString('q-ak=test-ak', $auth);
        $this->assertSame('public-read', $captured['headers']['x-cos-acl']);
        // 密钥不进请求头之外的返回结构
        $this->assertStringNotContainsString('test-sk', json_encode($result));
        $this->assertSame(200, $result['status']);

        unlink($localPath);
    }

    /**
     * get 下载落盘：sink 指向 storage/app/tmp 之内的随机名临时文件。
     */
    public function test_get_downloads_into_target_dir(): void
    {
        $targetDir = (string) TencentCosSupport::resolveTargetDir(storage_path('app/tmp'));
        $captured = [];
        $transport = function (string $method, string $url, array $options) use (&$captured): array {
            $captured = ['method' => $method, 'url' => $url, 'sink' => $options['sink'] ?? null];
            if (! empty($options['sink'])) {
                file_put_contents($options['sink'], 'downloaded-bytes');
            }

            return ['status' => 200, 'headers' => [], 'body' => ''];
        };

        $client = $this->client($this->config(), $transport);
        $result = $client->getObject('media/img.jpg', $targetDir);

        $this->assertSame('GET', $captured['method']);
        $this->assertSame('https://testbucket-1250000000.cos.ap-beijing.myqcloud.com/media/img.jpg', $captured['url']);
        $this->assertMatchesRegularExpression('/cos_[0-9a-f]{24}\\.tmp$/', str_replace('\\', '/', (string) $captured['sink']));
        $this->assertSame(200, $result['status']);
        $this->assertFileExists($result['temp_path']);
        $this->assertSame(16, strlen((string) file_get_contents($result['temp_path'])));

        unlink($result['temp_path']);
    }

    /**
     * logic 层前置校验：非法 object_key / 非法 local_path 在发请求之前直接失败（Http::fake 兜底断言零请求）。
     */
    public function test_logic_rejects_invalid_inputs_before_any_request(): void
    {
        Http::fake();

        $logic = new TencentCos;

        $badKey = $logic->execute([
            'action' => 'storage.delete',
            'payload' => ['object_key' => '/media/img.jpg'],
            'config' => $this->config(),
        ]);
        $this->assertFalse($badKey['success']);
        $this->assertSame('object_key_invalid', $badKey['data']['code']);
        $this->assertStringStartsWith('腾讯云COS存储：', $badKey['message']);

        $badPath = $logic->execute([
            'action' => 'storage.put',
            'payload' => ['object_key' => 'media/img.jpg', 'local_path' => 'C:/Windows/win.ini'],
            'config' => $this->config(),
        ]);
        $this->assertFalse($badPath['success']);
        $this->assertSame('local_path_invalid', $badPath['data']['code']);

        $unknown = $logic->execute([
            'action' => 'storage.copy',
            'payload' => [],
            'config' => $this->config(),
        ]);
        $this->assertFalse($unknown['success']);
        // unknown_action=action 名错误（与 ftp/qiniu 契约对齐）
        $this->assertSame('unknown_action', $unknown['data']['code']);

        Http::assertNothingSent();
    }

    /**
     * logic 层 delete 归一：404 按 missing=true 成功；delete 走 Http::fake 验证 URL 与签名头。
     */
    public function test_logic_delete_missing_via_fake_http(): void
    {
        Http::fake([
            'testbucket-1250000000.cos.ap-beijing.myqcloud.com/*' => Http::response('', 404),
        ]);

        $logic = new TencentCos;
        $result = $logic->execute([
            'action' => 'storage.delete',
            'payload' => ['object_key' => 'media/missing.jpg'],
            'config' => $this->config(),
        ]);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['data']['missing']);
        $this->assertTrue($result['data']['deleted']);

        Http::assertSent(function (Request $request) {
            $authorization = $request->header('Authorization');

            return $request->hasHeader('Authorization')
                && str_starts_with((string) ($authorization[0] ?? ''), 'q-sign-algorithm=sha1')
                && $request->method() === 'DELETE'
                && $request->url() === 'https://testbucket-1250000000.cos.ap-beijing.myqcloud.com/media/missing.jpg';
        });
    }

    /**
     * logic 层 put 归一：成功返回 object_key/url/size，外链用桶默认域名。
     */
    public function test_logic_put_success_returns_full_contract(): void
    {
        $localPath = storage_path('app/tmp').'/cos_put2_'.bin2hex(random_bytes(4)).'.jpg';
        file_put_contents($localPath, 'jpeg-bytes');

        Http::fake([
            'testbucket-1250000000.cos.ap-beijing.myqcloud.com/*' => Http::response('', 200, ['ETag' => '"abc123"']),
        ]);

        $logic = new TencentCos;
        $result = $logic->execute([
            'action' => 'storage.put',
            'payload' => [
                'local_path' => $localPath,
                'object_key' => 'media/img_20300101_abc.jpg',
                'mime_type' => 'image/jpeg',
                'content_length' => 10,
            ],
            'config' => $this->config(['public_domain' => '']),
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('media/img_20300101_abc.jpg', $result['data']['object_key']);
        $this->assertSame(
            'https://testbucket-1250000000.cos.ap-beijing.myqcloud.com/media/img_20300101_abc.jpg',
            $result['data']['url'],
        );
        $this->assertSame(10, $result['data']['size']);
        $this->assertSame('abc123', $result['data']['etag']);

        unlink($localPath);
    }

    /**
     * 签名复算（A 项）：固定 KeyTime/密钥/HttpString，按官方六步手工推演 40 位 hex 签名。
     * 口径以官方 SDK cos-php-sdk-v5 src/Signature.php 为准：StringToSign 末尾带换行。
     */
    public function test_authorization_signature_recomputation(): void
    {
        $keyTime = '1699999940;1700003600'; // now=1700000000（起点前移 60 秒，间隔 3600）
        $signKey = hash_hmac('sha1', $keyTime, self::SECRET_KEY);

        // HttpString 手工推演：params 为空保留空行；头按名小写字典序，值 rawurlencode
        $httpString = "put\n/media/img_20300101_abc.jpg\n\n"
            .'content-length=1024&content-type=image%2Fjpeg&host=testbucket-1250000000.cos.ap-beijing.myqcloud.com'."\n";
        $stringToSign = 'sha1'."\n".$keyTime."\n".sha1($httpString)."\n";
        $expectedSignature = hash_hmac('sha1', $stringToSign, $signKey);

        $authorization = TencentCosSigner::authorization(
            self::SECRET_ID,
            self::SECRET_KEY,
            'put',
            '/media/img_20300101_abc.jpg',
            [
                'host' => 'testbucket-1250000000.cos.ap-beijing.myqcloud.com',
                'content-type' => 'image/jpeg',
                'content-length' => '1024',
            ],
            [],
            1700000000,
        );

        $this->assertSame(
            'q-sign-algorithm=sha1&q-ak=test-ak&q-sign-time=1699999940;1700003600&q-key-time=1699999940;1700003600'
            .'&q-header-list=content-length;content-type;host&q-url-param-list=&q-signature='.$expectedSignature,
            $authorization,
        );
    }

    /**
     * logic 层 get 404 归一为 missing（对象不存在，与 delete 语境一致）。
     */
    public function test_logic_get_404_normalizes_to_missing(): void
    {
        Http::fake([
            'testbucket-1250000000.cos.ap-beijing.myqcloud.com/*' => Http::response('', 404),
        ]);

        $logic = new TencentCos;
        $result = $logic->execute([
            'action' => 'storage.get',
            'payload' => ['object_key' => 'media/missing.jpg', 'target_dir' => storage_path('app/tmp')],
            'config' => $this->config(),
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('missing', $result['data']['code']);
        $this->assertSame('腾讯云COS存储：对象不存在：media/missing.jpg', $result['message']);
    }

    /**
     * logic 层 storage.test：403 视为链路通（writable=false）。
     */
    public function test_logic_test_403_reachable_but_readonly(): void
    {
        Http::fake([
            'testbucket-1250000000.cos.ap-beijing.myqcloud.com/*' => Http::response('', 403),
        ]);

        $logic = new TencentCos;
        $reachable = $logic->execute([
            'action' => 'storage.test',
            'payload' => [],
            'config' => $this->config(),
        ]);

        $this->assertTrue($reachable['success']);
        $this->assertTrue($reachable['data']['ok']);
        $this->assertFalse($reachable['data']['writable']);
        $this->assertSame('testbucket-1250000000', $reachable['data']['bucket']);
        $this->assertSame('ap-beijing', $reachable['data']['region']);
    }

    /**
     * logic 层 storage.test：401 归一 auth_failed（独立用例，避免 fake stub 叠加）。
     */
    public function test_logic_test_401_normalizes_to_auth_failed(): void
    {
        Http::fake([
            'testbucket-1250000000.cos.ap-beijing.myqcloud.com/*' => Http::response('', 401),
        ]);

        $logic = new TencentCos;
        $denied = $logic->execute([
            'action' => 'storage.test',
            'payload' => [],
            'config' => $this->config(),
        ]);

        $this->assertFalse($denied['success']);
        $this->assertSame('auth_failed', $denied['data']['code']);
        $this->assertStringStartsWith('腾讯云COS存储：', $denied['message']);
    }

    /**
     * logic 层 put 非 200/204 归一 upstream_rejected（HTTP 500）。
     */
    public function test_logic_put_500_normalizes_to_upstream_rejected(): void
    {
        $localPath = storage_path('app/tmp').'/cos_put500_'.bin2hex(random_bytes(4)).'.jpg';
        file_put_contents($localPath, 'jpeg-bytes');

        Http::fake([
            'testbucket-1250000000.cos.ap-beijing.myqcloud.com/*' => Http::response('internal error', 500),
        ]);

        $logic = new TencentCos;
        $result = $logic->execute([
            'action' => 'storage.put',
            'payload' => [
                'local_path' => $localPath,
                'object_key' => 'media/img.jpg',
                'mime_type' => 'image/jpeg',
                'content_length' => 10,
            ],
            'config' => $this->config(),
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('upstream_rejected', $result['data']['code']);
        $this->assertStringStartsWith('腾讯云COS存储：', $result['message']);

        unlink($localPath);
    }

    /**
     * logic 层 put：本地实测大小与 content_length 不一致时，发请求前拦截（local_path_invalid）。
     */
    public function test_logic_put_rejects_content_length_mismatch(): void
    {
        Http::fake();

        $localPath = storage_path('app/tmp').'/cos_mismatch_'.bin2hex(random_bytes(4)).'.jpg';
        file_put_contents($localPath, 'jpeg-bytes'); // 实测 10 字节

        $logic = new TencentCos;
        $result = $logic->execute([
            'action' => 'storage.put',
            'payload' => [
                'local_path' => $localPath,
                'object_key' => 'media/img.jpg',
                'mime_type' => 'image/jpeg',
                'content_length' => 9, // 与实测不一致
            ],
            'config' => $this->config(),
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('local_path_invalid', $result['data']['code']);
        $this->assertStringContainsString('content_length 不一致', $result['message']);
        $this->assertStringContainsString('10 字节', $result['message']);

        Http::assertNothingSent();

        unlink($localPath);
    }

    /**
     * Throwable 兜底：非 RuntimeException 异常不外抛，归一为 upstream_rejected 且消息脱敏。
     */
    public function test_logic_catches_throwable_and_sanitizes_message(): void
    {
        Http::fake(function () {
            throw new \LogicException('boomed with secret_key=AKID_leak inside');
        });

        $logic = new TencentCos;
        $result = $logic->execute([
            'action' => 'storage.exists',
            'payload' => ['object_key' => 'media/img.jpg'],
            'config' => $this->config(),
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('upstream_rejected', $result['data']['code']);
        // 密钥素材不进消息
        $this->assertStringNotContainsString('AKID_leak', $result['message']);
        $this->assertStringNotContainsString('secret_key', $result['message']);
        $this->assertStringStartsWith('腾讯云COS存储：', $result['message']);
    }

    /**
     * storage.url：完整对象键按 public_domain 现算外链（与 put 同一 buildPublicUrl，全等）。
     */
    public function test_storage_url_full_key(): void
    {
        $logic = new TencentCos;
        $result = $logic->execute([
            'action' => 'storage.url',
            'payload' => ['object_key' => 'media/img_20300101_abc.jpg'],
            'config' => $this->config(['public_domain' => 'https://cdn.example.com']),
        ]);

        $this->assertTrue($result['success'], $result['message']);
        $this->assertSame('media/img_20300101_abc.jpg', $result['data']['object_key']);
        $this->assertSame('https://cdn.example.com/media/img_20300101_abc.jpg', $result['data']['url']);
        $this->assertSame(
            TencentCosSupport::buildPublicUrl('media/img_20300101_abc.jpg', 'testbucket-1250000000', 'ap-beijing', 'https://cdn.example.com'),
            $result['data']['url']
        );
    }

    /**
     * storage.url：裸文件名按 object_prefix 补前缀后再拼外链。
     */
    public function test_storage_url_bare_filename_gets_object_prefix(): void
    {
        $logic = new TencentCos;
        $result = $logic->execute([
            'action' => 'storage.url',
            'payload' => ['object_key' => 'a.jpg'],
            'config' => $this->config(['public_domain' => 'https://cdn.example.com']),
        ]);

        $this->assertTrue($result['success'], $result['message']);
        $this->assertSame('media/a.jpg', $result['data']['object_key']);
        $this->assertSame('https://cdn.example.com/media/a.jpg', $result['data']['url']);
    }

    /**
     * storage.url：public_domain 为空时回落桶默认域名（与 put 行为一致，不算失败）。
     */
    public function test_storage_url_without_public_domain_falls_back_to_bucket_domain(): void
    {
        $logic = new TencentCos;
        $result = $logic->execute([
            'action' => 'storage.url',
            'payload' => ['object_key' => 'media/a.jpg'],
            'config' => $this->config(),
        ]);

        $this->assertTrue($result['success'], $result['message']);
        $this->assertSame('https://testbucket-1250000000.cos.ap-beijing.myqcloud.com/media/a.jpg', $result['data']['url']);
    }

    /**
     * storage.url：非法对象键按 object_key_invalid 拒绝。
     */
    public function test_storage_url_rejects_invalid_object_key(): void
    {
        $logic = new TencentCos;
        $result = $logic->execute([
            'action' => 'storage.url',
            'payload' => ['object_key' => '/media/a.jpg'],
            'config' => $this->config(),
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('object_key_invalid', $result['data']['code']);
    }
}
