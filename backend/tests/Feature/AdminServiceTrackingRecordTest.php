<?php

declare(strict_types=1);

namespace TestsFeature;

use App\Models\ActivityLog;
use App\Models\AdminUser;
use App\Models\MediaFile;
use App\Models\Product;
use App\Models\Role;
use App\Models\Service;
use App\Models\ServiceTrackingRecord;
use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * 管理端服务控制台「历史跟踪」备注（/api/v2/admin/users/{user}/services/{service}/tracking-*）：
 * 1. 列表按 tracked_at 倒序返回，且支持关键词与日期区间过滤；
 * 2. 新增记录写库，操作人自动取当前登录管理员，并同步写服务操作日志；
 * 3. 删除记录写操作日志并从库中移除；
 * 4. 越权（服务不属于该用户 / 记录不属于该服务）一律 404，不泄露存在性；
 * 5. 截图上传复用媒体库，返回可访问 URL；非法类型被校验拦截。
 * 使用 DatabaseTransactions，测试结束回滚；上传用例自行清理磁盘文件。
 */
class AdminServiceTrackingRecordTest extends TestCase
{
    use DatabaseTransactions;

    public function test_list_returns_records_ordered_by_tracked_at_desc(): void
    {
        $admin = $this->makeAdmin([AdminPermissions::USER_DETAIL]);
        $user = $this->makeUser('tracking-list');
        $service = $this->makeService($user);
        Sanctum::actingAs($admin);

        $this->makeRecord($service, '早先的跟进记录', '2026-10-01 09:00:00');
        $this->makeRecord($service, '最新的跟进记录', '2026-10-20 18:30:00');

        $this->getJson("/api/v2/admin/users/{$user->id}/services/{$service->id}/tracking-records")
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.list.0.content', '最新的跟进记录')
            ->assertJsonPath('data.list.0.tracked_at', '2026-10-20 18:30:00')
            ->assertJsonPath('data.list.1.content', '早先的跟进记录');
    }

    public function test_list_filters_by_keyword_and_date_range(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::USER_DETAIL]));

        $user = $this->makeUser('tracking-filter');
        $service = $this->makeService($user);

        $this->makeRecord($service, '已对接上游续费', '2026-09-05 10:00:00', '张三');
        $this->makeRecord($service, '等待客户确认配置', '2026-10-08 10:00:00', '李四');
        $this->makeRecord($service, '已对接上游扩容', '2026-10-22 10:00:00', '王五');

        $this->getJson("/api/v2/admin/users/{$user->id}/services/{$service->id}/tracking-records?keyword=上游&start_date=2026-10-01&end_date=2026-10-31")
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.list.0.content', '已对接上游扩容');

        $this->getJson("/api/v2/admin/users/{$user->id}/services/{$service->id}/tracking-records?keyword=李四")
            ->assertOk()
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.list.0.content', '等待客户确认配置');
    }

    public function test_store_creates_record_with_current_admin_and_writes_operation_log(): void
    {
        $admin = $this->makeAdmin([AdminPermissions::USER_MANAGE]);
        $user = $this->makeUser('tracking-store');
        $service = $this->makeService($user);
        Sanctum::actingAs($admin);

        $this->postJson("/api/v2/admin/users/{$user->id}/services/{$service->id}/tracking-records", [
            'tracked_at' => '2026-10-24 15:20:00',
            'content' => '已联系上游确认续费时间',
            'images' => [
                ['url' => '/media/a.png', 'name' => 'a.png'],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('message', '跟踪记录已新增')
            ->assertJsonPath('data.record.tracked_at', '2026-10-24 15:20:00')
            ->assertJsonPath('data.record.content', '已联系上游确认续费时间')
            ->assertJsonPath('data.record.operator_id', (int) $admin->id)
            ->assertJsonPath('data.record.operator_name', (string) $admin->username)
            ->assertJsonCount(1, 'data.record.images')
            ->assertJsonPath('data.record.images.0.url', '/media/a.png');

        $record = ServiceTrackingRecord::query()->where('service_id', $service->id)->firstOrFail();
        $this->assertSame('2026-10-24 15:20:00', $record->tracked_at?->format('Y-m-d H:i:s'));
        $this->assertSame((int) $admin->id, (int) $record->operator_id);
        $this->assertSame((int) $user->id, (int) $record->user_id);

        $log = ActivityLog::query()
            ->where('action', 'service.console.tracking.create')
            ->where('subject_id', $service->id)
            ->first();
        $this->assertNotNull($log, '新增历史跟踪记录必须同步写服务操作日志');
        $this->assertSame('admin', $log->actor_type);
        $this->assertSame('新增历史跟踪记录', (string) ($log->context['summary'] ?? ''));
        $this->assertSame(1, (int) ($log->context['image_count'] ?? 0));
        $this->assertSame('已联系上游确认续费时间', (string) ($log->context['content'] ?? ''));
    }

    public function test_store_requires_tracked_at_and_content(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::USER_MANAGE]));

        $user = $this->makeUser('tracking-validate');
        $service = $this->makeService($user);

        $empty = $this->postJson("/api/v2/admin/users/{$user->id}/services/{$service->id}/tracking-records", [])
            ->assertStatus(422)
            ->assertJsonPath('code', 42200);
        $this->assertNotNull($empty->json('data.errors.tracked_at'), '缺少跟踪时间必须报校验错误');
        $this->assertNotNull($empty->json('data.errors.content'), '缺少留言内容必须报校验错误');

        $this->postJson("/api/v2/admin/users/{$user->id}/services/{$service->id}/tracking-records", [
            'tracked_at' => '2026-10-24',
            'content' => '',
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 42200);
    }

    public function test_store_rejects_more_than_nine_images(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::USER_MANAGE]));

        $user = $this->makeUser('tracking-images');
        $service = $this->makeService($user);

        $images = [];
        for ($i = 1; $i <= 10; $i++) {
            $images[] = ['url' => "/media/{$i}.png", 'name' => "{$i}.png"];
        }

        $this->postJson("/api/v2/admin/users/{$user->id}/services/{$service->id}/tracking-records", [
            'tracked_at' => '2026-10-24 15:20:00',
            'content' => '图片超限',
            'images' => $images,
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 42200);
    }

    public function test_destroy_removes_record_and_writes_operation_log(): void
    {
        $admin = $this->makeAdmin([AdminPermissions::USER_MANAGE]);
        $user = $this->makeUser('tracking-destroy');
        $service = $this->makeService($user);
        $record = $this->makeRecord($service, '待删除的跟踪记录', '2026-10-23 11:00:00');
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v2/admin/users/{$user->id}/services/{$service->id}/tracking-records/{$record->id}")
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('message', '跟踪记录已删除');

        $this->assertDatabaseMissing('service_tracking_records', ['id' => $record->id]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'service.console.tracking.delete',
            'subject_id' => $service->id,
        ]);
    }

    public function test_destroy_missing_record_returns_404(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::USER_MANAGE]));

        $user = $this->makeUser('tracking-missing');
        $service = $this->makeService($user);

        $this->deleteJson("/api/v2/admin/users/{$user->id}/services/{$service->id}/tracking-records/999999999")
            ->assertStatus(404)
            ->assertJsonPath('code', 40400);
    }

    public function test_service_of_another_user_is_not_accessible(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::USER_DETAIL, AdminPermissions::USER_MANAGE]));

        $owner = $this->makeUser('tracking-owner');
        $other = $this->makeUser('tracking-other');
        $service = $this->makeService($owner);

        $this->getJson("/api/v2/admin/users/{$other->id}/services/{$service->id}/tracking-records")
            ->assertStatus(404)
            ->assertJsonPath('code', 40400)
            ->assertJsonPath('message', '服务不存在');

        $this->postJson("/api/v2/admin/users/{$other->id}/services/{$service->id}/tracking-records", [
            'tracked_at' => '2026-10-24 15:20:00',
            'content' => '越权新增',
        ])
            ->assertStatus(404)
            ->assertJsonPath('code', 40400);
    }

    public function test_tracking_endpoints_require_permissions(): void
    {
        $user = $this->makeUser('tracking-rbac');
        $service = $this->makeService($user);
        $record = $this->makeRecord($service, '权限用例记录', '2026-10-23 11:00:00');

        Sanctum::actingAs($this->makeAdmin([AdminPermissions::DASHBOARD_VIEW]));

        $this->getJson("/api/v2/admin/users/{$user->id}/services/{$service->id}/tracking-records")
            ->assertStatus(403)
            ->assertJsonPath('code', 40300);

        $this->postJson("/api/v2/admin/users/{$user->id}/services/{$service->id}/tracking-records", [
            'tracked_at' => '2026-10-24 15:20:00',
            'content' => '无权限新增',
        ])
            ->assertStatus(403)
            ->assertJsonPath('code', 40300);

        $this->deleteJson("/api/v2/admin/users/{$user->id}/services/{$service->id}/tracking-records/{$record->id}")
            ->assertStatus(403)
            ->assertJsonPath('code', 40300);
    }

    public function test_upload_image_stores_media_file_and_returns_url(): void
    {
        $admin = $this->makeAdmin([AdminPermissions::USER_MANAGE]);
        $user = $this->makeUser('tracking-upload');
        $service = $this->makeService($user);
        Sanctum::actingAs($admin);

        $file = UploadedFile::fake()->image('跟进截图.png', 320, 200);
        $createdId = 0;

        try {
            $response = $this->postJson("/api/v2/admin/users/{$user->id}/services/{$service->id}/tracking-images", [
                'file' => $file,
            ])
                ->assertOk()
                ->assertJsonPath('code', 0)
                ->assertJsonPath('message', '图片上传成功')
                ->assertJsonPath('data.image.filename', '跟进截图.png');

            $createdId = (int) $response->json('data.image.id');
            $this->assertGreaterThan(0, $createdId);

            $media = MediaFile::query()->findOrFail($createdId);
            $this->assertSame('service-tracking', $media->group);
            $this->assertSame((int) $admin->id, (int) $media->uploaded_by);
            $this->assertSame(320, (int) $media->width);
            $this->assertSame(200, (int) $media->height);
            $this->assertStringContainsString('/media/', (string) $media->url);
        } finally {
            $this->cleanupUploadedMedia($createdId);
        }
    }

    public function test_upload_rejects_non_image_file(): void
    {
        Sanctum::actingAs($this->makeAdmin([AdminPermissions::USER_MANAGE]));

        $user = $this->makeUser('tracking-upload-bad');
        $service = $this->makeService($user);

        $this->postJson("/api/v2/admin/users/{$user->id}/services/{$service->id}/tracking-images", [
            'file' => UploadedFile::fake()->create('说明.txt', 10, 'text/plain'),
        ])
            ->assertStatus(422)
            ->assertJsonPath('code', 42200);
    }

    private function cleanupUploadedMedia(int $mediaId): void
    {
        if ($mediaId <= 0) {
            return;
        }

        $media = MediaFile::query()->find($mediaId);
        if (! $media instanceof MediaFile) {
            return;
        }

        $path = public_path(ltrim((string) $media->path, '/'));
        if (is_file($path)) {
            @unlink($path);
        }

        $media->delete();
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function makeAdmin(array $permissions = [AdminPermissions::USER_DETAIL]): AdminUser
    {
        $role = Role::query()->create([
            'name' => 'role_'.uniqid(),
            'label' => '服务控制台测试角色',
            'permissions' => $permissions,
        ]);

        return AdminUser::query()->create([
            'username' => 'admin_'.uniqid(),
            'password' => 'secret123',
            'nickname' => '管理员',
            'status' => 1,
            'role_id' => $role->id,
        ]);
    }

    private function makeUser(string $prefix): User
    {
        return User::query()->create([
            'email' => $prefix.'-'.uniqid().'@example.test',
            'password' => 'x',
            'nickname' => $prefix.'-tester',
            'total_sales_amount' => 0,
        ]);
    }

    private function makeService(User $user): Service
    {
        $product = Product::query()->create([
            'product_group_id' => null,
            'service_type_code' => 'test',
            'product_type' => 'other',
            'pricing' => ['monthly' => 100.00],
            'setup_fee' => 0,
            'status' => 1,
            'sort_order' => 0,
        ]);

        return Service::query()->create([
            'user_id' => $user->id,
            'product_id' => $product->id,
            'name' => 'svc-'.uniqid(),
            'billing_cycle' => 'monthly',
            'amount' => 100.00,
            'status' => 1,
            'expires_at' => now()->addMonth(),
        ]);
    }

    private function makeRecord(Service $service, string $content, string $trackedAt, string $operatorName = '管理员'): ServiceTrackingRecord
    {
        return ServiceTrackingRecord::query()->create([
            'service_id' => (int) $service->id,
            'user_id' => (int) $service->user_id,
            'tracked_at' => $trackedAt,
            'content' => $content,
            'images' => [],
            'operator_id' => 1,
            'operator_name' => $operatorName,
        ]);
    }
}
