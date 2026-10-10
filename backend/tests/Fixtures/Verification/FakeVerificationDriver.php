<?php

declare(strict_types=1);

namespace Tests\Fixtures\Verification;

use App\Services\Verification\Contracts\VerificationDriver;
use App\Services\Verification\Data\VerificationInitializeRequest;
use App\Services\Verification\Data\VerificationInitializeResult;
use App\Services\Verification\Data\VerificationScanUrlResult;
use App\Services\Verification\Data\VerificationStatusResult;

/**
 * 实名认证 fake 驱动（V7-11：把逐用例匿名替身收敛为可复用夹具）。
 * 默认键取系统默认驱动键 stay33，注册进 VerificationDriverManager 后即可离线跑通全链路。
 */
final class FakeVerificationDriver implements VerificationDriver
{
    /** @var list<VerificationInitializeRequest> */
    public array $initialized = [];

    public int $scanUrlCalls = 0;

    public int $statusCalls = 0;

    public function __construct(
        private readonly string $driverKey = 'stay33',
        public int $initializeStatus = 200,
        public string $initializeMessage = '初始化成功',
        public string $certifyId = 'cert-fake-001',
        public int $scanStatus = 200,
        public string $scanMessage = '获取成功',
        public string $scanUrl = 'https://verify.example.test/session/abc',
        public int $queryStatusValue = 1,
        public string $queryMessage = '审核通过',
    ) {}

    public function key(): string
    {
        return $this->driverKey;
    }

    public function label(): string
    {
        return '假实名通道';
    }

    public function initialize(VerificationInitializeRequest $request): VerificationInitializeResult
    {
        $this->initialized[] = $request;

        return new VerificationInitializeResult(
            $this->initializeStatus,
            $this->initializeMessage,
            $this->initializeStatus === 200 ? $this->certifyId : '',
        );
    }

    public function generateScanUrl(string $certifyId): VerificationScanUrlResult
    {
        $this->scanUrlCalls++;

        return new VerificationScanUrlResult(
            $this->scanStatus,
            $this->scanMessage,
            $this->scanStatus === 200 ? $this->scanUrl : '',
        );
    }

    public function queryStatus(string $certifyId): VerificationStatusResult
    {
        $this->statusCalls++;

        return new VerificationStatusResult($this->queryStatusValue, $this->queryMessage);
    }
}
