import { test, expect } from "@playwright/test";

// 冒烟用例走 .env.e2e 构建（playwright.config 的 build:e2e）：
// VITE_API_BASE_URL=/api 经 vite preview 同源代理到本地后端 127.0.0.1:8000，
// 规避 CORS；内容列表用例对分页接口做了 mock。断言均为结构性断言，对数据内容不敏感。

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

test("首页渲染且无 console error", async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await page.goto("/", { waitUntil: "domcontentloaded" });
  await expect(page.locator(".home-page")).toBeVisible();
  expect(errors).toEqual([]);
});

test("1024 视口机型规格表可横向滚动且价格列可达", async ({ page }) => {
  await page.setViewportSize({ width: 1024, height: 800 });
  await page.goto("/products", { waitUntil: "domcontentloaded" });

  const scroll = page.locator(".desktop-machine-table-scroll");
  await expect(scroll).toBeVisible();

  const metrics = await scroll.evaluate((el) => ({
    scrollWidth: el.scrollWidth,
    clientWidth: el.clientWidth,
  }));
  expect(metrics.scrollWidth).toBeGreaterThan(metrics.clientWidth);

  // 右侧库存/价格列可达：横向滚动容器到底，其余方向由 scrollIntoViewIfNeeded 收尾
  await scroll.evaluate((el) => {
    el.scrollLeft = el.scrollWidth;
  });
  const price = scroll.locator(".machine-spec-cell--price").last();
  await price.scrollIntoViewIfNeeded();
  await expect(price).toBeInViewport();
});

test("≤768 视口商品页移动抽屉弹出行选", async ({ page }) => {
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto("/products", { waitUntil: "domcontentloaded" });

  const pickerTrigger = page
    .locator(".mobile-picker-col", { hasText: "产品规格选择" })
    .locator(".mobile-picker-trigger");
  await expect(pickerTrigger).toBeVisible();
  await pickerTrigger.click();

  await expect(page.locator(".mopt-picker")).toBeVisible();
  await expect(page.locator(".mopt-item").first()).toBeVisible();
});

test("360 视口内容列表分页换行无横向溢出", async ({ page }) => {
  await page.setViewportSize({ width: 360, height: 800 });

  // 本地后端公告数可能不足分页阈值：mock 12 条公告，保证分页区确定渲染
  const mockArticles = Array.from({ length: 12 }, (_, i) => ({
    id: 9000 + i,
    title: `分页冒烟测试公告 ${i + 1}（超长英文串 aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa 验证换行兜底）`,
    publish_at: "2026-08-01 10:00:00",
  }));
  await page.route("**/api/v2/site/content/overview", (route) =>
    route
      .fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          code: 0,
          message: "操作成功",
          data: { notices: [], notice_categories: [] },
        }),
      })
      .then(() => undefined),
  );
  await page.route(
    (url) => url.pathname.includes("/api/v2/site/notices"),
    async (route) => {
      const url = new URL(route.request().url());
      const pageNum = Number(url.searchParams.get("page") || "1");
      const size = Number(url.searchParams.get("page_size") || "10");
      const start = (pageNum - 1) * size;
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          code: 0,
          message: "操作成功",
          data: {
            list: mockArticles.slice(start, start + size),
            total: mockArticles.length,
            categories: [],
          },
        }),
      });
    },
  );

  await page.goto("/notices", { waitUntil: "domcontentloaded" });

  const pager = page.locator(".list-panel__pager");
  await expect(pager).toBeVisible();
  await expect
    .poll(() =>
      page.evaluate(
        () =>
          document.documentElement.scrollWidth -
          document.documentElement.clientWidth,
      ),
    )
    .toBeLessThanOrEqual(0);
});

test("360 视口首页无横向滚动条", async ({ page }) => {
  await page.setViewportSize({ width: 360, height: 800 });
  await page.goto("/", { waitUntil: "domcontentloaded" });

  await expect(page.locator(".home-page")).toBeVisible();
  await expect
    .poll(() =>
      page.evaluate(
        () =>
          document.documentElement.scrollWidth -
          document.documentElement.clientWidth,
      ),
    )
    .toBeLessThanOrEqual(0);
});
