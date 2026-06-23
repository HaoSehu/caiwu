<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\User\ListUsersRequest;
use App\Http\Requests\Admin\User\ManualInvoiceEntryRequest;
use App\Http\Requests\Admin\User\ManualProvisionUserServiceRequest;
use App\Http\Requests\Admin\User\RechargeUserRequest;
use App\Http\Requests\Admin\User\RefreshServiceStatusesRequest;
use App\Http\Requests\Admin\User\RefundUserInvoiceRequest;
use App\Http\Requests\Admin\User\RefundUserServiceRequest;
use App\Http\Requests\Admin\User\ServiceModuleStatusRequest;
use App\Http\Requests\Admin\User\ServicePowerRequest;
use App\Http\Requests\Admin\User\ServiceReinstallRequest;
use App\Http\Requests\Admin\User\ServiceResetPasswordRequest;
use App\Http\Requests\Admin\User\StoreUserRequest;
use App\Http\Requests\Admin\User\StoreUserServiceRequest;
use App\Http\Requests\Admin\User\UpdateUserRequest;
use App\Http\Requests\Admin\User\UpdateUserServiceMetaRequest;
use App\Http\Requests\Admin\User\UserBalanceLogsRequest;
use App\Http\Requests\Admin\User\UserInvoicesRequest;
use App\Http\Requests\Admin\User\UserLogPaginationRequest;
use App\Http\Requests\Admin\User\UserOperationLogsRequest;
use App\Http\Requests\Admin\User\UserServicesRequest;
use App\Http\Requests\Admin\User\UserTicketsRequest;
use App\Http\Resources\Admin\AdminUserListResource;
use App\Http\Resources\Admin\OperationLogResource;
use App\Http\Resources\Finance\FinanceLedgerResource;
use App\Http\Resources\User\UserResource;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Services\Finance\PaymentService;
use App\Services\User\UserService;
use App\Support\AdminPermissions;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private UserService $userService,
        private AuthService $authService,
    ) {}

    /**
     * 用户列表
     */
    public function index(ListUsersRequest $request)
    {
        $paginator = $this->userService->list($request->filters(), $request->perPage());

        return $this->paginate($paginator, AdminUserListResource::class);
    }

    /**
     * 用户详情
     */
    public function show(User $user)
    {
        $detail = $this->userService->detail($user);

        return $this->success([
            'user' => new UserResource($detail['user']),
            'stats' => $detail['stats'],
            'referral' => $detail['referral'] ?? null,
        ]);
    }

    /**
     * 创建用户
     */
    public function store(StoreUserRequest $request)
    {
        $user = $this->userService->create($request->payload());

        return $this->success(new UserResource($user), '创建成功');
    }

    /**
     * 更新用户
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        $user = $this->userService->update($user, $request->payload());

        return $this->success(new UserResource($user), '更新成功');
    }

    /**
     * 删除用户（软删除）
     */
    public function destroy(User $user)
    {
        $user->delete();

        return $this->success(null, '删除成功');
    }

    /**
     * 切换用户状态
     */
    public function toggleStatus(User $user)
    {
        $user = $this->userService->toggleStatus($user);

        return $this->success(new UserResource($user), '操作成功');
    }

    /**
     * 资金调整（增加或扣减余额）
     */
    public function recharge(RechargeUserRequest $request, User $user)
    {
        $payload = $request->payload();
        $amount = (float) $payload['amount'];

        app(PaymentService::class)->adjustBalance(
            $user,
            $amount,
            $payload['remark'] ?? '管理员手动调整',
            [
                'operator_name' => (string) ($request->user()?->username ?? $request->user()?->name ?? $request->user()?->email ?? 'admin'),
                'trace_id' => (string) $request->header('X-Request-Id', ''),
            ]
        );

        $label = $amount > 0 ? '增加' : '扣减';

        return $this->success(null, "余额{$label}成功");
    }

    /**
     * 用户服务列表
     */
    public function services(UserServicesRequest $request, User $user)
    {
        return $this->success(
            $this->userService->services($user, $request->filters(), $request->perPage())
        );
    }

    /**
     * 管理员手动新增服务
     */
    public function storeService(StoreUserServiceRequest $request, User $user)
    {
        return $this->success(
            $this->userService->createManualService($user, $request->payload(), [
                'operator_id' => (int) ($request->user()?->id ?? 0),
                'operator_name' => (string) ($request->user()?->name ?? $request->user()?->email ?? ''),
                'trace_id' => (string) ($request->header('X-Request-Id', '')),
                'ip_address' => (string) $request->ip(),
            ]),
            '服务创建成功'
        );
    }

    /**
     * 删除用户服务记录
     */
    public function destroyService(Request $request, User $user, int $serviceId)
    {
        $this->userService->deleteService($user, $serviceId, [
            'operator_id' => (int) ($request->user()?->id ?? 0),
            'operator_name' => (string) ($request->user()?->username ?? $request->user()?->name ?? $request->user()?->email ?? 'admin'),
            'trace_id' => (string) ($request->header('X-Request-Id', '')),
            'ip_address' => (string) $request->ip(),
        ]);

        return $this->success(null, '服务记录已删除');
    }

    /**
     * 用户服务详情
     */
    public function serviceDetail(Request $request, User $user, int $serviceId)
    {
        return $this->success($this->userService->serviceDetail(
            $user,
            $serviceId,
            $this->booleanQuery($request, 'refresh'),
            (bool) $request->user()?->hasPermission(AdminPermissions::USER_MANAGE)
        ));
    }

    public function serviceBaseDetail(Request $request, User $user, int $serviceId)
    {
        return $this->success(
            $this->userService->serviceBaseDetail(
                $user,
                $serviceId,
                (bool) $request->user()?->hasPermission(AdminPermissions::USER_MANAGE)
            )
        );
    }

    public function serviceRemoteStatus(Request $request, User $user, int $serviceId)
    {
        return $this->success(
            $this->userService->serviceRemoteStatusPatch(
                $user,
                $serviceId,
                (bool) $request->user()?->hasPermission(AdminPermissions::USER_MANAGE)
            )
        );
    }

    public function refreshServiceStatuses(RefreshServiceStatusesRequest $request, User $user)
    {
        $payload = $request->validated();

        return $this->success(
            $this->userService->refreshServiceStatuses($user, (array) ($payload['service_ids'] ?? [])),
            '当前页实例状态已刷新'
        );
    }

    /**
     * 更新用户服务业务信息
     */
    public function updateServiceMeta(UpdateUserServiceMetaRequest $request, User $user, int $serviceId)
    {
        return $this->success(
            $this->userService->updateServiceMeta($user, $serviceId, $request->payload(), [
                'operator_id' => (int) ($request->user()?->id ?? 0),
                'operator_name' => (string) ($request->user()?->name ?? $request->user()?->email ?? ''),
                'trace_id' => (string) ($request->header('X-Request-Id', '')),
            ]),
            '服务信息已更新'
        );
    }

    /**
     * 重新提交失败服务的上游开通
     */
    public function manualProvisionService(ManualProvisionUserServiceRequest $request, User $user, int $serviceId)
    {
        return $this->success(
            $this->userService->manualProvisionService($user, $serviceId, $request->payload(), [
                'operator_id' => (int) ($request->user()?->id ?? 0),
                'operator_name' => (string) ($request->user()?->username ?? $request->user()?->name ?? $request->user()?->email ?? 'admin'),
                'trace_id' => (string) ($request->header('X-Request-Id', '')),
                'ip_address' => (string) $request->ip(),
            ]),
            '已重新提交上游开通'
        );
    }

    /**
     * 用户服务电源操作
     */
    public function servicePower(ServicePowerRequest $request, User $user, int $serviceId)
    {
        $payload = $request->validated();

        return $this->success(
            $this->userService->servicePower(
                $user,
                $serviceId,
                (string) $payload['action'],
                [
                    'actor_type' => 'admin',
                    'actor_user_id' => (int) ($request->user()?->id ?? 0),
                    'actor_name' => (string) ($request->user()?->username ?? $request->user()?->name ?? $request->user()?->email ?? 'admin'),
                    'ip_address' => (string) $request->ip(),
                    'trace_id' => (string) $request->header('X-Request-Id', ''),
                ]
            ),
            '操作已提交'
        );
    }

    /**
     * 用户服务模块状态
     */
    public function serviceModuleStatus(ServiceModuleStatusRequest $request, User $user, int $serviceId)
    {
        $filters = $request->validated();

        return $this->success(
            $this->userService->serviceModuleStatus($user, $serviceId, (string) $filters['type'])
        );
    }

    /**
     * 用户服务重装系统选项
     */
    public function serviceReinstallOptions(Request $request, User $user, int $serviceId)
    {
        return $this->success(
            $this->userService->serviceReinstallOptions(
                $user,
                $serviceId,
                $this->booleanQuery($request, 'refresh')
            )
        );
    }

    /**
     * 重置用户服务密码
     */
    public function serviceResetPassword(ServiceResetPasswordRequest $request, User $user, int $serviceId)
    {
        $payload = $request->validated();

        return $this->success(
            $this->userService->serviceResetPassword(
                $user,
                $serviceId,
                $payload,
                [
                    'actor_type' => 'admin',
                    'actor_user_id' => (int) ($request->user()?->id ?? 0),
                    'actor_name' => (string) ($request->user()?->username ?? $request->user()?->name ?? $request->user()?->email ?? 'admin'),
                    'ip_address' => (string) $request->ip(),
                    'trace_id' => (string) $request->header('X-Request-Id', ''),
                ]
            ),
            '重置密码指令已提交'
        );
    }

    /**
     * 用户服务重装系统
     */
    public function serviceReinstall(ServiceReinstallRequest $request, User $user, int $serviceId)
    {
        $payload = $request->validated();

        return $this->success(
            $this->userService->serviceReinstall(
                $user,
                $serviceId,
                $payload,
                [
                    'actor_type' => 'admin',
                    'actor_user_id' => (int) ($request->user()?->id ?? 0),
                    'actor_name' => (string) ($request->user()?->username ?? $request->user()?->name ?? $request->user()?->email ?? 'admin'),
                    'ip_address' => (string) $request->ip(),
                    'trace_id' => (string) $request->header('X-Request-Id', ''),
                ]
            ),
            '重装系统任务已提交'
        );
    }

    /**
     * 以该客户身份登录（生成客户端 token）
     */
    public function loginAs(Request $request, User $user)
    {
        return $this->success(
            $this->authService->issueAdminLoginAsCode($user, [
                'admin_id' => (int) ($request->user()?->id ?? 0),
                'ip_address' => (string) $request->ip(),
                'user_agent' => (string) ($request->userAgent() ?? ''),
            ])
        );
    }

    /**
     * 用户账单列表
     */
    public function invoices(UserInvoicesRequest $request, User $user)
    {
        return $this->paginate($this->userService->invoices($user, $request->filters(), $request->perPage()));
    }

    public function invoiceDetail(User $user, int $invoice)
    {
        return $this->success($this->userService->invoiceDetail($user, $invoice));
    }

    public function manualInvoiceEntry(ManualInvoiceEntryRequest $request, User $user, int $invoice)
    {
        return $this->success(
            $this->userService->manualInvoiceEntry($user, $invoice, $request->payload(), [
                'operator_id' => (int) ($request->user()?->id ?? 0),
                'operator_name' => (string) ($request->user()?->username ?? $request->user()?->name ?? $request->user()?->email ?? 'admin'),
                'trace_id' => (string) ($request->header('X-Request-Id', '')),
                'ip_address' => (string) $request->ip(),
            ]),
            '手动入账成功'
        );
    }

    public function sendInvoiceEmail(Request $request, User $user, int $invoice)
    {
        return $this->success(
            $this->userService->sendInvoiceEmail($user, $invoice, [
                'operator_id' => (int) ($request->user()?->id ?? 0),
                'operator_name' => (string) ($request->user()?->username ?? $request->user()?->name ?? $request->user()?->email ?? 'admin'),
                'trace_id' => (string) ($request->header('X-Request-Id', '')),
                'ip_address' => (string) $request->ip(),
            ]),
            '账单邮件已发送'
        );
    }

    public function refundInvoice(RefundUserInvoiceRequest $request, User $user, int $invoice)
    {
        return $this->success(
            $this->userService->refundInvoice($user, $invoice, $request->payload(), [
                'operator_id' => (int) ($request->user()?->id ?? 0),
                'operator_name' => (string) ($request->user()?->username ?? $request->user()?->name ?? $request->user()?->email ?? 'admin'),
                'trace_id' => (string) ($request->header('X-Request-Id', '')),
                'ip_address' => (string) $request->ip(),
            ]),
            '账单已完成退款'
        );
    }

    public function refundService(RefundUserServiceRequest $request, User $user, int $serviceId)
    {
        return $this->success(
            $this->userService->refundService($user, $serviceId, $request->payload(), [
                'operator_id' => (int) ($request->user()?->id ?? 0),
                'operator_name' => (string) ($request->user()?->username ?? $request->user()?->name ?? $request->user()?->email ?? 'admin'),
                'trace_id' => (string) ($request->header('X-Request-Id', '')),
                'ip_address' => (string) $request->ip(),
            ]),
            '服务已完成退款'
        );
    }

    /**
     * 用户余额变动记录
     */
    public function balanceLogs(UserBalanceLogsRequest $request, User $user)
    {
        $result = $this->userService->balanceLogs($user, $request->filters(), $request->perPage());

        return $this->paginate($result['paginator'], $result['resource_class'] ?? FinanceLedgerResource::class, [
            'summary' => $result['summary'],
        ]);
    }

    /**
     * 用户工单列表
     */
    public function tickets(UserTicketsRequest $request, User $user)
    {
        $result = $this->userService->tickets($user, $request->filters(), $request->perPage());

        return $this->paginate($result['paginator'], extra: [
            'summary' => $result['summary'],
        ]);
    }

    /**
     * 用户操作日志
     */
    public function operationLogs(UserOperationLogsRequest $request, User $user)
    {
        return $this->paginate(
            $this->userService->operationLogs($user->id, $request->filters(), $request->perPage()),
            OperationLogResource::class
        );
    }

    /**
     * 用户短信日志
     */
    public function smsLogs(UserLogPaginationRequest $request, User $user)
    {
        return $this->paginate($this->userService->smsLogs($user, $request->perPage()));
    }

    /**
     * 用户邮件日志
     */
    public function emailLogs(UserLogPaginationRequest $request, User $user)
    {
        return $this->paginate($this->userService->emailLogs($user, $request->perPage()));
    }

    /**
     * 操作系统选项列表（用于手动开通服务时选择系统）
     */
    public function osOptions()
    {
        return $this->success([
            'groups' => [
                [
                    'label' => 'CentOS',
                    'children' => [
                        ['label' => 'CentOS 7', 'value' => 'CentOS 7'],
                        ['label' => 'CentOS 8', 'value' => 'CentOS 8'],
                        ['label' => 'CentOS 9', 'value' => 'CentOS 9'],
                    ],
                ],
                [
                    'label' => 'Ubuntu',
                    'children' => [
                        ['label' => 'Ubuntu 20.04', 'value' => 'Ubuntu 20.04'],
                        ['label' => 'Ubuntu 22.04', 'value' => 'Ubuntu 22.04'],
                        ['label' => 'Ubuntu 24.04', 'value' => 'Ubuntu 24.04'],
                    ],
                ],
                [
                    'label' => 'Debian',
                    'children' => [
                        ['label' => 'Debian 11', 'value' => 'Debian 11'],
                        ['label' => 'Debian 12', 'value' => 'Debian 12'],
                    ],
                ],
                [
                    'label' => 'Rocky Linux',
                    'children' => [
                        ['label' => 'Rocky Linux 8', 'value' => 'Rocky Linux 8'],
                        ['label' => 'Rocky Linux 9', 'value' => 'Rocky Linux 9'],
                    ],
                ],
                [
                    'label' => 'AlmaLinux',
                    'children' => [
                        ['label' => 'AlmaLinux 8', 'value' => 'AlmaLinux 8'],
                        ['label' => 'AlmaLinux 9', 'value' => 'AlmaLinux 9'],
                    ],
                ],
                [
                    'label' => 'Windows Server',
                    'children' => [
                        ['label' => 'Windows Server 2019', 'value' => 'Windows Server 2019'],
                        ['label' => 'Windows Server 2022', 'value' => 'Windows Server 2022'],
                        ['label' => 'Windows Server 2025', 'value' => 'Windows Server 2025'],
                    ],
                ],
            ],
        ]);
    }
}
