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

// 5 条 SEO 落地页此前零自动化验收：薄壳组件与 slug 的对应关系一旦错位，
// 线上只会表现成「页面能开但内容是别家的」。这里逐条校验标题、h1 与页面专属标签。
const SEO_LANDING_PAGES = [
  { path: "/cloud-server", keyword: "云服务器", heading: "稳定易用的云服务器" },
  { path: "/hong-kong-server", keyword: "香港服务器", heading: "面向亚太业务的香港服务器" },
  { path: "/us-server", keyword: "美国服务器", heading: "适合海外部署的美国服务器" },
  { path: "/high-defense-server", keyword: "高防服务器", heading: "面向防护需求的高防服务器" },
  { path: "/cloud-pc", keyword: "云电脑", heading: "灵活可用的云电脑" },
];

test("5 条 SEO 落地页各自渲染对应内容且互不串页", async ({ page }) => {
  const errors: string[] = [];
  page.on("pageerror", (err) => errors.push(String(err)));

  for (const target of SEO_LANDING_PAGES) {
    await page.goto(target.path, { waitUntil: "domcontentloaded" });

    // h1 必须存在且含该页关键词，且页面主体是落地页而非 404
    const heading = page.locator(".seo-hero__content h1");
    await expect(heading).toBeVisible();
    await expect(heading).toHaveText(target.heading);

    // 页面标题（构建期预渲染写入 + 运行时 applyRouteMeta 覆盖）也要带关键词
    expect(await page.title()).toContain(target.keyword);

    // 落地页特有区块存在，说明挂的是 SeoLandingView 而不是兜底 404
    await expect(page.locator(".seo-landing-page")).toHaveCount(1);
    await expect(page.locator(".not-found-code")).toHaveCount(0);
  }

  expect(errors).toEqual([]);
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
    // 等到首页容器与首屏轮播真正渲染（数据加载完成后 homeHeroReady 才为真），
    // 再量宽度，避免拿骨架屏阶段的布局做溢出结论。
    await page.locator("#main-content .home-page").waitFor({ state: "visible" });
    await page.locator(".hero-section").first().waitFor({ state: "visible" });

    const overflow = await page.evaluate(
      () =>
        document.documentElement.scrollWidth -
        document.documentElement.clientWidth,
    );
    expect(overflow, `${width}px 视口出现横向溢出 ${overflow}px`).toBeLessThanOrEqual(1);
  }
});
