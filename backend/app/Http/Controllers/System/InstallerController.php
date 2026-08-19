<?php

declare(strict_types=1);

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Http\Requests\System\InstallerDatabaseVerifyRequest;
use App\Http\Requests\System\InstallerInstallRequest;
use App\Services\Installer\DatabaseSetupService;
use App\Services\Installer\EnvironmentCheckService;
use App\Services\Installer\InstallerStateService;
use App\Services\Installer\InstallRunnerService;
use App\Support\ApiResponseBuilder;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class InstallerController extends Controller
{
    public function status(InstallerStateService $state): JsonResponse
    {
        return ApiResponseBuilder::success(['installed' => $state->isInstalled()]);
    }

    public function environment(EnvironmentCheckService $environment): JsonResponse
    {
        return ApiResponseBuilder::success(['items' => $environment->check(), 'passed' => $environment->passed()]);
    }

    public function verifyDatabase(InstallerDatabaseVerifyRequest $request, DatabaseSetupService $database): JsonResponse
    {
        try {
            return ApiResponseBuilder::success($database->verify($request->validated()));
        } catch (RuntimeException $exception) {
            return ApiResponseBuilder::error(42200, $exception->getMessage());
        }
    }

    public function install(InstallerInstallRequest $request, InstallRunnerService $runner): JsonResponse
    {
        try {
            return ApiResponseBuilder::success($runner->run($request->validated()), '安装完成');
        } catch (RuntimeException $exception) {
            return ApiResponseBuilder::error(50000, $exception->getMessage());
        }
    }
}
