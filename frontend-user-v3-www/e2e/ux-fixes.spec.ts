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
  await expect(page.locator(".t-empty")).toHaveCount(0);
});

// 商品详情页的加载遮罩从 v-loading 指令改为 <t-loading> 组件包裹：
// TDesign 的 v-loading 指令需要 app.use(Loading) 注册才生效，本站为首屏体积没有全量注册插件。
// 这条用例钉住改造后的契约——加载期间 .pd-page 仍是页面根节点（版式类挂在包裹层上），
// 遮罩层 .t-loading--full 铺满整页；加载结束后遮罩必须撤掉，不能残留挡住操作。
test("商品详情页加载态用 t-loading 遮罩铺满整页且结束后撤除", async ({ page }) => {
  // 用可控闸门挂住详情接口，稳定停在加载态；断言完再放行
  let releaseDetail = () => undefined as void;
  const detailGate = new Promise<void>((resolve) => {
    releaseDetail = () => resolve();
  });

  await page.route("**/api/v2/site/products/123*", async (route) => {
    await detailGate;
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        code: 0,
        message: "ok",
        data: {
          product: {
            id: 123,
            name: "E2E 加载态云服务器",
            // h1 走 resolveProductDisplayName(product)，读的是 display_name 系列字段；
            // name 只用于运行时注入 meta title。二者都补齐才能同时覆盖标题与遮罩撤除。
            display_name: "E2E 加载态云服务器",
            description: "E2E 商品描述文案",
            price: 100,
            billing_cycles: [],
            config: [],
            siblings: [],
          },
        },
      }),
    });
  });

  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto("/products/123", { waitUntil: "domcontentloaded" });

  const shell = page.locator(".pd-page");
  await expect(shell).toBeVisible();
  // .pd-page 落在 t-loading 的包裹层上（class 被 mergeProps 合并到 .t-loading__parent）
  await expect(page.locator(".t-loading__parent.pd-page")).toHaveCount(1);

  const overlay = page.locator(".pd-page > .t-loading--full");
  await expect(overlay).toBeVisible();
  expect(await overlay.evaluate((el) => getComputedStyle(el).zIndex)).toBe("3500");

  const shellBox = await shell.boundingBox();
  const overlayBox = await overlay.boundingBox();
  expect(shellBox && overlayBox).toBeTruthy();
  // 遮罩铺满整页：宽度一致、高度覆盖根节点
  expect(Math.abs(overlayBox!.width - shellBox!.width)).toBeLessThanOrEqual(1);
  expect(overlayBox!.height).toBeGreaterThanOrEqual(shellBox!.height - 1);

  releaseDetail();
  await expect(overlay).toHaveCount(0, { timeout: 15_000 });
  // 加载结束后页面正常渲染商品名，说明遮罩撤除且数据已接入
  await expect(page.getByText("E2E 加载态云服务器").first()).toBeVisible();
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
  // TDesign Pagination 的 DOM 类名：总条数 .t-pagination__total、每页条数选择器
  // .t-pagination__select、跳页 .t-pagination__jump（见 es/pagination/hooks/usePaginationClasses.mjs）。
  // showPageSize / showJumper 为 false 时组件直接不渲染对应节点，因此用 toHaveCount(0) 断言。
  await expect(page.locator(".list-panel__pager .t-pagination__total")).toBeVisible();
  await expect(page.locator(".list-panel__pager .t-pagination__select")).toHaveCount(0);
  await expect(page.locator(".list-panel__pager .t-pagination__jump")).toHaveCount(0);
});

test("分页条在多页时恢复每页条数与跳页", async ({ page }) => {
  await mockArticleList(page, 26);
  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto("/help", { waitUntil: "domcontentloaded" });

  await expect(page.locator(".list-panel__pager .t-pagination__select")).toBeVisible();
  await expect(page.locator(".list-panel__pager .t-pagination__jump")).toBeVisible();
});

test("侧栏重试的文字按钮可键盘聚焦且有可见焦点环", async ({ page }) => {
  // 迁移前是 link 型按钮，迁移后改 t-button variant="text"：文字按钮最容易在换组件体系时
  // 丢掉焦点可见性（WCAG 2.4.7）。这里让侧栏接口失败以渲染「重试」，再键盘聚焦它。
  await mockArticleList(page, 6);
  await page.route("**/api/v2/site/help-articles/hot*", (route) =>
    route.fulfill({ status: 500, body: "" }),
  );
  await page.route("**/api/v2/site/help-articles/latest*", (route) =>
    route.fulfill({ status: 500, body: "" }),
  );
  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto("/help", { waitUntil: "domcontentloaded" });

  const retry = page.locator(".sidebar-card .t-button--variant-text").first();
  await expect(retry).toBeVisible({ timeout: 15_000 });
  // 用键盘 Tab 过去会受页面顺序影响，直接 focus 只验「聚焦后有没有可见焦点环」
  await retry.evaluate((el) => (el as HTMLElement).focus());
  await expect
    .poll(() =>
      retry.evaluate((el) => {
        const style = getComputedStyle(el);
        return { outlineWidth: style.outlineStyle !== "none" ? style.outlineWidth : "0px", boxShadow: style.boxShadow };
      }),
    )
    .toEqual(
      expect.objectContaining({
        outlineWidth: expect.not.stringMatching(/^0(px)?$/),
      }),
    );
  // 焦点确实落在该按钮上（防止上面 poll 命中的是别的元素的样式）
  expect(
    await retry.evaluate((el) => document.activeElement === el),
  ).toBe(true);
});

test("优惠券下拉的清空图标能重置为未选并触发原价询价", async ({ page }) => {
  // clearable 是 t-select 自带的清空入口，与自建的「移除」按钮是两条独立路径，
  // 之前没有任何用例覆盖它。清空必须走 onChange(undefined) → 归零 → 重新询价。
  const catalog = {
    effective_product_group_id: 31,
    effective_product_group_level: 2,
    children: [],
    items_by_group: [],
  };
  await page.route("**/api/v2/site/product-purchase-context*", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        code: 0,
        message: "操作成功",
        data: {
          types: [
            { id: 1, value: "vps", label: "云服务器", product_type: "cloud_server", first_product_group_code: "vps" },
          ],
          root_groups: [
            { id: 31, name: "襄阳", product_count: 1, product_type: "cloud_server", first_product_group_code: "vps", effective_product_group_id: 31, effective_product_group_level: 2 },
          ],
          catalog,
        },
      }),
    }),
  );
  await page.route("**/api/v2/site/products/123/stock*", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ code: 0, message: "操作成功", data: { stock: 10 } }),
    }),
  );
  await page.route("**/api/v2/site/products/123", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        code: 0,
        message: "操作成功",
        data: {
          id: 123,
          name: "E2E 清空优惠券",
          display_name: "E2E 清空优惠券",
          price: 48,
          pricing_entries: [{ cycle: "monthly", label: "月付", amount: "48.00" }],
          billing_cycles: [],
          config: [],
          siblings: [],
        },
      }),
    }),
  );

  const quoteCouponIds: number[] = [];
  await page.route("**/api/v2/site/products/123/quote*", async (route) => {
    const body = route.request().postDataJSON() as { user_coupon_id?: number };
    const appliedCouponId = Number(body?.user_coupon_id || 0);
    quoteCouponIds.push(appliedCouponId);
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        code: 0,
        message: "操作成功",
        data: {
          product_id: 123,
          billing_cycle: "monthly",
          base_amount: "48.00",
          total_amount: appliedCouponId ? "38.00" : "48.00",
          quantity: 1,
          coupon: appliedCouponId
            ? { id: appliedCouponId, code: "NEW10", name: "新客立减", discount_label: "立减 ¥10", discount_amount: "10.00" }
            : null,
          user_coupon_id: appliedCouponId,
          available_coupons: [{ id: 501, name: "新客立减", discount_label: "立减 ¥10" }],
          quote_token: "quote_e2e_token",
          quote_expires_at: "2099-01-01T00:00:00+08:00",
          items: [],
        },
      }),
    });
  });

  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto("/products", { waitUntil: "domcontentloaded" });

  const couponSelect = page.locator(".coupon-panel .t-select");
  await expect(couponSelect).toBeVisible({ timeout: 15_000 });
  await couponSelect.click();
  await page.locator(".t-select__dropdown .t-select-option").first().click();
  await expect(couponSelect.locator("input")).toHaveValue("新客立减 · 立减 ¥10");
  await expect.poll(() => quoteCouponIds[quoteCouponIds.length - 1]).toBe(501);

  // hover 才显示清空图标，先移进去再点
  await couponSelect.hover();
  const clearIcon = couponSelect.locator(".t-input__suffix-clear");
  await expect(clearIcon).toBeVisible();
  await clearIcon.click();

  await expect(couponSelect.locator("input")).toHaveValue("");
  // 清空必须真的触发一次不带券的询价，否则界面显示未选但价格还算着折扣
  await expect.poll(() => quoteCouponIds[quoteCouponIds.length - 1]).toBe(0);
});

test("分页条跳页输入能翻到指定页", async ({ page }) => {
  // 跳页是唯一「输入页码后直接换页」的入口，只看 .t-pagination__jump 是否存在
  // 证明不了它接上了。这里输入 3 回车，断言列表内容真的换到第 3 页，
  // 并且不是靠在第 1 页里截断——total=137 时第 3 页首条应是序号 21。
  await mockArticleList(page, 137);
  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto("/help", { waitUntil: "domcontentloaded" });

  const jumpInput = page.locator(".list-panel__pager .t-pagination__input input");
  await expect(jumpInput).toBeVisible();
  await jumpInput.fill("3");
  await jumpInput.press("Enter");

  await expect(
    page.locator(".list-panel .list-item__title").first(),
  ).toContainText("分页用例第 21 条");
  // 页码同步到 3，避免「列表换了但分页条还显示第 1 页」这种视觉错位
  await expect(
    page.locator(".list-panel__pager .t-pagination__number.t-is-current"),
  ).toHaveText("3");
});

test("窄屏下多页分页条换行而不被列表容器裁切", async ({ page }) => {
  // 迁移后分页条总条数位改用 TDesign：根节点 width:100% + .t-pagination__total{flex:1}，
  // 站点为保持「总条数与页码靠右」的版式补了 :deep(.t-pagination){width:auto}。收成内容宽度后，
  // 6 个子项在 375 宽下合计 740px，而 TDesign 根规则是 white-space:nowrap 且没有 flex-wrap，
  // 叠加 .list-panel{overflow:hidden} 会把溢出部分静默裁掉 —— 总条数与跳页完全不可见、
  // 「下一页」按在裁切区外点不到，且 documentElement.scrollWidth 恒等于视口宽，常规溢出检查查不出来。
  // 因此这里同时断言「父容器内全部子项可见」与「每个子项的中点命中自身」。
  await mockArticleList(page, 137);
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto("/help", { waitUntil: "domcontentloaded" });

  const pager = page.locator(".list-panel__pager");
  await expect(pager).toBeVisible();
  await expect(pager.locator(".t-pagination__total")).toBeVisible();
  await expect(pager.locator(".t-pagination__jump")).toBeVisible();
  // 分页条在长列表底部、初始不在视口内，elementFromPoint 对视口外的坐标一律返回 null，
  // 必须先滚到可见再判定命中的是谁。
  await pager.scrollIntoViewIfNeeded();
  await page.waitForTimeout(150);

  const layout = await pager.evaluate((el) => {
    const pagination = el.querySelector(".t-pagination") as HTMLElement | null;
    if (!pagination) return null;
    const box = el.getBoundingClientRect();
    const children = Array.from(pagination.children) as HTMLElement[];
    return {
      pagerWidth: Math.round(box.width),
      paginationHeight: Math.round(pagination.getBoundingClientRect().height),
      // 子项必须整体落在父容器内（左不越界、右不越界）
      inside: children.map((child) => {
        const rect = child.getBoundingClientRect();
        return rect.left >= box.left - 1 && rect.right <= box.right + 1;
      }),
      // 子项中点必须命中自身或其后代，否则说明被祖先的 overflow 裁掉、点到别处去了
      hitsSelf: children.map((child) => {
        const rect = child.getBoundingClientRect();
        const x = Math.min(Math.max(rect.left + rect.width / 2, 0), window.innerWidth - 1);
        const y = Math.min(Math.max(rect.top + rect.height / 2, 0), window.innerHeight - 1);
        const hit = document.elementFromPoint(x, y);
        return Boolean(hit && child.contains(hit));
      }),
      childCount: children.length,
    };
  });

  expect(layout).not.toBeNull();
  expect(layout!.childCount).toBeGreaterThanOrEqual(6);
  expect(layout!.inside.every(Boolean)).toBe(true);
  expect(layout!.hitsSelf.every(Boolean)).toBe(true);
  // 换行生效的副证：两行以上，高度不再是单行的 32px
  expect(layout!.paginationHeight).toBeGreaterThan(32);
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

// 空状态是异步加载完成后的结果，视觉上靠 t-empty 撑开，但读屏只能靠 live region。
// 此前 8 处空态全部裸写，搜不到内容时读屏拿到的是与「还在加载」无差别的沉默。
test("内容列表空结果与侧栏空数据向读屏播报", async ({ page }) => {
  await mockArticleList(page, 0);
  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto("/help", { waitUntil: "domcontentloaded" });

  const listEmpty = page.locator(".list-empty .t-empty");
  await expect(listEmpty).toBeVisible();
  await expect(listEmpty).toHaveAttribute("role", "status");
  await expect(listEmpty).toHaveAttribute("aria-live", "polite");
  // 文案位必须落在 title：t-empty 不传 title 时会回落到 locale 的「暂无数据」
  // 占住主标题位，与 description 叠成两行，空态语义就丢了。
  await expect(listEmpty.locator(".t-empty__title")).toHaveText("暂无帮助内容");
  // 侧栏两处空态是写死文案的，同样钉住
  await expect(
    page.locator(".sidebar-card .t-empty .t-empty__title").first(),
  ).toHaveText("暂无数据");

  // 侧栏「热门 / 最新」在无数据时也各有一个空态，同样必须可播报
  const sidebarEmpty = page.locator(".sidebar-card .t-empty");
  await expect(sidebarEmpty.first()).toBeVisible();
  expect(await sidebarEmpty.count()).toBeGreaterThanOrEqual(2);
  await expect(sidebarEmpty.first()).toHaveAttribute("role", "status");
  await expect(sidebarEmpty.first()).toHaveAttribute("aria-live", "polite");
});
// 选购页的「地区 / 可用区」两级目录在迁移后由 TDesign Tabs 的 :list 驱动渲染
// （上一版组件体系是子组件声明式写法）。:list 驱动时标签文案、激活态
// 与点击后的切换请求都走 TDesign 自己的实现，任何一处接错都会让分类点击失效。
test("选购页地区与可用区用 TDesign Tabs 渲染并可切换分组", async ({ page }) => {
  const groups = [
    {
      id: 31,
      name: "襄阳",
      product_count: 1,
      product_type: "cloud_server",
      first_product_group_code: "vps",
      effective_product_group_id: 31,
      effective_product_group_level: 2,
    },
    {
      id: 32,
      name: "华南",
      product_count: 1,
      product_type: "cloud_server",
      first_product_group_code: "vps",
      effective_product_group_id: 32,
      effective_product_group_level: 2,
    },
  ];
  const catalogOf = (groupId: number, childName: string) => ({
    effective_product_group_id: groupId,
    effective_product_group_level: 2,
    children: [
      {
        id: groupId * 10 + 1,
        name: childName,
        product_count: 1,
        product_type: "cloud_server",
        first_product_group_code: "vps",
        effective_product_group_id: groupId * 10 + 1,
        effective_product_group_level: 3,
      },
    ],
    items_by_group: [],
  });

  await page.route("**/api/v2/site/product-purchase-context*", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        code: 0,
        message: "操作成功",
        data: {
          types: [
            {
              id: 1,
              value: "vps",
              label: "云服务器",
              product_type: "cloud_server",
              first_product_group_code: "vps",
            },
          ],
          root_groups: groups,
          catalog: catalogOf(31, "高宽"),
        },
      }),
    }),
  );

  // 切换分组时前端会先并行拉 children（只为尽快画出可用区标签），再由 catalog 补齐商品。
  // 这里一并 mock，否则会打到真实后端（CI 无后端时变成未处理请求）。
  await page.route("**/api/v2/site/product-groups/*/children*", (route) => {
    const matched = route.request().url().match(/product-groups\/(\d+)\/children/);
    const groupId = Number(matched?.[1] || 31);
    const catalog = catalogOf(groupId, groupId === 32 ? "深圳" : "高宽");

    return route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        code: 0,
        message: "操作成功",
        data: { list: catalog.children },
      }),
    });
  });

  // 切到第二个地区时前端会按新分组重新拉目录：这条请求同时是「change 事件确实接上了」的证据
  let groupCatalogRequests = 0;
  await page.route("**/api/v2/site/product-groups/32/catalog*", (route) => {
    groupCatalogRequests += 1;
    return route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        code: 0,
        message: "操作成功",
        data: catalogOf(32, "深圳"),
      }),
    });
  });

  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto("/products", { waitUntil: "domcontentloaded" });

  const regionTabs = page.locator(".catalog-tabs").first();
  const regionItems = regionTabs.locator(".t-tabs__nav-item");
  await expect(regionItems).toHaveCount(2);
  await expect(regionItems.nth(0)).toContainText("襄阳");
  await expect(regionItems.nth(1)).toContainText("华南");
  // 激活态由 TDesign 用 t-is-active 标记，样式挂在同一条目上
  await expect(regionItems.nth(0)).toHaveClass(/t-is-active/);

  // 可用区是第二级 Tabs，来自首个分组的 children
  const childTabs = page.locator(".catalog-tabs--child");
  await expect(childTabs.locator(".t-tabs__nav-item")).toHaveCount(1);
  await expect(childTabs.locator(".t-tabs__nav-item").first()).toContainText(
    "高宽",
  );

  await regionItems.nth(1).click();
  await expect(regionItems.nth(1)).toHaveClass(/t-is-active/);
  await expect(regionItems.nth(0)).not.toHaveClass(/t-is-active/);
  await expect.poll(() => groupCatalogRequests).toBeGreaterThan(0);
  await expect(childTabs.locator(".t-tabs__nav-item").first()).toContainText(
    "深圳",
  );
});
// 费用栏的优惠券选择在迁移前是子组件声明式写法，迁移后改为 t-select
// 的 options 数据驱动（label 由「名称 · 折扣」拼接）。这条用例钉住下拉能展开、
// 选项文案来自报价接口的 available_coupons，且选中后把 user_coupon_id 带回询价请求。
test("选购页优惠券用 TDesign Select 渲染并回传所选券", async ({ page }) => {
  const catalog = {
    effective_product_group_id: 31,
    effective_product_group_level: 2,
    children: [],
    items_by_group: [
      {
        effective_product_group_id: 31,
        products: [
          {
            id: 123,
            name: "E2E 优惠券云服务器",
            display_name: "E2E 优惠券云服务器",
            product_type: "cloud_server",
            first_product_group_code: "vps",
            effective_product_group_id: 31,
            pricing_entries: [
              { cycle: "monthly", label: "月付", amount: "48.00" },
            ],
          },
        ],
      },
    ],
  };

  await page.route("**/api/v2/site/product-purchase-context*", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        code: 0,
        message: "操作成功",
        data: {
          types: [{ id: 1, value: "vps", label: "云服务器", product_type: "cloud_server", first_product_group_code: "vps" }],
          root_groups: [
            { id: 31, name: "襄阳", product_count: 1, product_type: "cloud_server", first_product_group_code: "vps", effective_product_group_id: 31, effective_product_group_level: 2 },
          ],
          catalog,
        },
      }),
    }),
  );
  await page.route("**/api/v2/site/products/123/stock*", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ code: 0, message: "操作成功", data: { stock: 10 } }),
    }),
  );
  await page.route("**/api/v2/site/products/123*", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        code: 0,
        message: "操作成功",
        data: {
          product: {
            id: 123,
            name: "E2E 优惠券云服务器",
            display_name: "E2E 优惠券云服务器",
            price: 48,
            // buildPricingEntries 从 detail 的 pricing_entries 取计费周期；
            // 缺这一项时 selectedCycle 为空，executeQuote 直接短路，永远不会询价
            pricing_entries: [
              { cycle: "monthly", label: "月付", amount: "48.00" },
            ],
            billing_cycles: [],
            config: [],
            siblings: [],
          },
        },
      }),
    }),
  );

  // 报价接口：可用优惠券两张，选中后要带上 user_coupon_id 再询价一次
  const quoteCouponIds: number[] = [];
  await page.route("**/api/v2/site/products/123/quote*", async (route) => {
    const body = route.request().postDataJSON() as { user_coupon_id?: number };
    quoteCouponIds.push(Number(body?.user_coupon_id || 0));
    const appliedCouponId = Number(body?.user_coupon_id || 0);
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        code: 0,
        message: "操作成功",
        data: {
          product_id: 123,
          billing_cycle: "monthly",
          base_amount: "48.00",
          total_amount: appliedCouponId ? "38.00" : "48.00",
          quantity: 1,
          coupon: appliedCouponId
            ? { id: appliedCouponId, code: "NEW10", name: "新客立减", discount_label: "立减 ¥10", discount_amount: "10.00" }
            : null,
          user_coupon_id: appliedCouponId,
          available_coupons: [
            { id: 501, name: "新客立减", discount_label: "立减 ¥10" },
            { id: 502, name: "续费折扣", discount_label: "立减 ¥5" },
          ],
          quote_token: "quote_e2e_token",
          quote_expires_at: "2099-01-01T00:00:00+08:00",
          items: [],
        },
      }),
    });
  });

  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto("/products", { waitUntil: "domcontentloaded" });

  const couponSelect = page.locator(".coupon-panel .t-select");
  await expect(couponSelect).toBeVisible({ timeout: 15_000 });
  // TDesign 的 placeholder 落在内部 input 的 placeholder 属性上，不是文本节点
  await expect(couponSelect.locator("input")).toHaveAttribute(
    "placeholder",
    "请选择优惠券",
  );

  // 展开下拉：选项文案来自 available_coupons 的「名称 · 折扣」
  await couponSelect.click();
  const options = page.locator(".t-select__dropdown .t-select-option");
  await expect(options).toHaveCount(2);
  await expect(options.nth(0)).toContainText("新客立减 · 立减 ¥10");
  await expect(options.nth(1)).toContainText("续费折扣 · 立减 ¥5");

  await options.nth(0).click();
  await expect(couponSelect.locator("input")).toHaveValue("新客立减 · 立减 ¥10");
  // 选中后必须带着券再询价，否则优惠永远不会生效
  await expect
    .poll(() => quoteCouponIds[quoteCouponIds.length - 1])
    .toBe(501);
});
// 移除优惠券是「不可撤销 + 立刻改价」的破坏性操作，迁移前走上一版组件体系的确认弹框，
// 迁移后是 DialogPlugin.confirm。两者 DOM 与返回语义完全不同（Promise resolve vs 回调），
// 这条用例钉住三条契约：确认后真的移除并重新按原价询价、取消后券保持不变、弹层用完即销毁。
test("移除优惠券的二次确认走 TDesign Dialog 且确认与取消语义正确", async ({ page }) => {
  const catalog = {
    effective_product_group_id: 31,
    effective_product_group_level: 2,
    children: [],
    items_by_group: [
      {
        effective_product_group_id: 31,
        products: [
          {
            id: 123,
            name: "E2E 优惠券云服务器",
            display_name: "E2E 优惠券云服务器",
            product_type: "cloud_server",
            first_product_group_code: "vps",
            effective_product_group_id: 31,
            pricing_entries: [
              { cycle: "monthly", label: "月付", amount: "48.00" },
            ],
          },
        ],
      },
    ],
  };

  await page.route("**/api/v2/site/product-purchase-context*", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        code: 0,
        message: "操作成功",
        data: {
          types: [{ id: 1, value: "vps", label: "云服务器", product_type: "cloud_server", first_product_group_code: "vps" }],
          root_groups: [
            { id: 31, name: "襄阳", product_count: 1, product_type: "cloud_server", first_product_group_code: "vps", effective_product_group_id: 31, effective_product_group_level: 2 },
          ],
          catalog,
        },
      }),
    }),
  );
  await page.route("**/api/v2/site/products/123/stock*", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ code: 0, message: "操作成功", data: { stock: 10 } }),
    }),
  );
  await page.route("**/api/v2/site/products/123*", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        code: 0,
        message: "操作成功",
        data: {
          product: {
            id: 123,
            name: "E2E 优惠券云服务器",
            display_name: "E2E 优惠券云服务器",
            price: 48,
            pricing_entries: [
              { cycle: "monthly", label: "月付", amount: "48.00" },
            ],
            billing_cycles: [],
            config: [],
            siblings: [],
          },
        },
      }),
    }),
  );

  // 报价接口：带券时 38 元，不带券时 48 元，移除后必须回到原价
  const quoteCouponIds: number[] = [];
  await page.route("**/api/v2/site/products/123/quote*", async (route) => {
    const body = route.request().postDataJSON() as { user_coupon_id?: number };
    const appliedCouponId = Number(body?.user_coupon_id || 0);
    quoteCouponIds.push(appliedCouponId);
    await route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        code: 0,
        message: "操作成功",
        data: {
          product_id: 123,
          billing_cycle: "monthly",
          base_amount: "48.00",
          total_amount: appliedCouponId ? "38.00" : "48.00",
          quantity: 1,
          coupon: appliedCouponId
            ? { id: appliedCouponId, code: "NEW10", name: "新客立减", discount_label: "立减 ¥10", discount_amount: "10.00" }
            : null,
          user_coupon_id: appliedCouponId,
          available_coupons: [
            { id: 501, name: "新客立减", discount_label: "立减 ¥10" },
          ],
          quote_token: "quote_e2e_token",
          quote_expires_at: "2099-01-01T00:00:00+08:00",
          items: [],
        },
      }),
    });
  });

  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto("/products", { waitUntil: "domcontentloaded" });

  const couponSelect = page.locator(".coupon-panel .t-select");
  await expect(couponSelect).toBeVisible({ timeout: 15_000 });
  await couponSelect.click();
  await page.locator(".t-select__dropdown .t-select-option").first().click();
  await expect(couponSelect.locator("input")).toHaveValue("新客立减 · 立减 ¥10");
  await expect.poll(() => quoteCouponIds[quoteCouponIds.length - 1]).toBe(501);

  const dialog = page.locator(".t-dialog");
  const clearBtn = page.locator(".coupon-clear-btn");

  // 取消：券必须原封不动，也不该触发重新询价
  const quotesBeforeCancel = quoteCouponIds.length;
  await clearBtn.click();
  await expect(dialog).toBeVisible();
  await expect(dialog.locator(".t-dialog__header")).toContainText("移除优惠券");
  await dialog.getByRole("button", { name: "取消" }).click();
  await expect(dialog).toHaveCount(0);
  await expect(couponSelect.locator("input")).toHaveValue("新客立减 · 立减 ¥10");
  expect(quoteCouponIds.length).toBe(quotesBeforeCancel);

  // 确认：移除成功、按钮消失、按原价重新询价
  await clearBtn.click();
  await expect(dialog).toBeVisible();
  await dialog.getByRole("button", { name: "移除" }).click();
  await expect(dialog).toHaveCount(0);
  await expect(couponSelect.locator("input")).toHaveValue("");
  await expect(page.locator(".coupon-clear-btn")).toHaveCount(0);
  await expect.poll(() => quoteCouponIds[quoteCouponIds.length - 1]).toBe(0);
});
// 底部弹层（t-drawer）在 TDesign 里不做滚动锁，也不做焦点管理：
// preventScrollThrough 的注入条件是 !showInAttachedElement，而本组件必须 attach="body"
// 才有遮罩，所以官方锁永远不生效。这条用例钉住自定义补锁的两个契约——
// 打开期间背景不可滚、关闭后回到原滚动位次，且 Tab 焦点不逃出弹层。
// @needs-backend：本用例没有 mock，靠真实地区目录接口渲染出「已选规格」的触发器文案；
// 无后端时触发器停在「请选择地区」，点它只会弹一句警告而不开抽屉，用例必然超时。
// 因此必须打标签，让 CI 的 --grep-invert @needs-backend 跳过它（CI 不启后端）。
test("移动端底部弹层打开时锁背景滚动且焦点不逃出弹层 @needs-backend", async ({ page }) => {
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto("/products", { waitUntil: "domcontentloaded" });

  // 等数据到位、页面撑出可滚动高度后再定位次，否则 scrollTo 会被空文档吞掉
  const trigger = page.locator(".mobile-picker-trigger").first();
  await expect(trigger).toBeVisible({ timeout: 15_000 });
  // 地区目录没加载完时点按钮只会弹一句「地区列表尚未加载完成」警告、不打开抽屉，
  // 此时页面骨架已撑满高度，光等 scrollHeight 会误判为可以开跑，必须先等数据到位
  await expect(trigger).not.toContainText("请选择地区", { timeout: 15_000 });
  await expect
    .poll(() =>
      page.evaluate(() => document.documentElement.scrollHeight - window.innerHeight),
    )
    .toBeGreaterThan(400);

  // 用 JS 直接触发点击：Playwright 的 click 会先 scrollIntoView，把位次冲掉，
  // 这里要断言的是「打开前的位置」能否原样恢复
  await page.evaluate(() => window.scrollTo(0, 250));
  await expect.poll(() => page.evaluate(() => Math.round(window.scrollY))).toBe(250);

  // 先聚焦再点击，模拟真实键盘/触摸用户的落点：JS 的 click() 不会移动焦点，
  // 不聚焦就等于「打开前焦点在 body」，后面就没有可还原的焦点可验
  await page.evaluate(() => {
    const el = document.querySelector(".mobile-picker-trigger");
    el.focus({ preventScroll: true });
    el.click();
  });

  const panel = page.locator(".ms-drawer .ms");
  await expect(panel).toBeVisible();
  // t-drawer 把 attrs 让给了内层（inheritAttrs:false），读屏语义要由站点自己挂稳：
  // 外壳是全屏 fixed 容器（含遮罩），role=dialog 放外壳会把"对话框"边界覆盖到遮罩上，
  // 因此这三条属性钉在内层 .ms 面板上，这里逐条守住，避免以后被挪走或丢失。
  await expect(panel).toHaveAttribute("role", "dialog");
  await expect(panel).toHaveAttribute("aria-modal", "true");
  await expect(panel).toHaveAttribute("aria-label");
  await expect(panel).toHaveAttribute("tabindex", "-1");
  // 锁滚动：body 转 fixed，位次寄存在 top 上（移动端 Safari 只设 overflow 仍会滚）
  await expect
    .poll(() => page.evaluate(() => document.body.style.position))
    .toBe("fixed");
  expect(await page.evaluate(() => document.body.style.top)).toBe("-250px");

  // 真的滚不动：滚轮 600px 后 scrollY 仍为 0（fixed 期间恒为 0）
  await page.mouse.move(180, 120);
  await page.mouse.wheel(0, 600);
  await expect.poll(() => page.evaluate(() => Math.round(window.scrollY))).toBe(0);

  // 焦点进入弹层，且连续 Tab 始终留在弹层内
  await expect
    .poll(() => page.evaluate(() => document.querySelector(".ms-drawer .ms")?.contains(document.activeElement)))
    .toBe(true);
  for (let i = 0; i < 6; i++) {
    await page.keyboard.press("Tab");
    expect(
      await page.evaluate(() =>
        document.querySelector(".ms-drawer .ms")?.contains(document.activeElement),
      ),
    ).toBe(true);
  }

  await page.keyboard.press("Escape");
  await expect(panel).toHaveCount(0, { timeout: 5_000 });
  // 解锁必须还原 body 内联样式并把滚动位次还回去
  await expect.poll(() => page.evaluate(() => document.body.style.position)).toBe("");
  await expect.poll(() => page.evaluate(() => Math.round(window.scrollY))).toBe(250);
  // 焦点必须回到触发弹层的元素：t-drawer 关闭时会先 focus 一次即将被移除的外壳，
  // 还焦点的动作只能排在销毁之后，否则焦点会掉到 <body>
  await expect
    .poll(() =>
      page.evaluate(() =>
        Boolean(document.activeElement?.closest(".mobile-picker-trigger")),
      ),
    )
    .toBe(true);
});


// 购买要求提示条（t-alert）在两处渲染：商品详情页侧栏与选购页费用栏。
// 迁移时曾把文案放进 #message 槽位，导致同一句「购买要求：…」在详情页是粗体一级标题、
// 在选购页变成灰色说明文字（.t-alert__title 是 600 字重 primary 色，
// .t-alert__description 是 secondary 灰）。这条用例钉住两处都走 #title 槽位、
// 都带 role="alert"（读屏播报），且不会因 t-empty 式的双行叠字多出一条描述行。
test("购买要求提示条统一走 Alert 标题槽位并带 alert 语义", async ({ page }) => {
  const catalog = {
    effective_product_group_id: 31,
    effective_product_group_level: 2,
    children: [],
    items_by_group: [
      {
        effective_product_group_id: 31,
        products: [
          {
            id: 123,
            name: "E2E 购买要求云服务器",
            display_name: "E2E 购买要求云服务器",
            product_type: "cloud_server",
            first_product_group_code: "vps",
            effective_product_group_id: 31,
            pricing_entries: [{ cycle: "monthly", label: "月付", amount: "48.00" }],
          },
        ],
      },
    ],
  };

  await page.route("**/api/v2/site/product-purchase-context*", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        code: 0,
        message: "操作成功",
        data: {
          types: [{ id: 1, value: "vps", label: "云服务器", product_type: "cloud_server", first_product_group_code: "vps" }],
          root_groups: [
            { id: 31, name: "襄阳", product_count: 1, product_type: "cloud_server", first_product_group_code: "vps", effective_product_group_id: 31, effective_product_group_level: 2 },
          ],
          catalog,
        },
      }),
    }),
  );
  await page.route("**/api/v2/site/products/123/stock*", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({ code: 0, message: "操作成功", data: { stock: 10 } }),
    }),
  );
  await page.route("**/api/v2/site/products/123*", (route) =>
    route.fulfill({
      status: 200,
      contentType: "application/json",
      body: JSON.stringify({
        code: 0,
        message: "操作成功",
        data: {
          product: {
            id: 123,
            name: "E2E 购买要求云服务器",
            display_name: "E2E 购买要求云服务器",
            price: 48,
            // purchase_requires 驱动 resolvePurchaseRequirementList，
            // 不填这一项提示条根本不渲染，用例会空转成假绿
            purchase_requires: { require_verification: true, require_phone: true },
            pricing_entries: [{ cycle: "monthly", label: "月付", amount: "48.00" }],
            billing_cycles: [],
            config: [],
            siblings: [],
          },
        },
      }),
    }),
  );

  await page.setViewportSize({ width: 1280, height: 900 });
  await page.goto("/products/123", { waitUntil: "domcontentloaded" });

  const detailAlert = page.locator(".purchase-guard-alert");
  await expect(detailAlert).toBeVisible({ timeout: 15_000 });
  await expect(detailAlert).toHaveAttribute("role", "alert");
  // 文案在标题槽位：标题有内容、描述位是空壳。
  // TDesign 的 renderDescription 无条件创建 .t-alert__description 容器
  // （alert.mjs:110-134），所以只能断言它为空，不能断言它不存在。
  await expect(detailAlert.locator(".t-alert__title")).toContainText("购买要求：");
  await expect(detailAlert.locator(".t-alert__description")).toBeEmpty();
  // 文案来自 productPurchaseRequirements 的 description 拼接
  await expect(detailAlert).toContainText("购买前需完成实名认证");
  await expect(detailAlert).toContainText("购买前需先绑定手机号");
});

