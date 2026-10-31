import type { Page } from '@playwright/test';

import { collectConsoleErrors, envelope, expect, mockAuthenticatedSession, test } from './fixtures';

/**
 * 管理端充值列表：插件名称与支付渠道分两行展示（2026-10-31）。
 *
 * 背景：pay 插件体系拆成 plugin_name（插件名称，仅管理端/日志）与 channel_name（支付渠道）。
 * 后端 AdminFinanceRechargeListItemResource 在顶层与嵌套 payment 块都分别下发两个字段，
 * 列表的「渠道 / 三方单号」列必须分两行渲染，不得拼成「易支付 - 微信支付」。
 *
 * 断言目的：
 * - 行内 strong 显示插件名称，span 显示「渠道：支付渠道」；
 * - 第三方单号仍保留；
 * - 页面文本中不存在插件名与渠道名的拼接串。
 */

const TOP_LEVEL_ROW = {
  id: 1,
  payment_no: 'PY202610310001',
  invoice_no: 'IN202610310001',
  invoice_id: 1,
  gateway: 'yipay',
  gateway_key: 'yipay',
  gateway_label: '微信支付',
  plugin_name: '易支付',
  channel_name: '微信支付',
  trade_no: 'T202610310001',
  user: { nickname: '测试用户', email: 'e2e@example.com' },
  amount: '99.00',
  paid_amount: '99.00',
  status: 1,
  created_at: '2026-10-31 09:59:00',
  paid_at: '2026-10-31 10:00:00',
};

const NESTED_ROW = {
  id: 2,
  payment_no: 'PY202610310002',
  trade_no: 'T202610310002',
  user: { nickname: '测试用户', email: 'e2e@example.com' },
  payment: {
    gateway: 'yipay',
    gateway_label: '微信支付',
    plugin_name: '易支付',
    channel_name: '微信支付',
    trade_no: 'T202610310002',
  },
  amount: '99.00',
  paid_amount: '99.00',
  status: 1,
  created_at: '2026-10-31 09:59:00',
  paid_at: '2026-10-31 10:00:00',
};

const RECHARGES_ROUTE = '**/v2/admin/finance/recharges*';

function listEnvelope(row: Record<string, unknown>) {
  return envelope({ list: [row], total: 1, page: 1, page_size: 20 });
}

async function mockRecharges(page: Page, row: Record<string, unknown>) {
  await page.route(RECHARGES_ROUTE, (route) => route.fulfill(jsonResponse(listEnvelope(row))));
}

test('充值列表行内分两行展示插件名称与支付渠道，且不出现拼接串', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await mockRecharges(page, TOP_LEVEL_ROW);

  await page.goto('/admin/finance/recharges', { waitUntil: 'domcontentloaded' });

  await expect(page.getByText('PY202610310001')).toBeVisible();

  const cell = page.locator('.stack-cell').filter({ hasText: 'T202610310001' });
  await expect(cell).toHaveCount(1);
  await expect(cell.locator('strong')).toHaveText('易支付');
  await expect(cell.locator('span').first()).toHaveText('渠道：微信支付');

  // 第三方单号那行保留
  await expect(cell).toContainText('T202610310001');

  const content = (await page.locator('#main-content').textContent()) || '';
  expect(content).not.toContain('易支付 - 微信支付');
  expect(content).not.toContain('易支付-微信支付');

  expect(errors).toEqual([]);
});

test('嵌套 payment 块同样分两行展示插件名称与支付渠道', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await mockRecharges(page, NESTED_ROW);

  await page.goto('/admin/finance/recharges', { waitUntil: 'domcontentloaded' });

  await expect(page.getByText('PY202610310002')).toBeVisible();

  const cell = page.locator('.stack-cell').filter({ hasText: 'T202610310002' });
  await expect(cell).toHaveCount(1);
  await expect(cell.locator('strong')).toHaveText('易支付');
  await expect(cell.locator('span').first()).toHaveText('渠道：微信支付');

  expect(errors).toEqual([]);
});

function jsonResponse(body: unknown, status = 200) {
  return { status, contentType: 'application/json', body: JSON.stringify(body) };
}
