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

/**
 * 取消订单（2026-10-28）：挂账订单被豁免支付窗口清理后，管理端是唯一的人工终态出口。
 * 仅待支付（status=0）且持 order.manage 权限时可见，点击需二次确认，成功后重新拉详情。
 */
const PENDING_ORDER_DETAIL = {
  order: {
    id: 9,
    basic: {
      order_no: 'E2E202610280009',
      type: 'manual',
      type_label: '人工',
      status: 0,
      status_label: '待支付',
      billing_cycle: 'month',
      quantity: 1,
    },
    financial: { amount: '99.00', paid_amount: '0', discount: '0' },
    timestamps: { created_at: '2026-10-28 10:00:00', updated_at: '2026-10-28 10:00:00' },
  },
};

test('待支付订单详情可取消订单，二次确认后调用取消接口', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);

  let cancelCalls = 0;
  // 一条通配同时覆盖详情的 GET（/orders/9?_t=）与取消的 POST（/orders/9/cancellations）。
  // 注意 Playwright 的 glob 里 `*` 不跨 `/`，写成 `9*` 匹配不到 `/9/cancellations`，必须用 `9**`。
  await page.route('**/v2/admin/orders/9**', (route) => {
    const request = route.request();
    if (request.method() === 'POST' && request.url().includes('/cancellations')) {
      cancelCalls += 1;
      return route.fulfill(
        jsonResponse(
          envelope({
            id: 9,
            status: 'completed',
            message: '订单已取消',
            detail: { type: 'cancellation', order: { id: 9, order_no: 'E2E202610280009', status: 4 } },
          }),
        ),
      );
    }
    return route.fulfill(jsonResponse(envelope(PENDING_ORDER_DETAIL)));
  });

  await page.goto('/admin/finance/orders/9', { waitUntil: 'domcontentloaded' });

  const cancelButton = page.getByRole('button', { name: '取消订单' });
  await expect(cancelButton).toBeVisible();

  // 二次确认：取消对话框先弹，未确认前不得发出请求
  await cancelButton.click();
  await expect(page.getByText('关联账单会一并取消')).toBeVisible();
  expect(cancelCalls).toBe(0);

  await page.getByRole('button', { name: '确定' }).click();
  await expect(page.getByText('订单已取消').first()).toBeVisible();
  expect(cancelCalls).toBe(1);

  expect(errors).toEqual([]);
});

test('取消失败时保留对话框并提示服务端原因', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);

  let cancelCalls = 0;
  await page.route('**/v2/admin/orders/9**', (route) => {
    const request = route.request();
    if (request.method() === 'POST' && request.url().includes('/cancellations')) {
      cancelCalls += 1;
      // D-6：与支付回调竞态时服务端返回业务失败，前端必须把原因透出且不当作成功
      return route.fulfill(jsonResponse(envelope(null, 42200, '当前账单状态不支持取消订单')));
    }
    return route.fulfill(jsonResponse(envelope(PENDING_ORDER_DETAIL)));
  });

  await page.goto('/admin/finance/orders/9', { waitUntil: 'domcontentloaded' });

  await page.getByRole('button', { name: '取消订单' }).click();
  await expect(page.getByText('关联账单会一并取消')).toBeVisible();
  await page.getByRole('button', { name: '确定' }).click();

  await expect(page.getByText('当前账单状态不支持取消订单')).toBeVisible();
  expect(cancelCalls).toBe(1);
  // 失败时不得出现成功提示，取消按钮回到可点状态（loading 已复位）
  await expect(page.getByText('订单已取消')).toHaveCount(0);

  expect(errors).toEqual([]);
});

test('已支付订单不展示取消订单入口', async ({ page }) => {
  await mockAuthenticatedSession(page);
  await page.route('**/v2/admin/orders/1*', (route) => route.fulfill(jsonResponse(envelope(ORDER_DETAIL_RESPONSE))));
  await page.goto('/admin/finance/orders/1', { waitUntil: 'domcontentloaded' });

  await expect(page.locator('.identity-title').first()).toHaveText('E2E202610230002');
  await expect(page.getByRole('button', { name: '取消订单' })).toHaveCount(0);
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
