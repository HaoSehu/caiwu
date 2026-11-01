<?php

declare(strict_types=1);

namespace Tests\Unit;

use Caiwu\Plugins\Addons\AliyunOss\AliyunOss;
use Caiwu\Plugins\Addons\AliyunOss\Lib\AliyunOssClient;
use Caiwu\Plugins\Addons\AliyunOss\Lib\AliyunOssSigner;
use Caiwu\Plugins\Addons\AliyunOss\Lib\AliyunOssSupport;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// 插件类由运行时的 PluginFileLoader 按 require 加载，测试中需手动引入
require_once __DIR__.'/../lib/AliyunOssClient.php';
require_once __DIR__.'/../lib/AliyunOssSigner.php';
require_once __DIR__.'/../lib/AliyunOssSupport.php';
require_once __DIR__.'/../logic/AliyunOss.php';

/**
 * 阿里云 OSS 插件契约回归（不发真实网络请求）。
 *
 * 覆盖四块：
 * 1. V1 签名纯函数：stringToSign 五段拼接、CanonicalizedOSSHeaders 字典序、空 Content-MD5 以空串参与；
 * 2. Support 校验：object_key 规则、local_path/target_dir 越界拦截、外链拼接公式（含 scheme）；
 * 3. 传输桩全链路：请求形状（方法/URL/参与签名的头）与返回结构归一；
 * 4. 安全：密钥不进返回结构，x-oss-object-acl 参与 CanonicalizedOSSHeaders。
 */
class AliyunOssClientTest extends TestCase
{
    /** 测试用占位密钥（非真实凭据） */
    private const ACCESS_KEY_ID = 'test-ak';

    private const ACCESS_KEY_SECRET = 'test-sk';

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function config(array $overrides = []): array
    {
        return array_merge([
            'access_key_id' => self::ACCESS_KEY_ID,
            'access_key_secret' => self::ACCESS_KEY_SECRET,
            'bucket' => 'testbucket',
            'endpoint' => 'oss-cn-beijing.aliyuncs.com',
            'scheme' => 'https',
            'acl' => 'none',
            'public_domain' => '',
            'object_prefix' => 'media',
            'timeout' => 30,
        ], $overrides);
    }

    /**
     * V1 签名：固定时间戳下 stringToSign 各段拼接正确（含字典序 x-oss- 头与空 Content-MD5）。
     */
    public function test_authorization_matches_v1_string_to_sign(): void
    {
        // 手工推演：PUT /testbucket/media/img.jpg，Content-Type image/jpeg，Date 固定，
        // x-oss-object-acl: public-read 参与 CanonicalizedOSSHeaders（字典序仅一个头）
        $date = 'Sun, 01 Nov 2026 12:00:00 GMT';
        $expectedStringToSign = "PUT\n\nimage/jpeg\n{$date}\nx-oss-object-acl:public-read\n/testbucket/media/img.jpg";
        $expectedSignature = base64_encode(hash_hmac('sha1', $expectedStringToSign, self::ACCESS_KEY_SECRET, true));

        $authorization = AliyunOssSigner::authorization(
            self::ACCESS_KEY_ID,
            self::ACCESS_KEY_SECRET,
            'PUT',
            '/testbucket/media/img.jpg',
            '',
            'image/jpeg',
            $date,
            ['x-oss-object-acl' => 'public-read'],
        );

        $this->assertSame('OSS '.self::ACCESS_KEY_ID.':'.$expectedSignature, $authorization);

        // 无 x-oss- 头时 CanonicalizedOSSHeaders 为空串（Date 后直接接资源串）
        $bare = AliyunOssSigner::authorization(
            self::ACCESS_KEY_ID,
            self::ACCESS_KEY_SECRET,
            'GET',
            '/testbucket/media/img.jpg',
            '',
            '',
            $date,
            [],
        );
        $bareExpected = base64_encode(
            hash_hmac('sha1', "GET\n\n\n{$date}\n/testbucket/media/img.jpg", self::ACCESS_KEY_SECRET, true),
        );
        $this->assertSame('OSS '.self::ACCESS_KEY_ID.':'.$bareExpected, $bare);

        // 多个 x-oss- 头按名称字典序排列
        $sorted = AliyunOssSigner::authorization(
            self::ACCESS_KEY_ID,
            self::ACCESS_KEY_SECRET,
            'PUT',
            '/testbucket/media/img.jpg',
            '',
            'image/jpeg',
            $date,
            ['x-oss-meta-b' => '2', 'x-oss-object-acl' => 'public-read', 'X-OSS-Meta-A' => '1'],
        );
        $sortedExpected = base64_encode(hash_hmac('sha1',
            "PUT\n\nimage/jpeg\n{$date}\nx-oss-meta-a:1\nx-oss-meta-b:2\nx-oss-object-acl:public-read\n/testbucket/media/img.jpg",
            self::ACCESS_KEY_SECRET,
            true,
        ));
        $this->assertSame('OSS '.self::ACCESS_KEY_ID.':'.$sortedExpected, $sorted);
    }

    /**
     * gmtDate 输出 GMT 格式且可注入时间戳。
     */
    public function test_gmt_date_format(): void
    {
        // 1793563200 = 2026-11-01 20:00:00 UTC（GMT 无时区偏移）
        $this->assertSame('Sun, 01 Nov 2026 20:00:00 GMT', AliyunOssSigner::gmtDate(1793563200));
    }

    /**
     * object_key 校验规则：非空、≤500、无前导 /、段内无 ./..、字符集受限。
     */
    public function test_normalize_object_key_rules(): void
    {
        $this->assertSame('media/img.jpg', AliyunOssSupport::normalizeObjectKey(' media/img.jpg '));
        $this->assertNull(AliyunOssSupport::normalizeObjectKey(''));
        $this->assertNull(AliyunOssSupport::normalizeObjectKey('/media/img.jpg'));
        $this->assertNull(AliyunOssSupport::normalizeObjectKey('media//img.jpg'));
        $this->assertNull(AliyunOssSupport::normalizeObjectKey('media/../img.jpg'));
        $this->assertNull(AliyunOssSupport::normalizeObjectKey('media/img name.jpg'));
        $this->assertNull(AliyunOssSupport::normalizeObjectKey(str_repeat('a', 501)));
    }

    /**
     * local_path 越界拦截：必须在 public/media 或 storage/app/tmp 之内。
     */
    public function test_resolve_local_file_rejects_paths_outside_allowed_roots(): void
    {
        $inside = storage_path('app/tmp').DIRECTORY_SEPARATOR.'oss_allowed_'.bin2hex(random_bytes(4)).'.txt';
        file_put_contents($inside, 'x');
        $this->assertSame((string) realpath($inside), (string) AliyunOssSupport::resolveLocalFile($inside));
        unlink($inside);

        // 系统临时目录（storage/app/tmp 之外）必须拒绝
        $outside = sys_get_temp_dir().'/oss_denied_'.bin2hex(random_bytes(4)).'.txt';
        file_put_contents($outside, 'x');
        $this->assertNull(AliyunOssSupport::resolveLocalFile($outside));
        unlink($outside);

        $this->assertNull(AliyunOssSupport::resolveLocalFile(__FILE__));
        $this->assertNull(AliyunOssSupport::resolveLocalFile(null));
    }

    /**
     * target_dir 校验：必须位于 storage/app/tmp 之内。
     */
    public function test_resolve_target_dir_rejects_paths_outside_tmp(): void
    {
        $this->assertSame(
            (string) realpath(storage_path('app/tmp')),
            (string) AliyunOssSupport::resolveTargetDir(storage_path('app/tmp')),
        );
        $this->assertNull(AliyunOssSupport::resolveTargetDir(sys_get_temp_dir()));
        $this->assertNull(AliyunOssSupport::resolveTargetDir(public_path('media')));
    }

    /**
     * 外链拼接：public_domain 优先，否则 {scheme}://{bucket}.{endpoint}；路径段编码。
     */
    public function test_build_public_url_formula(): void
    {
        $this->assertSame(
            'https://testbucket.oss-cn-beijing.aliyuncs.com/media/img.jpg',
            AliyunOssSupport::buildPublicUrl('media/img.jpg', 'testbucket', 'oss-cn-beijing.aliyuncs.com'),
        );
        $this->assertSame(
            'http://testbucket.oss-cn-beijing.aliyuncs.com/media/img.jpg',
            AliyunOssSupport::buildPublicUrl('media/img.jpg', 'testbucket', 'oss-cn-beijing.aliyuncs.com', 'http'),
        );
        $this->assertSame(
            'https://cdn.example.com/media/img.jpg',
            AliyunOssSupport::buildPublicUrl('media/img.jpg', 'testbucket', 'oss-cn-beijing.aliyuncs.com', 'https', 'https://cdn.example.com/'),
        );
        // 路径段编码：空格 → %20，斜杠保留
        $this->assertSame(
            'media/a%20b.jpg',
            AliyunOssSupport::encodeKeyPath('media/a b.jpg'),
        );
    }

    /**
     * put 请求形状（传输桩）：PUT /{key}，public-read 时 x-oss-object-acl 头参与签名；
     * Authorization 为 "OSS {ak}:{signature}" 形态；密钥不进返回结构。
     */
    public function test_put_request_shape_and_result(): void
    {
        $localPath = storage_path('app/tmp').'/oss_put_'.bin2hex(random_bytes(4)).'.jpg';
        file_put_contents($localPath, 'jpeg-bytes');

        $captured = [];
        $transport = function (string $method, string $url, array $options) use (&$captured): array {
            $captured = ['method' => $method, 'url' => $url, 'headers' => $options['headers']];

            return ['status' => 200, 'headers' => ['etag' => '"d41d8cd98f00b204e9800998ecf8427e"'], 'body' => ''];
        };

        $config = $this->config(['acl' => 'public-read']);
        $client = new AliyunOssClient($config, $transport);
        $result = $client->putObject('media/img.jpg', $localPath, 'image/jpeg');

        $this->assertSame('PUT', $captured['method']);
        $this->assertSame('https://testbucket.oss-cn-beijing.aliyuncs.com/media/img.jpg', $captured['url']);
        $this->assertSame('public-read', $captured['headers']['x-oss-object-acl']);
        $this->assertMatchesRegularExpression('/^OSS test-ak:[A-Za-z0-9+\/=]+$/', $captured['headers']['Authorization']);
        // Date 头为 GMT 格式且与签名用值一致（存在即证明参与）
        $this->assertMatchesRegularExpression('/^[A-Z][a-z]{2}, \d{2} [A-Z][a-z]{2} \d{4} \d{2}:\d{2}:\d{2} GMT$/', $captured['headers']['Date']);
        // 密钥不进请求头之外的返回结构
        $this->assertStringNotContainsString('test-sk', json_encode($result));
        $this->assertSame(200, $result['status']);

        unlink($localPath);
    }

    /**
     * scheme=http 时请求 URL 走 http（传输桩断言）。
     */
    public function test_put_uses_http_scheme_when_configured(): void
    {
        $localPath = storage_path('app/tmp').'/oss_put2_'.bin2hex(random_bytes(4)).'.jpg';
        file_put_contents($localPath, 'jpeg-bytes');

        $captured = [];
        $transport = function (string $method, string $url, array $options) use (&$captured): array {
            $captured['url'] = $url;

            return ['status' => 200, 'headers' => [], 'body' => ''];
        };

        $client = new AliyunOssClient($this->config(['scheme' => 'http']), $transport);
        $client->putObject('media/img.jpg', $localPath, 'image/jpeg');

        $this->assertSame('http://testbucket.oss-cn-beijing.aliyuncs.com/media/img.jpg', $captured['url']);

        unlink($localPath);
    }

    /**
     * get 下载落盘：sink 指向 storage/app/tmp 之内的随机名临时文件。
     */
    public function test_get_downloads_into_target_dir(): void
    {
        $targetDir = (string) AliyunOssSupport::resolveTargetDir(storage_path('app/tmp'));
        $captured = [];
        $transport = function (string $method, string $url, array $options) use (&$captured): array {
            $captured = ['method' => $method, 'url' => $url, 'sink' => $options['sink'] ?? null];
            if (! empty($options['sink'])) {
                file_put_contents($options['sink'], 'downloaded-bytes');
            }

            return ['status' => 200, 'headers' => [], 'body' => ''];
        };

        $client = new AliyunOssClient($this->config(), $transport);
        $result = $client->getObject('media/img.jpg', $targetDir);

        $this->assertSame('GET', $captured['method']);
        $this->assertSame('https://testbucket.oss-cn-beijing.aliyuncs.com/media/img.jpg', $captured['url']);
        $this->assertMatchesRegularExpression('/oss_[0-9a-f]{24}\\.tmp$/', str_replace('\\', '/', (string) $captured['sink']));
        $this->assertSame(200, $result['status']);
        $this->assertFileExists($result['temp_path']);
        $this->assertSame(16, strlen((string) file_get_contents($result['temp_path'])));

        unlink($result['temp_path']);
    }

    /**
     * logic 层前置校验：非法 object_key / 非法 local_path 在发请求之前直接失败。
     */
    public function test_logic_rejects_invalid_inputs_before_any_request(): void
    {
        Http::fake();

        $logic = new AliyunOss;

        $badKey = $logic->execute([
            'action' => 'storage.delete',
            'payload' => ['object_key' => '/media/img.jpg'],
            'config' => $this->config(),
        ]);
        $this->assertFalse($badKey['success']);
        $this->assertSame('object_key_invalid', $badKey['data']['code']);
        $this->assertStringStartsWith('阿里云OSS存储：', $badKey['message']);

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
     * logic 层 delete 归一：404 按 missing=true 成功；delete 走 Http::fake 验证 URL 与 Authorization 头。
     */
    public function test_logic_delete_missing_via_fake_http(): void
    {
        Http::fake([
            'testbucket.oss-cn-beijing.aliyuncs.com/*' => Http::response('', 404),
        ]);

        $logic = new AliyunOss;
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
                && str_starts_with((string) ($authorization[0] ?? ''), 'OSS test-ak:')
                && $request->method() === 'DELETE'
                && $request->url() === 'https://testbucket.oss-cn-beijing.aliyuncs.com/media/missing.jpg';
        });
    }

    /**
     * logic 层 put 归一：成功返回 object_key/url/size/etag，外链含 scheme。
     */
    public function test_logic_put_success_returns_full_contract(): void
    {
        $localPath = storage_path('app/tmp').'/oss_put3_'.bin2hex(random_bytes(4)).'.jpg';
        file_put_contents($localPath, 'jpeg-bytes');

        Http::fake([
            'testbucket.oss-cn-beijing.aliyuncs.com/*' => Http::response('', 200, ['ETag' => '"abc123"']),
        ]);

        $logic = new AliyunOss;
        $result = $logic->execute([
            'action' => 'storage.put',
            'payload' => [
                'local_path' => $localPath,
                'object_key' => 'media/img_20300101_abc.jpg',
                'mime_type' => 'image/jpeg',
                'content_length' => 10,
            ],
            'config' => $this->config(),
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('media/img_20300101_abc.jpg', $result['data']['object_key']);
        $this->assertSame(
            'https://testbucket.oss-cn-beijing.aliyuncs.com/media/img_20300101_abc.jpg',
            $result['data']['url'],
        );
        $this->assertSame(10, $result['data']['size']);
        $this->assertSame('abc123', $result['data']['etag']);

        unlink($localPath);
    }

    /**
     * logic 层 get 404 归一为 missing（对象不存在，与 delete 语境一致）。
     */
    public function test_logic_get_404_normalizes_to_missing(): void
    {
        Http::fake([
            'testbucket.oss-cn-beijing.aliyuncs.com/*' => Http::response('', 404),
        ]);

        $logic = new AliyunOss;
        $result = $logic->execute([
            'action' => 'storage.get',
            'payload' => ['object_key' => 'media/missing.jpg', 'target_dir' => storage_path('app/tmp')],
            'config' => $this->config(),
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('missing', $result['data']['code']);
        $this->assertSame('阿里云OSS存储：对象不存在：media/missing.jpg', $result['message']);
    }

    /**
     * logic 层 storage.test：403 视为链路通（writable=false）。
     */
    public function test_logic_test_403_reachable_but_readonly(): void
    {
        Http::fake([
            'testbucket.oss-cn-beijing.aliyuncs.com/*' => Http::response('', 403),
        ]);

        $logic = new AliyunOss;
        $reachable = $logic->execute([
            'action' => 'storage.test',
            'payload' => [],
            'config' => $this->config(),
        ]);

        $this->assertTrue($reachable['success']);
        $this->assertTrue($reachable['data']['ok']);
        $this->assertFalse($reachable['data']['writable']);
        $this->assertSame('testbucket', $reachable['data']['bucket']);
        $this->assertSame('oss-cn-beijing.aliyuncs.com', $reachable['data']['endpoint']);
    }

    /**
     * logic 层 storage.test：401 归一 auth_failed（独立用例，避免 fake stub 叠加）。
     */
    public function test_logic_test_401_normalizes_to_auth_failed(): void
    {
        Http::fake([
            'testbucket.oss-cn-beijing.aliyuncs.com/*' => Http::response('', 401),
        ]);

        $logic = new AliyunOss;
        $denied = $logic->execute([
            'action' => 'storage.test',
            'payload' => [],
            'config' => $this->config(),
        ]);

        $this->assertFalse($denied['success']);
        $this->assertSame('auth_failed', $denied['data']['code']);
        $this->assertStringStartsWith('阿里云OSS存储：', $denied['message']);
    }

    /**
     * logic 层 put 非 200/204 归一 upstream_rejected（HTTP 500）。
     */
    public function test_logic_put_500_normalizes_to_upstream_rejected(): void
    {
        $localPath = storage_path('app/tmp').'/oss_put500_'.bin2hex(random_bytes(4)).'.jpg';
        file_put_contents($localPath, 'jpeg-bytes');

        Http::fake([
            'testbucket.oss-cn-beijing.aliyuncs.com/*' => Http::response('internal error', 500),
        ]);

        $logic = new AliyunOss;
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
        $this->assertStringStartsWith('阿里云OSS存储：', $result['message']);

        unlink($localPath);
    }

    /**
     * logic 层 put：本地实测大小与声明 content_length 不一致时拒绝，且不发出任何请求。
     */
    public function test_logic_put_rejects_content_length_mismatch(): void
    {
        $localPath = storage_path('app/tmp').'/oss_mismatch_'.bin2hex(random_bytes(4)).'.jpg';
        file_put_contents($localPath, 'jpeg-bytes'); // 10 字节

        Http::fake();

        $logic = new AliyunOss;
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
            throw new \LogicException('boomed with access_key_secret leak inside');
        });

        $logic = new AliyunOss;
        $result = $logic->execute([
            'action' => 'storage.exists',
            'payload' => ['object_key' => 'media/img.jpg'],
            'config' => $this->config(),
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('upstream_rejected', $result['data']['code']);
        // 密钥素材不进消息
        $this->assertStringNotContainsString('leak inside', $result['message']);
        $this->assertStringNotContainsString('access_key_secret', $result['message']);
        $this->assertStringStartsWith('阿里云OSS存储：', $result['message']);
    }

    /**
     * storage.url：完整对象键按 public_domain 现算外链（与 put 同一 buildPublicUrl，全等）。
     */
    public function test_storage_url_full_key(): void
    {
        $logic = new AliyunOss;
        $result = $logic->execute([
            'action' => 'storage.url',
            'payload' => ['object_key' => 'media/img_20300101_abc.jpg'],
            'config' => $this->config(['public_domain' => 'https://cdn.example.com']),
        ]);

        $this->assertTrue($result['success'], $result['message']);
        $this->assertSame('media/img_20300101_abc.jpg', $result['data']['object_key']);
        $this->assertSame('https://cdn.example.com/media/img_20300101_abc.jpg', $result['data']['url']);
        $this->assertSame(
            AliyunOssSupport::buildPublicUrl('media/img_20300101_abc.jpg', 'testbucket', 'oss-cn-beijing.aliyuncs.com', 'https', 'https://cdn.example.com'),
            $result['data']['url']
        );
    }

    /**
     * storage.url：裸文件名按 object_prefix 补前缀后再拼外链。
     */
    public function test_storage_url_bare_filename_gets_object_prefix(): void
    {
        $logic = new AliyunOss;
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
        $logic = new AliyunOss;
        $result = $logic->execute([
            'action' => 'storage.url',
            'payload' => ['object_key' => 'media/a.jpg'],
            'config' => $this->config(),
        ]);

        $this->assertTrue($result['success'], $result['message']);
        $this->assertSame('https://testbucket.oss-cn-beijing.aliyuncs.com/media/a.jpg', $result['data']['url']);
    }

    /**
     * storage.url：非法对象键按 object_key_invalid 拒绝。
     */
    public function test_storage_url_rejects_invalid_object_key(): void
    {
        $logic = new AliyunOss;
        $result = $logic->execute([
            'action' => 'storage.url',
            'payload' => ['object_key' => '/media/a.jpg'],
            'config' => $this->config(),
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('object_key_invalid', $result['data']['code']);
    }
}
