<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Services\Auth\LoginRiskControlService;
use App\Services\Referral\ReferralService;
use App\Services\System\NotificationService;
use App\Services\System\OperationLogService;
use App\Services\User\AdminRoleBridgeService;
use Tests\TestCase;

class AdminLoginAsRedirectUrlTest extends TestCase
{
    public function test_issue_admin_login_as_code_uses_frontend_url_for_redirect(): void
    {
        config([
            'app.frontend_url' => 'https://www.sw7111.top',
            'app.admin_url' => 'https://admin.sw7111.top',
        ]);

        $operationLogService = $this->createMock(OperationLogService::class);
        $operationLogService->expects($this->once())->method('write');

        $result = $this->makeAuthService($operationLogService)
            ->issueAdminLoginAsCode($this->makeClientUser());

        $this->assertSame('https://www.sw7111.top/client/dashboard', $result['redirect_url']);
        $this->assertNotEmpty($result['login_code']);
    }

    public function test_issue_admin_login_as_code_rejects_missing_frontend_url(): void
    {
        config([
            'app.frontend_url' => '',
            'app.admin_url' => 'https://admin.sw7111.top',
        ]);

        $operationLogService = $this->createMock(OperationLogService::class);
        $operationLogService->expects($this->never())->method('write');

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('FRONTEND_URL');

        $this->makeAuthService($operationLogService)
            ->issueAdminLoginAsCode($this->makeClientUser());
    }

    public function test_issue_admin_login_as_code_rejects_admin_url_as_frontend_url(): void
    {
        config([
            'app.frontend_url' => 'https://admin.sw7111.top',
            'app.admin_url' => 'https://admin.sw7111.top',
        ]);

        $operationLogService = $this->createMock(OperationLogService::class);
        $operationLogService->expects($this->never())->method('write');

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('FRONTEND_URL');

        $this->makeAuthService($operationLogService)
            ->issueAdminLoginAsCode($this->makeClientUser());
    }

    private function makeClientUser(): User
    {
        $user = new User([
            'email' => 'client@example.com',
            'nickname' => 'Client',
            'status' => 1,
        ]);
        $user->id = 123;
        $user->exists = true;

        return $user;
    }

    private function makeAuthService(OperationLogService $operationLogService): AuthService
    {
        return new AuthService(
            $this->createMock(NotificationService::class),
            $this->createMock(ReferralService::class),
            $operationLogService,
            $this->createMock(AdminRoleBridgeService::class),
            $this->createMock(LoginRiskControlService::class),
        );
    }
}
