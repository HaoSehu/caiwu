<?php

declare(strict_types=1);

namespace App\Services\ProductCatalog;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Collection;

/**
 * ProductCatalogService - 外观类，委托给各专责子服务。
 *
 * 子服务：
 *   - ProductCategoryService 分类管理（兼容旧分组接口）
 *   - ProductAdminService  商品后台管理（列表、CRUD、排序、移动、开关）
 *   - ProductSyncService   上游同步（批量同步、固化绑定、库存）
 *   - ProductSiteService   前台目录（商品类型、分组树、商品卡片、全量 Catalog）
 */
class ProductCatalogService
{
    public function __construct(
        private readonly ProductCategoryService $categoryService,
        private readonly ProductAdminService $adminService,
        private readonly ProductSyncService $syncService,
        private readonly ProductSiteService $siteService,
    ) {}

    // -------------------------------------------------------------------------
    // 分组管理
    // -------------------------------------------------------------------------

    public function adminSummary(): array
    {
        return $this->categoryService->adminSummary();
    }

    public function adminGroupTree(?string $productType = null): Collection
    {
        return $this->categoryService->adminCategoryTree($productType);
    }

    public function adminCategoryTree(?string $productType = null): Collection
    {
        return $this->categoryService->adminCategoryTree($productType);
    }

    public function groupOptions(?string $productType = null): array
    {
        return $this->categoryService->categoryOptions($productType);
    }

    public function categoryOptions(?string $productType = null): array
    {
        return $this->categoryService->categoryOptions($productType);
    }

    public function createGroup(array $data): ProductCategory
    {
        return $this->categoryService->createCategory($data);
    }

    public function createCategory(array $data): ProductCategory
    {
        return $this->categoryService->createCategory($data);
    }

    public function updateGroup(ProductCategory $group, array $data): ProductCategory
    {
        return $this->categoryService->updateCategory($group, $data);
    }

    public function updateCategory(ProductCategory $category, array $data): ProductCategory
    {
        return $this->categoryService->updateCategory($category, $data);
    }

    public function deleteGroup(ProductCategory $group): void
    {
        $this->categoryService->deleteCategory($group);
    }

    public function deleteCategory(ProductCategory $category): void
    {
        $this->categoryService->deleteCategory($category);
    }

    public function reorderAdminGroups(?string $productType, ?int $parentId, array $groupIds): array
    {
        return $this->categoryService->reorderAdminCategories($productType, $parentId, $groupIds);
    }

    public function reorderAdminCategories(?string $productType, ?int $parentId, array $groupIds): array
    {
        return $this->categoryService->reorderAdminCategories($productType, $parentId, $groupIds);
    }

    public function moveAdminGroup(
        ProductCategory $group,
        ?string $targetProductType,
        ?int $targetParentId,
        ?int $referenceGroupId,
        string $position = 'append',
    ): array {
        return $this->categoryService->moveAdminCategory($group, $targetProductType, $targetParentId, $referenceGroupId, $position);
    }

    public function moveAdminCategory(
        ProductCategory $category,
        ?string $targetProductType,
        ?int $targetParentId,
        ?int $referenceCategoryId,
        string $position = 'append',
    ): array {
        return $this->categoryService->moveAdminCategory($category, $targetProductType, $targetParentId, $referenceCategoryId, $position);
    }

    // -------------------------------------------------------------------------
    // 商品后台管理
    // -------------------------------------------------------------------------

    public function adminProductList(array $filters, int $perPage = 20)
    {
        return $this->adminService->adminProductList($filters, $perPage);
    }

    public function reorderAdminProducts(array $filters, int $page, int $pageSize, array $productIds): array
    {
        return $this->adminService->reorderAdminProducts($filters, $page, $pageSize, $productIds);
    }

    public function moveAdminProduct(
        Product $product,
        int $targetCategoryId,
        ?int $referenceProductId,
        string $position = 'append',
    ): array {
        return $this->adminService->moveAdminProduct($product, $targetCategoryId, $referenceProductId, $position);
    }

    public function adminProductOwners(Product $product, array $filters, int $perPage = 20): array
    {
        return $this->adminService->adminProductOwners($product, $filters, $perPage);
    }

    public function createProduct(array $data): Product
    {
        return $this->adminService->createProduct($data);
    }

    public function updateProduct(Product $product, array $data): Product
    {
        return $this->adminService->updateProduct($product, $data);
    }

    public function batchUpdateProvisionHostname(array $data, array $context = []): array
    {
        return $this->adminService->batchUpdateProvisionHostname($data, $context);
    }

    public function batchUpdateCategory(array $data): array
    {
        return $this->adminService->batchUpdateCategory($data);
    }

    public function splitProducts(array $data): array
    {
        return $this->adminService->splitProducts($data);
    }

    public function previewSplitProducts(array $data): array
    {
        return $this->adminService->previewSplitProducts($data);
    }

    public function updateProductSortOrder(Product $product, int $sortOrder): Product
    {
        return $this->adminService->updateProductSortOrder($product, $sortOrder);
    }

    public function deleteProduct(Product $product): void
    {
        $this->adminService->deleteProduct($product);
    }

    public function toggleProductStatus(Product $product): Product
    {
        return $this->adminService->toggleProductStatus($product);
    }

    // -------------------------------------------------------------------------
    // 上游同步 / 库存
    // -------------------------------------------------------------------------

    public function batchSyncProducts(array $data): array
    {
        return $this->syncService->batchSyncProducts($data);
    }

    public function finalizeUpstreamBindings(array $data = []): array
    {
        return $this->syncService->finalizeUpstreamBindings($data);
    }

    public function syncUpstreamProductConfigOptions(): array
    {
        return $this->syncService->syncUpstreamProductConfigOptions();
    }

    public function applyLiveStockToProduct(Product $product, bool $strict = false): Product
    {
        return $this->syncService->applyLiveStockToProduct($product, $strict);
    }

    public function applyLiveStockToProducts(Collection $products, bool $strict = false): Collection
    {
        return $this->syncService->applyLiveStockToProducts($products, $strict);
    }

    public function siteProductStock(int $productId): ?array
    {
        return $this->syncService->siteProductStock($productId);
    }

    public function assertProductCanBeProvisioned(Product $product, int $requiredQuantity = 1): void
    {
        $this->syncService->assertProductCanBeProvisioned($product, $requiredQuantity);
    }

    public function bulkConnectSupplierProducts(Supplier $supplier, array $data): array
    {
        return $this->syncService->bulkConnectSupplierProducts($supplier, $data);
    }

    // -------------------------------------------------------------------------
    // 前台商品目录
    // -------------------------------------------------------------------------

    public function siteProductTypes(): array
    {
        return $this->siteService->siteProductTypes();
    }

    public function siteRootGroups(?string $productType = null): array
    {
        return $this->siteService->siteRootGroups($productType);
    }

    public function siteRootCategories(?string $productType = null): array
    {
        return $this->siteService->siteRootGroups($productType);
    }

    public function siteChildGroups(int $groupId): array
    {
        return $this->siteService->siteChildGroups($groupId);
    }

    public function siteChildCategories(int $groupId): array
    {
        return $this->siteService->siteChildGroups($groupId);
    }

    public function siteGroupCatalog(int $groupId): array
    {
        return $this->siteService->siteGroupCatalog($groupId);
    }

    public function siteCategoryCatalog(int $groupId): array
    {
        return $this->siteService->siteGroupCatalog($groupId);
    }

    public function siteProductsByGroupIds(array $groupIds): array
    {
        return $this->siteService->siteProductsByGroupIds($groupIds);
    }

    public function siteProductDetail(int $productId): ?array
    {
        return $this->siteService->siteProductDetail($productId);
    }

    public function siteCatalog(): Collection
    {
        return $this->siteService->siteCatalog();
    }
}
