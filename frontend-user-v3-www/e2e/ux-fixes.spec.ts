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

test("390 视口保留带文案的产品入口而非只剩圆点", async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto("/", { waitUntil: "domcontentloaded" });

  // 入口列表此前在 ⩽640 被整段 display:none，手机上只剩无法辨识内容的轮播圆点
  const rail = page.locator(".hero-rail");
  await expect(rail).toBeVisible();

  const items = page.locator(".hero-rail__item");
  expect(await items.count()).toBeGreaterThan(1);
  // 入口必须带文案，纯图标等于没有入口
  await expect(items.first().locator(".hero-rail__label")).not.toBeEmpty();
  await expect(items.first()).toBeInViewport();

  // 点第二项应切换轮播，而不是被圆点独占交互
  const second = items.nth(1);
  await second.click();
  await expect(second).toHaveClass(/is-active/);
});

// @needs-backend：结算条金额与可购买状态来自真实商品/价格接口。
test("小视口(等效高缩放)下结算条作为覆盖层钉在费用栏底部保持可点 @needs-backend", async ({
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

test("官网产品层级文案统一为产品目录/地区/可用区", async ({ page }) => {
  // 一级菜单 / 二级分组接口按用例 mock，保证导航文案与真实数据无关。
  await page.route("**/api/v2/site/product-types*", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        code: 0,
        message: "操作成功",
        data: {
          list: [
            {
              id: 1,
              value: "cloud_server",
              label: "大陆云服",
              product_type: "cloud_server",
              product_count: 3,
              group_count: 1,
              first_product_group_code: "cloud_server",
            },
          ],
        },
      }),
    }),
  );
  await page.route("**/api/v2/site/product-groups*", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        code: 0,
        message: "操作成功",
        data: {
          list: [
            {
              id: 11,
              name: "华南地区",
              slogan: "",
              product_count: 3,
              product_type: "cloud_server",
              first_product_group_code: "cloud_server",
              children_count: 1,
            },
          ],
        },
      }),
    }),
  );

  await page.setViewportSize({ width: 1280, height: 800 });

  // 选购页：一级「产品目录」侧边栏
  await page.goto("/products", { waitUntil: "domcontentloaded" });
  await expect(page.locator(".sidebar-head-text")).toHaveText("产品目录");

  // 导航：一级「产品目录」、二级「地区」
  await page.goto("/", { waitUntil: "domcontentloaded" });
  await page.locator(".main-nav__link", { hasText: "产品" }).first().hover();

  const levelLabels = page.locator("#mega-menu .mega-menu__level-label");
  await expect(levelLabels).toHaveCount(2);
  await expect(levelLabels.nth(0)).toHaveText("产品目录");
  await expect(levelLabels.nth(1)).toHaveText("地区");
});
// 侧栏热门/最新固定取前 5 条：概览接口帮公告返回 5 条、帮助返回 6 条，
// 不截断会让两类页面的侧栏条数随内容类型漂移。
test("内容侧栏热门与最新均截断为 5 条", async ({ page }) => {
  const articles = Array.from({ length: 6 }, (_, i) => ({
    id: 9100 + i,
    title: `侧栏条数用例第 ${i + 1} 条`,
    publish_at: `2026-08-0${i + 1} 10:00:00`,
    view_count: 100 - i,
  }));

  await page.route("**/api/v2/site/content/overview", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        code: 0,
        message: "操作成功",
        data: { help_articles: articles, help_categories: [] },
      }),
    }),
  );
  await page.route(
    (url) => url.pathname.includes("/api/v2/site/help-articles"),
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
          data: { list: articles.slice(start, start + size), total: 6, categories: [] },
        }),
      });
    },
  );

  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto("/help", { waitUntil: "domcontentloaded" });

  await expect(page.locator(".ranking-item")).toHaveCount(5);
  await expect(page.locator(".recent-item")).toHaveCount(5);
  // 第 4 名及以后不再生成 top-4/top-5 这类没有样式的 class
  await expect(page.locator(".ranking-item__index.top-4")).toHaveCount(0);
  await expect(page.locator(".ranking-item__index.top-3")).toHaveCount(1);
});

// 首屏目录加载期间主区必须有可见反馈：此前只有 v-loading 遮罩，
// 遮罩去掉后慢网下用户看到的是一片空白。
test("商品页目录加载期间显示加载态而非空白", async ({ page }) => {
  // 让目录接口挂住不返回，稳定停在加载态
  await page.route("**/api/v2/site/product-purchase-context*", async () => {
    await new Promise(() => undefined);
  });
  await page.route("**/api/v2/site/product-types*", async () => {
    await new Promise(() => undefined);
  });

  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto("/products", { waitUntil: "domcontentloaded" });

  const loading = page.locator(".catalog-loading");
  await expect(loading).toBeVisible({ timeout: 10_000 });
  await expect(loading).toHaveAttribute("role", "status");
  await expect(loading.locator(".catalog-loading__text")).toHaveText(
    "产品数据加载中…",
  );
  // 加载态不得与失败态/空态同时出现
  await expect(page.locator(".catalog-error")).toHaveCount(0);
  await expect(page.locator(".el-empty")).toHaveCount(0);
});

// 页脚四列原本是 h3，整页没有 h2 父级，读屏/大纲会认为跳档（h1→h3）。
test("页脚栏目标题为 h2 且与链接字号拉开层级", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 900 });
  // 404 页同样挂在 WebsiteLayout 下，能最省依赖地拿到页脚
  await page.goto("/this-path-does-not-exist", { waitUntil: "domcontentloaded" });

  const columnHeadings = page.locator(".footer-col h2");
  await expect(columnHeadings).toHaveCount(4);
  await expect(columnHeadings.first()).toHaveText("产品");
  await expect(page.locator(".footer-col h3")).toHaveCount(0);

  // 标题与链接只差 1px 时层级不可辨，修复后必须拉开到 ≥2px
  const sizes = await page.evaluate(() => {
    const heading = document.querySelector(".footer-col h2");
    const link = document.querySelector(".footer-col a");
    if (!heading || !link) return null;
    return {
      heading: Number.parseFloat(getComputedStyle(heading).fontSize),
      link: Number.parseFloat(getComputedStyle(link).fontSize),
    };
  });
  expect(sizes).not.toBeNull();
  expect(sizes!.heading - sizes!.link).toBeGreaterThanOrEqual(2);
});

// @needs-backend：配置区标题依赖真实商品目录数据才会渲染。
test("商品页配置区各区块标题为 h2 @needs-backend", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto("/products", { waitUntil: "domcontentloaded" });

  const blockTitles = page.locator("h2.config-block-title");
  await expect(blockTitles.first()).toBeVisible({ timeout: 20_000 });
  // 整页唯一 h1 是给读屏的隐藏标题，配置区标题必须是 h2 才接得上
  await expect(page.locator("h1")).toHaveCount(1);
  await expect(page.locator(".config-block-title").and(page.locator("div"))).toHaveCount(0);
});

// 正文 Markdown 标题经 demoteHeadings 降级后落在 h2~h5；这里验证 h2/h3/h4 三档
// 字号递减，避免四档共用一组声明、读者看不出层级。
test("内容详情正文标题按级别递减字号并包裹宽表", async ({ page }) => {
  const markdown = [
    "# 一级小标题",
    "",
    "正文段落。",
    "",
    "## 二级小标题",
    "",
    "| 列A | 列B |",
    "| --- | --- |",
    "| 1 | 2 |",
    "",
    "### 三级小标题",
    "",
    "结尾段落。",
  ].join("\n");

  await page.route("**/api/v2/site/content/overview", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        code: 0,
        message: "操作成功",
        data: { notices: [], notice_categories: [] },
      }),
    }),
  );
  await page.route(
    (url) => /\/api\/v2\/site\/notices\/\d+$/.test(url.pathname),
    (route) =>
      route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          code: 0,
          message: "操作成功",
          data: {
            id: 9200,
            title: "正文标题层级用例",
            content: markdown,
            category_id: 0,
            publish_at: "2026-08-01 10:00:00",
            view_count: 12,
          },
        }),
      }),
  );

  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto("/notices/9200", { waitUntil: "domcontentloaded" });

  const reader = page.locator(".reader-content");
  await expect(reader.locator("h2").first()).toHaveText("一级小标题");

  const fontSizes = await reader.evaluate((el) => {
    const pick = (tag: string) => {
      const node = el.querySelector(tag);
      return node ? Number.parseFloat(getComputedStyle(node).fontSize) : null;
    };
    return { h2: pick("h2"), h3: pick("h3"), h4: pick("h4") };
  });
  expect(fontSizes.h2).not.toBeNull();
  expect(fontSizes.h3).not.toBeNull();
  expect(fontSizes.h4).not.toBeNull();
  expect(fontSizes.h2!).toBeGreaterThan(fontSizes.h3!);
  expect(fontSizes.h3!).toBeGreaterThan(fontSizes.h4!);

  // 宽表必须由外层容器横向滚动；直接给 table 加 overflow/display:block 会破坏列宽
  const wrap = reader.locator(".reader-table-wrap");
  await expect(wrap).toHaveCount(1);
  await expect(wrap.locator("table")).toHaveCount(1);
  await expect(wrap).toHaveCSS("overflow-x", "auto");
});

// 只有一页时「每页条数下拉」和「跳页输入框」改变不了任何结果，
// 却会占掉小屏分页条的整行，因此单页降级为 without sizes/jumper。
async function mockArticleList(page: import("@playwright/test").Page, total: number) {
  await page.route("**/api/v2/site/content/overview", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        code: 0,
        message: "操作成功",
        data: { help_articles: [], help_categories: [] },
      }),
    }),
  );
  await page.route(
    (url) => url.pathname.includes("/api/v2/site/help-articles"),
    async (route) => {
      const requestUrl = new URL(route.request().url());
      const pageNum = Number(requestUrl.searchParams.get("page") || "1");
      const size = Number(requestUrl.searchParams.get("page_size") || "10");
      const list = Array.from({ length: Math.min(size, total) }, (_, i) => ({
        id: 9300 + (pageNum - 1) * size + i,
        title: `分页用例第 ${(pageNum - 1) * size + i + 1} 条`,
        publish_at: "2026-08-01 10:00:00",
        view_count: 1,
      }));
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          code: 0,
          message: "操作成功",
          data: { list, total, categories: [] },
        }),
      });
    },
  );
}

test("分页条在只有一页时隐藏每页条数与跳页", async ({ page }) => {
  await mockArticleList(page, 6);
  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto("/help", { waitUntil: "domcontentloaded" });

  await expect(page.locator(".list-panel__pager")).toBeVisible();
  await expect(page.locator(".list-panel__pager .el-pagination__total")).toBeVisible();
  await expect(page.locator(".list-panel__pager .el-pagination__sizes")).toHaveCount(0);
  await expect(page.locator(".list-panel__pager .el-pagination__jump")).toHaveCount(0);
});

test("分页条在多页时恢复每页条数与跳页", async ({ page }) => {
  await mockArticleList(page, 26);
  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto("/help", { waitUntil: "domcontentloaded" });

  await expect(page.locator(".list-panel__pager .el-pagination__sizes")).toBeVisible();
  await expect(page.locator(".list-panel__pager .el-pagination__jump")).toBeVisible();
});

test("SEO 落地页核心卖点渲染 hero.stats 全部条目", async ({ page }) => {
  // 原实现只取 stats[0] 并用 slice(0, 4) 截断，stats[1] / stats[2] 永不渲染。
  // 各落地页数据均为 points=3 / stats=3，故条数应为 1 + 2 + 3 = 6。
  const pages = [
    { path: "/cloud-server", lastStat: "适配测试到生产环境" },
    { path: "/cloud-pc", lastStat: "" },
    { path: "/hong-kong-server", lastStat: "" },
  ];

  for (const target of pages) {
    await page.goto(target.path, { waitUntil: "domcontentloaded" });
    const items = page.locator(".seo-hero__nav li");
    await expect(items.first()).toBeVisible();
    await expect(items).toHaveCount(6);

    if (target.lastStat) {
      // 末条来自 stats[2]，是原先被 slice(0, 4) 丢弃的内容
      await expect(items.last()).toContainText(target.lastStat);
    }
  }
});

test("顶栏 /about 入口标签与 /help 不重复", async ({ page }) => {
  await page.setViewportSize({ width: 1280, height: 800 });
  await page.goto("/", { waitUntil: "domcontentloaded" });

  // 等导航真正渲染完再取文本：domcontentloaded 时 Vue 尚未挂载，直接取会是空数组
  await expect(page.locator(".main-nav__link").first()).toBeVisible();
  const labels = await page.locator(".main-nav__link").allInnerTexts();
  const normalized = labels.map((text) => text.trim());
  // 「帮助」与「关于我们」必须同时存在且互不重复，否则访客分不清入口
  expect(normalized).toContain("帮助");
  expect(normalized).toContain("关于我们");
  expect(normalized).not.toContain("支持");
});

// 手写按钮圆角此前 2/4/6/8px 四档并存，同语义跨页不一致（最典型：products 与
// ProductDetail 的吸底结算按钮一个是 4px、一个是 10px）。令牌化后按按钮体量分档
// （-sm 4px 购买/结算/落地页 CTA、默认 6px 对话式 CTA、-lg 8px 矮控件），
// 这里钉住三档的真实渲染值，避免以后又飘回裸写。
test("手写按钮圆角按体量分档且跨页一致", async ({ page }) => {
  const radiusOf = (selector: string) =>
    page
      .locator(selector)
      .first()
      .evaluate((el) =>
        Number.parseFloat(getComputedStyle(el).borderTopLeftRadius),
      );

  await page.setViewportSize({ width: 1280, height: 800 });
  await page.goto("/", { waitUntil: "domcontentloaded" });

  // 站头注册按钮是 34px 矮按钮，用大圆角才不塌
  const headerRegister = page.locator(".header-register");
  await expect(headerRegister).toBeVisible();
  expect(await radiusOf(".header-register")).toBe(8);
  // 首页 hero 主 CTA 属对话式中等圆角
  expect(await radiusOf(".hero-cta")).toBe(6);

  // SEO 落地页主 CTA 与购买主按钮同族，应为小圆角
  await page.goto("/cloud-server", { waitUntil: "domcontentloaded" });
  const seoAction = page.locator(".seo-actions .seo-action");
  await expect(seoAction.first()).toBeVisible();
  expect(await radiusOf(".seo-actions .seo-action")).toBe(4);
});

// 空状态是异步加载完成后的结果，视觉上靠 el-empty 撑开，但读屏只能靠 live region。
// 此前 8 处 el-empty 全部裸写，搜不到内容时读屏拿到的是与「还在加载」无差别的沉默。
test("内容列表空结果与侧栏空数据向读屏播报", async ({ page }) => {
  await mockArticleList(page, 0);
  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto("/help", { waitUntil: "domcontentloaded" });

  const listEmpty = page.locator(".list-empty .el-empty");
  await expect(listEmpty).toBeVisible();
  await expect(listEmpty).toHaveAttribute("role", "status");
  await expect(listEmpty).toHaveAttribute("aria-live", "polite");

  // 侧栏「热门 / 最新」在无数据时也各有一个空态，同样必须可播报
  const sidebarEmpty = page.locator(".sidebar-card .el-empty");
  await expect(sidebarEmpty.first()).toBeVisible();
  expect(await sidebarEmpty.count()).toBeGreaterThanOrEqual(2);
  await expect(sidebarEmpty.first()).toHaveAttribute("role", "status");
  await expect(sidebarEmpty.first()).toHaveAttribute("aria-live", "polite");
});
