<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\Blackhole\AddNingboWhitelistRequest;
use App\Http\Requests\Client\Blackhole\AddShiyanLayer4RuleRequest;
use App\Http\Requests\Client\Blackhole\DeleteShiyanLayer4RuleRequest;
use App\Http\Requests\Client\Blackhole\QueryRequest;
use App\Http\Requests\Client\Blackhole\SetShiyanLayer7RuleRequest;
use App\Services\Security\BlackholeService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class BlackholeController extends Controller
{
    use ApiResponse;

    public function __construct(private BlackholeService $blackhole) {}

    public function query(QueryRequest $request): JsonResponse
    {
        // validation handled by QueryRequest

        $result = $this->blackhole->query($request->input('ip'));

        return $this->success($result);
    }

    public function addNingboWhitelist(AddNingboWhitelistRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $result = $this->blackhole->addNingboWhitelist($payload['ip'], $payload['domain']);

        if (($result['success'] ?? false) !== true) {
            return $this->error(50000, $result['message'] ?? '操作失败', $result);
        }

        return $this->success($result, $result['message'] ?? '操作成功');
    }

    public function setShiyanLayer7Rule(SetShiyanLayer7RuleRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $result = $this->blackhole->setShiyanLayer7Rule(
            $payload['ip'],
            (int) $payload['rule_id'],
            (bool) $payload['enabled']
        );

        if (($result['success'] ?? false) !== true) {
            return $this->error(50000, $result['message'] ?? '操作失败', $result);
        }

        return $this->success($result, $result['message'] ?? '操作成功');
    }

    public function addShiyanLayer4Rule(AddShiyanLayer4RuleRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $result = $this->blackhole->addShiyanLayer4Rule($payload['ip'], (int) $payload['mode']);

        if (($result['success'] ?? false) !== true) {
            return $this->error(50000, $result['message'] ?? '操作失败', $result);
        }

        return $this->success($result, $result['message'] ?? '操作成功');
    }

    public function deleteShiyanLayer4Rule(DeleteShiyanLayer4RuleRequest $request): JsonResponse
    {
        $payload = $request->validated();

        $result = $this->blackhole->deleteShiyanLayer4Rule($payload['ip'], $payload['rule_id']);

        if (($result['success'] ?? false) !== true) {
            return $this->error(50000, $result['message'] ?? '操作失败', $result);
        }

        return $this->success($result, $result['message'] ?? '操作成功');
    }
}
