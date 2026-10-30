import { collectConsoleErrors, envelope, expect, mockAuthenticatedSession, test } from './fixtures';

/**
 * 财务账单详情页「取消账单」（2026-10-28 P3-e）。
 *
 * 修复前 handleCancel 写成 `const confirmed = await DialogPlugin.confirm({...})`，
 * 而 DialogPlugin.confirm 返回的是 DialogInstance（不是 Promise<boolean>），await 拿到的是
 * 实例本身、恒为真值 → `if (!confirmed) return` 永不生效，点「取消账单」立刻发请求，
 * 二次确认形同虚设。修复后结果在 onConfirm 里处理，本用例因此可以先断言
 * 「未点确定时请求次数为 0」，再断言「点确定后为 1」。
 */

const UNPAID_INVOICE_DETAIL = {
  invoice: {
    id: 501,
    basic: {
      invoice_no: 'E2E-INV-202610280501',
      type: 'normal',
      type_label: '新购',
      status: 0,
      status_label: '待支付',
      billing_cycle: 'month',
      quantity: 1,
      due_date: '2026-11-04',
    },
    financial: { amount: '99.00', paid_amount: '0', discount: '0' },
    order: { id: 1, order_no: 'E2E202610280001' },
    // can_cancel 只影响抽屉内的动作区，页面按钮由 cancelable（status + invoice.manage 权限）决定
    actions: { can_refund_to_balance: false, can_cancel: true },
    timestamps: { created_at: '2026-10-28 10:00:00', updated_at: '2026-10-28 10:00:00' },
  },
  payments: [] as Record<string, unknown>[],
  items: [] as Record<string, unknown>[],
  logs: [] as Record<string, unknown>[],
};

// 提为模块常量：内联 envelope(...) 会触发 prettier 与 antfu/consistent-list-newline 的循环修复
const UNPAID_INVOICE_ENVELOPE = envelope(UNPAID_INVOICE_DETAIL);

test('账单详情取消账单需二次确认，确认后才调用取消接口', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);

  let cancelCalls = 0;
  // 一条通配同时覆盖详情的 GET（/invoices/501?_t=）与取消的 POST（/invoices/501/cancellations）。
  // 注意 Playwright 的 glob 里 `*` 不跨 `/`，写成 `501*` 匹配不到 `/501/cancellations`，必须用 `501**`。
  await page.route('**/v2/admin/invoices/501**', (route) => {
    const request = route.request();
    if (request.method() === 'POST' && request.url().includes('/cancellations')) {
      cancelCalls += 1;
      return route.fulfill(
        jsonResponse(
          envelope({
            id: 501,
            status: 'completed',
            message: '账单已取消',
            detail: { type: 'cancellation', invoice: { id: 501, status: 4 } },
          }),
        ),
      );
    }
    return route.fulfill(jsonResponse(UNPAID_INVOICE_ENVELOPE));
  });

  await page.goto('/admin/finance/invoices/501', { waitUntil: 'domcontentloaded' });

  const cancelButton = page.getByRole('button', { name: '取消账单' });
  await expect(cancelButton).toBeVisible();

  // 打开确认框但先不确认：修复前这里已经发出请求
  await cancelButton.click();
  await expect(page.getByText('取消后账单不可继续支付')).toBeVisible();
  expect(cancelCalls).toBe(0);

  await page.getByRole('button', { name: '确定' }).click();
  await expect(page.getByText('账单已取消').first()).toBeVisible();
  expect(cancelCalls).toBe(1);

  expect(errors).toEqual([]);
});

test('取消账单失败时提示服务端原因且不当作成功', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);

  let cancelCalls = 0;
  await page.route('**/v2/admin/invoices/501**', (route) => {
    const request = route.request();
    if (request.method() === 'POST' && request.url().includes('/cancellations')) {
      cancelCalls += 1;
      // 与支付回调竞态时账单已被入账，服务端拒绝取消：前端必须把原因透出
      return route.fulfill(jsonResponse(envelope(null, 42200, '当前账单状态不支持取消')));
    }
    return route.fulfill(jsonResponse(UNPAID_INVOICE_ENVELOPE));
  });

  await page.goto('/admin/finance/invoices/501', { waitUntil: 'domcontentloaded' });

  await page.getByRole('button', { name: '取消账单' }).click();
  await expect(page.getByText('取消后账单不可继续支付')).toBeVisible();
  await page.getByRole('button', { name: '确定' }).click();

  await expect(page.getByText('当前账单状态不支持取消')).toBeVisible();
  expect(cancelCalls).toBe(1);
  await expect(page.getByText('账单已取消')).toHaveCount(0);

  expect(errors).toEqual([]);
});

function jsonResponse(body: unknown, status = 200) {
  return { status, contentType: 'application/json', body: JSON.stringify(body) };
}
