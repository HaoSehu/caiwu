import type { Page } from '@playwright/test';

import { envelope, expect, mockAuthenticatedSession, test } from './fixtures';

// 订单列表与账单列表的「支付渠道」列：用户端只展示渠道名（支付宝 / 微信支付），
// 插件名（如「易支付」）属于管理端与日志维度，任何用户可见位置都不得出现。
// 订单侧渠道来自后端 listItem() 的 channel_name / gateway_label（由 payments 快照解析）；
// 账单侧来自 payment_summary.channel_name / gateway_label。
// 「计费模式」列：billing_cycle 是下单时按商品定价周期写入的英文键，前端翻成 一个月 / 三个月 / 半年 / 一年 / 按量。

const ORDER_ROWS = [
  {
    id: 1,
    order_no: 'ORD202610310001',
    type: 'new',
    type_label: '新购',
    status: 2,
    status_label: '已支付',
    amount: '99.00',
    paid_amount: '99.00',
    discount: '0.00',
    member_discount_amount: '0.00',
    billing_cycle: 'monthly',
    quantity: 1,
    product_name: '云服务器',
    product_full_path: '云服务器 / 标准型',
    service_name: '',
    // 已支付订单：渠道名由后端下发
    channel_name: '微信支付',
    gateway_label: '微信支付',
    invoice: { id: 11, invoice_no: 'INV202610310001', status: 1, amount: '99.00', payment_window_expired: false },
    paid_at: '2026-10-31 10:00:00',
    // 结束时间：已支付取支付完成时间（待支付不下发）
    finished_at: '2026-10-31 10:00:00',
    created_at: '2026-10-31 09:59:00',
  },
  {
    id: 2,
    order_no: 'ORD202610310002',
    type: 'new',
    type_label: '新购',
    status: 0,
    status_label: '待支付',
    amount: '20.00',
    paid_amount: '0.00',
    discount: '0.00',
    member_discount_amount: '0.00',
    billing_cycle: 'quarterly',
    quantity: 1,
    product_name: '云服务器',
    product_full_path: '云服务器 / 标准型',
    service_name: '',
    // 未支付订单：后端返回空串，前端兜底占位符
    channel_name: '',
    gateway_label: '',
    invoice: { id: 12, invoice_no: 'INV202610310002', status: 0, amount: '20.00', payment_window_expired: false },
    paid_at: null,
    finished_at: null,
    created_at: '2026-10-31 11:00:00',
  },
];

const INVOICE_ROWS = [
  {
    id: 11,
    invoice_no: 'INV202610310001',
    order_id: 1,
    type: 'new',
    type_label: '新购',
    status: 1,
    status_label: '已支付',
    amount: '99.00',
    discount: '0.00',
    member_discount_amount: '0.00',
    coupon_code: '',
    coupon_name: '',
    paid_amount: '99.00',
    payable_amount: '0.00',
    due_date: '2026-11-07 10:00:00',
    finished_at: '2026-10-31 10:00:00',
    created_at: '2026-10-31 09:59:00',
    payment_window_expired: false,
    // 历史账单自身 billing_cycle 为空，需回落到关联订单的周期
    order: { id: 1, order_no: 'ORD202610310001', status: 2, type: 'new', billing_cycle: 'semiannually' },
    // 账单侧渠道来自支付摘要，且必须只取渠道字段
    payment_summary: {
      id: 1,
      payment_no: 'PY202610310001',
      gateway: 'yipay',
      gateway_key: 'yipay',
      gateway_label: '微信支付',
      channel_name: '微信支付',
      // 插件名随摘要下发时也不能被渲染出来
      plugin_name: '易支付',
      amount: '99.00',
      status: 1,
      status_label: '成功',
      paid_at: '2026-10-31 10:00:00',
    },
  },
];

function jsonOk(data: unknown) {
  return { status: 200, contentType: 'application/json', body: JSON.stringify(envelope(data)) };
}

async function mockList(page: Page, path: string, rows: unknown[]) {
  await page.route(`**/api/v2/client/${path}?*`, (route) => {
    const body = { list: rows, total: rows.length, page: 1, page_size: 20 };
    return route.fulfill(jsonOk(body));
  });
}

test('订单列表：展示支付渠道，不出现插件名，未支付订单走占位符', async ({ page }) => {
  await mockAuthenticatedSession(page);
  await mockList(page, 'orders', ORDER_ROWS);

  await page.goto('/client/orders', { waitUntil: 'domcontentloaded' });

  const table = page.locator('.record-table');
  // 表头出现「支付渠道」列
  await expect(table.getByText('支付渠道', { exact: true })).toBeVisible();
  // 已支付订单显示渠道名
  await expect(table.getByText('微信支付').first()).toBeVisible();
  // 未支付订单显示占位符，不能是空白单元格
  await expect(table.locator('.order-channel').nth(1)).toHaveText('--');
  // 计费模式：英文键按月/季翻成中文，不允许把 billing_cycle 原样漏出
  await expect(table.getByText('计费模式', { exact: true })).toBeVisible();
  await expect(table.locator('.order-cycle').nth(0)).toHaveText('一个月');
  await expect(table.locator('.order-cycle').nth(1)).toHaveText('三个月');
  // 结束时间：已支付显示支付完成时间，待支付不显示（占位符）
  await expect(table.getByText('结束时间', { exact: true })).toBeVisible();
  await expect(table.locator('tbody tr').nth(0).locator('td').nth(7)).toHaveText('2026-10-31 10:00:00');
  await expect(table.locator('tbody tr').nth(1).locator('td').nth(7)).toHaveText('--');
  // 插件名不得出现在页面任何位置
  await expect(page.locator('body')).not.toContainText('易支付');
});

test('账单列表：列收敛为六列、只展示实付金额，不出现插件名', async ({ page }) => {
  await mockAuthenticatedSession(page);
  await mockList(page, 'invoices', INVOICE_ROWS);

  await page.goto('/client/invoices', { waitUntil: 'domcontentloaded' });

  const table = page.locator('.record-table');
  // 优惠金额展示规范 1.2 列序：订单号、商品/服务名称、下单时间、实付金额、状态、操作
  await expect(table.getByText('订单号', { exact: true })).toBeVisible();
  await expect(table.getByText('商品/服务名称', { exact: true })).toBeVisible();
  await expect(table.getByText('下单时间', { exact: true })).toBeVisible();
  await expect(table.getByText('实付金额', { exact: true })).toBeVisible();
  // 列表只有实付金额一个金额位，且取值来自 paid_amount
  await expect(table.locator('.invoice-money').first()).toHaveText('¥99.00');
  // 规则 2：不展开优惠明细，渠道、计费模式与结束时间已从列表口径移除
  await expect(table.getByText('支付渠道', { exact: true })).toHaveCount(0);
  await expect(table.getByText('计费模式', { exact: true })).toHaveCount(0);
  await expect(table.getByText('结束时间', { exact: true })).toHaveCount(0);
  // 即使摘要里带 plugin_name，也不能渲染
  await expect(page.locator('body')).not.toContainText('易支付');
});
