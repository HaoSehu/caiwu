<?php

declare(strict_types=1);

namespace Tests\Feature\Sms;

use App\Exceptions\BusinessException;
use App\Models\NotificationTemplate;
use App\Models\SmsProviderTemplate;
use App\Services\Sms\SmsProviderTemplateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use ReflectionMethod;
use Tests\TestCase;

/**
 * 运营商模板同步服务（短信模板管理机制 2026-10-11）：
 * 1. upsert 幂等（同 (plugin_key, provider_template_id) 只一行，重复同步更新不重复插入）；
 * 2. 删除探测：运营商列表缺失的存量非 deleted 行标 deleted（防死模板继续命中解析）；
 * 3. 挂接守卫：仅 approved 可挂接、业务码必须存在；解绑保留映射行只清 business_code。
 * upsert/删除探测经反射调用（内部方法），link/unlink 走公开 API。使用 DatabaseTransactions。
 */
class SmsProviderTemplateSyncTest extends TestCase
{
    use DatabaseTransactions;

    private SmsProviderTemplateService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SmsProviderTemplateService::class);
    }

    public function test_upsert_pulled_is_idempotent(): void
    {
        $templates = [$this->pulled('SMS_TPL_1', 'approved', ['site_name'])];

        $first = $this->upsertPulled('aliyun', $templates);
        $second = $this->upsertPulled('aliyun', $templates);

        $this->assertSame(1, $first['upserted']);
        $this->assertSame(1, $second['upserted']);
        $this->assertSame(1, SmsProviderTemplate::query()->where('plugin_key', 'aliyun')->count());

        $row = SmsProviderTemplate::query()->where('provider_template_id', 'SMS_TPL_1')->first();
        $this->assertSame('approved', (string) $row->status);
        $this->assertSame(['site_name'], $row->variables_json);
    }

    public function test_upsert_preserves_business_code_link(): void
    {
        $this->upsertPulled('aliyun', [$this->pulled('SMS_TPL_2', 'approved', ['site_name'])]);
        $row = SmsProviderTemplate::query()->where('provider_template_id', 'SMS_TPL_2')->first();
        $row->business_code = '190001';
        $row->save();

        $this->upsertPulled('aliyun', [$this->pulled('SMS_TPL_2', 'approved', ['site_name'])]);

        $this->assertSame(
            '190001',
            (string) SmsProviderTemplate::query()->where('provider_template_id', 'SMS_TPL_2')->value('business_code'),
            '重复同步不得覆盖管理端挂接的业务码'
        );
    }

    public function test_deletion_detection_marks_missing_rows_deleted(): void
    {
        $this->upsertPulled('aliyun', [$this->pulled('SMS_GONE', 'approved', []), $this->pulled('SMS_KEEP', 'approved', [])]);

        // 运营商侧只剩 SMS_KEEP，SMS_GONE 消失 → 删除探测标 deleted。
        $summary = $this->upsertPulled('aliyun', [$this->pulled('SMS_KEEP', 'approved', [])]);

        $this->assertSame(1, $summary['marked_deleted']);
        $this->assertSame('deleted', (string) SmsProviderTemplate::query()->where('provider_template_id', 'SMS_GONE')->value('status'));
        $this->assertSame('approved', (string) SmsProviderTemplate::query()->where('provider_template_id', 'SMS_KEEP')->value('status'));
    }

    public function test_deletion_detection_skips_already_deleted(): void
    {
        SmsProviderTemplate::query()->create($this->row('aliyun', 'SMS_DEL', 'approved', []));
        SmsProviderTemplate::query()->where('provider_template_id', 'SMS_DEL')->update(['status' => 'deleted']);

        $summary = $this->upsertPulled('aliyun', [$this->pulled('SMS_OTHER', 'pending', [])]);

        $this->assertSame(0, $summary['marked_deleted'], '已 deleted 的行不重复计数');
    }

    public function test_link_rejects_non_approved_mapping(): void
    {
        $row = SmsProviderTemplate::query()->create($this->row('aliyun', 'SMS_PENDING_1', 'pending', []));

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('仅审核通过');

        $this->service->link((int) $row->id, $this->makeBusinessCode());
    }

    public function test_link_rejects_unknown_business_code(): void
    {
        $row = SmsProviderTemplate::query()->create($this->row('aliyun', 'SMS_OK_1', 'approved', []));

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('短信业务模板不存在');

        $this->service->link((int) $row->id, '190099');
    }

    public function test_link_and_unlink_roundtrip(): void
    {
        $businessCode = $this->makeBusinessCode();
        $row = SmsProviderTemplate::query()->create($this->row('aliyun', 'SMS_OK_2', 'approved', ['site_name']));

        $linked = $this->service->link((int) $row->id, $businessCode);
        $this->assertSame($businessCode, (string) $linked['business_code']);

        $unlinked = $this->service->unlink((int) $row->id);
        $this->assertNull($unlinked['business_code']);
        $this->assertSame(
            1,
            SmsProviderTemplate::query()->where('provider_template_id', 'SMS_OK_2')->count(),
            '解绑保留映射行'
        );
    }

    public function test_link_releases_previous_mapping_of_same_business_code(): void
    {
        // Review 🟡2：单挂接语义——同一业务码挂接新模板时自动解绑旧挂接。
        $businessCode = $this->makeBusinessCode();
        $first = SmsProviderTemplate::query()->create($this->row('aliyun', 'SMS_LINK_1', 'approved', []));
        $second = SmsProviderTemplate::query()->create($this->row('aliyun', 'SMS_LINK_2', 'approved', []));

        $this->service->link((int) $first->id, $businessCode);
        $this->service->link((int) $second->id, $businessCode);

        $this->assertSame(
            $businessCode,
            (string) SmsProviderTemplate::query()->whereKey($second->id)->value('business_code'),
            '新挂接生效'
        );
        $this->assertNull(
            SmsProviderTemplate::query()->whereKey($first->id)->value('business_code'),
            '旧挂接被自动解绑'
        );
        $this->assertSame(1, SmsProviderTemplate::query()->where('business_code', $businessCode)->count());
    }

    // ===== 照抄 ZJMF 生命周期重构（2026-10-11）：创建草稿/修改/删除/状态机守卫 =====

    public function test_create_makes_draft_without_provider_id(): void
    {
        $row = $this->service->create('到期提醒', '您的产品{product_name}即将到期', '测试备注');

        $this->assertSame('draft', $row['status']);
        $this->assertNull($row['provider_template_id'], '草稿没有运营商模板 ID');
        $this->assertSame('到期提醒', $row['title']);
        $this->assertSame('测试备注', $row['remark']);
    }

    public function test_create_rejects_blank_content(): void
    {
        $this->expectException(BusinessException::class);

        $this->service->create('', '内容', '');
    }

    public function test_submit_review_rejects_approved_row_before_runtime(): void
    {
        $row = SmsProviderTemplate::query()->create($this->row('aliyun', 'SMS_SUB_1', 'approved', []));

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('仅未提交的草稿');

        $this->service->submitForReview((int) $row->id);
    }

    public function test_submit_review_rejects_rejected_row(): void
    {
        // Review 🟡1：rejected 行不再走 Add 提审（避免运营商侧重复创建），
        // 统一经「编辑」走 sms.templates.update（Modify 复用旧 ID 重审）。
        $row = SmsProviderTemplate::query()->create($this->row('aliyun', 'SMS_SUB_2', 'rejected', []));

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('仅未提交的草稿');

        $this->service->submitForReview((int) $row->id);
    }

    public function test_update_template_edits_draft_locally(): void
    {
        $draft = $this->service->create('旧名称', '旧内容', '');

        $updated = $this->service->updateTemplate((int) $draft['id'], '新名称', '新内容{code}', '备注');

        $this->assertSame('新名称', $updated['title']);
        $this->assertSame('新内容{code}', $updated['content']);
        $this->assertSame('draft', $updated['status'], '草稿修改不调运营商，保持 draft');
    }

    public function test_update_template_rejects_deleted_row(): void
    {
        $row = SmsProviderTemplate::query()->create($this->row('aliyun', 'SMS_DEL_2', 'deleted', []));

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('运营商侧已删除');

        $this->service->updateTemplate((int) $row->id, '名', '容', '');
    }

    public function test_destroy_draft_row_without_operator_call(): void
    {
        $draft = $this->service->create('待删除', '内容', '');

        $summary = $this->service->destroy((int) $draft['id']);

        $this->assertFalse($summary['operator_deleted'], '草稿删除不调运营商');
        $this->assertSame(0, SmsProviderTemplate::query()->whereKey($draft['id'])->count());
    }

    public function test_destroy_deleted_row_skips_operator_call(): void
    {
        $row = SmsProviderTemplate::query()->create($this->row('aliyun', 'SMS_DEL_3', 'deleted', []));

        $summary = $this->service->destroy((int) $row->id);

        $this->assertFalse($summary['operator_deleted'], '运营商侧已删除的行免调删除接口');
        $this->assertSame(0, SmsProviderTemplate::query()->whereKey($row->id)->count());
    }

    public function test_refresh_mapping_rejects_draft(): void
    {
        $draft = $this->service->create('未提交', '内容', '');

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('未提交审核');

        $this->service->refreshMapping((int) $draft['id']);
    }

    /**
     * @param  array<int, array<string, mixed>>  $templates
     * @return array<string, int>
     */
    private function upsertPulled(string $pluginKey, array $templates): array
    {
        $method = new ReflectionMethod(SmsProviderTemplateService::class, 'upsertPulled');

        return $method->invoke($this->service, $pluginKey, $templates);
    }

    /**
     * @return array<string, mixed>
     */
    private function pulled(string $providerTemplateId, string $status, array $variables): array
    {
        return [
            'provider_template_id' => $providerTemplateId,
            'provider_sign' => '签名',
            'content' => '内容 ${site_name}',
            'variables' => $variables,
            'status' => $status,
            'reject_reason' => $status === 'rejected' ? '驳回' : '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $pluginKey, string $providerTemplateId, string $status, array $variables): array
    {
        return [
            'plugin_key' => $pluginKey,
            'business_code' => null,
            'provider_template_id' => $providerTemplateId,
            'provider_sign' => '',
            'content' => '内容',
            'variables_json' => $variables,
            'status' => $status,
            'reject_reason' => '',
            'synced_at' => now(),
        ];
    }

    private function makeBusinessCode(): string
    {
        NotificationTemplate::query()->create([
            'channel' => 'sms',
            'code' => '190002',
            'name' => '挂接测试业务码',
            'description' => '',
            'audience' => 'user',
            'subject' => null,
            'content' => '【{site_name}】测试',
            'variables_json' => ['site_name'],
            'provider_template_id' => null,
            'is_enabled' => true,
            'is_custom' => false,
            'sort_order' => 0,
        ]);

        return '190002';
    }
}
