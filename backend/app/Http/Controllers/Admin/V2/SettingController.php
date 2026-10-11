<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\V2\Setting\ListNotificationTemplatesRequest;
use App\Http\Requests\Admin\V2\Setting\ListSettingsRequest;
use App\Http\Requests\Admin\V2\Setting\RevealSettingSecretRequest;
use App\Http\Requests\Admin\V2\Setting\SmsProviderTemplateFormRequest;
use App\Http\Requests\Admin\V2\Setting\SmsProviderTemplateLinkRequest;
use App\Http\Requests\Admin\V2\Setting\TestNotificationTemplateSendRequest;
use App\Http\Requests\Admin\V2\Setting\UpdateSettingsRequest;
use App\Http\Resources\Admin\V2\AdminActionResultResource;
use App\Http\Resources\Admin\V2\AdminNotificationTemplateResource;
use App\Services\Admin\V2\AdminConfigurationV2QueryService;
use App\Services\Sms\SmsProviderTemplateService;
use App\Services\System\NotificationTemplateService;
use App\Services\System\NotificationTemplateTestSendService;
use App\Services\System\OperationLogService;
use App\Services\System\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class SettingController extends Controller
{
    public function __construct(
        private readonly AdminConfigurationV2QueryService $queryService,
        private readonly SettingService $settings,
        private readonly NotificationTemplateService $notificationTemplates,
        private readonly NotificationTemplateTestSendService $templateTestSender,
        private readonly OperationLogService $operationLogService,
        private readonly SmsProviderTemplateService $smsProviderTemplates,
    ) {}

    public function index(ListSettingsRequest $request)
    {
        return $this->success($this->queryService->settings(
            $request->group(),
            $request->pageNumber(),
            $request->pageSize()
        ));
    }

    public function notificationTemplates(ListNotificationTemplatesRequest $request)
    {
        $templates = collect($this->notificationTemplates->list($request->channel()));
        $list = AdminNotificationTemplateResource::collection($templates)->resolve();

        // 全量模板列表无真实分页，统一经标准分页器出信封（page=1、page_size=条目数）。
        $total = count($list);

        return $this->paginate(new LengthAwarePaginator($list, $total, max($total, 1), 1));
    }

    /**
     * 短信运营商模板列表（管理端「运营商模板」对话框，按当前绑定 driver）。
     */
    public function listSmsProviderTemplates(): JsonResponse
    {
        return $this->success($this->smsProviderTemplates->listTemplates());
    }

    /**
     * 短信运营商模板：批量提审全部草稿与已驳回模板（单条失败不阻断）。
     */
    public function submitAllSmsProviderTemplates(): JsonResponse
    {
        return $this->success(
            $this->smsProviderTemplates->submitAllForReview(),
            '批量提审已完成'
        );
    }

    /**
     * 短信运营商模板：创建本地草稿（draft，不调运营商）。
     */
    public function createSmsProviderTemplate(SmsProviderTemplateFormRequest $request): JsonResponse
    {
        return $this->success(
            $this->smsProviderTemplates->create(
                $request->templateTitle(),
                $request->templateContent(),
                $request->templateRemark()
            ),
            '模板草稿已创建，提交审核后生效'
        );
    }

    /**
     * 短信运营商模板：修改并重新提审（draft 仅更新本地）。
     */
    public function updateSmsProviderTemplate(SmsProviderTemplateFormRequest $request, string $id): JsonResponse
    {
        return $this->success(
            $this->smsProviderTemplates->updateTemplate(
                (int) $id,
                $request->templateTitle(),
                $request->templateContent(),
                $request->templateRemark()
            ),
            '模板已保存'
        );
    }

    /**
     * 短信运营商模板：提交运营商审核（draft/rejected → pending）。
     */
    public function submitSmsProviderTemplateForReview(string $id): JsonResponse
    {
        return $this->success(
            $this->smsProviderTemplates->submitForReview((int) $id),
            '已提交运营商审核'
        );
    }

    /**
     * 短信运营商模板：回查单个模板的审核状态。
     */
    public function refreshSmsProviderTemplateById(string $id): JsonResponse
    {
        return $this->success(
            $this->smsProviderTemplates->refreshMapping((int) $id),
            '审核状态已回查'
        );
    }

    /**
     * 短信运营商模板：删除（联动运营商删除后删本地行）。
     */
    public function deleteSmsProviderTemplate(string $id): JsonResponse
    {
        return $this->success(
            $this->smsProviderTemplates->destroy((int) $id),
            '模板已删除'
        );
    }

    /**
     * 短信运营商模板：导入插件默认模板（种子落为草稿）。
     */
    public function importDefaultSmsProviderTemplates(): JsonResponse
    {
        $summary = $this->smsProviderTemplates->importDefaults();

        return $this->success($summary, '已导入默认模板 '.$summary['imported'].' 条（草稿）');
    }

    /**
     * 短信模板管理页：从当前绑定运营商拉取模板与审核状态（含删除探测）。
     * 同步入口归属短信模板管理页面（用户裁决 2026-10-11：非定时任务）。
     */
    public function syncSmsProviderTemplates(): JsonResponse
    {
        return $this->success(
            $this->smsProviderTemplates->pull(),
            '已拉取运营商短信模板'
        );
    }

    /**
     * 短信模板：挂接/解绑运营商模板到业务码。
     */
    public function linkSmsProviderTemplate(SmsProviderTemplateLinkRequest $request, string $code): JsonResponse
    {
        $action = $request->linkAction();
        $row = $action === SmsProviderTemplateLinkRequest::ACTION_LINK
            ? $this->smsProviderTemplates->link($request->mappingId(), $code)
            : $this->smsProviderTemplates->unlink($request->mappingId());

        return $this->success(
            $row,
            $action === SmsProviderTemplateLinkRequest::ACTION_LINK ? '已挂接业务码 '.$code : '已解绑'
        );
    }

    public function revealSecret(RevealSettingSecretRequest $request)
    {
        $group = $request->groupKey();
        $key = $request->secretKey();
        $result = $this->queryService->settingSecret($group, $key);

        // 密钥 reveal 统一审计：记录谁在何时查看了哪个敏感字段。
        $this->operationLogService->write(
            userId: (int) ($request->user()->id ?? 0),
            userType: 'admin',
            action: 'secret.reveal',
            module: 'secret',
            targetId: 0,
            detail: [
                'secret_type' => 'setting',
                'secret_key' => $key,
                'group' => $group,
                'operator_name' => (string) ($request->user()->username ?? ''),
                'trace_id' => (string) $request->header('X-Request-Id', ''),
            ],
            ipAddress: (string) $request->ip(),
        );

        return $this->success($result);
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $group = $request->group();
        $this->settings->saveGroupSettings($group, $request->settings());

        return $this->success(AdminActionResultResource::make([
            'id' => 'settings',
            'status' => 'completed',
            'message' => '配置已更新',
            'detail' => [
                'group' => $group,
            ],
        ])->resolve(), '配置已更新');
    }

    public function testNotificationTemplate(TestNotificationTemplateSendRequest $request): JsonResponse
    {
        $result = $this->templateTestSender->send(
            $request->channel(),
            $request->code(),
            $request->recipients()
        );

        return $this->success($result, '测试发送已完成');
    }
}
