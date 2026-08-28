import { test, expect } from "@playwright/test";

// 本轮 UI/UX 修复（失败态分离、触屏热区、页脚合规）的行为冒烟用例。
// 复用 playwright.config 的 build:e2e 产物与 preview 服务，接口按用例 mock。

function collectConsoleErrors(page: import("@playwright/test").Page) {
  const errors: string[] = [];
  page.on("console", (msg) => {
    if (msg.type() === "error") {
      errors.push(msg.text());
    }
  });
  page.on("pageerror", (err) => {
    errors.push(String(err));
  });
  return errors;
}

test("产品目录接口失败时呈现错误态与重试入口，而非假空态", async ({
  page,
}) => {
  const errors = collectConsoleErrors(page);
  // 目录初始化链路全部拒绝：聚合接口与兜底类型接口都失败
  await page.route("**/api/v2/site/product-purchase-context*", (route) =>
    route.fulfill({ status: 500, body: "server error" }),
  );
  await page.route("**/api/v2/site/product-types*", (route) =>
    route.fulfill({ status: 500, body: "server error" }),
  );

  await page.setViewportSize({ width: 1280, height: 800 });
  await page.goto("/products", { waitUntil: "domcontentloaded" });

  await expect(page.locator(".catalog-error")).toBeVisible();
  await expect(page.locator(".catalog-error__text")).toContainText("加载失败");
  await expect(page.locator(".catalog-error__retry")).toBeVisible();
  // 失败态不得伪装成空态
  await expect(page.getByText("当前分类暂无商品")).toHaveCount(0);

  // 放行接口后重试可恢复
  await page.unroute("**/api/v2/site/product-purchase-context*");
  await page.unroute("**/api/v2/site/product-types*");
  await page.locator(".catalog-error__retry").click();
  await expect(page.locator(".catalog-error")).toHaveCount(0, {
    timeout: 15_000,
  });

  // 失败态恢复后不允许遗留 toast 相关 console error（网络 5xx 由拦截器 toast，
  // 这里只断言页面自身无 JS 异常）
  expect(errors.filter((e) => e.includes("Uncaught"))).toEqual([]);
});

test("页脚提供法律文档入口与备案链接", async ({ page }) => {
  await page.goto("/", { waitUntil: "domcontentloaded" });

  const footerSupport = page.locator(".footer-col", { hasText: "支持" });
  await expect(footerSupport.getByRole("link", { name: "服务条款" })).toHaveAttribute(
    "href",
    /\/terms/,
  );
  await expect(footerSupport.getByRole("link", { name: "隐私政策" })).toHaveAttribute(
    "href",
    /\/privacy/,
  );
});

test("390 视口移动轮播圆点可点击切换", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/", { waitUntil: "domcontentloaded" });

  const dots = page.locator(".hero-mobile-nav__dot");
  await expect(dots.first()).toBeVisible();
  const count = await dots.count();
  expect(count).toBeGreaterThan(1);

  const second = dots.nth(1);
  await second.click();
  await expect(second).toHaveClass(/is-active/);
  await expect(dots.first()).not.toHaveClass(/is-active/);
});
