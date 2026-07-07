<?php

declare(strict_types=1);

namespace App\Http\Controllers\Zjmf;

use App\Exceptions\BusinessException;
use App\Http\Controllers\Controller;
use App\Services\ZjmfBridge\ZjmfErrorMapper;
use App\Services\ZjmfBridge\ZjmfProductCatalogService;
use App\Services\ZjmfBridge\ZjmfResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function __construct(
        private readonly ZjmfProductCatalogService $catalog,
        private readonly ZjmfResponseFactory $responses,
        private readonly ZjmfErrorMapper $errors,
    ) {}

    public function products(Request $request): JsonResponse
    {
        return $this->respond(fn (): array => $this->catalog->products($request->query()));
    }

    public function productConfig(Request $request): JsonResponse
    {
        return $this->respond(fn (): array => $this->catalog->productConfig($request->query()));
    }

    public function productsTotal(Request $request): JsonResponse
    {
        return $this->respond(fn (): array => $this->catalog->quote($request->all()));
    }

    public function categories(Request $request): JsonResponse
    {
        return $this->respond(fn (): array => $this->catalog->categories($request->query()));
    }

    private function respond(callable $callback): JsonResponse
    {
        try {
            return $this->responses->success($callback());
        } catch (BusinessException $exception) {
            return $this->responses->error(
                $this->errors->fromCaiwuCode($exception->getErrorCode()),
                $exception->getMessage()
            );
        } catch (\Throwable) {
            return $this->responses->error(500, 'ZJMF Bridge 处理失败');
        }
    }
}
