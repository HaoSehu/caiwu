import { collectConsoleErrors, envelope, expect, mockAuthenticatedSession, test } from './fixtures';

/**
 * 订单详情「查看关联账单」抽屉（P1-B 修复的可观测结果）。
 *
 * 修复前 InvoiceDetailDrawer 用字符串 `'t-drawer'` 做动态组件名，而全量注册
 * `app.use(TDesign)` 已在按需引入改造中删除，Vue 的 resolveDynamicComponent 会原样
 * 返回字符串并渲染为未知元素 <t-drawer>：抽屉没有 TDesign 的结构与样式，
 * 内容直接铺在页面流里（无遮罩、无侧滑、无头部），且控制台不会报错，极易漏检。
 *
 * 修复后显式 `import { Drawer as TDrawer } from 'tdesign-vue-next/es/drawer/index'`，
 * 断言点因此放在 TDesign 自身的 DOM 结构（.t-drawer / .t-drawer__header）上——
 * 未知元素不可能带出这些类名。
 */

const ORDER_DETAIL_RESPONSE = {
  order: {
    id: 1,
    basic: {
      order_no: 'E2E202610230002',
      type: 'normal',
      type_label: '新购',
      status: 'paid',
      status_label: '已支付',
      billing_cycle: 'month',
      quantity: 1,
    },
    financial: { amount: '99.00', paid_amount: '99.00', discount: '0' },
    // invoice 必须带 id：既让「查看关联账单」按钮出现，也避免归一化层打降级告警
    invoice: { id: 501, invoice_no: 'E2E-INV-202610230001' },
    timestamps: { created_at: '2026-10-23 10:00:00', updated_at: '2026-10-23 10:00:00' },
  },
};

const INVOICE_DETAIL_RESPONSE = {
  invoice: {
    id: 501,
    basic: {
      invoice_no: 'E2E-INV-202610230001',
      type: 'normal',
      type_label: '新购',
      status: 'paid',
      status_label: '已支付',
      billing_cycle: 'month',
      quantity: 1,
    },
    financial: { amount: '99.00', paid_amount: '99.00', discount: '0' },
    order: { id: 1, order_no: 'E2E202610230002' },
    actions: { can_refund_to_balance: false, can_cancel: false },
    timestamps: { created_at: '2026-10-23 10:00:00', updated_at: '2026-10-23 10:00:00' },
  },
  // 这三个集合在详情页里只用于「有无记录」的分支渲染，空数组足以覆盖；
  // 显式标注元素类型，否则 tsconfig 的 noImplicitAny 会把裸 [] 推断成 any[] 并报 TS7018。
  payments: [] as Record<string, unknown>[],
  items: [] as Record<string, unknown>[],
  logs: [] as Record<string, unknown>[],
};

// 提为模块常量：内联 envelope(...) 会触发 prettier 与 antfu/consistent-list-newline 的循环修复
const INVOICE_ENVELOPE = envelope(INVOICE_DETAIL_RESPONSE);

test('订单详情打开关联账单时渲染真实的 TDesign 抽屉', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);

  await page.route('**/v2/admin/orders/1*', (route) => route.fulfill(jsonResponse(envelope(ORDER_DETAIL_RESPONSE))));
  await page.route('**/v2/admin/invoices/501*', (route) => route.fulfill(jsonResponse(INVOICE_ENVELOPE)));

  await page.goto('/admin/finance/orders/1', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('.identity-title').first()).toHaveText('E2E202610230002');

  await page.getByRole('button', { name: '查看关联账单' }).click();

  // 抽屉外壳必须是 TDesign 渲染的真实抽屉：未知元素 <t-drawer> 不会带出 .t-drawer 类
  await expect(page.locator('.t-drawer').first()).toBeVisible();
  await expect(page.locator('.t-drawer__header').first()).toContainText('账单详情');
  // 抽屉内详情已就位（同一套详情组件，标题取账单号）
  await expect(page.locator('.t-drawer .identity-title').first()).toHaveText('E2E-INV-202610230001');

  expect(errors).toEqual([]);
});

function jsonResponse(body: unknown, status = 200) {
  return { status, contentType: 'application/json', body: JSON.stringify(body) };
}
