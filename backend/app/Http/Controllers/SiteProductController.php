<?php

namespace App\Http\Controllers;

use App\Constants\ProductType;
use App\Models\User;
use App\Services\Site\SiteProductQuoteService;
use App\Services\Site\SiteProductReadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class SiteProductController extends Controller
{
    public function __construct(
        private SiteProductReadService $siteProductReadService,
        private SiteProductQuoteService $siteProductQuoteService,
    ) {}

    public function productTypes()
    {
        return $this->success($this->siteProductReadService->productTypes());
    }

    public function productGroups(Request $request)
    {
        $validated = $request->validate([
            'product_type' => ['nullable', Rule::in(ProductType::allowedValues())],
        ]);

        return $this->success(
            $this->siteProductReadService->productGroups($validated['product_type'] ?? null)
        );
    }

    public function childGroups(int $groupId)
    {
        return $this->success($this->siteProductReadService->childGroups($groupId));
    }

    public function groupCatalog(int $groupId)
    {
        return $this->success($this->siteProductReadService->groupCatalog($groupId));
    }

    public function index(Request $request)
    {
        $validated = $request->validate([
            'category_id' => ['nullable', 'integer', 'min:1'],
            'category_ids' => ['nullable', 'array', 'min:1'],
            'category_ids.*' => ['integer', 'min:1'],
            'product_group_id' => ['nullable', 'integer', 'min:1'],
            'product_group_ids' => ['nullable', 'array', 'min:1'],
            'product_group_ids.*' => ['integer', 'min:1'],
            'group_id' => ['nullable', 'integer', 'min:1'],
            'group_ids' => ['nullable', 'array', 'min:1'],
            'group_ids.*' => ['integer', 'min:1'],
        ]);

        return $this->success($this->siteProductReadService->products($validated));
    }

    public function show(int $productId)
    {
        $product = $this->siteProductReadService->productDetail($productId);

        if (! $product) {
            return $this->error(40400, '商品不存在或已下架');
        }

        return $this->success([
            'product' => $product,
        ]);
    }

    public function stock(int $productId)
    {
        $payload = $this->siteProductReadService->productStock($productId);

        if (! $payload) {
            return $this->error(40400, '商品不存在或已下架');
        }

        return $this->success($payload);
    }

    public function quote(Request $request, int $productId)
    {
        $validated = $request->validate([
            'billing_cycle' => ['required', 'string', 'max:30'],
            'config' => ['nullable', 'array'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:10'],
            'user_coupon_id' => ['nullable', 'integer', 'min:1'],
        ]);

        $payload = $this->siteProductQuoteService->resolveQuotePayload(
            $productId,
            $validated,
            [
                'user_id' => (int) ($this->resolveClientUser($request)?->id ?? 0),
                'request_id' => (string) $request->header('X-Request-Id', ''),
                'ip_address' => (string) $request->ip(),
            ]
        );

        if (! is_array($payload)) {
            return $this->error(40400, '商品不存在或已下架');
        }

        return $this->success($payload);
    }

    private function resolveClientUser(Request $request): ?User
    {
        $requestUser = $request->user();
        if ($requestUser instanceof User) {
            return $requestUser;
        }

        $sanctumUser = Auth::guard('sanctum')->user();

        return $sanctumUser instanceof User ? $sanctumUser : null;
    }
}
