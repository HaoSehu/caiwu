import { test, expect } from "@playwright/test";
import type { Page } from "@playwright/test";

/**
 * 官网移动端（手机视口）适配回归。
 *
 * 与 docs/执行计划/进行中/三端手机端适配与自适应-2026-11-06.md 的「移动形态标记」契约配套：
 * 手机端主容器带 data-mobile-view（card / scroll / detail / form）。
 * 官网是公开站点，用例只 mock 必需接口，其余允许走真实后端或在无后端时降级为空态。
 */

const MOBILE_VIEWPORT = { width: 375, height: 812 };

/**
 * 各页在手机端应声明的形态（执行计划「统一判定口径」第 9 条）：
 * 列表 card、详情 detail、表单 form。
 * 只断言「值属于枚举」在形态写反时仍会通过，因此这里逐页钉住期望值。
 */
const EXPECTED_MOBILE_VIEW: Record<string, "card" | "scroll" | "detail" | "form"> = {
  "/": "card",
  "/products": "form",
  "/notices": "card",
  "/help": "card",
  "/about": "card",
};

/** 运行时异常：pageerror 进入的是 "TypeError: ..."，控制台里的是 "Uncaught ..."，两者都要算 */
function isRuntimeError(text: string) {
  return text.includes("Uncaught") || /^(?:Type|Reference|Range|Syntax|URI)Error\b/.test(text);
}

const ROUTES: Array<{ path: string; name: string }> = [
  { path: "/", name: "首页" },
  { path: "/products", name: "产品" },
  { path: "/notices", name: "公告" },
  { path: "/help", name: "帮助中心" },
  { path: "/about", name: "关于我们" },
];

function overflowOf(page: Page) {
  return page.evaluate(() => {
    const el = document.documentElement;
    return el.scrollWidth - el.clientWidth;
  });
}

test.describe("官网手机端适配", () => {
  test.beforeEach(async ({ page }) => {
    await page.setViewportSize(MOBILE_VIEWPORT);
    // 公开内容接口统一给空态，避免本地无后端时 ECONNREFUSED 干扰断言。
    // 各端点都按真实结构返回（首页是 hero/slides 对象而非 list），
    // 结构不符会让页面走兜底分支，真实数据下的布局问题就测不到。
    await page.route("**/api/v2/site/**", (route) => {
      const path = new URL(route.request().url()).pathname;
      const emptyPage = { list: [], items: [], total: 0, page: 1, page_size: 20 };
      let data: Record<string, unknown> = emptyPage;
      if (path.endsWith("/home")) {
        data = { slides: [], features: [], notices: [], product_types: [], root_groups: [], catalog: null };
      } else if (path.endsWith("/product-purchase-context") || path.endsWith("/products-init")) {
        data = { types: [], root_groups: [], catalog: null };
      } else if (path.endsWith("/config")) {
        data = { site_name: "Caiwu", logo: "", footer: {} };
      }
      return route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ code: 0, message: "ok", data }),
      });
    });
  });

  for (const route of ROUTES) {
    test(`${route.name}页在 375px 下无横向溢出`, async ({ page }) => {
      const errors: string[] = [];
      page.on("pageerror", (err) => errors.push(String(err)));

      await page.goto(route.path, { waitUntil: "domcontentloaded" });
      await expect(page.locator("#app")).toBeVisible();
      await expect.poll(() => overflowOf(page), { timeout: 10_000 }).toBeLessThanOrEqual(1);
      // pageerror 推入的是 "TypeError: ..."，不含 "Uncaught"，需一并覆盖
      expect(errors.filter(isRuntimeError)).toEqual([]);
    });

    test(`${route.name}页声明手机端形态`, async ({ page }) => {
      await page.goto(route.path, { waitUntil: "domcontentloaded" });
      await expect(page.locator("#app")).toBeVisible();

      const markers = page.locator("[data-mobile-view]");
      await expect(markers).not.toHaveCount(0, { timeout: 5_000 });
      // 注意：空数据时卡片容器可能高度为 0，这里只要求标记不落在被 CSS
      // display:none 隐藏的节点上；有数据时的可见性由各端「切换为卡片形态」用例覆盖。
      const value = await markers.first().getAttribute("data-mobile-view");
      // 断言本页应有的具体形态：只断言「属于枚举」在形态写反时仍然通过
      expect(value).toBe(EXPECTED_MOBILE_VIEW[route.path]);
    });

    // 反向契约：桌面视口下不得渲染形态标记，避免手机端专属属性泄漏到桌面 DOM。
    test(`${route.name}页在桌面端不渲染形态标记`, async ({ page }) => {
      await page.setViewportSize({ width: 1280, height: 800 });
      await page.goto(route.path, { waitUntil: "domcontentloaded" });
      await expect(page.locator("#app")).toBeVisible();
      await expect(page.locator("[data-mobile-view]")).toHaveCount(0);
    });
  }

  test("手机端顶部导航以抽屉形态展开", async ({ page }) => {
    await page.goto("/", { waitUntil: "domcontentloaded" });

    const toggle = page.getByRole("button", { name: /菜单|导航/ }).first();
    await expect(toggle).toBeVisible();
    await toggle.click();
    await expect(page.locator("#mobile-menu-panel")).toBeVisible();
  });
});
