<?php

declare(strict_types=1);

namespace Tests\Unit\Plugins\Addons\Qiniu;

use Illuminate\Http\Client\Request as HttpClientRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

// 插件类由运行时 PluginFileLoader 按需 require，测试中需手动引入
require_once __DIR__.'/../lib/QiniuSupport.php';
require_once __DIR__.'/../lib/QiniuSigner.php';
require_once __DIR__.'/../lib/QiniuClient.php';
require_once __DIR__.'/../logic/Qiniu.php';

use Caiwu\Plugins\Addons\Qiniu\Lib\QiniuClient;
use Caiwu\Plugins\Addons\Qiniu\Lib\QiniuSigner;
use Caiwu\Plugins\Addons\Qiniu\Lib\QiniuSupport;
use Caiwu\Plugins\Addons\Qiniu\Logic\Qiniu;

/**
 * 七牛插件单元测试：纯函数 + Http::fake 传输桩，不发真实网络请求。
 */
final class QiniuClientTest extends TestCase
{
    /** 构造基础配置（占位凭据） */
    private function baseConfig(): array
    {
        return [
            'access_key' => 'test-ak',
            'secret_key' => 'test-sk',
            'bucket' => 'test-bucket',
            'region' => 'z0',
            'upload_host' => '',
            'api_host' => 'https://rs.qiniuapi.com',
            'public_domain' => 'https://cdn.example.com',
            'object_prefix' => 'media',
            'private_bucket' => false,
            'timeout' => 30,
        ];
    }

    /** 在 storage/app/tmp 下造一个本地临时文件 */
    private function makeLocalFile(string $content, string $ext): array
    {
        $dir = storage_path('app/tmp');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $name = 'qiniu_test_'.bin2hex(random_bytes(6)).'.'.$ext;
        $path = $dir.DIRECTORY_SEPARATOR.$name;
        file_put_contents($path, $content);

        return ['path' => $path, 'name' => $name];
    }

    // ---------- urlsafe_base64 ----------

    public function test_urlsafe_base64(): void
    {
        // 构造含 + / 的三字节（0xFB 0xFF 0xBF → base64 '+/+/'），验证替换与去填充
        $binary = "\xFB\xFF\xBF";
        $encoded = QiniuSigner::urlsafeBase64($binary);
        $this->assertSame('-_-_', $encoded);
        // 中英文与斜杠
        $this->assertSame('bWVkaWEvYS5qcGc', QiniuSigner::urlsafeBase64('media/a.jpg'));
    }

    // ---------- 上传凭证 ----------

    public function test_upload_token_structure(): void
    {
        $token = QiniuSigner::uploadToken('test-ak', 'test-sk', 'test-bucket', 'media/a.jpg', 1700000000);
        $parts = explode(':', $token);
        $this->assertCount(3, $parts);
        $this->assertSame('test-ak', $parts[0]);
        // 解码策略验证 scope/deadline/insertOnly
        $policy = json_decode((string) base64_decode(strtr($parts[2], ['-' => '+', '_' => '/'])), true);
        $this->assertSame('test-bucket:media/a.jpg', $policy['scope']);
        $this->assertSame(1700000000, $policy['deadline']);
        $this->assertSame(0, $policy['insertOnly']);
        // 签名部分可用相同密钥复算验证
        $expected = QiniuSigner::urlsafeBase64(hash_hmac('sha1', $parts[2], 'test-sk', true));
        $this->assertSame($expected, $parts[1]);
    }

    // ---------- 管理凭证（EncodedEntryURI + accessToken） ----------

    public function test_encoded_entry(): void
    {
        $encoded = QiniuSigner::encodedEntry('test-bucket', 'media/a.jpg');
        $this->assertSame('dGVzdC1idWNrZXQ6bWVkaWEvYS5qcGc', $encoded);
    }

    public function test_access_token_structure(): void
    {
        $token = QiniuSigner::accessToken('test-ak', 'test-sk', 'POST', '/delete/abc', '', 'rs.qiniuapi.com');
        $this->assertStringStartsWith('Qiniu test-ak:', $token);
        // 签名数据 = "POST /delete/abc\nHost: rs.qiniuapi.com\n"
        $expectedSign = QiniuSigner::urlsafeBase64(hash_hmac('sha1', "POST /delete/abc\nHost: rs.qiniuapi.com\n", 'test-sk', true));
        $this->assertSame('Qiniu test-ak:'.$expectedSign, $token);
    }

    // ---------- 私有下载凭证 ----------

    public function test_private_download_token(): void
    {
        $url = QiniuSigner::privateDownloadToken('test-ak', 'test-sk', 'https://cdn.example.com/media/a.jpg', 1700000000);
        $this->assertStringStartsWith('https://cdn.example.com/media/a.jpg?e=1700000000&token=test-ak:', $url);
    }

    // ---------- object_key 校验与解析 ----------

    public function test_object_key_valid_and_invalid(): void
    {
        $this->assertTrue(QiniuSupport::isValidObjectKey('media/img.jpg'));
        $this->assertTrue(QiniuSupport::isValidObjectKey('a.jpg'));
        $this->assertFalse(QiniuSupport::isValidObjectKey(''));
        $this->assertFalse(QiniuSupport::isValidObjectKey('/media/a.jpg'));
        $this->assertFalse(QiniuSupport::isValidObjectKey('media/../a.jpg'));
        $this->assertFalse(QiniuSupport::isValidObjectKey('media/名字.jpg'));
        $this->assertFalse(QiniuSupport::isValidObjectKey(str_repeat('a', 501)));
    }

    public function test_resolve_object_key_semantics(): void
    {
        // 完整键原样；裸文件名拼前缀（lead 裁决语义）
        $this->assertSame('media/img.jpg', QiniuSupport::resolveObjectKey('media/img.jpg', 'media'));
        $this->assertSame('media/a.jpg', QiniuSupport::resolveObjectKey('a.jpg', 'media'));
        $this->assertSame('a.jpg', QiniuSupport::resolveObjectKey('a.jpg', ''));
    }

    // ---------- 外链拼接 ----------

    public function test_build_url(): void
    {
        $this->assertSame(
            'https://cdn.example.com/media/img_20300101_abc.jpg',
            QiniuSupport::buildUrl('https://cdn.example.com', 'media/img_20300101_abc.jpg')
        );
        $this->assertSame(
            'https://cdn.example.com/media/a%20b.jpg',
            QiniuSupport::buildUrl('https://cdn.example.com', 'media/a b.jpg')
        );
        // 无协议自动补 https
        $this->assertSame(
            'https://cdn.example.com/media/a.jpg',
            QiniuSupport::buildUrl('cdn.example.com', 'media/a.jpg')
        );
        $this->assertSame(
            'http://cdn.example.com/media/a.jpg',
            QiniuSupport::buildUrl('http://cdn.example.com/', 'media/a.jpg')
        );
    }

    // ---------- 错误归一 ----------

    public function test_failure_message_prefix(): void
    {
        $result = QiniuSupport::failure('auth_failed', '凭证无效');
        $this->assertFalse($result['success']);
        $this->assertSame('七牛云存储：凭证无效', $result['message']);
        $this->assertSame('auth_failed', $result['data']['code']);
    }

    public function test_normalize_error_masks_secret_like_messages(): void
    {
        $e = new \RuntimeException('upstream rejected secret_key=sk-1234567890');
        $result = QiniuSupport::normalizeError($e);
        $this->assertFalse($result['success']);
        $this->assertStringNotContainsString('sk-1234567890', $result['message']);
    }

    // ---------- Http::fake 传输桩 ----------

    public function test_put_success_with_fake(): void
    {
        $local = $this->makeLocalFile('hello-qiniu', 'txt');
        Http::fake([
            'up-z0.qiniup.com' => Http::response(['key' => 'media/'.$local['name'], 'hash' => 'etag-hash-123'], 200),
        ]);
        $client = new QiniuClient($this->baseConfig());

        $result = $client->put($local['path'], 'media/'.$local['name']);

        $this->assertTrue($result['success'], $result['message']);
        $this->assertSame('media/'.$local['name'], $result['data']['object_key']);
        $this->assertSame('https://cdn.example.com/media/'.$local['name'], $result['data']['url']);
        $this->assertSame(11, $result['data']['size']);
        $this->assertSame('etag-hash-123', $result['data']['etag']);
        // 请求携带 key/token 字段
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'up-z0.qiniup.com');
        });
        @unlink($local['path']);
    }

    public function test_put_upstream_error_message(): void
    {
        $local = $this->makeLocalFile('x', 'txt');
        Http::fake([
            'up-z0.qiniup.com' => Http::response(['error' => 'bad token'], 401),
        ]);
        $client = new QiniuClient($this->baseConfig());

        $result = $client->put($local['path'], 'media/a.jpg');

        $this->assertFalse($result['success']);
        $this->assertSame('auth_failed', $result['data']['code']);
        @unlink($local['path']);
    }

    public function test_delete_missing_object_is_success(): void
    {
        Http::fake([
            'rs.qiniuapi.com/*' => Http::response(['code' => 612], 200),
        ]);
        $client = new QiniuClient($this->baseConfig());

        $result = $client->delete('media/a.jpg');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['data']['missing']);
        $this->assertFalse($result['data']['deleted']);
        // 管理凭证头存在
        Http::assertSent(function ($request) {
            $auth = $request->header('Authorization');
            $authValue = is_array($auth) ? (string) ($auth[0] ?? '') : (string) $auth;

            return str_starts_with($authValue, 'Qiniu test-ak:');
        });
    }

    public function test_delete_success(): void
    {
        Http::fake([
            'rs.qiniuapi.com/*' => Http::response(['code' => 200], 200),
        ]);
        $client = new QiniuClient($this->baseConfig());

        $result = $client->delete('media/a.jpg');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['data']['deleted']);
        $this->assertFalse($result['data']['missing']);
    }

    public function test_delete_auth_failed_on_401(): void
    {
        Http::fake([
            'rs.qiniuapi.com/*' => Http::response(['code' => 401], 200),
        ]);
        $client = new QiniuClient($this->baseConfig());

        $result = $client->delete('media/a.jpg');

        $this->assertFalse($result['success']);
        $this->assertSame('auth_failed', $result['data']['code']);
    }

    public function test_exists_with_fake(): void
    {
        Http::fake([
            'rs.qiniuapi.com/*' => Http::response(['hash' => 'h', 'fsize' => 100, 'mimeType' => 'image/jpeg'], 200),
        ]);
        $client = new QiniuClient($this->baseConfig());

        $result = $client->exists('media/a.jpg');

        $this->assertTrue($result['success']);
        $this->assertTrue($result['data']['exists']);
    }

    public function test_exists_not_found(): void
    {
        Http::fake([
            'rs.qiniuapi.com/*' => Http::response(['code' => 612], 200),
        ]);
        $client = new QiniuClient($this->baseConfig());

        $result = $client->exists('media/a.jpg');

        $this->assertTrue($result['success']);
        $this->assertFalse($result['data']['exists']);
    }

    public function test_get_public_bucket_direct_download(): void
    {
        Http::fake([
            'cdn.example.com/*' => Http::response('remote-body-123', 200),
        ]);
        $client = new QiniuClient($this->baseConfig());

        $result = $client->get('media/a.jpg', storage_path('app/tmp'));

        $this->assertTrue($result['success'], $result['message']);
        $this->assertFileExists($result['data']['temp_path']);
        $this->assertSame(15, $result['data']['size']);
        $this->assertStringStartsWith((string) realpath(storage_path('app/tmp')), (string) realpath($result['data']['temp_path']));
        // 公开空间 URL 不带 e/token
        Http::assertSent(function ($request) {
            return $request->url() === 'https://cdn.example.com/media/a.jpg';
        });
        @unlink($result['data']['temp_path']);
    }

    public function test_get_private_bucket_with_download_token(): void
    {
        $config = $this->baseConfig();
        $config['private_bucket'] = true;
        Http::fake([
            'cdn.example.com/*' => Http::response('private-body', 200),
        ]);
        $client = new QiniuClient($config);

        $result = $client->get('media/a.jpg', storage_path('app/tmp'));

        $this->assertTrue($result['success'], $result['message']);
        // 私有空间 URL 带 e/token 参数
        Http::assertSent(function ($request) {
            $url = $request->url();

            return str_contains($url, 'e=') && str_contains($url, 'token=test-ak:');
        });
        @unlink($result['data']['temp_path']);
    }

    public function test_get_upstream_reject(): void
    {
        Http::fake([
            'cdn.example.com/*' => Http::response('not found', 404),
        ]);
        $client = new QiniuClient($this->baseConfig());

        $result = $client->get('media/a.jpg', storage_path('app/tmp'));

        $this->assertFalse($result['success']);
        $this->assertSame('upstream_rejected', $result['data']['code']);
    }

    public function test_storage_test_action(): void
    {
        Http::fake([
            'rs.qiniuapi.com/*' => Http::response(['code' => 612], 200), // 探测键必然不存在 → 空间可达
        ]);
        $client = new QiniuClient($this->baseConfig());

        $result = $client->test();

        $this->assertTrue($result['success'], $result['message']);
        $this->assertTrue($result['data']['ok']);
        $this->assertSame('https://rs.qiniuapi.com', $result['data']['endpoint']);
        $this->assertSame('test-bucket', $result['data']['bucket']);
        $this->assertIsInt($result['data']['latency_ms']);
    }

    public function test_storage_test_bucket_missing_631(): void
    {
        Http::fake([
            'rs.qiniuapi.com/*' => Http::response(['code' => 631], 200),
        ]);
        $client = new QiniuClient($this->baseConfig());

        $result = $client->test();

        $this->assertFalse($result['success']);
        $this->assertSame('bucket_missing', $result['data']['code']);
    }

    // ---------- 上传域名推导 ----------

    public function test_upload_host_derivation(): void
    {
        $client = new QiniuClient($this->baseConfig());
        $this->assertSame('https://up-z0.qiniup.com', $client->uploadHost());

        $config = $this->baseConfig();
        $config['region'] = 'cn-east-2';
        $client = new QiniuClient($config);
        $this->assertSame('https://up-cn-east-2.qiniup.com', $client->uploadHost());

        $config['upload_host'] = 'https://up.example.com';
        $client = new QiniuClient($config);
        $this->assertSame('https://up.example.com', $client->uploadHost());
    }

    // ---------- 凭据完整性 ----------

    public function test_missing_credentials_auth_failed(): void
    {
        $config = $this->baseConfig();
        $config['access_key'] = '';
        $client = new QiniuClient($config);

        $result = $client->test();

        $this->assertFalse($result['success']);
        $this->assertSame('auth_failed', $result['data']['code']);
    }

    public function test_bucket_with_colon_rejected(): void
    {
        $config = $this->baseConfig();
        $config['bucket'] = 'bad:bucket';
        $client = new QiniuClient($config);

        $result = $client->delete('media/a.jpg');

        $this->assertFalse($result['success']);
        $this->assertSame('bucket_missing', $result['data']['code']);
    }

    // ---------- 裁决修复回归 ----------

    /** 管理接口请求不得带 Content-Type 与请求体（与 accessToken 签名数据吻合） */
    public function test_rs_post_sends_no_content_type_and_no_body(): void
    {
        Http::fake([
            'rs.qiniuapi.com/*' => Http::response(['code' => 200], 200),
        ]);
        $client = new QiniuClient($this->baseConfig());

        $result = $client->delete('media/a.jpg');

        $this->assertTrue($result['success'], $result['message']);
        Http::assertSent(function ($request) {
            $contentType = $request->header('Content-Type');
            $contentTypeValue = is_array($contentType) ? (string) ($contentType[0] ?? '') : (string) $contentType;

            return $request->method() === 'POST'
                && $contentTypeValue === ''
                && $request->body() === '';
        });
    }

    /** 私有空间下载时密钥缺失：前置归一 auth_failed，不发请求 */
    public function test_get_private_bucket_without_credentials_returns_auth_failed(): void
    {
        $config = $this->baseConfig();
        $config['private_bucket'] = true;
        $config['access_key'] = '';
        Http::fake();
        $client = new QiniuClient($config);

        $result = $client->get('media/a.jpg', storage_path('app/tmp'));

        $this->assertFalse($result['success']);
        $this->assertSame('auth_failed', $result['data']['code']);
        Http::assertNothingSent();
    }

    /** logic execute 前置拦截：非法 object_key 时直接拒绝，不发网络请求 */
    public function test_logic_execute_rejects_invalid_object_key_without_request(): void
    {
        Http::fake();
        $logic = new Qiniu;

        $result = $logic->execute([
            'action' => 'storage.delete',
            'payload' => ['object_key' => '/media/a.jpg'],
            'config' => $this->baseConfig(),
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('object_key_invalid', $result['data']['code']);
        Http::assertNothingSent();
    }

    /** logic execute 前置拦截：非法 local_path 时直接拒绝，不发网络请求 */
    public function test_logic_execute_rejects_invalid_local_path_without_request(): void
    {
        Http::fake();
        $logic = new Qiniu;

        $result = $logic->execute([
            'action' => 'storage.put',
            'payload' => ['object_key' => 'media/a.jpg', 'local_path' => 'E:/caiwu/composer.json'],
            'config' => $this->baseConfig(),
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('local_path_invalid', $result['data']['code']);
        Http::assertNothingSent();
    }

    /** logic execute 前置拦截：非法 target_dir 时直接拒绝，不发网络请求 */
    public function test_logic_execute_rejects_invalid_target_dir_without_request(): void
    {
        Http::fake();
        $logic = new Qiniu;

        $result = $logic->execute([
            'action' => 'storage.get',
            'payload' => ['object_key' => 'media/a.jpg', 'target_dir' => 'E:/caiwu'],
            'config' => $this->baseConfig(),
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('local_path_invalid', $result['data']['code']);
        Http::assertNothingSent();
    }

    // ---------- 修复 P-13：api_host 路径前缀参与签名 ----------

    /** api_host 带路径前缀时，管理凭证签名路径必须带同一前缀，否则七牛端按裸路径验签必 401 */
    public function test_rs_post_signs_path_prefix_when_api_host_has_one(): void
    {
        $requests = [];
        Http::fake(function (HttpClientRequest $request) use (&$requests) {
            $requests[] = $request;

            return Http::response(['code' => 612], 200);
        });
        $config = $this->baseConfig();
        $config['api_host'] = 'https://rs.example.com/qiniu';
        $client = new QiniuClient($config);

        $result = $client->delete('media/a.jpg');

        $this->assertTrue($result['success'], $result['message']); // 612 → missing=true
        $this->assertTrue($result['data']['missing']);
        $this->assertCount(1, $requests);

        // 请求 URL 已带前缀（$host 原样拼接）
        $url = (string) $requests[0]->url();
        $this->assertStringStartsWith('https://rs.example.com/qiniu/delete/', $url);
        // 签名数据里的 path 也必须带前缀：手工按同公式重算并比对 Authorization
        $expected = QiniuSigner::accessToken('test-ak', 'test-sk', 'POST', '/qiniu/delete/'.basename($url), '', 'rs.example.com');
        $authHeader = $requests[0]->header('Authorization');
        $this->assertSame($expected, $authHeader[0] ?? '');
    }

    // ---------- 修复 P-13：test() 写探针 ----------

    /** 写探针成功（stat 612 可达 + put 200 返回 key + delete 清理）→ writable=true */
    public function test_storage_test_writable_true_when_probe_upload_succeeds(): void
    {
        $probeKeyHolder = [];
        Http::fake(function (HttpClientRequest $request) use (&$probeKeyHolder) {
            $url = (string) $request->url();
            if (str_contains($url, 'rs.qiniuapi.com')) {
                return Http::response(['code' => 612], 200); // stat/delete：探测键不存在
            }
            // 上传域名：记录 key 并返回成功
            $probeKeyHolder['sent'] = true;

            return Http::response(['key' => 'probe-key', 'hash' => 'probe-hash'], 200);
        });
        $client = new QiniuClient($this->baseConfig());

        $result = $client->test();

        $this->assertTrue($result['success'], $result['message']);
        $this->assertTrue($result['data']['ok']);
        $this->assertTrue($result['data']['writable']);
        $this->assertTrue($probeKeyHolder['sent'] ?? false, '写探针应发出上传请求');
    }

    /** 写探针失败（上传域名拒绝 401）→ ok=true 链路可达，但 writable=false 不硬编码 */
    public function test_storage_test_writable_false_when_probe_upload_rejected(): void
    {
        Http::fake(function (HttpClientRequest $request) {
            $url = (string) $request->url();
            if (str_contains($url, 'rs.qiniuapi.com')) {
                return Http::response(['code' => 612], 200);
            }

            return Http::response(['error' => 'bad token'], 401); // 上传被拒
        });
        $client = new QiniuClient($this->baseConfig());

        $result = $client->test();

        $this->assertTrue($result['success'], $result['message']);
        $this->assertTrue($result['data']['ok'], '探针失败不影响链路可达结论');
        $this->assertFalse($result['data']['writable']);
    }

    /** 既有用例回归：stat 探测失败语义不变（桶缺失 631 仍 bucket_missing） */
    public function test_storage_test_probe_paths_do_not_break_stat_semantics(): void
    {
        Http::fake([
            'rs.qiniuapi.com/*' => Http::response(['code' => 631], 200),
        ]);
        $client = new QiniuClient($this->baseConfig());

        $result = $client->test();

        $this->assertFalse($result['success']);
        $this->assertSame('bucket_missing', $result['data']['code']);
    }

    // ---------- 修复 P-13：actionPut 一致性比对 ----------

    /** payload content_length 与本地实测不一致 → local_path_invalid 且不发请求 */
    public function test_logic_execute_put_rejects_content_length_mismatch_without_request(): void
    {
        Http::fake();
        $local = $this->makeLocalFile('hello-qiniu', 'jpg'); // 实测 11 字节
        $logic = new Qiniu;

        $result = $logic->execute([
            'action' => 'storage.put',
            'payload' => [
                'object_key' => 'media/a.jpg',
                'local_path' => $local['path'],
                'content_length' => 999, // 与实测不一致
            ],
            'config' => $this->baseConfig(),
        ]);
        @unlink($local['path']);

        $this->assertFalse($result['success']);
        $this->assertSame('local_path_invalid', $result['data']['code']);
        $this->assertStringContainsString('实测 11 字节', $result['message']);
        Http::assertNothingSent();
    }

    /** actionPut 透传 mime_type：上传请求的文件 part 应带 Content-Type */
    public function test_logic_execute_put_passes_mime_type_to_upload(): void
    {
        $bodies = [];
        Http::fake(function (HttpClientRequest $request) use (&$bodies) {
            $bodies[] = (string) $request->body();

            return Http::response(['key' => 'media/a.jpg', 'hash' => 'h'], 200);
        });
        $local = $this->makeLocalFile('hello-qiniu', 'jpg');
        $logic = new Qiniu;

        $result = $logic->execute([
            'action' => 'storage.put',
            'payload' => [
                'object_key' => 'media/a.jpg',
                'local_path' => $local['path'],
                'mime_type' => 'image/jpeg',
                'content_length' => 11,
            ],
            'config' => $this->baseConfig(),
        ]);
        @unlink($local['path']);

        $this->assertTrue($result['success'], $result['message']);
        $this->assertSame('media/a.jpg', $result['data']['object_key']);
        // multipart body 里应包含文件 part 的 Content-Type 头
        $found = false;
        foreach ($bodies as $body) {
            if (str_contains($body, 'Content-Type: image/jpeg')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, '上传 multipart 应含文件 part Content-Type: image/jpeg');
    }

    // ---------- storage.url ----------

    /** storage.url：完整对象键按 public_domain 现算外链（与 put 同一 buildUrl，全等） */
    public function test_storage_url_full_key(): void
    {
        $logic = new Qiniu;

        $result = $logic->execute([
            'action' => 'storage.url',
            'payload' => ['object_key' => 'media/img_20300101_abc.jpg'],
            'config' => $this->baseConfig(),
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('media/img_20300101_abc.jpg', $result['data']['object_key']);
        $this->assertSame('https://cdn.example.com/media/img_20300101_abc.jpg', $result['data']['url']);
        $this->assertSame(
            QiniuSupport::buildUrl('https://cdn.example.com', 'media/img_20300101_abc.jpg'),
            $result['data']['url']
        );
    }

    /** storage.url：裸文件名按 object_prefix 补前缀 */
    public function test_storage_url_bare_filename_gets_object_prefix(): void
    {
        $logic = new Qiniu;

        $result = $logic->execute([
            'action' => 'storage.url',
            'payload' => ['object_key' => 'a.jpg'],
            'config' => $this->baseConfig(),
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('media/a.jpg', $result['data']['object_key']);
        $this->assertSame('https://cdn.example.com/media/a.jpg', $result['data']['url']);
    }

    /** storage.url：public_domain 为空返回 public_domain_missing（七牛无桶默认域名回落） */
    public function test_storage_url_without_public_domain_returns_code(): void
    {
        $config = $this->baseConfig();
        $config['public_domain'] = '';

        $logic = new Qiniu;

        $result = $logic->execute([
            'action' => 'storage.url',
            'payload' => ['object_key' => 'media/a.jpg'],
            'config' => $config,
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('public_domain_missing', $result['data']['code']);
        $this->assertStringContainsString('外链域名', $result['message']);
    }

    /** storage.url：非法对象键按 object_key_invalid 拒绝 */
    public function test_storage_url_rejects_invalid_object_key(): void
    {
        $logic = new Qiniu;

        $result = $logic->execute([
            'action' => 'storage.url',
            'payload' => ['object_key' => 'media/../a.jpg'],
            'config' => $this->baseConfig(),
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('object_key_invalid', $result['data']['code']);
    }
}
