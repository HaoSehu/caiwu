import { envelope, expect, mockAuthenticatedSession, test } from './fixtures';

// 续费链路用例（接口全 mock）：服务控制台续费入口 → 创建续费账单 → 跳转支付页；
// 续费账单支付成功；以及支付窗口已关闭后续费账单的守卫文案。
//
// 背景：续费账单的支付窗口按账单 due_date 计（后端 CheckoutSecurityService::paymentSessionExpiresAt），
// 不再是 5 分钟支付会话窗口；窗口关闭后 session_token 为空、can_pay=false，
// 页面必须给出「回服务控制台重新发起续费」的可行下一步，而不是「刷新页面重试」。

function jsonOk(data: unknown) {
  return { status: 200, contentType: 'application/json', body: JSON.stringify(envelope(data)) };
}

const RENEW_INVOICE_NO = 'E2E-INV-RENEW-0001';
const RENEW_INVOICE_ID = 9;
const RENEW_PAY_METHODS = [
  { key: 'balance', option_key: 'balance', name: '余额支付', label: '余额支付', payment_type: 'balance' },
];

/** 续费账单详情：basic.type=renew 决定页面渲染「续费信息」卡（信封由 jsonOk 统一包）。 */
function renewInvoiceDetailPayload(canPay: boolean, paid: boolean) {
  return {
    invoice: {
      id: RENEW_INVOICE_ID,
      basic: {
        invoice_no: RENEW_INVOICE_NO,
        type: 'renew',
        type_label: '续费',
        status: paid ? 1 : 0,
        status_label: paid ? '已支付' : '待支付',
        billing_cycle: 'monthly',
        due_date: '2026-12-31',
      },
      display: { product_display_name: '云服务器 S1' },
      financial: {
        amount: '99.00',
        payable_amount: paid ? '0.00' : '99.00',
        paid_amount: paid ? '99.00' : '0.00',
      },
      service: { id: 1, name: '云服务器 E2E-01', expires_at: '2026-12-01 00:00:00' },
      payment_options: {
        can_cancel: !paid,
        pay_methods: RENEW_PAY_METHODS,
        payment_security: {
          can_pay: canPay,
          session_token: canPay ? 'e2e-renew-session-token' : '',
          // 续费账单的凭证有效期跟随 due_date（不是 5 分钟），此处给未来时间
          expires_at: '2026-12-31 23:59:59',
        },
      },
    },
  };
}

test('续费账单支付窗口已关闭：展示回服务控制台重新发起续费的引导，支付按钮不可用', async ({ page }) => {
  await mockAuthenticatedSession(page);

  await page.route('**/api/v2/client/invoices/9', (route) => {
    return route.fulfill(jsonOk(renewInvoiceDetailPayload(false, false)));
  });

  await page.goto('/client/invoices/9/pay', { waitUntil: 'domcontentloaded' });
  await expect(page.getByText(RENEW_INVOICE_NO).first()).toBeVisible();
  // 续费账单识别正常，未被当成「不支持支付」直接跳回列表
  await expect(page.getByText('续费信息')).toBeVisible();

  const closedTip =
    '支付窗口已关闭，该续费账单无法继续支付。请返回服务控制台重新发起续费，系统会生成一张新的续费账单。';
  await expect(page.getByText(closedTip)).toBeVisible();
  // 不得再出现「刷新页面重试」这类无法解决问题的旧文案
  await expect(page.getByText('支付会话已失效，请刷新页面后重试')).toHaveCount(0);
  await expect(page.getByRole('button', { name: '支付', exact: true })).toBeDisabled();
});

/** 账单列表行（信封由 jsonOk 统一包）：字段与后端 ClientInvoiceSummaryResource 对齐。 */
function invoiceListRow(overrides: Record<string, unknown>) {
  return {
    id: 1,
    invoice_no: 'E2E-INV-0001',
    type: 'renew',
    type_label: '续费',
    status: 0,
    status_label: '待支付',
    amount: '99.00',
    discount: '0.00',
    member_discount_amount: '0.00',
    paid_amount: '0.00',
    payable_amount: '99.00',
    due_date: '2026-12-31',
    created_at: '2026-12-01 10:00:00',
    payment_window_expired: false,
    ...overrides,
  };
}

test('账单列表：支付窗口已关闭的待支付账单不再显示「去支付」', async ({ page }) => {
  await mockAuthenticatedSession(page);

  await page.route('**/api/v2/client/invoices/21', (route) => {
    return route.fulfill(jsonOk(renewInvoiceDetailPayload(true, false)));
  });
  await page.route('**/api/v2/client/invoices?*', (route) => {
    return route.fulfill(
      jsonOk({
        list: [
          invoiceListRow({ id: 21, invoice_no: 'E2E-INV-OPEN-0021', payment_window_expired: false }),
          invoiceListRow({
            id: 22,
            invoice_no: 'E2E-INV-CLOSED-0022',
            type: 'manual',
            type_label: '人工账单',
            payment_window_expired: true,
          }),
          invoiceListRow({ id: 23, invoice_no: 'E2E-INV-PAID-0023', status: 1, status_label: '已支付' }),
        ],
        total: 3,
        page: 1,
        page_size: 20,
      }),
    );
  });

  await page.goto('/client/invoices', { waitUntil: 'domcontentloaded' });

  const table = page.locator('.record-table');
  await expect(table.getByText('E2E-INV-OPEN-0021')).toBeVisible();
  await expect(table.getByText('E2E-INV-CLOSED-0022')).toBeVisible();

  // 只有「待支付且窗口仍开放」的行给「去支付」；窗口已关闭的行给一句说明，而不是可点按钮
  await expect(table.getByRole('button', { name: '去支付' })).toHaveCount(1);
  await expect(table.getByText('支付窗口已关闭')).toHaveCount(1);

  await table.getByRole('button', { name: '去支付' }).click();
  await expect(page).toHaveURL(/\/client\/invoices\/21\/pay$/);
});

test('续费账单余额支付成功：提示账单已支付并跳回账单列表', async ({ page }) => {
  await mockAuthenticatedSession(page);

  let payCompleted = false;
  await page.route('**/api/v2/client/invoices/9/pay/balance', (route) => {
    payCompleted = true;
    return route.fulfill(jsonOk({ invoice: { id: RENEW_INVOICE_ID, status: 1, status_label: '已支付' } }));
  });
  await page.route('**/api/v2/client/invoices?*', (route) => {
    return route.fulfill(jsonOk({ list: [], total: 0, page: 1, page_size: 15 }));
  });
  await page.route('**/api/v2/client/invoices/9', (route) => {
    return route.fulfill(jsonOk(renewInvoiceDetailPayload(!payCompleted, payCompleted)));
  });

  await page.goto('/client/invoices/9/pay', { waitUntil: 'domcontentloaded' });
  await expect(page.getByText(RENEW_INVOICE_NO).first()).toBeVisible();

  await page.getByRole('button', { name: '支付', exact: true }).click();
  await page.getByRole('button', { name: '确定' }).click();

  await expect(page.getByText('账单已支付', { exact: true })).toBeVisible();
  await expect(page).toHaveURL(/\/client\/invoices$/);
});

test('服务控制台续费入口：创建续费账单后直接跳转支付页', async ({ page }) => {
  await mockAuthenticatedSession(page);

  const serviceDetail = {
    id: 1,
    name: '云服务器 E2E-01',
    status: 1,
    status_tone: 'success',
    console_template: '',
    machine_category: { key: 'cloud', label: '云服务器' },
    product: { id: 1, name: '云服务器', type: 'cloud', type_label: '云服务器', display_name: '云服务器 S1' },
    amount: '99.00',
    billing_cycle: 'monthly',
    billing_cycle_label: '月付',
    expires_at: '2026-12-01 00:00:00',
    auto_renew: 0,
    actions: {},
  };

  await page.route('**/api/v2/client/services/1', (route) => {
    return route.fulfill(jsonOk({ service: serviceDetail }));
  });
  await page.route('**/api/v2/client/services/1/runtime', (route) => {
    return route.fulfill(jsonOk({ runtime: { power_state: 'running', power_label: '运行中' } }));
  });
  await page.route('**/api/v2/client/services/1/connection', (route) => {
    return route.fulfill(jsonOk({ connection: { dedicated_ip: '203.0.113.10' } }));
  });

  // 同一路径按方法分流：GET 取续费预览，POST 创建续费账单
  await page.route('**/api/v2/client/services/1/renewals*', (route) => {
    if (route.request().method() === 'POST') {
      return route.fulfill(jsonOk({ id: RENEW_INVOICE_ID, status: 0, service_id: 1 }));
    }

    return route.fulfill(
      jsonOk({
        default_cycle: 'monthly',
        cycles: [{ billing_cycle: 'monthly', billing_cycle_label: '月付', amount: '99.00' }],
        available_coupons: [],
        renew_coupon_binding: null,
      }),
    );
  });

  await page.route('**/api/v2/client/invoices/9', (route) => {
    return route.fulfill(jsonOk(renewInvoiceDetailPayload(true, false)));
  });

  await page.goto('/client/services/1', { waitUntil: 'domcontentloaded' });
  await expect(page.getByText('付费信息')).toBeVisible();

  await page.getByRole('button', { name: '续费管理' }).click();
  await expect(page.getByText('服务续费')).toBeVisible();
  // 预览取回后默认周期已选中，确认按钮才可用（disabled 绑定 billing_cycle）
  const submitButton = page.getByRole('button', { name: '确定' });
  await expect(submitButton).toBeEnabled();
  await submitButton.click();

  await expect(page.getByText('续费账单已创建，正在跳转支付')).toBeVisible();
  await expect(page).toHaveURL(/\/client\/invoices\/9\/pay$/);
  await expect(page.getByText('续费信息')).toBeVisible();
});

test('账单详情页：支付窗口已关闭时不提供必然被拦下的「去支付」入口', async ({ page }) => {
  await mockAuthenticatedSession(page);

  await page.route('**/api/v2/client/invoices/9', (route) => {
    return route.fulfill(jsonOk(renewInvoiceDetailPayload(false, false)));
  });

  await page.goto('/client/invoices/9', { waitUntil: 'domcontentloaded' });

  await expect(page.getByText(RENEW_INVOICE_NO).first()).toBeVisible();
  // 窗口判断与支付页同源（后端下发的 payment_security.can_pay），详情页不再给出可点但必然失败的入口
  await expect(page.getByRole('button', { name: '去支付' })).toHaveCount(0);
  await expect(page.getByText('支付窗口已关闭')).toBeVisible();
});

test('账单详情页：支付窗口仍开放时「去支付」跳转支付页', async ({ page }) => {
  await mockAuthenticatedSession(page);

  await page.route('**/api/v2/client/invoices/9', (route) => {
    return route.fulfill(jsonOk(renewInvoiceDetailPayload(true, false)));
  });

  await page.goto('/client/invoices/9', { waitUntil: 'domcontentloaded' });

  const payButton = page.getByRole('button', { name: '去支付' });
  await expect(payButton).toBeVisible();
  await expect(page.getByText('支付窗口已关闭')).toHaveCount(0);

  await payButton.click();
  await expect(page).toHaveURL(/\/client\/invoices\/9\/pay$/);
});

/** 订单列表行：字段与后端 ClientOrderQueryService::listItem 对齐，invoice 可能为 null。 */
function orderListRow(overrides: Record<string, unknown>) {
  return {
    id: 1,
    order_no: 'E2E-ORDER-0001',
    type: 'renew',
    type_label: '续费',
    status: 0,
    status_label: '待支付',
    amount: '99.00',
    discount: '0.00',
    member_discount_amount: '0.00',
    paid_amount: '0.00',
    billing_cycle: 'monthly',
    product_name: '云服务器 S1',
    created_at: '2026-12-01 10:00:00',
    invoice: null as null | Record<string, unknown>,
    ...overrides,
  };
}

test('订单列表：只有「订单待支付 + 账单待支付 + 窗口未关闭」才给「去支付」', async ({ page }) => {
  await mockAuthenticatedSession(page);

  await page.route('**/api/v2/client/invoices/931', (route) => {
    const payload = renewInvoiceDetailPayload(true, false);
    payload.invoice.id = 931;
    payload.invoice.basic.invoice_no = 'E2E-INV-OPEN-0931';
    return route.fulfill(jsonOk(payload));
  });
  await page.route('**/api/v2/client/orders*', (route) => {
    return route.fulfill(
      jsonOk({
        list: [
          orderListRow({
            id: 31,
            order_no: 'E2E-ORDER-OPEN-0031',
            invoice: { id: 931, invoice_no: 'E2E-INV-OPEN-0931', status: 0, payment_window_expired: false },
          }),
          orderListRow({
            id: 32,
            order_no: 'E2E-ORDER-CLOSED-0032',
            invoice: { id: 932, invoice_no: 'E2E-INV-CLOSED-0932', status: 0, payment_window_expired: true },
          }),
          // 管理端「只建订单不建账单」：invoice 为 null，前端拼不出支付页地址
          orderListRow({ id: 33, order_no: 'E2E-ORDER-NOINV-0033', invoice: null }),
        ],
        total: 3,
        page: 1,
        page_size: 20,
      }),
    );
  });

  await page.goto('/client/orders', { waitUntil: 'domcontentloaded' });

  const table = page.locator('.record-table');
  await expect(table.getByText('E2E-ORDER-OPEN-0031')).toBeVisible();
  await expect(table.getByText('E2E-ORDER-NOINV-0033')).toBeVisible();

  // 三行三种结论：可支付 / 窗口已关闭 / 没有可支付账单；只有第一种给按钮
  await expect(table.getByRole('button', { name: '去支付' })).toHaveCount(1);
  await expect(table.getByText('支付窗口已关闭')).toHaveCount(1);
  await expect(table.getByText('无可支付账单')).toHaveCount(1);

  // 跳转必须带真实账单 id（invoice=null 时绝不能拼出 /invoices/undefined/pay）
  await table.getByRole('button', { name: '去支付' }).click();
  await expect(page).toHaveURL(/\/client\/invoices\/931\/pay$/);
});

test('订单详情：没有关联账单时不提供「去支付」，也不谎报支付窗口已关闭', async ({ page }) => {
  await mockAuthenticatedSession(page);

  await page.route('**/api/v2/client/orders/33', (route) => {
    return route.fulfill(jsonOk(orderListRow({ id: 33, order_no: 'E2E-ORDER-NOINV-0033', invoice: null })));
  });

  await page.goto('/client/orders/33', { waitUntil: 'domcontentloaded' });
  await expect(page.getByText('E2E-ORDER-NOINV-0033').first()).toBeVisible();

  // 账单不存在时既不给入口，也不能套用「支付窗口已关闭」的结论
  await expect(page.getByRole('button', { name: '去支付' })).toHaveCount(0);
  await expect(page.getByText('支付窗口已关闭')).toHaveCount(0);
  await expect(page.getByText('无可支付账单')).toBeVisible();

  // 关联信息 tab 明确告知「暂无关联账单」，不留空白区块
  await page.getByText('关联信息', { exact: true }).click();
  await expect(page.getByText('暂无关联账单')).toBeVisible();
});
