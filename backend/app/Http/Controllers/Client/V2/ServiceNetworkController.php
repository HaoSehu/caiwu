<?php

declare(strict_types=1);

namespace App\Http\Controllers\Client\V2;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\V2\Service\CreateNatForwardingRequest;
use App\Http\Requests\Client\V2\Service\CreateSecurityGroupRequest;
use App\Http\Requests\Client\V2\Service\CreateSecurityRuleRequest;
use App\Services\ClientServiceConsole\ClientServiceConsoleService;
use App\Services\ClientServiceConsole\ServiceActionLock;
use App\Support\RequestContext;
use Illuminate\Http\Request;

class ServiceNetworkController extends Controller
{
    public function __construct(
        private ClientServiceConsoleService $clientServiceConsoleService,
        private ServiceActionLock $serviceActionLock,
    ) {}

    public function natForwardings(Request $request, int $id)
    {
        return $this->success(
            $this->clientServiceConsoleService->getNatForwardingsForUser($request->user(), $id)
        );
    }

    public function createNatForwarding(CreateNatForwardingRequest $request, int $id)
    {
        $data = $request->validated();

        return $this->success(
            $this->lockedAction($request, $id, 'nat_create', fn () => $this->clientServiceConsoleService->createNatForwardingForUser(
                $request->user(),
                $id,
                $data,
                RequestContext::forClient($request)
            )),
            '端口转发创建成功'
        );
    }

    public function deleteNatForwarding(Request $request, int $id, int $forwardingId)
    {
        return $this->success(
            $this->lockedAction($request, $id, 'nat_delete_'.$forwardingId, fn () => $this->clientServiceConsoleService->deleteNatForwardingForUser(
                $request->user(),
                $id,
                $forwardingId,
                RequestContext::forClient($request)
            )),
            '端口转发删除成功'
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

    public function createSecurityGroup(CreateSecurityGroupRequest $request, int $id)
    {
        $data = $request->validated();

        return $this->success(
            $this->lockedAction($request, $id, 'security_group_create', fn () => $this->clientServiceConsoleService->createSecurityGroupForUser(
                $request->user(),
                $id,
                $data,
                RequestContext::forClient($request)
            )),
            '创建成功'
        );
    }

    public function applySecurityGroup(Request $request, int $id, int $groupId)
    {
        return $this->success(
            $this->lockedAction($request, $id, 'security_group_apply_'.$groupId, fn () => $this->clientServiceConsoleService->applySecurityGroupForUser(
                $request->user(),
                $id,
                $groupId,
                RequestContext::forClient($request)
            )),
            '应用成功'
        );
    }

    public function deleteSecurityGroup(Request $request, int $id, int $groupId)
    {
        return $this->success(
            $this->lockedAction($request, $id, 'security_group_delete_'.$groupId, fn () => $this->clientServiceConsoleService->deleteSecurityGroupForUser(
                $request->user(),
                $id,
                $groupId,
                RequestContext::forClient($request)
            )),
            '删除成功'
        );
    }

    public function createSecurityRule(CreateSecurityRuleRequest $request, int $id, int $groupId)
    {
        $data = $request->validated();

        return $this->success(
            $this->lockedAction($request, $id, 'security_rule_create_'.$groupId, fn () => $this->clientServiceConsoleService->createSecurityRuleForUser(
                $request->user(),
                $id,
                $groupId,
                $data,
                RequestContext::forClient($request)
            )),
            '创建成功'
        );
    }

    public function deleteSecurityRule(Request $request, int $id, int $groupId, int $ruleId)
    {
        return $this->success(
            $this->lockedAction($request, $id, 'security_rule_delete_'.$groupId.'_'.$ruleId, fn () => $this->clientServiceConsoleService->deleteSecurityRuleForUser(
                $request->user(),
                $id,
                $groupId,
                $ruleId,
                RequestContext::forClient($request)
            )),
            '删除成功'
        );
    }

    private function lockedAction(Request $request, int $id, string $action, callable $callback): mixed
    {
        return $this->serviceActionLock->execute(
            (int) ($request->user()?->id ?? 0),
            $id,
            $action,
            $callback
        );
    }
}
