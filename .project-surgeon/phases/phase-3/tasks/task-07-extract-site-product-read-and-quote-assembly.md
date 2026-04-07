# Task 07: extract-site-product-read-quote-and-website-quantity-submit

> Phase: 3 — 性能与结构收敛
> Status: Pending

---

## Objective

把站点商品读接口与报价拼装从控制器中收敛到专用站点服务，并补齐前台数量下单契约。

## Traceability

- Finding(s): `D7-002`, `Bug Fixer review-1 / M-1`
- Severity: `medium`, `medium`
- Dimension: `D7 Consistency`, `Bug Fixer Milestone Review`

## Related Findings — 关联发现

| Finding ID | Severity | File | Description |
|-----------|----------|------|-------------|
| D7-002 | MEDIUM | `backend/app/Http/Controllers/SiteProductController.php` | SiteProductController 仍承担较多读逻辑与报价拼装 |
| BF-M-1 | MEDIUM | `frontend-client/src/views/website/products/useWebsiteProductCheckout.js` | 前台数量控件仍拦截提交且遗漏 quantity 下单字段 |

## Files

**Create:**

- `backend/app/Services/SiteProductReadService.php`
- `backend/app/Services/SiteProductQuoteService.php`
- `backend/tests/Feature/SiteProductReadServiceTest.php`

**Modify:**

- `backend/app/Http/Controllers/SiteProductController.php`
- `frontend-client/src/views/website/products/useWebsiteProductCheckout.js`
- `frontend-client/src/views/website/ProductDetail/index.vue`

**Delete:**

- 无

**Test:**

- `backend/tests/Feature/SiteProductReadServiceTest.php`

## Dependencies

| Library | GitHub Repo | Usage in This Task |
|---------|-------------|-------------------|
| Laravel Framework | laravel/framework | 收敛站点商品读接口和报价服务边界 |
| PHPUnit | sebastianbergmann/phpunit | 验证商品读接口与报价响应兼容 |

## Steps

### Step 1: 抽出站点商品读取服务

把商品类型、分组、列表、详情、库存查询逻辑搬到 `SiteProductReadService`。

```php
$siteProductReadService->productDetail($productId)
```

### Step 2: 抽出站点报价服务

把报价、优惠券预览、安全令牌发放等拼装逻辑搬到 `SiteProductQuoteService`。

```php
$siteProductQuoteService->quote($product, $validated, $requestContext)
```

### Step 3: 让控制器只负责请求/响应

保留 `SiteProductController` 的参数校验和统一响应格式，移除大段商品/报价组装代码。

```php
return $this->success($siteProductQuoteService->quote(...));
```

### Step 4: 补齐网站前台 quantity 提交契约

修改网站商品详情页与网站商品 checkout 组合逻辑，把 `quantity` 一并带入创建订单 payload，并移除“后端已支持、前台仍强制拦截”的提交流程。

```js
payload.quantity = quantity.value
```

### Step 5: 锁定站点契约

增加特征测试，确认站点商品列表、详情和报价接口输出结构保持兼容。

```bash
php artisan test --filter=SiteProductReadServiceTest
```

## Verification

### Baseline Tests — 基线测试

- [ ] Run test suite before task: `php artisan test`
- [ ] Record baseline: `31` passing, `0` failing
- [ ] Run test suite after task
- [ ] Verify no new failures (Preservation Gate)

- [ ] `SiteProductController` 中的大块读逻辑与报价拼装已下沉
- [ ] 站点商品和报价接口结构保持兼容
- [ ] 网站前台创建订单请求已带 `quantity`，且不再无条件阻断 `quantity > 1`

**Test command:**
```bash
php artisan test --filter=SiteProductReadServiceTest
```

**Expected output:**
```text
PASS  Tests\Feature\SiteProductReadServiceTest
```

## Rollback Plan

- 若前台契约波动，先回退报价服务拆分，不回退读取服务
- 保留新增测试，确认是商品读取还是报价拼装导致漂移
- 若服务拆分过大，可先保留控制器薄层包装，再逐步迁移

## Commit

```text
refactor: extract site product read and quote services with website quantity submit (Phase 3, Task 07)
```
