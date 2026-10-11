import { envelope, expect, mockAuthenticatedSession, test } from './fixtures';

// 账单支付页用例（mock 网关）：支付成功后只保留一条「账单已支付」成功提示并跳回账单列表，
// 不得再叠加「该账单已支付，无需再次支付」的守卫提示（loadDetail skipStatusNotice 回归）。

function jsonOk(data: unknown) {
  return { status: 200, contentType: 'application/json', body: JSON.stringify(data) };
}

const PAID_INVOICE_BASIC = {
  invoice_no: 'E2E-INV-0001',
  type: 'new',
  type_label: '新购',
};

/** 待支付详情（payment_options.pay_methods 按用例传入），进支付页时的初始状态。 */
function pendingInvoiceDetailPayload(payMethods: unknown[]) {
  return envelope({
    invoice: {
      id: 1,
      basic: { ...PAID_INVOICE_BASIC, status: 0, status_label: '待支付' },
      display: { product_display_name: '云服务器 S1' },
      financial: { amount: '20.00', payable_amount: '20.00', paid_amount: '0.00' },
      payment_options: {
        can_cancel: true,
        pay_methods: payMethods,
        payment_security: { can_pay: true, session_token: 'e2e-session-token' },
      },
    },
  });
}

/** 已支付详情：支付成功后 loadDetail 刷新应拿到该形态并直接跳回列表（不再弹守卫提示）。 */
function paidInvoiceDetailPayload() {
  return envelope({
    invoice: {
      id: 1,
      basic: { ...PAID_INVOICE_BASIC, status: 1, status_label: '已支付' },
      display: { product_display_name: '云服务器 S1' },
      financial: { amount: '20.00', payable_amount: '20.00', paid_amount: '20.00' },
      payment_options: {
        can_cancel: false,
        pay_methods: [],
        payment_security: { can_pay: false },
      },
    },
  });
}

test('账单余额支付成功：仅保留一条成功提示并跳回账单列表，不叠加「已支付」守卫提示', async ({ page }) => {
  await mockAuthenticatedSession(page);

  // 布防：守卫提示若在支付链路中弹出（3s 内），waitFor 立即兑现为 true；
  // 不弹则等满超时返回 false。不能用 toBeHidden——toast 数秒后自动消失，会让该断言恒真。
  const guardToastAppeared = page
    .getByText('该账单已支付，无需再次支付')
    .first()
    .waitFor({ state: 'visible', timeout: 3_000 })
    .then(() => true)
    .catch(() => false);

  let payCompleted = false;
  await page.route('**/api/v2/client/invoices/1/pay/balance', (route) => {
    payCompleted = true;
    return route.fulfill(
      jsonOk(
        envelope({
          invoice: {
            id: 1,
            ...PAID_INVOICE_BASIC,
            status: 1,
            status_label: '已支付',
            amount: '20.00',
            payable_amount: '20.00',
            paid_amount: '20.00',
          },
        }),
      ),
    );
  });
  await page.route('**/api/v2/client/invoices?*', (route) => {
    route.fulfill(jsonOk(envelope({ list: [], total: 0, page: 1, page_size: 15 })));
  });
  await page.route('**/api/v2/client/invoices/1', (route) => {
    route.fulfill(
      jsonOk(
        payCompleted
          ? paidInvoiceDetailPayload()
          : pendingInvoiceDetailPayload([
              { key: 'balance', option_key: 'balance', name: '余额支付', label: '余额支付', payment_type: 'balance' },
            ]),
      ),
    );
  });

  await page.goto('/client/invoices/1/pay', { waitUntil: 'domcontentloaded' });
  // 账单号在「账单信息」卡与「账单摘要」卡各出现一次，取首个即可
  await expect(page.getByText('E2E-INV-0001').first()).toBeVisible();

  // 余额支付方式自动选中，点「支付」→ 确认弹窗「确定」
  await page.getByRole('button', { name: '支付', exact: true }).click();
  await page.getByRole('button', { name: '确定' }).click();

  // 成功提示出现并跳回账单列表；守卫提示不得曾经出现
  await expect(page.getByText('账单已支付', { exact: true })).toBeVisible();
  await expect(page).toHaveURL(/\/client\/invoices$/);
  expect(await guardToastAppeared).toBe(false);
});

test('账单支付宝扫码成功：轮询到已支付后仅保留一条成功提示并跳回列表', async ({ page }) => {
  await mockAuthenticatedSession(page);

  const guardToastAppeared = page
    .getByText('该账单已支付，无需再次支付')
    .first()
    .waitFor({ state: 'visible', timeout: 3_000 })
    .then(() => true)
    .catch(() => false);

  let payCompleted = false;
  let statusCalls = 0;
  await page.route('**/api/v2/client/invoices/1/pay/alipay/status*', (route) => {
    statusCalls += 1;
    const paid = statusCalls >= 2;
    if (paid) payCompleted = true;
    return route.fulfill(
      jsonOk(envelope(paid ? { paid: true, invoice: { id: 1, status: 1 } } : { paid: false, message: '等待支付' })),
    );
  });
  await page.route('**/api/v2/client/invoices/1/pay/alipay', (route) => {
    route.fulfill(
      jsonOk(
        envelope({
          qr_code: 'https://pay.e2e.example.com/qr/E2E-INV-0001',
          payment_no: 'E2E-PAY-INV-0001',
          poll_token: 'e2e-inv-poll-token',
          gateway: 'alipay',
          amount: '20.00',
        }),
      ),
    );
  });
  await page.route('**/api/v2/client/invoices?*', (route) => {
    route.fulfill(jsonOk(envelope({ list: [], total: 0, page: 1, page_size: 15 })));
  });
  await page.route('**/api/v2/client/invoices/1', (route) => {
    route.fulfill(
      jsonOk(
        payCompleted
          ? paidInvoiceDetailPayload()
          : pendingInvoiceDetailPayload([
              {
                key: 'alipay',
                option_key: 'alipay:alipay',
                name: '支付宝',
                label: '支付宝扫码',
                payment_type: 'alipay',
              },
            ]),
      ),
    );
  });

  await page.goto('/client/invoices/1/pay', { waitUntil: 'domcontentloaded' });
  await expect(page.getByText('E2E-INV-0001').first()).toBeVisible();

  // 生成二维码，弹窗展示商家订单号
  await page.getByRole('button', { name: '生成支付宝二维码' }).click();
  await expect(page.getByText('E2E-PAY-INV-0001')).toBeVisible();

  // 手动触发轮询：第 1 次未支付给出等待提示，第 2 次已支付走成功链路
  const refreshButton = page.getByRole('button', { name: '我已完成支付，刷新状态' });
  await refreshButton.click();
  await expect(page.getByText('等待支付', { exact: true })).toBeVisible();
  await refreshButton.click();

  await expect(page.getByText('账单已支付', { exact: true })).toBeVisible();
  await expect(page).toHaveURL(/\/client\/invoices$/);
  expect(await guardToastAppeared).toBe(false);
});
