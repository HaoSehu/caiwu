<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Exceptions\BusinessException;
use App\Models\SmsProviderTemplate;
use App\Services\Integrations\Plugins\IntegrationDriverBindingResolver;
use App\Services\Integrations\Plugins\PluginDomain;
use App\Services\Integrations\Plugins\PluginRuntimeRegistry;
use App\Services\System\ActivityLogService;
use App\Services\System\NotificationTemplateService;
use App\Support\SchemaMetadataCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 短信运营商模板管理服务（照抄 ZJMF 模板生命周期重构 2026-10-11）。
 *
 * 生命周期对齐 ZJMF message_template：本地创建草稿（draft，无运营商 ID）
 * → 提交运营商审核（push 回填 provider_template_id → pending）
 * → 回查（approved / rejected+原因）→ 修改重提（update → pending）
 * → 删除（delete 联动运营商 + 删本地）。另保留 pull（页面级手动同步，
 * 收纳运营商控制台直建的模板，含运营商侧删除探测）。
 *
 * 发送侧不变：仅 approved 且已挂接业务码的模板进入 SmsTemplateResolver。
 * 插件只做协议翻译，不落库、不自建表（项目红线）；所有 action 调用经
 * PluginRuntimeRegistry，runtime log 自动审计。
 */
class SmsProviderTemplateService
{
    /** 插件返回的审核状态白名单（协议契约）；draft 仅本地产生，不在协议内 */
    private const VALID_STATUSES = [
        SmsProviderTemplate::STATUS_PENDING,
        SmsProviderTemplate::STATUS_APPROVED,
        SmsProviderTemplate::STATUS_REJECTED,
        SmsProviderTemplate::STATUS_DELETED,
    ];

    public function __construct(
        private readonly PluginRuntimeRegistry $runtime,
        private readonly IntegrationDriverBindingResolver $bindingResolver,
        private readonly NotificationTemplateService $notificationTemplates,
        private readonly ActivityLogService $activityLogs,
    ) {}

    /**
     * 当前绑定的短信 driver key（管理端操作默认作用于绑定 driver）。
     */
    public function boundDriverKey(): string
    {
        return $this->bindingResolver->smsDriverKey();
    }

    /**
     * 经 runtime 执行插件模板 action；非业务异常（插件内部错误、网络异常等）
     * 统一转为 BusinessException，避免管理端操作冒泡成 500（Review 🟡3）。
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function executeTemplateAction(string $pluginKey, string $action, array $payload = []): array
    {
        try {
            return $this->runtime->execute(PluginDomain::SMS, $pluginKey, $action, $payload);
        } catch (BusinessException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw new BusinessException('短信模板操作失败（'.$action.'）：'.$exception->getMessage());
        }
    }

    /**
     * 拉取单个插件的运营商模板并 upsert（含删除探测）。
     *
     * @return array<string, mixed> 同步摘要
     */
    public function pull(?string $pluginKey = null): array
    {
        $pluginKey = trim((string) ($pluginKey ?? $this->boundDriverKey()));
        if ($pluginKey === '') {
            throw new BusinessException('短信插件未绑定，无法同步模板');
        }

        $result = $this->executeTemplateAction($pluginKey, 'sms.templates.pull');
        $templates = $this->normalizeTemplates($result, $pluginKey);

        $summary = $this->upsertPulled($pluginKey, $templates);
        $summary['plugin_key'] = $pluginKey;

        $this->activityLogs->logSafely(
            'integrations',
            'sms_template.pull',
            sprintf(
                '短信模板同步（%s）：拉取 %d 条，更新 %d 条，删除探测 %d 条',
                $pluginKey,
                count($templates),
                $summary['upserted'],
                $summary['marked_deleted']
            ),
            null,
            null,
            $summary,
        );

        return $summary;
    }

    /**
     * 运营商模板列表（管理端「运营商模板」对话框，按绑定 driver）。
     *
     * @return array<int, array<string, mixed>>
     */
    public function listTemplates(?string $pluginKey = null): array
    {
        if (! SchemaMetadataCache::hasTable('sms_provider_templates')) {
            return [];
        }

        $pluginKey = trim((string) ($pluginKey ?? $this->boundDriverKey()));

        return SmsProviderTemplate::query()
            ->where('plugin_key', $pluginKey)
            ->orderByDesc('id')
            ->get()
            ->map(fn (SmsProviderTemplate $row): array => $this->formatRow($row))
            ->values()
            ->all();
    }

    /**
     * 创建运营商模板草稿（本地动作，不调运营商）——对齐 ZJMF 本地建模板。
     */
    public function create(string $title, string $content, string $remark, ?string $pluginKey = null): array
    {
        $title = trim($title);
        $content = trim($content);
        if ($title === '' || $content === '') {
            throw new BusinessException('模板名称与内容不能为空');
        }

        $pluginKey = trim((string) ($pluginKey ?? $this->boundDriverKey()));
        if ($pluginKey === '') {
            throw new BusinessException('短信插件未绑定，无法创建模板');
        }

        $row = SmsProviderTemplate::query()->create([
            'plugin_key' => $pluginKey,
            'title' => $title,
            'business_code' => null,
            'provider_template_id' => null,
            'content' => $content,
            'remark' => trim($remark),
            'status' => SmsProviderTemplate::STATUS_DRAFT,
            'synced_at' => now(),
        ]);

        $this->activityLogs->logSafely(
            'integrations',
            'sms_template.create',
            sprintf('短信运营商模板创建草稿（%s / #%d）：%s', $pluginKey, $row->id, $title),
            'sms_provider_template',
            (int) $row->id,
        );

        return $this->formatRow($row);
    }

    /**
     * 提交运营商模板草稿审核（draft → pending/插件结论）——对齐 ZJMF createCnTemplate。
     * 运营商返回的模板 ID 回填本行，不新建。
     * 仅 draft 可提审：rejected 行经「编辑」走 sms.templates.update（Modify 复用旧
     * ID 重审），避免 Add 重复创建（Review 🟡1）。
     */
    public function submitForReview(int $mappingId, ?string $pluginKey = null): array
    {
        $row = SmsProviderTemplate::query()->findOrFail($mappingId);
        $pluginKey = trim((string) ($pluginKey ?? $this->boundDriverKey()));

        if ($row->status !== SmsProviderTemplate::STATUS_DRAFT) {
            throw new BusinessException('仅未提交的草稿可提审；已驳回模板请编辑后重审');
        }
        if (trim((string) $row->content) === '') {
            throw new BusinessException('模板内容为空，无法提交审核');
        }

        $result = $this->executeTemplateAction($pluginKey, 'sms.templates.push', [
            'title' => (string) $row->title,
            'content' => (string) $row->content,
            'remark' => (string) $row->remark,
        ]);

        $data = is_array($result['data'] ?? null) ? $result['data'] : [];
        $providerTemplateId = trim((string) ($data['provider_template_id'] ?? ''));
        if ($providerTemplateId === '') {
            throw new BusinessException('插件未返回运营商模板 ID，无法登记');
        }

        // 状态采纳插件结论：普通运营商返回 pending（审核异步）；提交即过审的
        // 文本通道（ZJMF smsbao 范式）返回 approved，无需再回查。
        $status = (string) ($data['status'] ?? SmsProviderTemplate::STATUS_PENDING);
        if ($status === '' || ! in_array($status, self::VALID_STATUSES, true)) {
            $status = SmsProviderTemplate::STATUS_PENDING;
        }

        $row->provider_template_id = $providerTemplateId;
        $row->content = (string) ($data['content'] ?? $row->content);
        $row->variables_json = array_values(array_filter(
            array_map('strval', (array) ($data['variables'] ?? [])),
            static fn (string $name): bool => $name !== ''
        )) ?: $row->variables_json;
        $row->status = $status;
        $row->reject_reason = '';
        $row->raw_json = $this->rawSummary($data);
        $row->synced_at = now();

        try {
            $row->save();
        } catch (Throwable $exception) {
            // 运营商侧已受理但本地落库失败：留痕供人工核对，避免重试时
            // 对同一文案向运营商重复建模板（2026-10-13 审查 P2-4）。
            Log::error('[短信模板] 提审后本地落库失败，运营商侧模板已存在，请人工核对', [
                'mapping_id' => (int) $row->id,
                'plugin_key' => $pluginKey,
                'provider_template_id' => $providerTemplateId,
                'title' => (string) $row->title,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);

            throw $exception;
        }

        $this->activityLogs->logSafely(
            'integrations',
            'sms_template.push',
            sprintf('短信运营商模板提交审核（%s / %s）：#%d %s', $pluginKey, $providerTemplateId, $row->id, $row->title),
            'sms_provider_template',
            (int) $row->id,
            ['status' => $row->status],
        );

        return $this->formatRow($row);
    }

    /**
     * 修改模板并重新提审——对齐 ZJMF putCnTemplate（put*Template）。
     * draft 行仅更新本地内容；已有运营商 ID 的行调插件 update 后回到审核中。
     */
    public function updateTemplate(int $mappingId, string $title, string $content, string $remark): array
    {
        $row = SmsProviderTemplate::query()->findOrFail($mappingId);
        $title = trim($title);
        $content = trim($content);
        if ($title === '' || $content === '') {
            throw new BusinessException('模板名称与内容不能为空');
        }
        if ($row->status === SmsProviderTemplate::STATUS_DELETED) {
            throw new BusinessException('运营商侧已删除的模板不可修改');
        }

        $row->title = $title;
        $row->content = $content;
        $row->remark = trim($remark);

        if ($row->status !== SmsProviderTemplate::STATUS_DRAFT && trim((string) $row->provider_template_id) !== '') {
            $result = $this->executeTemplateAction($this->boundDriverKey(), 'sms.templates.update', [
                'provider_template_id' => $row->provider_template_id,
                'title' => $title,
                'content' => $content,
                'remark' => $row->remark,
            ]);

            $data = is_array($result['data'] ?? null) ? $result['data'] : [];
            $row->raw_json = $this->rawSummary($data);
            $row->status = SmsProviderTemplate::STATUS_PENDING;
            $row->reject_reason = '';
        }

        $row->synced_at = now();
        $row->save();

        $this->activityLogs->logSafely(
            'integrations',
            'sms_template.update',
            sprintf('短信运营商模板修改（%s / #%d）：%s', $row->plugin_key, $row->id, $title),
            'sms_provider_template',
            (int) $row->id,
            ['status' => $row->status],
        );

        return $this->formatRow($row);
    }

    /**
     * 回查单个模板的审核状态——对齐 ZJMF getCnTemplate。
     */
    public function refreshMapping(int $mappingId, ?string $pluginKey = null): array
    {
        $row = SmsProviderTemplate::query()->findOrFail($mappingId);
        $pluginKey = trim((string) ($pluginKey ?? $this->boundDriverKey()));

        if (trim((string) $row->provider_template_id) === '' || $row->status === SmsProviderTemplate::STATUS_DRAFT) {
            throw new BusinessException('未提交审核的模板没有可回查的状态');
        }

        $result = $this->executeTemplateAction($pluginKey, 'sms.templates.status', [
            'provider_template_id' => $row->provider_template_id,
        ]);

        $data = is_array($result['data'] ?? null) ? $result['data'] : [];
        $status = (string) ($data['status'] ?? '');
        if ($status !== '' && in_array($status, self::VALID_STATUSES, true)) {
            $row->status = $status;
        }
        $row->reject_reason = (string) ($data['reject_reason'] ?? $row->reject_reason);
        $row->synced_at = now();
        $row->save();

        $this->activityLogs->logSafely(
            'integrations',
            'sms_template.status',
            sprintf('短信运营商模板状态回查（%s / %s）：%s', $pluginKey, $row->provider_template_id, $row->status),
            'sms_provider_template',
            (int) $row->id,
            ['status' => $row->status],
        );

        return $this->formatRow($row);
    }

    /**
     * 删除模板——对齐 ZJMF deleteCnTemplate：已有运营商 ID 且未确认删除的
     * 先调插件删除，再删本地行（draft / deleted 免调）。
     */
    public function destroy(int $mappingId, ?string $pluginKey = null): array
    {
        $row = SmsProviderTemplate::query()->findOrFail($mappingId);
        $pluginKey = trim((string) ($pluginKey ?? $this->boundDriverKey()));
        $summary = [
            'id' => (int) $row->id,
            'title' => (string) $row->title,
            'provider_template_id' => (string) ($row->provider_template_id ?? ''),
            'plugin_key' => (string) $row->plugin_key,
            'operator_deleted' => false,
        ];

        if (trim((string) $row->provider_template_id) !== ''
            && $row->status !== SmsProviderTemplate::STATUS_DELETED
            && $row->status !== SmsProviderTemplate::STATUS_DRAFT
        ) {
            $this->executeTemplateAction($pluginKey, 'sms.templates.delete', [
                'provider_template_id' => $row->provider_template_id,
            ]);
            $summary['operator_deleted'] = true;
        }

        $row->delete();

        $this->activityLogs->logSafely(
            'integrations',
            'sms_template.delete',
            sprintf(
                '短信运营商模板删除（%s / #%d %s%s）',
                $summary['plugin_key'],
                $summary['id'],
                $summary['title'],
                $summary['operator_deleted'] ? '，已联动运营商删除' : ''
            ),
            'sms_provider_template',
            $summary['id'],
            $summary,
        );

        return $summary;
    }

    /**
     * 导入插件默认模板——对齐 ZJMF 插件 install() 返回 smsTemplate.php 种子。
     * 种子一律落为 draft 草稿，由管理员确认后逐条提审。
     * 按 (plugin_key, title, content) 去重：重复导入幂等，跳过已存在条目。
     *
     * @return array{imported: int, skipped: int, plugin_key: string}
     */
    public function importDefaults(?string $pluginKey = null): array
    {
        $pluginKey = trim((string) ($pluginKey ?? $this->boundDriverKey()));
        if ($pluginKey === '') {
            throw new BusinessException('短信插件未绑定，无法导入默认模板');
        }

        $result = $this->executeTemplateAction($pluginKey, 'sms.templates.defaults');
        $defaults = $result['data']['defaults'] ?? null;
        if (! is_array($defaults) || $defaults === []) {
            throw new BusinessException('插件未提供默认模板');
        }

        $imported = 0;
        $skipped = 0;
        foreach ($defaults as $default) {
            if (! is_array($default)) {
                continue;
            }
            $title = trim((string) ($default['title'] ?? ''));
            $content = trim((string) ($default['content'] ?? ''));
            if ($title === '' || $content === '') {
                continue;
            }

            // 幂等去重：同插件同名同内容（非 deleted）已存在则跳过，
            // 防止重复点击「导入默认模板」产生多批重复草稿。
            $exists = SmsProviderTemplate::query()
                ->where('plugin_key', $pluginKey)
                ->where('title', $title)
                ->where('content', $content)
                ->where('status', '!=', SmsProviderTemplate::STATUS_DELETED)
                ->exists();
            if ($exists) {
                $skipped++;

                continue;
            }

            SmsProviderTemplate::query()->create([
                'plugin_key' => $pluginKey,
                'title' => $title,
                'business_code' => null,
                'provider_template_id' => null,
                'content' => $content,
                'remark' => trim((string) ($default['remark'] ?? '插件默认模板')),
                'variables_json' => array_values(array_filter(
                    array_map('strval', (array) ($default['variables'] ?? [])),
                    static fn (string $name): bool => $name !== ''
                )),
                'status' => SmsProviderTemplate::STATUS_DRAFT,
                'synced_at' => now(),
            ]);
            $imported++;
        }

        $this->activityLogs->logSafely(
            'integrations',
            'sms_template.import_defaults',
            sprintf('短信运营商模板导入默认模板（%s）：新增 %d 条，跳过重复 %d 条', $pluginKey, $imported, $skipped),
            null,
            null,
            ['imported' => $imported, 'skipped' => $skipped],
        );

        return ['imported' => $imported, 'skipped' => $skipped, 'plugin_key' => $pluginKey];
    }

    /**
     * 批量提审：把当前绑定 driver 下全部 draft 模板逐条提交
     * 运营商审核（对齐 ZJMF checkPost 批量提交）。单条失败不阻断其余，
     * 失败明细随摘要返回。
     *
     * @return array{submitted: int, failed: int, errors: array<int, array{id: int, title: string, message: string}>}
     */
    public function submitAllForReview(?string $pluginKey = null): array
    {
        $pluginKey = trim((string) ($pluginKey ?? $this->boundDriverKey()));
        if ($pluginKey === '') {
            throw new BusinessException('短信插件未绑定，无法提交审核');
        }

        $rows = SmsProviderTemplate::query()
            ->where('plugin_key', $pluginKey)
            ->where('status', SmsProviderTemplate::STATUS_DRAFT)
            ->orderBy('id')
            ->get();

        $submitted = 0;
        $failed = 0;
        $errors = [];
        foreach ($rows as $row) {
            try {
                $this->submitForReview((int) $row->id, $pluginKey);
                $submitted++;
            } catch (Throwable $exception) {
                $failed++;
                $errors[] = [
                    'id' => (int) $row->id,
                    'title' => (string) $row->title,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        $this->activityLogs->logSafely(
            'integrations',
            'sms_template.submit_all',
            sprintf('短信运营商模板批量提审（%s）：成功 %d 条，失败 %d 条', $pluginKey, $submitted, $failed),
            null,
            null,
            ['submitted' => $submitted, 'failed' => $failed],
        );

        return ['submitted' => $submitted, 'failed' => $failed, 'errors' => $errors];
    }

    /**
     * 挂接：把 approved 运营商模板绑定到业务码。
     * 单挂接语义（Review 🟡2，对齐 ZJMF SetSmsTemplatePost 先删后插）：同一
     * 业务码已挂接的其他映射自动解绑，避免多模板同时「已挂接」误导管理员——
     * 发送解析只取 synced_at 最新一条。
     */
    public function link(int $mappingId, string $businessCode): array
    {
        $businessCode = trim($businessCode);
        $row = SmsProviderTemplate::query()->findOrFail($mappingId);

        if ($row->status !== SmsProviderTemplate::STATUS_APPROVED) {
            throw new BusinessException('仅审核通过的运营商模板可挂接业务码');
        }

        if (! is_array($this->notificationTemplates->find('sms', $businessCode))) {
            throw new BusinessException('短信业务模板不存在：'.$businessCode);
        }

        // 单挂接「先解绑后挂接」两步事务化（2026-10-13 审查 P2-5）：两步之间失败
        // 会让业务码短暂无挂接（发送解析回落旧列/fallback 链），包事务保证原子切换。
        // 并发保护（同日审查 P2-1）：business_code 无唯一约束，事务本身只保证失败原子性，
        // 两个并发挂接仍可能各留一条；以业务码为粒度加互斥锁，串行化同一业务码的挂接。
        $lock = Cache::lock('lock:sms-provider-template-link:'.$businessCode, 15);
        if (! $lock->get()) {
            throw new BusinessException('该业务码正在被其他挂接操作处理，请稍后重试', 40900, 409);
        }

        try {
            $releasedCount = DB::transaction(function () use ($row, $businessCode): int {
                $released = 0;
                SmsProviderTemplate::query()
                    ->where('business_code', $businessCode)
                    ->whereKeyNot($row->getKey())
                    ->get()
                    ->each(function (SmsProviderTemplate $other) use ($businessCode, &$released): void {
                        $other->business_code = null;
                        $other->save();
                        $released++;

                        $this->activityLogs->logSafely(
                            'integrations',
                            'sms_template.unlink',
                            sprintf('短信模板自动解绑（%s / %s，原业务码 %s，被新挂接替换）', $other->plugin_key, $other->provider_template_id, $businessCode),
                            'sms_provider_template',
                            (int) $other->id,
                        );
                    });

                $row->business_code = $businessCode;
                $row->save();

                return $released;
            });
        } finally {
            $lock->release();
        }

        $this->activityLogs->logSafely(
            'integrations',
            'sms_template.link',
            sprintf('短信模板挂接（%s / %s → 业务码 %s）', $row->plugin_key, $row->provider_template_id, $businessCode),
            'sms_provider_template',
            (int) $row->id,
            ['business_code' => $businessCode, 'released' => $releasedCount],
        );

        return $this->formatRow($row);
    }

    /**
     * 解绑：移除映射的业务码关联（映射行保留，供审计与回看）。
     */
    public function unlink(int $mappingId): array
    {
        $row = SmsProviderTemplate::query()->findOrFail($mappingId);
        $previous = (string) ($row->business_code ?? '');

        $row->business_code = null;
        $row->save();

        $this->activityLogs->logSafely(
            'integrations',
            'sms_template.unlink',
            sprintf('短信模板解绑（%s / %s，原业务码 %s）', $row->plugin_key, $row->provider_template_id, $previous ?: '无'),
            'sms_provider_template',
            (int) $row->id,
            ['previous_business_code' => $previous],
        );

        return $this->formatRow($row);
    }

    /**
     * pull 结果 upsert：业务码与挂接关系不覆盖（link 的权威在管理端操作），
     * 其余字段以运营商侧为准；本次列表缺失的存量行做删除探测。
     *
     * @param  array<int, array<string, mixed>>  $templates
     * @return array<string, int>
     */
    private function upsertPulled(string $pluginKey, array $templates): array
    {
        $upserted = 0;

        foreach ($templates as $template) {
            SmsProviderTemplate::query()->updateOrCreate(
                [
                    'plugin_key' => $pluginKey,
                    'provider_template_id' => (string) $template['provider_template_id'],
                ],
                [
                    'provider_sign' => (string) ($template['provider_sign'] ?? ''),
                    'content' => (string) ($template['content'] ?? ''),
                    'variables_json' => (array) ($template['variables'] ?? []),
                    'status' => (string) ($template['status'] ?? SmsProviderTemplate::STATUS_PENDING),
                    'reject_reason' => (string) ($template['reject_reason'] ?? ''),
                    'raw_json' => $this->rawSummary($template),
                    'synced_at' => now(),
                ],
            );
            $upserted++;
        }

        // 删除探测：运营商列表已不存在的存量行（非 deleted，draft 无运营商 ID
        // 天然不在集合内）标 deleted，防止解析层继续命中死模板 ID。
        $pulledIds = array_column($templates, 'provider_template_id');
        $markedDeleted = 0;
        if ($pulledIds !== []) {
            $markedDeleted = SmsProviderTemplate::query()
                ->where('plugin_key', $pluginKey)
                ->whereNotNull('provider_template_id')
                ->whereNotIn('provider_template_id', $pulledIds)
                ->where('status', '!=', SmsProviderTemplate::STATUS_DELETED)
                ->update([
                    'status' => SmsProviderTemplate::STATUS_DELETED,
                    'synced_at' => now(),
                ]);
        }

        return ['upserted' => $upserted, 'marked_deleted' => (int) $markedDeleted];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeTemplates(array $result, string $pluginKey): array
    {
        if (($result['success'] ?? true) === false) {
            throw new BusinessException((string) ($result['message'] ?? '插件拉取短信模板失败'));
        }

        $templates = $result['data']['templates'] ?? null;
        if (! is_array($templates)) {
            throw new BusinessException('插件未返回短信模板列表');
        }

        $normalized = [];
        foreach ($templates as $template) {
            if (! is_array($template)) {
                continue;
            }

            $providerTemplateId = trim((string) ($template['provider_template_id'] ?? ''));
            $status = (string) ($template['status'] ?? SmsProviderTemplate::STATUS_PENDING);
            if ($providerTemplateId === '') {
                continue;
            }
            if (! in_array($status, self::VALID_STATUSES, true)) {
                throw new BusinessException('插件返回了未知的模板审核状态：'.$status.'（'.$pluginKey.'）');
            }

            $normalized[] = [
                'provider_template_id' => $providerTemplateId,
                'provider_sign' => (string) ($template['provider_sign'] ?? ''),
                'content' => (string) ($template['content'] ?? ''),
                'variables' => array_values(array_filter(
                    array_map('strval', (array) ($template['variables'] ?? [])),
                    static fn (string $name): bool => $name !== ''
                )),
                'status' => $status,
                'reject_reason' => (string) ($template['reject_reason'] ?? ''),
            ];
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function rawSummary(array $data): ?array
    {
        $raw = $data['raw'] ?? null;

        return is_array($raw) && $raw !== [] ? $raw : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatRow(SmsProviderTemplate $row): array
    {
        return [
            'id' => (int) $row->id,
            'plugin_key' => (string) $row->plugin_key,
            'title' => (string) $row->title,
            'business_code' => $row->business_code !== null ? (string) $row->business_code : null,
            'provider_template_id' => $row->provider_template_id !== null ? (string) $row->provider_template_id : null,
            'provider_sign' => (string) $row->provider_sign,
            'content' => (string) $row->content,
            'remark' => (string) $row->remark,
            'variables' => array_values((array) ($row->variables_json ?? [])),
            'status' => (string) $row->status,
            'reject_reason' => (string) $row->reject_reason,
            'synced_at' => $row->synced_at?->toDateTimeString(),
            'is_bound_driver' => (string) $row->plugin_key === $this->boundDriverKey(),
        ];
    }
}
