import type { Page } from '@playwright/test';

import { collectConsoleErrors, envelope, expect, mockAuthenticatedSession, test } from './fixtures';

/**
 * 用户详情「充值记录」金额符号回归（审查条目 P2-1）。
 *
 * 后端退款冲抵记录的 amount 本身已带负号（FinanceDocumentService 的 refund_offset 写入
 * `Money::format(-$offsetAmount)`），而列表还会返回 direction='out'。修复前模板用
 * `${direction === 'out' ? '-' : '+'}${formatMoney(amount)}` 又拼了一次负号，
 * 页面上会出现「--¥100.00」。修复后只由 formatMoney 排负号，正负各一条用例锁住。
 */

const USER_DETAIL_RESPONSE = {
  user: {
    id: 1,
    nickname: '测试用户',
    email: 'user@example.com',
    phone: '13800000000',
    status: 1,
    cash_balance: '100.00',
    member_level_id: null as number | null,
    promotion_ambassador_id: null as number | null,
  },
  stats: { ticket_open: 0, total_expense: '0' },
  referral: {},
};

const RECHARGE_RECORDS_RESPONSE = {
  list: [
    {
      id: 1,
      record_no: 'RC202610240001',
      scene: 'refund',
      entry_type: 'refund_offset',
      direction: 'out',
      amount: '-100.00',
      currency: 'CNY',
      remark: '退款冲抵原充值记录 RC202610230001',
      operator_name: '测试管理员',
      invoice_id: 501,
      invoice_no: 'E2E-INV-202610240001',
      order_id: 1,
      payment_id: null as number | null,
      trace_id: 'e2e-trace-1',
      created_at: '2026-10-24 10:00:00',
    },
    {
      id: 2,
      record_no: 'RC202610240002',
      scene: 'manual',
      entry_type: 'manual_recharge',
      direction: 'in',
      amount: '1234.50',
      currency: 'CNY',
      remark: '手工充值',
      operator_name: '测试管理员',
      invoice_id: null,
      invoice_no: '',
      order_id: null,
      payment_id: null as number | null,
      trace_id: 'e2e-trace-2',
      created_at: '2026-10-24 09:00:00',
    },
  ],
  total: 2,
  page: 1,
  page_size: 20,
};

// 末尾必须带通配：请求层会给 GET 追加 `?_t=` 时间戳防缓存，精确路径匹配不到
const USER_DETAIL_PATH = '**/v2/admin/users/1*';
// 选项列表用空数组：裸 [] 在 tsconfig noImplicitAny 下会被推断成 any[]（TS7018）
const EMPTY_OPTIONS = envelope({ list: [] as Record<string, unknown>[] });
const RECHARGE_RECORDS_PATH = '**/v2/admin/users/1/recharge-records*';

/**
 * 详情页挂载即拉会员等级、推广大使选项与商品树，漏 mock 会落 console error。
 *
 * 商品树（coupon-product-groups）来自「新建实例」弹窗里的商品选择器：该组件在 setup 阶段
 * 就调用 loadTree()，弹窗还没打开请求已经发出（见 components/product-binding-tree-select/index.vue
 * 与 hooks/useProductBindingTree.ts 的 loadTree）。
 */
async function mockUserDetailPage(page: Page) {
  await mockAuthenticatedSession(page);
  await page.route(USER_DETAIL_PATH, (route) => route.fulfill(jsonResponse(envelope(USER_DETAIL_RESPONSE))));
  await page.route('**/v2/admin/member-levels*', (route) => route.fulfill(jsonResponse(EMPTY_OPTIONS)));
  await page.route('**/v2/admin/promotion-ambassadors*', (route) => route.fulfill(jsonResponse(EMPTY_OPTIONS)));
  await page.route('**/v2/admin/coupon-product-groups**', (route) => route.fulfill(jsonResponse(EMPTY_OPTIONS)));
  await page.route(RECHARGE_RECORDS_PATH, (route) => route.fulfill(jsonResponse(envelope(RECHARGE_RECORDS_RESPONSE))));
}

test('充值记录：退款冲抵只渲染一个负号，充值入账为正向金额', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockUserDetailPage(page);

  // 直接带 ?tab=recharges 进入，详情页 onMounted 会同步初始化该 Tab
  await page.goto('/admin/users/1?tab=recharges', { waitUntil: 'domcontentloaded' });

  const outAmount = page.locator('.amount-down').first();
  await expect(outAmount).toBeVisible();
  // 修复前这里是「--¥100.00」；此处同时断言符号与千分位（docs/DESIGN.md §6）
  await expect(outAmount).toHaveText('-¥100.00');
  await expect(page.locator('.amount-up').first()).toHaveText('¥1,234.50');

  // 负号的唯一来源是 amount 本身：整表不应出现任何「--」
  await expect(page.locator('.table-scroll')).not.toContainText('--');

  expect(errors).toEqual([]);
});

function jsonResponse(body: unknown, status = 200) {
  return { status, contentType: 'application/json', body: JSON.stringify(body) };
}
