<?php

namespace App\Http\Controllers\Client;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Client\Service\ListServiceOperationLogsRequest;
use App\Services\ClientServiceConsole\ClientServiceConsoleService;
use App\Services\Provisioning\ServiceRenewService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    public function __construct(
        private ClientServiceConsoleService $clientServiceConsoleService,
        private ServiceRenewService $serviceRenewService,
    ) {}

    public function index(Request $request)
    {
        $filters = $request->validate([
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'integer'],
            'status_scope' => ['nullable', 'string', Rule::in(['active_pending'])],
            'quick_filter' => ['nullable', 'string', Rule::in(['expiring_7d', 'auto_renew_enabled', 'auto_renew_7d'])],
            'catalog_type' => ['nullable', 'string', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
            'page_size' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return $this->success(
            $this->clientServiceConsoleService->paginateForUser($request->user(), $filters)
        );
    }

    public function groupedOverview(Request $request)
    {
        return $this->success(
            $this->clientServiceConsoleService->groupedOverviewForUser($request->user())
        );
    }

    public function config(Request $request, int $id)
    {
        return $this->success(
            $this->clientServiceConsoleService->getServiceConfigForUser(
                $request->user(),
                $id
            )
        );
    }

    public function show(Request $request, int $id)
    {
        return $this->success(
            $this->clientServiceConsoleService->getDetailForUser(
                $request->user(),
                $id,
                $this->booleanQuery($request, 'refresh')
            )
        );
    }

    public function baseDetail(Request $request, int $id)
    {
        return $this->success(
            $this->clientServiceConsoleService->getBaseDetailForUser(
                $request->user(),
                $id
            )
        );
    }

    public function remoteStatus(Request $request, int $id)
    {
        return $this->success(
            $this->clientServiceConsoleService->getRemoteStatusPatchForUser(
                $request->user(),
                $id
            )
        );
    }

    public function renewPreview(Request $request, int $id)
    {
        $data = $request->validate([
            'billing_cycle' => ['nullable', 'string', 'max:30'],
            'user_coupon_id' => ['nullable', 'integer', 'min:1'],
        ]);

        return $this->success(
            $this->serviceRenewService->previewForUser(
                $request->user(),
                $id,
                $data['billing_cycle'] ?? null,
                (int) ($data['user_coupon_id'] ?? 0)
            )
        );
    }

    public function updateRemark(Request $request, int $id)
    {
        $data = $request->validate([
            'remark' => ['nullable', 'string', 'max:120'],
        ]);

        return $this->success(
            $this->clientServiceConsoleService->updateRemarkForUser(
                $request->user(),
                $id,
                $data['remark'] ?? null,
                $this->buildOperationContext($request)
            ),
            '备注已更新'
        );
    }

    public function updateName(Request $request, int $id)
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
        ]);

        return $this->success(
            $this->clientServiceConsoleService->updateServiceNameForUser(
                $request->user(),
                $id,
                $data['name'] ?? null,
                $this->buildOperationContext($request)
            ),
            '实例名称已更新'
        );
    }

    public function trafficPackages(Request $request, int $id)
    {
        return $this->success(
            $this->clientServiceConsoleService->getTrafficPackagePreviewForUser(
                $request->user(),
                $id
            )
        );
    }

    public function quoteTrafficPackage(Request $request, int $id)
    {
        $data = $request->validate([
            'target_value' => ['required', 'integer', 'min:1'],
        ]);

        return $this->success(
            $this->clientServiceConsoleService->quoteTrafficPackageForUser(
                $request->user(),
                $id,
                $data
            )
        );
    }

    public function createRenewOrder(Request $request, int $id)
    {
        $data = $request->validate([
            'billing_cycle' => ['required', 'string', 'max:30'],
            'user_coupon_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $invoice = $this->executeLockedServiceAction(
            $request,
            $id,
            'renew_order',
            fn () => $this->serviceRenewService->createRenewInvoiceForUser(
                $request->user(),
                $id,
                (string) $data['billing_cycle'],
                (int) ($data['user_coupon_id'] ?? 0),
                $this->buildOperationContext($request)
            )
        );
        $invoice->loadMissing(['product:id,product_type,product_group_id,config_options,purchase_requires', 'service']);

        return $this->success([
            'id' => (int) $invoice->id,
            'invoice_no' => (string) $invoice->invoice_no,
            'service_id' => (int) ($invoice->service_id ?? 0),
        ], '续费账单创建成功');
    }

    public function createTrafficPackageOrder(Request $request, int $id)
    {
        $data = $request->validate([
            'target_value' => ['required', 'integer', 'min:1'],
        ]);

        $invoice = $this->executeLockedServiceAction(
            $request,
            $id,
            'traffic_package_order',
            fn () => $this->clientServiceConsoleService->createTrafficPackageInvoiceForUser(
                $request->user(),
                $id,
                $data,
                $this->buildOperationContext($request)
            )
        );
        $invoice->loadMissing(['product:id,product_type,product_group_id,config_options,purchase_requires', 'service']);

        return $this->success([
            'id' => (int) $invoice->id,
            'invoice_no' => (string) $invoice->invoice_no,
            'service_id' => (int) ($invoice->service_id ?? 0),
        ], '流量包账单创建成功');
    }

    public function updateAutoRenew(Request $request, int $id)
    {
        $data = $request->validate([
            'auto_renew' => ['required', 'in:0,1'],
        ]);

        return $this->success(
            $this->executeLockedServiceAction(
                $request,
                $id,
                'renew_auto',
                fn () => $this->serviceRenewService->updateAutoRenewForUser(
                    $request->user(),
                    $id,
                    (int) $data['auto_renew'],
                    $this->buildOperationContext($request)
                )
            ),
            '自动续费状态已更新'
        );
    }

    public function power(Request $request, int $id)
    {
        $data = $request->validate([
            'action' => ['required', 'string', 'in:on,off,reboot,hard_off,hard_reboot'],
        ]);

        return $this->success(
            $this->executeLockedServiceAction(
                $request,
                $id,
                'power_'.(string) $data['action'],
                fn () => $this->clientServiceConsoleService->powerActionForUser(
                    $request->user(),
                    $id,
                    $data['action'],
                    $this->buildOperationContext($request)
                )
            ),
            '操作已提交'
        );
    }

    public function moduleStatus(Request $request, int $id)
    {
        $filters = $request->validate([
            'type' => ['required', 'string', 'in:host,reinstall,repassword'],
        ]);

        return $this->success(
            $this->clientServiceConsoleService->getModuleStatusForUser($request->user(), $id, $filters['type'])
        );
    }

    public function reinstallOptions(Request $request, int $id)
    {
        return $this->success(
            $this->clientServiceConsoleService->getReinstallOptionsForUser(
                $request->user(),
                $id,
                $this->booleanQuery($request, 'refresh')
            )
        );
    }

    public function resetPassword(Request $request, int $id)
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:8', 'max:100', 'confirmed'],
        ]);

        return $this->success(
            $this->executeLockedServiceAction(
                $request,
                $id,
                'password_reset',
                fn () => $this->clientServiceConsoleService->resetPasswordForUser(
                    $request->user(),
                    $id,
                    $data,
                    $this->buildOperationContext($request)
                )
            ),
            '重置密码指令已提交'
        );
    }

    public function reinstall(Request $request, int $id)
    {
        $data = $request->validate([
            'os_id' => ['required', 'string', 'max:50'],
        ]);

        return $this->success(
            $this->executeLockedServiceAction(
                $request,
                $id,
                'reinstall',
                fn () => $this->clientServiceConsoleService->reinstallForUser(
                    $request->user(),
                    $id,
                    $data,
                    $this->buildOperationContext($request)
                )
            ),
            '重装系统任务已提交'
        );
    }

    public function natForwardings(Request $request, int $id)
    {
        return $this->success(
            $this->clientServiceConsoleService->getNatForwardingsForUser($request->user(), $id)
        );
    }

    public function createNatForwarding(Request $request, int $id)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'ext_port' => ['nullable', 'integer', 'between:1,65535'],
            'int_port' => ['required', 'integer', 'between:1,65535'],
            'protocol' => ['required', 'string', 'in:1,2,3'],
        ]);

        return $this->success(
            $this->executeLockedServiceAction(
                $request,
                $id,
                'nat_create',
                fn () => $this->clientServiceConsoleService->createNatForwardingForUser(
                    $request->user(),
                    $id,
                    $data,
                    $this->buildOperationContext($request)
                )
            ),
            '端口转发创建成功'
        );
    }

    public function deleteNatForwarding(Request $request, int $id, int $forwardingId)
    {
        return $this->success(
            $this->executeLockedServiceAction(
                $request,
                $id,
                'nat_delete_'.$forwardingId,
                fn () => $this->clientServiceConsoleService->deleteNatForwardingForUser(
                    $request->user(),
                    $id,
                    $forwardingId,
                    $this->buildOperationContext($request)
                )
            ),
            '端口转发删除成功'
        );
    }

    public function operationLogs(ListServiceOperationLogsRequest $request, int $id)
    {
        return $this->success(
            $this->clientServiceConsoleService->getOperationLogsForUser(
                $request->user(),
                $id,
                $request->filters(),
                $request->perPage(10, 50)
            )
        );
    }

    public function monitor(Request $request, int $id)
    {
        $filters = $request->validate([
            'type' => ['nullable', 'string', 'max:100'],
            'range' => ['nullable', 'string', 'in:3h,24h,7d,30d'],
            'start' => ['nullable', 'integer', 'min:0'],
            'end' => ['nullable', 'integer', 'min:0'],
        ]);

        if ($request->has('fresh')) {
            $filters['fresh'] = $this->booleanQuery($request, 'fresh');
        }

        return $this->success(
            $this->clientServiceConsoleService->getMonitorForUser($request->user(), $id, $filters)
        );
    }

    public function monitorBatch(Request $request, int $id)
    {
        $filters = $request->validate([
            'types' => ['nullable', 'array', 'max:20'],
            'types.*' => ['nullable', 'string', 'max:100'],
            'range' => ['nullable', 'string', 'in:3h,24h,7d,30d'],
            'start' => ['nullable', 'integer', 'min:0'],
            'end' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        if ($request->has('fresh')) {
            $filters['fresh'] = $this->booleanQuery($request, 'fresh');
        }

        return $this->success(
            $this->clientServiceConsoleService->getMonitorBatchForUser($request->user(), $id, $filters)
        );
    }

    public function securityGroups(Request $request, int $id)
    {
        return $this->success(
            $this->clientServiceConsoleService->getSecurityGroupsForUser(
                $request->user(),
                $id,
                $this->booleanQuery($request, 'fresh')
            )
        );
    }

    public function securityGroupRules(Request $request, int $id, int $groupId)
    {
        return $this->success(
            $this->clientServiceConsoleService->getSecurityGroupRulesForUser($request->user(), $id, $groupId)
        );
    }

    public function createSecurityGroup(Request $request, int $id)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->success(
            $this->executeLockedServiceAction(
                $request,
                $id,
                'security_group_create',
                fn () => $this->clientServiceConsoleService->createSecurityGroupForUser(
                    $request->user(),
                    $id,
                    $data,
                    $this->buildOperationContext($request)
                )
            ),
            '创建成功'
        );
    }

    public function applySecurityGroup(Request $request, int $id, int $groupId)
    {
        return $this->success(
            $this->executeLockedServiceAction(
                $request,
                $id,
                'security_group_apply_'.$groupId,
                fn () => $this->clientServiceConsoleService->applySecurityGroupForUser(
                    $request->user(),
                    $id,
                    $groupId,
                    $this->buildOperationContext($request)
                )
            ),
            '应用成功'
        );
    }

    public function deleteSecurityGroup(Request $request, int $id, int $groupId)
    {
        return $this->success(
            $this->executeLockedServiceAction(
                $request,
                $id,
                'security_group_delete_'.$groupId,
                fn () => $this->clientServiceConsoleService->deleteSecurityGroupForUser(
                    $request->user(),
                    $id,
                    $groupId,
                    $this->buildOperationContext($request)
                )
            ),
            '删除成功'
        );
    }

    public function createSecurityRule(Request $request, int $id, int $groupId)
    {
        $data = $request->validate([
            'direction' => ['required', 'string', 'max:20'],
            'protocol' => ['required', 'string', 'max:50'],
            'port' => ['required', 'string', 'max:100'],
            'ip' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->success(
            $this->executeLockedServiceAction(
                $request,
                $id,
                'security_rule_create_'.$groupId,
                fn () => $this->clientServiceConsoleService->createSecurityRuleForUser(
                    $request->user(),
                    $id,
                    $groupId,
                    $data,
                    $this->buildOperationContext($request)
                )
            ),
            '创建成功'
        );
    }

    public function deleteSecurityRule(Request $request, int $id, int $groupId, int $ruleId)
    {
        return $this->success(
            $this->executeLockedServiceAction(
                $request,
                $id,
                'security_rule_delete_'.$groupId.'_'.$ruleId,
                fn () => $this->clientServiceConsoleService->deleteSecurityRuleForUser(
                    $request->user(),
                    $id,
                    $groupId,
                    $ruleId,
                    $this->buildOperationContext($request)
                )
            ),
            '删除成功'
        );
    }

    public function vnc(Request $request, int $id)
    {
        return $this->success(
            $this->clientServiceConsoleService->getVncUrlForUser(
                $request->user(),
                $id,
                $this->buildOperationContext($request)
            ),
            '获取VNC链接成功'
        );
    }

    public function vncToken(Request $request, string $token)
    {
        return $this->success(
            $this->clientServiceConsoleService->resolvePublicVncTokenPayload($token)
        );
    }

    private function buildOperationContext(Request $request, string $actorType = 'client'): array
    {
        $user = $request->user();

        return [
            'actor_type' => $actorType,
            'actor_user_id' => (int) ($user?->id ?? 0),
            'actor_name' => (string) ($user?->display_name ?? $user?->nickname ?? $user?->email ?? ''),
            'ip_address' => (string) $request->ip(),
            'trace_id' => (string) $request->header('X-Request-Id', ''),
            'request_origin' => $this->resolveRequestOrigin($request),
        ];
    }

    private function resolveRequestOrigin(Request $request): string
    {
        $origin = trim((string) $request->headers->get('Origin', ''));
        if ($origin !== '') {
            return rtrim($origin, '/');
        }

        $referer = trim((string) $request->headers->get('Referer', ''));
        if ($referer !== '') {
            $parts = parse_url($referer);
            if (is_array($parts)) {
                $scheme = strtolower((string) ($parts['scheme'] ?? ''));
                $host = (string) ($parts['host'] ?? '');
                $port = (int) ($parts['port'] ?? 0);

                if ($scheme !== '' && $host !== '') {
                    $defaultPort = $scheme === 'https' ? 443 : 80;

                    if ($port > 0 && $port !== $defaultPort) {
                        return sprintf('%s://%s:%d', $scheme, $host, $port);
                    }

                    return sprintf('%s://%s', $scheme, $host);
                }
            }
        }

        return rtrim($request->getSchemeAndHttpHost(), '/');
    }

    private function executeLockedServiceAction(Request $request, int $serviceId, string $action, callable $callback): mixed
    {
        $userId = (int) ($request->user()?->id ?? 0);
        $lockKey = sprintf('lock:client:service:%d:%d:%s', $userId, $serviceId, sha1($action));

        try {
            return Cache::lock($lockKey, 20)->block(3, $callback);
        } catch (LockTimeoutException) {
            throw new BusinessException('操作处理中，请勿重复提交', 40900, 409);
        }
    }
}
