import { collectConsoleErrors, envelope, expect, mockAuthenticatedSession, test } from './fixtures';

/**
 * 订单详情失败态与重试（P1-2 修复的可观测结果）。
 *
 * 修复前 record-detail-page 的 `ready` 只区分「有数据 / 无数据」，
 * 首次加载失败与「订单不存在」共用同一个空态，用户既看不到失败原因也没有重试入口。
 * 修复后由 shared/user-v3/components/DataState.vue 渲染失败态与「重新加载」按钮。
 */

const ORDERS_EMPTY = envelope({ list: [], total: 0, page: 1, page_size: 20 });

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
    timestamps: { created_at: '2026-10-23 10:00:00', updated_at: '2026-10-23 10:00:00' },
  },
};

test('订单详情首次加载失败展示失败态，点击重新加载后恢复', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);

  let attempt = 0;
  await page.route('**/v2/admin/orders/1*', (route) => {
    attempt += 1;
    // 首次返回业务失败信封，重试返回正常详情
    if (attempt === 1) return route.fulfill(jsonResponse(envelope(null, 50000, '订单数据加载异常')));
    return route.fulfill(jsonResponse(envelope(ORDER_DETAIL_RESPONSE)));
  });

  await page.goto('/admin/finance/orders/1', { waitUntil: 'domcontentloaded' });

  // 失败态：错误文案 + 重新加载入口（修复前这里是「订单不存在」空态，且无按钮）
  await expect(page.getByText('订单数据加载异常')).toBeVisible();
  await expect(page.getByRole('button', { name: '重新加载' })).toBeVisible();

  // 重试后渲染真实详情：标题取自订单号（同一单号在页面与退款弹窗内均有出现，故锁定标题节点）
  await page.getByRole('button', { name: '重新加载' }).click();
  await expect(page.locator('.identity-title').first()).toHaveText('E2E202610230002');
  await expect(page.getByRole('button', { name: '重新加载' })).toHaveCount(0);

  expect(errors).toEqual([]);
});

test('≤991px 时页面边距统一为 12px', async ({ page }) => {
  await page.setViewportSize({ width: 900, height: 800 });
  await mockAuthenticatedSession(page);
  await page.route('**/v2/admin/orders*', (route) => route.fulfill(jsonResponse(ORDERS_EMPTY)));
  await page.goto('/admin/finance/orders', { waitUntil: 'domcontentloaded' });

  const padding = await page.locator('#main-content').evaluate((el) => {
    const style = getComputedStyle(el);
    return { top: style.paddingTop, left: style.paddingLeft };
  });
  // docs/DESIGN.md §3.3：≤991px 统一 12px
  expect(padding).toEqual({ top: '12px', left: '12px' });
});

function jsonResponse(body: unknown, status = 200) {
  return { status, contentType: 'application/json', body: JSON.stringify(body) };
}
