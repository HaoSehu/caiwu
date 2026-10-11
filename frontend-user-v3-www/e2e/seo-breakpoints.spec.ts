import { test, expect } from "@playwright/test";

// 官网 SEO 与断点回归（对应 T21 / T22）。
// 全部走 page.route mock，不依赖本地后端；断言只关心运行时注入的 meta 与布局是否溢出。

function envelope(data: unknown) {
  return { code: 0, message: "ok", data };
}

async function readMeta(page: import("@playwright/test").Page) {
  return page.evaluate(() => ({
    title: document.title,
    description:
      document.querySelector('meta[name="description"]')?.getAttribute("content") ?? "",
    canonical:
      document.querySelector('link[rel="canonical"]')?.getAttribute("href") ?? "",
    ogTitle:
      document.querySelector('meta[property="og:title"]')?.getAttribute("content") ?? "",
    jsonLd: Array.from(
      document.querySelectorAll('script[type="application/ld+json"]'),
    ).map((el) => el.textContent ?? ""),
  }));
}

test("商品详情页运行时注入 title/description/canonical 与 Product JSON-LD", async ({
  page,
}) => {
  await page.route("**/api/v2/site/products/123*", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify(
        envelope({
          product: {
            id: 123,
            name: "E2E 云服务器",
            description: "E2E 商品描述文案",
            price: 100,
            billing_cycles: [],
            config: [],
            siblings: [],
          },
        }),
      ),
    }),
  );

  await page.goto("/products/123", { waitUntil: "domcontentloaded" });

  // 等 meta 被运行时覆盖（初始为路由静态 meta）
  await expect
    .poll(async () => (await readMeta(page)).title, { timeout: 15_000 })
    .toContain("E2E 云服务器");

  const meta = await readMeta(page);
  expect(meta.title).toContain("E2E 云服务器");
  expect(meta.description).toBe("E2E 商品描述文案");
  expect(meta.canonical).toContain("/products/123");
  expect(meta.ogTitle).toContain("E2E 云服务器");

  const productLd = meta.jsonLd.find((text) => text.includes('"Product"'));
  expect(productLd, "缺少 Product 结构化数据").toBeTruthy();
  expect(productLd).toContain("E2E 云服务器");
});

test("404 路由带 description 且保持 noindex", async ({ page }) => {
  await page.goto("/e2e-not-exist-page", { waitUntil: "domcontentloaded" });

  const meta = await readMeta(page);
  expect(meta.description.length).toBeGreaterThan(0);

  const robots =
    (await page
      .locator('meta[name="robots"]')
      .getAttribute("content")
      .catch(() => "")) ?? "";
  expect(robots).toContain("noindex");
});

test("992/768 阈值视口首页无横向溢出", async ({ page }) => {
  for (const width of [992, 768]) {
    await page.setViewportSize({ width, height: 800 });
    await page.goto("/", { waitUntil: "domcontentloaded" });
    await page.waitForTimeout(400);

    const overflow = await page.evaluate(
      () =>
        document.documentElement.scrollWidth -
        document.documentElement.clientWidth,
    );
    expect(overflow, `${width}px 视口出现横向溢出 ${overflow}px`).toBeLessThanOrEqual(1);
  }
});
