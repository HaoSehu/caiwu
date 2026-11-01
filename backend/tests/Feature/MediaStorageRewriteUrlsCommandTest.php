<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\MediaFile;
use App\Models\Setting;
use App\Services\Content\Storage\RemoteMediaStorage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * 远程外链订正命令回归：
 * 1. 默认 dry-run 只报告，不改库；
 * 2. --execute 回写 media_files.path/url（两者同值），并同步替换 settings 里的旧外链；
 * 3. 现算结果与存量一致的行标 unchanged，不产生写入；
 * 4. 插件现算失败的行标 failed、命令返回失败码，且不写库。
 *
 * 插件侧由 RemoteMediaStorage mock 替代：本测试只验收宿主侧编排与写库，
 * 四插件的 storage.url 动作另由 Plugins 套件的插件单测覆盖。
 */
class MediaStorageRewriteUrlsCommandTest extends TestCase
{
    use DatabaseTransactions;

    private const OLD_URL = 'http://154.219.97.172/media/img_155342_g76d3jXT.jpg';

    private const NEW_URL = 'https://media.example.com/media/img_155342_g76d3jXT.jpg';

    #[Test]
    public function dry_run_reports_pending_change_without_writing(): void
    {
        $mediaFile = $this->makeRemoteRow(self::OLD_URL);
        Setting::setValue('site-settings', 'media_rewrite_probe', self::OLD_URL);

        $this->mockStorageUrl(self::NEW_URL);

        $this->artisan('media:storage-rewrite-urls')
            ->expectsOutputToContain('写入 0 条，待写入 1 条')
            ->assertExitCode(0);

        $fresh = $mediaFile->fresh();
        $this->assertSame(self::OLD_URL, (string) $fresh->path);
        $this->assertSame(self::OLD_URL, (string) $fresh->url);
        $this->assertSame(self::OLD_URL, Setting::getValue('site-settings', 'media_rewrite_probe', ''));
    }

    #[Test]
    public function execute_rewrites_remote_row_and_setting_reference(): void
    {
        $mediaFile = $this->makeRemoteRow(self::OLD_URL);
        Setting::setValue('site-settings', 'media_rewrite_probe', self::OLD_URL);

        $this->mockStorageUrl(self::NEW_URL);

        $this->artisan('media:storage-rewrite-urls', ['--execute' => true])
            ->expectsOutputToContain('写入 1 条')
            ->assertExitCode(0);

        $fresh = $mediaFile->fresh();
        $this->assertSame(self::NEW_URL, (string) $fresh->path);
        $this->assertSame(self::NEW_URL, (string) $fresh->url);
        $this->assertSame(self::NEW_URL, Setting::getValue('site-settings', 'media_rewrite_probe', ''));
    }

    #[Test]
    public function execute_is_idempotent_and_marks_unchanged(): void
    {
        $this->makeRemoteRow(self::NEW_URL);

        $this->mockStorageUrl(self::NEW_URL);

        $this->artisan('media:storage-rewrite-urls', ['--json' => true, '--execute' => true])
            ->expectsOutputToContain('"unchanged": 1')
            ->assertExitCode(0);
    }

    #[Test]
    public function failure_to_compute_url_marks_row_failed_and_returns_failure_code(): void
    {
        $mediaFile = $this->makeRemoteRow(self::OLD_URL);

        $this->mockStorageUrl(null, '请在插件配置中填写可公开访问的外链域名（需含 http:// 或 https://）');

        $this->artisan('media:storage-rewrite-urls', ['--json' => true, '--execute' => true])
            ->expectsOutputToContain('"failed": 1')
            ->assertExitCode(1);

        $fresh = $mediaFile->fresh();
        $this->assertSame(self::OLD_URL, (string) $fresh->path);
        $this->assertSame(self::OLD_URL, (string) $fresh->url);
    }

    #[Test]
    public function dry_run_and_execute_conflict_returns_failure(): void
    {
        $this->artisan('media:storage-rewrite-urls', ['--dry-run' => true, '--execute' => true])
            ->expectsOutputToContain('--dry-run 与 --execute 不能同时使用。')
            ->assertExitCode(1);
    }

    private function makeRemoteRow(string $url): MediaFile
    {
        return MediaFile::query()->create([
            'filename' => 'img_155342_g76d3jXT.jpg',
            'path' => $url,
            'url' => $url,
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'group' => 'content',
            'uploaded_by' => 0,
            'disk' => 'ftp',
            'object_key' => 'media/img_155342_g76d3jXT.jpg',
        ]);
    }

    private function mockStorageUrl(?string $url, string $failureMessage = ''): void
    {
        $storage = $this->createMock(RemoteMediaStorage::class);

        $expectation = $storage->method('url');

        if ($url === null) {
            $expectation->willThrowException(new RuntimeException($failureMessage));
        } else {
            $expectation->willReturn($url);
        }

        $this->app->instance(RemoteMediaStorage::class, $storage);
    }
}
