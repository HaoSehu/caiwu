<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Log\CleanupLogRequest;
use App\Http\Requests\Admin\Log\EmailLogListRequest;
use App\Http\Requests\Admin\Log\GeneralLogListRequest;
use App\Http\Requests\Admin\Log\SmsLogListRequest;
use App\Services\System\AdminLogService;
use App\Services\System\ScheduleRunLogService;

class LogController extends Controller
{
    public function __construct(
        private AdminLogService $adminLogService,
    ) {}

    public function smsLogs(SmsLogListRequest $request)
    {
        return $this->success(
            $this->adminLogService->getSmsLogs($request->filters(), $request->perPage())
        );
    }

    public function smsLogsSummary(SmsLogListRequest $request)
    {
        return $this->success(
            $this->adminLogService->getSmsLogsSummary($request->filters())
        );
    }

    public function emailLogs(EmailLogListRequest $request)
    {
        return $this->success(
            $this->adminLogService->getEmailLogs($request->filters(), $request->perPage())
        );
    }

    public function emailLogsSummary(EmailLogListRequest $request)
    {
        return $this->success(
            $this->adminLogService->getEmailLogsSummary($request->filters())
        );
    }

    public function apiLogs(GeneralLogListRequest $request)
    {
        return $this->success(
            $this->adminLogService->getApiLogs($request->filters(), $request->pageNumber(), $request->perPage(20, 100))
        );
    }

    public function taskLogs(GeneralLogListRequest $request)
    {
        return $this->success(
            $this->adminLogService->getTaskLogs($request->filters(), $request->pageNumber(), $request->perPage(20, 100))
        );
    }

    public function taskLogsSummary(GeneralLogListRequest $request)
    {
        return $this->success(
            $this->adminLogService->getTaskLogsSummary($request->filters())
        );
    }

    public function systemLogs(GeneralLogListRequest $request)
    {
        return $this->success(
            $this->adminLogService->getSystemLogs($request->filters(), $request->pageNumber(), $request->perPage(20, 100))
        );
    }

    public function systemLogsSummary(GeneralLogListRequest $request)
    {
        return $this->success(
            $this->adminLogService->getSystemLogsSummary($request->filters())
        );
    }

    public function runtimeLogs(GeneralLogListRequest $request)
    {
        return $this->success(
            $this->adminLogService->getRuntimeLogs($request->filters(), $request->pageNumber(), $request->perPage(20, 100))
        );
    }

    public function runtimeLogsSummary(GeneralLogListRequest $request)
    {
        return $this->success(
            $this->adminLogService->getRuntimeLogsSummary($request->filters())
        );
    }

    public function adminLoginLogs(GeneralLogListRequest $request)
    {
        return $this->success(
            $this->adminLogService->getAdminLoginLogs($request->filters(), $request->pageNumber(), $request->perPage(20, 100))
        );
    }

    public function gatewayLogs(GeneralLogListRequest $request)
    {
        return $this->success(
            $this->adminLogService->getGatewayLogs($request->filters(), $request->pageNumber(), $request->perPage(20, 100))
        );
    }

    public function activityLogs(GeneralLogListRequest $request)
    {
        return $this->success(
            $this->adminLogService->getActivityLogs($request->filters(), $request->pageNumber(), $request->perPage(20, 100))
        );
    }

    public function scheduleLogs(GeneralLogListRequest $request)
    {
        return $this->success(
            app(ScheduleRunLogService::class)->getScheduleStatus($request->pageNumber(), $request->perPage(20, 100), $request->filters())
        );
    }

    public function scheduleHealth()
    {
        return $this->success(
            app(ScheduleRunLogService::class)->getHealthOverview()
        );
    }

    public function cleanupOverview()
    {
        return $this->success(
            $this->adminLogService->getCleanupOverview()
        );
    }

    public function cleanup(CleanupLogRequest $request)
    {
        return $this->success(
            $this->adminLogService->cleanup($request->payload()),
            '清理完成'
        );
    }
}
