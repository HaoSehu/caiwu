<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Constants\ServiceStatus;
use PHPUnit\Framework\TestCase;

/**
 * 服务状态五态映射回归：
 * 上游魔方/WHMCS 系 domainstatus → 本地五态（0开通中/1运行中/2已暂停/3已删除/4异常）。
 * 实锤口径：zjmf 实际写入 Deleted（注释里的 Terminated 无写入点），
 * cancelled/deleted/terminated 都归已删除；空串保守归开通中；fraud 及未知非空值归异常。
 */
class ServiceStatusMappingTest extends TestCase
{
    public function test_maps_active_suspended_and_terminated_family(): void
    {
        $this->assertSame(ServiceStatus::ACTIVE, ServiceStatus::fromUpstreamDomainStatus('Active'));
        $this->assertSame(ServiceStatus::SUSPENDED, ServiceStatus::fromUpstreamDomainStatus('Suspended'));
        $this->assertSame(ServiceStatus::TERMINATED, ServiceStatus::fromUpstreamDomainStatus('Deleted'));
        $this->assertSame(ServiceStatus::TERMINATED, ServiceStatus::fromUpstreamDomainStatus('Cancelled'));
        $this->assertSame(ServiceStatus::TERMINATED, ServiceStatus::fromUpstreamDomainStatus('Terminated'));
    }

    public function test_maps_pending_and_blank_to_pending(): void
    {
        $this->assertSame(ServiceStatus::PENDING, ServiceStatus::fromUpstreamDomainStatus('Pending'));
        $this->assertSame(ServiceStatus::PENDING, ServiceStatus::fromUpstreamDomainStatus(''));
        $this->assertSame(ServiceStatus::PENDING, ServiceStatus::fromUpstreamDomainStatus('  '));
        $this->assertSame(ServiceStatus::PENDING, ServiceStatus::fromUpstreamDomainStatus(null));
    }

    public function test_maps_fraud_and_unknown_to_abnormal(): void
    {
        $this->assertSame(ServiceStatus::ABNORMAL, ServiceStatus::fromUpstreamDomainStatus('Fraud'));
        $this->assertSame(ServiceStatus::ABNORMAL, ServiceStatus::fromUpstreamDomainStatus('WeirdState'));
        $this->assertSame(ServiceStatus::ABNORMAL, ServiceStatus::fromUpstreamDomainStatus('  completed '));
    }

    public function test_labels_match_five_states(): void
    {
        $this->assertSame([
            ServiceStatus::PENDING => '开通中',
            ServiceStatus::ACTIVE => '运行中',
            ServiceStatus::SUSPENDED => '已暂停',
            ServiceStatus::TERMINATED => '已删除',
            ServiceStatus::ABNORMAL => '异常',
        ], ServiceStatus::$labels);
    }
}
