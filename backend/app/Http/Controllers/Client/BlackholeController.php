<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Services\Security\BlackholeService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlackholeController extends Controller
{
    use ApiResponse;

    public function __construct(private BlackholeService $blackhole) {}

    public function query(Request $request): JsonResponse
    {
        $request->validate([
            'ip' => 'required|ip',
        ]);

        $result = $this->blackhole->query($request->input('ip'));

        return $this->success($result);
    }

    public function addNingboWhitelist(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'ip' => 'required|ip',
            'domain' => 'required|string|max:255',
        ]);

        $result = $this->blackhole->addNingboWhitelist($payload['ip'], $payload['domain']);

        if (($result['success'] ?? false) !== true) {
            return $this->error(50000, $result['message'] ?? '操作失败', $result);
        }

        return $this->success($result, $result['message'] ?? '操作成功');
    }

    public function setShiyanLayer7Rule(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'ip' => 'required|ip',
            'rule_id' => 'required|integer|min:1',
            'enabled' => 'required|boolean',
        ]);

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

    public function addShiyanLayer4Rule(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'ip' => 'required|ip',
            'mode' => 'required|integer|in:1,2',
        ]);

        $result = $this->blackhole->addShiyanLayer4Rule($payload['ip'], (int) $payload['mode']);

        if (($result['success'] ?? false) !== true) {
            return $this->error(50000, $result['message'] ?? '操作失败', $result);
        }

        return $this->success($result, $result['message'] ?? '操作成功');
    }

    public function deleteShiyanLayer4Rule(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'ip' => 'required|ip',
            'rule_id' => 'required|string|max:64',
        ]);

        $result = $this->blackhole->deleteShiyanLayer4Rule($payload['ip'], $payload['rule_id']);

        if (($result['success'] ?? false) !== true) {
            return $this->error(50000, $result['message'] ?? '操作失败', $result);
        }

        return $this->success($result, $result['message'] ?? '操作成功');
    }
}
