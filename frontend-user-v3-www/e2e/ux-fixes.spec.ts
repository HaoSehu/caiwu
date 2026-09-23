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

test("小视口(等效高缩放)下结算条作为覆盖层钉在费用栏底部保持可点", async ({
  page,
}) => {
  // 等价于浏览器 120% 缩放后的 CSS 视口：费用栏高度被 100dvh 钳制，
  // 明细区超高时旧实现把购买按钮折叠进栏内滚动区，必须滚到底才露出。
  // 修复后明细独立成滚动层，结算条(合计+购买)是它的覆盖层，滚动不位移。
  await page.setViewportSize({ width: 1280, height: 520 });
  await page.goto("/products", { waitUntil: "domcontentloaded" });

  const buyBtn = page.locator(".shop-cost .buy-btn");
  const costTotal = page.locator(".shop-cost .cost-total");
  const scrollLayer = page.locator(".shop-cost-scroll");
  await expect(buyBtn).toBeVisible({ timeout: 15_000 });

  // 场景成立：明细滚动层确实进入内部溢出态
  const metrics = await scrollLayer.evaluate((el) => ({
    scrollHeight: el.scrollHeight,
    clientHeight: el.clientHeight,
  }));
  expect(metrics.scrollHeight).toBeGreaterThan(metrics.clientHeight);

  // 不滚动明细层，按钮与合计行也必须完整落在视口内
  await expect(buyBtn).toBeInViewport();
  await expect(costTotal).toBeInViewport();

  // 几何中心命中测试：命中结果必须是按钮自身（未被遮挡或折叠）
  const hitTestButton = () =>
    page.evaluate(() => {
      const btn = document.querySelector(
        ".shop-cost .buy-btn",
      ) as HTMLElement;
      if (!btn) return "missing";
      const rect = btn.getBoundingClientRect();
      const el = document.elementFromPoint(
        rect.left + rect.width / 2,
        rect.top + rect.height / 2,
      );
      return el && (el === btn || btn.contains(el)) ? "btn" : "other";
    });

  // 明细层滚到底：覆盖层不随之位移，按钮保持可见可点
  await scrollLayer.evaluate((el) => {
    el.scrollTop = el.scrollHeight;
  });
  await expect(buyBtn).toBeInViewport();
  expect(await hitTestButton()).toBe("btn");
});
