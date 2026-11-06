import { test, expect } from "@playwright/test";

// 移动断点（992）统一后的行为回归：商品列表、商品详情与站点头部共用
// composables/useMobileBreakpoint 的同一份 matchMedia 判定。
// 断言只关心「跨断点时移动态与桌面态是否互换」，不依赖真实后端数据。

function envelope(data: unknown) {
  return { code: 0, message: "ok", data };
}

// 收集「一定不该出现」的运行时错误。
// 除未捕获异常外还收 CSP 违规：CSP 违规只在 console 里报一句且不含 "Uncaught"，
// 旧实现会把它整类漏掉（例如生产构建把 http://127.0.0.1:8000 写进 connect-src）。
function collectUncaughtErrors(page: import("@playwright/test").Page) {
  const errors: string[] = [];
  page.on("pageerror", (err) => errors.push(String(err)));
  page.on("console", (msg) => {
    if (msg.type() !== "error") {
      return;
    }
    const text = msg.text();
    if (text.includes("Uncaught") || text.includes("Content Security Policy")) {
      errors.push(text);
    }
  });
  return errors;
}

const PRODUCTS_INIT_PATH = "**/api/v2/site/product-purchase-context*";

async function mockProductsInit(page: import("@playwright/test").Page) {
  const body = JSON.stringify(
    envelope({
      types: [
        {
          product_type: "cloud",
          first_product_group_code: "cloud",
          first_product_group_name: "云服务器",
          first_product_group_id: 1,
          id: 1,
          product_count: 0,
        },
      ],
      root_groups: [
        {
          id: 1,
          effective_product_group_id: 1,
          effective_product_group_level: 2,
          name: "香港机房",
          product_count: 0,
        },
      ],
      catalog: {
        effective_product_group_id: 1,
        effective_product_group_level: 2,
        children: [],
        items_by_group: [{ effective_product_group_id: 1, products: [] }],
      },
    }),
  );
  await page.route(PRODUCTS_INIT_PATH, (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body,
    }),
  );
}

// 断点值本身必须被钉住：只取 375 与 1280 时，任何落在两者之间的断点
// （768/900/1100）都能让断言通过，992 这个具体取值从未被验证。
test("992 断点两侧各 1px 精确切换形态", async ({ page }) => {
  const errors = collectUncaughtErrors(page);
  await mockProductsInit(page);

  // 993：断点之上应是桌面三栏
  await page.setViewportSize({ width: 993, height: 800 });
  await page.goto("/products", { waitUntil: "domcontentloaded" });
  await expect(page.locator(".shop-sidebar")).toBeVisible();
  await expect(page.locator(".mobile-picker-row--duo")).toHaveCount(0);

  // 991：断点之下应是移动 picker
  await page.setViewportSize({ width: 991, height: 800 });
  await expect(page.locator(".mobile-picker-row--duo").first()).toBeVisible();
  await expect(page.locator(".shop-sidebar")).toHaveCount(0);

  expect(errors).toEqual([]);
});

test("商品列表页跨 992 断点切换移动 picker 与桌面侧栏", async ({ page }) => {
  const errors = collectUncaughtErrors(page);
  await mockProductsInit(page);

  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto("/products", { waitUntil: "domcontentloaded" });

  // 移动态：桌面侧栏不渲染，移动 picker 行渲染
  await expect(page.locator(".mobile-picker-row--duo").first()).toBeVisible();
  await expect(page.locator(".shop-sidebar")).toHaveCount(0);

  // 跨断点后无需刷新即互换（resize → updateMobileState → matchMedia 判定）
  await page.setViewportSize({ width: 1280, height: 800 });
  await expect(page.locator(".shop-sidebar")).toBeVisible();
  await expect(page.locator(".mobile-picker-row--duo")).toHaveCount(0);

  expect(errors).toEqual([]);
});

test("站点头部跨 992 断点收起移动菜单", async ({ page }) => {
  const errors = collectUncaughtErrors(page);
  await mockProductsInit(page);

  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto("/products", { waitUntil: "domcontentloaded" });

  await page.locator(".hamburger").click();
  await expect(page.locator("#mobile-menu-panel")).toBeVisible();

  // 变宽后 handleResize 观察到 isMobile 由真变假，主动收起移动菜单
  await page.setViewportSize({ width: 1280, height: 800 });
  await expect(page.locator("#mobile-menu-panel")).toHaveCount(0);

  expect(errors).toEqual([]);
});

// defineAsyncComponent 只把 chunk 从路由 chunk 里切出去，父组件渲染到该 vnode 时
// 仍会立即触发 loader。此前这些移动抽屉没有 isMobile 门控，桌面端实测仍下载
// MobileSheet / MobileRegionPicker / MobileOsPicker 约 12.5KB gzip。
test("移动专属抽屉只在移动端下载，桌面不拉取对应 chunk", async ({ page }) => {
  const mobileChunkRequests: string[] = [];
  page.on("request", (request) => {
    const url = request.url();
    if (
      /\/assets\/js\/Mobile(?:Sheet|RegionPicker|OsPicker|OptionPicker)[-_]/.test(
        url,
      )
    ) {
      mobileChunkRequests.push(url);
    }
  });
  await mockProductsInit(page);

  // 桌面：抽屉被 isMobile 门控掉，vnode 根本不渲染，因此不应发起任何请求
  await page.setViewportSize({ width: 1280, height: 800 });
  await page.goto("/products", { waitUntil: "domcontentloaded" });
  await expect(page.locator(".shop-sidebar")).toBeVisible();
  await page.waitForLoadState("networkidle");
  expect(mobileChunkRequests).toEqual([]);

  // 跨到移动端：门控放行后组件挂载，chunk 才按需拉取
  await page.setViewportSize({ width: 375, height: 812 });
  await expect(page.locator(".mobile-picker-row--duo").first()).toBeVisible();
  await expect
    .poll(() => mobileChunkRequests.length, { timeout: 10_000 })
    .toBeGreaterThan(0);
});
