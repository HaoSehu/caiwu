import { collectConsoleErrors, envelope, expect, mockAuthenticatedSession, test } from './fixtures';

/**
 * 管理端支付展示：插件名称与支付渠道分格展示（2026-10-31）。
 *
 * 背景：pay 插件体系拆成 plugin_name（插件名称，仅管理端/日志）与 channel_name（支付渠道，用户端唯一可读名）。
 * 后端 AdminOrderSummaryResource::payments() 已分别下发两个字段，管理端必须分格展示，
 * 严禁由前端拼接成「易支付 - 微信支付」这类串。
 *
 * 断言目的：
 * - 「插件名称」格显示 plugin_name，「支付渠道」格显示 channel_name；
 * - 页面文本中不存在「插件名 - 渠道名」的拼接串。
 */

const PAYMENT_CHAIN_PAYMENT = {
  id: 1,
  payment_no: 'PY202610310001',
  gateway: 'yipay',
  gateway_key: 'yipay',
  gateway_label: '微信支付',
  plugin_name: '易支付',
  channel_name: '微信支付',
  trade_no: 'T202610310001',
  amount: '99.00',
  status: 1,
  status_label: '成功',
  paid_at: '2026-10-31 10:00:00',
  trace_id: 'trace-e2e',
};

const ORDER_DETAIL_RESPONSE = {
  order: {
    id: 1,
    basic: {
      order_no: 'E2E202610310001',
      type: 'normal',
      type_label: '新购',
      status: 'paid',
      status_label: '已支付',
      billing_cycle: 'month',
      quantity: 1,
    },
    financial: { amount: '99.00', paid_amount: '99.00', discount: '0' },
    timestamps: { created_at: '2026-10-31 09:59:00', updated_at: '2026-10-31 10:00:00' },
    payment_chain: { payments: [PAYMENT_CHAIN_PAYMENT] },
  },
};

test('订单详情支付记录分格展示插件名称与支付渠道，且不出现拼接串', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await page.route('**/v2/admin/orders/1*', (route) => route.fulfill(jsonResponse(envelope(ORDER_DETAIL_RESPONSE))));

  await page.goto('/admin/finance/orders/1', { waitUntil: 'domcontentloaded' });

  // 有支付记录才有「支付记录」页签（record-detail-page 按 visibleTabs 渲染）
  await page.locator('.record-detail-tabs .t-tabs__nav-item').filter({ hasText: '支付记录' }).click();

  const pluginItem = page.locator('.detail-kv-item').filter({ hasText: '插件名称' });
  const channelItem = page.locator('.detail-kv-item').filter({ hasText: '支付渠道' });
  await expect(pluginItem).toHaveCount(1);
  await expect(channelItem).toHaveCount(1);
  await expect(pluginItem.locator('.detail-kv-item__value')).toHaveText('易支付');
  await expect(channelItem.locator('.detail-kv-item__value')).toHaveText('微信支付');

  // 两个维度同屏可见（各自独立可见，而不是一条拼接文案）
  await expect(page.getByText('易支付', { exact: true })).toBeVisible();
  await expect(page.getByText('微信支付', { exact: true })).toBeVisible();

  const content = (await page.locator('#main-content').textContent()) || '';
  expect(content).not.toContain('易支付 - 微信支付');
  expect(content).not.toContain('易支付-微信支付');

  expect(errors).toEqual([]);
});

function jsonResponse(body: unknown, status = 200) {
  return { status, contentType: 'application/json', body: JSON.stringify(body) };
}
