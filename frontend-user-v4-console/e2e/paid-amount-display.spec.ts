import type { Page } from '@playwright/test';

import { envelope, expect, mockAuthenticatedSession, test } from './fixtures';

// 优惠金额展示规范（docs/设计文档/前端/优惠金额展示规范-2026-10-31.md）的用户端验收：
// - 订单页（列表 + 详情）只出现实付金额，优惠拆分只出现在订单详情页最下方的费用明细区块；
// - 账单列表只出现实付金额，不展开优惠明细；
// - 账单详情只回答「付了多少钱、怎么付的、关联哪张账单与哪笔支付单」，不出现任何金额拆分；
// - 四页（订单列表 / 订单详情 / 账单列表 / 账单详情）实付金额同源于 paid_amount，数值必须完全一致。
//
// 取一组自洽数据：原价 108.00 − 优惠券 10.00 − 会员折扣 5.00 = 实付 93.00；
// 原价侧单价 9.00/月 × 12 个月 = 108.00。
// 后端口径见 backend/app/Support/Money.php 的 catalogAmountOf：
// 原价 = 应付价 + 优惠券减免 + 会员折扣减免。

const PAID = '¥93.00';
const CATALOG_FORMULA = '¥9.00/月 × 12 个月 = ¥108.00';

const ORDER_ROW = {
  id: 1,
  order_no: 'ORD202610310001',
  type: 'new',
  type_label: '新购',
  status: 1,
  status_label: '已支付',
  // 后端口径：amount 是应付价（= 原价 − 优惠合计），paid_amount 是实际入账。
  // 订单页唯一金额位取 paid_amount；列表若把 amount 当金额展示，断言会立刻失败。
  amount: '93.00',
  paid_amount: '93.00',
  discount: '10.00',
  member_discount_amount: '5.00',
  // 显式标注为可空记录：无优惠用例要把同一字段置 null，靠字面量推断会退化成 any
  member_discount_snapshot: { member_level_name: '黄金会员' } as Record<string, unknown> | null,
  coupon_code: 'E2ECOUPON',
  coupon_name: '新人券',
  billing_cycle: 'monthly',
  quantity: 12,
  // 计价快照：unit_total_amount × quantity 必须等于原价，否则费用明细退回只显示金额
  config_pricing_snapshot: { quantity: 12, unit_total_amount: '9.00' },
  product_name: '云服务器',
  product_full_path: '云服务器 / 标准型',
  service_name: '',
  channel_name: '微信支付',
  gateway_label: '微信支付',
  invoice: { id: 501, invoice_no: 'INV202610310001', status: 1, amount: '93.00', payment_window_expired: false },
  paid_at: '2026-10-31 10:00:00',
  finished_at: '2026-10-31 10:00:00',
  created_at: '2026-10-31 09:59:00',
};

/** 无优惠订单：单价 7.75/月 × 12 个月 = 93.00，与原价相等，费用明细只保留原价一行 */
const ORDER_ROW_PLAIN = {
  ...ORDER_ROW,
  amount: '93.00',
  discount: '0.00',
  member_discount_amount: '0.00',
  member_discount_snapshot: null as null | Record<string, unknown>,
  coupon_code: '',
  coupon_name: '',
  config_pricing_snapshot: { quantity: 12, unit_total_amount: '7.75' },
};

const INVOICE_ROW = {
  id: 501,
  invoice_no: 'INV202610310001',
  order_id: 1,
  type: 'new',
  type_label: '新购',
  status: 1,
  status_label: '已支付',
  amount: '93.00',
  discount: '10.00',
  member_discount_amount: '5.00',
  coupon_code: 'E2ECOUPON',
  coupon_name: '新人券',
  paid_amount: '93.00',
  payable_amount: '0.00',
  billing_cycle: 'monthly',
  finished_at: '2026-10-31 10:00:00',
  created_at: '2026-10-31 09:59:00',
  payment_window_expired: false,
  order: { id: 1, order_no: 'ORD202610310001', status: 1, type: 'new', billing_cycle: 'monthly' },
  payment_summary: {
    id: 1,
    payment_no: 'PY202610310001',
    gateway_label: '微信支付',
    channel_name: '微信支付',
    amount: '93.00',
    status: 1,
    status_label: '成功',
    paid_at: '2026-10-31 10:00:00',
  },
};

/** 账单详情走 v2 嵌套结构（basic / financial / display），与 src/api/client.ts 的 normalizeV2InvoiceDetail 对齐。 */
function invoiceDetailPayload() {
  return envelope({
    invoice: {
      id: 501,
      basic: {
        invoice_no: 'INV202610310001',
        type: 'new',
        type_label: '新购',
        status: 1,
        status_label: '已支付',
        billing_cycle: 'monthly',
        quantity: 12,
      },
      display: {
        product_display_name: '云服务器',
        combined_display_name: '云服务器 / 标准型',
        member_discount_amount: '5.00',
        coupon_code: 'E2ECOUPON',
        coupon_name: '新人券',
        member_discount_snapshot: { member_level_name: '黄金会员' },
      },
      financial: {
        amount: '93.00',
        discount: '10.00',
        paid_amount: '93.00',
        payable_amount: '0.00',
      },
      order: { id: 1, order_no: 'ORD202610310001', type_label: '新购' },
      payment_chain: {
        payments: [],
        payment_summary: {
          payment_no: 'PY202610310001',
          gateway_label: '微信支付',
          amount: '93.00',
          status: 1,
          status_label: '成功',
          paid_at: '2026-10-31 10:00:00',
        },
      },
      payment_options: { can_pay: false, pay_methods: [] },
      timestamps: { created_at: '2026-10-31 09:59:00', updated_at: '2026-10-31 10:00:00' },
    },
  });
}

function paged(rows: unknown[]) {
  return envelope({ list: rows, total: rows.length, page: 1, page_size: 20 });
}

function jsonOk(data: unknown) {
  return { status: 200, contentType: 'application/json', body: JSON.stringify(data) };
}

async function mockFinancePages(page: Page, orderRow: Record<string, unknown> = ORDER_ROW) {
  await page.route('**/api/v2/client/orders?*', (route) => route.fulfill(jsonOk(paged([ORDER_ROW]))));
  await page.route('**/api/v2/client/orders/1', (route) => route.fulfill(jsonOk(envelope(orderRow))));
  await page.route('**/api/v2/client/invoices?*', (route) => route.fulfill(jsonOk(paged([INVOICE_ROW]))));
  await page.route('**/api/v2/client/invoices/501', (route) => route.fulfill(jsonOk(invoiceDetailPayload())));
}

/** 金额文本取数：去掉币符、千分位与空白，保留负号 */
function toNumber(text: string | null) {
  return Number(String(text ?? '').replaceAll(/[¥,s]/g, ''));
}

test('四页实付金额完全一致，订单列表与账单列表不出现任何优惠拆分', async ({ page }) => {
  await mockAuthenticatedSession(page);
  await mockFinancePages(page);

  // 订单列表：列头「实付金额」，单元格取自 paid_amount
  await page.goto('/client/orders', { waitUntil: 'domcontentloaded' });
  const orderTable = page.locator('.record-table');
  await expect(orderTable.getByText('实付金额', { exact: true })).toBeVisible();
  await expect(orderTable.locator('.order-money').first()).toHaveText(PAID);
  // 旧金额列头与任何优惠口径都不该出现
  await expect(orderTable.getByText('金额', { exact: true })).toHaveCount(0);
  await expect(orderTable.getByText('优惠', { exact: true })).toHaveCount(0);
  await expect(orderTable.getByText('优惠券', { exact: true })).toHaveCount(0);
  // 断言范围限定列表内容区：左侧导航有「优惠券中心」入口，扫 body 会误伤
  const orderListText = (await page.locator('.client-orders').textContent()) ?? '';
  for (const banned of ['原价', '优惠券', '会员折扣', '优惠金额', '优惠合计', '-¥']) {
    expect(orderListText, `订单列表不应出现「${banned}」`).not.toContain(banned);
  }
  // 原价是前端由 amount + discount + member_discount_amount 还原出来的，列表不得把它算出来展示
  expect(orderListText).not.toContain('108.00');

  // 订单详情：唯一金额位是「实付金额」，与列表同值
  await page.goto('/client/orders/1', { waitUntil: 'domcontentloaded' });
  const paidItem = page.locator('.order-paid-amount');
  await expect(paidItem.locator('.order-paid-amount__label')).toHaveText('实付金额');
  await expect(paidItem.locator('.order-paid-amount__value')).toHaveText(PAID);

  // 账单列表：列头「实付金额」，与订单页同值
  await page.goto('/client/invoices', { waitUntil: 'domcontentloaded' });
  const invoiceTable = page.locator('.record-table');
  await expect(invoiceTable.getByText('实付金额', { exact: true })).toBeVisible();
  await expect(invoiceTable.locator('.invoice-money').first()).toHaveText(PAID);
  await expect(invoiceTable.getByText('金额', { exact: true })).toHaveCount(0);
  await expect(invoiceTable.getByText('已付', { exact: true })).toHaveCount(0);
  const invoiceListText = (await page.locator('.client-invoices').textContent()) ?? '';
  for (const banned of ['原价', '优惠券', '会员折扣', '优惠金额', '优惠合计', '-¥']) {
    expect(invoiceListText, `账单列表不应出现「${banned}」`).not.toContain(banned);
  }
  expect(invoiceListText).not.toContain('108.00');

  // 账单详情摘要条：仍是同一个 ¥93.00
  await page.goto('/client/invoices/501', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('.invoice-amount-summary__label')).toHaveText('实际支付金额');
  await expect(page.locator('.invoice-amount-summary__value')).toHaveText(PAID);
});

test('订单详情费用明细：位于页面最下方，行序固定且原价减各项优惠可直接核对到实付', async ({ page }) => {
  await mockAuthenticatedSession(page);
  await mockFinancePages(page);

  await page.goto('/client/orders/1', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('.order-paid-amount__value')).toHaveText(PAID);

  const feeDetail = page.locator('.order-fee-detail');
  await expect(feeDetail.getByText('费用明细', { exact: true })).toBeVisible();

  // 位置：费用明细必须排在订单信息卡之后，且是内容区（含订单信息卡的那一层）的最后一个区块
  const isLastBlock = await page.evaluate(() => {
    const fee = document.querySelector('.order-fee-detail');
    const card = document.querySelector('.order-detail-tabs-card');
    if (!fee || !card || fee.parentElement !== card.parentElement) return false;
    return [...card.parentElement.children].at(-1) === fee;
  });
  expect(isLastBlock).toBe(true);

  // 行序：原价（计算式）→ 优惠券 → 会员折扣；实付留在订单信息区的唯一金额位，区块内不重复
  const rows = feeDetail.locator('.order-fee-detail__row');
  await expect(rows).toHaveCount(3);
  await expect(rows.nth(0).locator('dt')).toHaveText('原价');
  await expect(rows.nth(0).locator('dd')).toHaveText(CATALOG_FORMULA);
  await expect(rows.nth(1).locator('dt')).toHaveText('新人券');
  await expect(rows.nth(1).locator('dd')).toHaveText('-¥10.00');
  await expect(rows.nth(2).locator('dt')).toHaveText('黄金会员');
  await expect(rows.nth(2).locator('dd')).toHaveText('-¥5.00');

  // 减号必须排在 ¥ 之前，不允许 ¥-10.00
  await expect(feeDetail).not.toContainText('¥-');

  // 优惠行左缩进：层级关系由缩进体现
  const indent = await rows.nth(1).evaluate((el) => getComputedStyle(el).paddingLeft);
  expect(indent).toBe('16px');

  // 等式可直接核对：计算式末尾的 108.00 − 10.00 − 5.00 = 93.00（实付取订单信息区那一处）
  const catalogText = (await rows.nth(0).locator('dd').textContent()) ?? '';
  const catalog = toNumber(catalogText.slice(catalogText.lastIndexOf('=') + 1));
  const coupon = Math.abs(toNumber(await rows.nth(1).locator('dd').textContent()));
  const member = Math.abs(toNumber(await rows.nth(2).locator('dd').textContent()));
  const paid = toNumber(await page.locator('.order-paid-amount__value').textContent());
  expect(catalog - coupon - member).toBeCloseTo(paid, 2);
});

test('订单详情无优惠时：费用明细只剩原价一行，不渲染优惠行与占位', async ({ page }) => {
  await mockAuthenticatedSession(page);
  await mockFinancePages(page, ORDER_ROW_PLAIN);

  await page.goto('/client/orders/1', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('.order-paid-amount__value')).toHaveText(PAID);

  const feeDetail = page.locator('.order-fee-detail');
  const rows = feeDetail.locator('.order-fee-detail__row');
  await expect(rows).toHaveCount(1);
  await expect(rows.nth(0).locator('dt')).toHaveText('原价');
  await expect(rows.nth(0).locator('dd')).toHaveText('¥7.75/月 × 12 个月 = ¥93.00');

  // 没有优惠就不该有优惠行，也不该留空行或占位
  await expect(feeDetail.locator('.order-fee-detail__row--discount')).toHaveCount(0);
  const feeText = (await feeDetail.textContent()) ?? '';
  for (const word of ['优惠券', '会员折扣', '优惠合计']) {
    expect(feeText, `无优惠订单的费用明细不应出现「${word}」`).not.toContain(word);
  }
});

test('账单详情不出现任何金额拆分：无费用明细、无原价、无优惠行', async ({ page }) => {
  await mockAuthenticatedSession(page);
  await mockFinancePages(page);

  await page.goto('/client/invoices/501', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('.invoice-amount-summary__value')).toHaveText(PAID);

  const detail = page.locator('.invoice-detail-page');
  await expect(detail.locator('.order-fee-detail')).toHaveCount(0);
  await expect(detail.getByText('费用明细', { exact: true })).toHaveCount(0);

  const detailText = (await detail.textContent()) ?? '';
  for (const banned of ['原价', '优惠券', '会员折扣', '优惠合计', '折扣率', '满减', '-¥']) {
    expect(detailText, `账单详情不应出现「${banned}」`).not.toContain(banned);
  }
  expect(detailText).not.toContain('108.00');
});

test('账单支付页不出现任何金额拆分：只留实际支付金额与本次应付，无原价与优惠行', async ({ page }) => {
  await mockAuthenticatedSession(page);
  // 待支付账单才会停留在支付页（已支付会直接跳回账单列表）。
  // 造部分已付：paid_amount 40.00 与 payable_amount 53.00 不同，用来证明摘要卡的金额位取 paid_amount，
  // 不会拿 amount（应付价 93.00）或 payable_amount 顶替。
  const payPagePayload = envelope({
    invoice: {
      id: 501,
      basic: {
        invoice_no: 'INV202610310001',
        type: 'new',
        type_label: '新购',
        status: 0,
        status_label: '待支付',
        billing_cycle: 'monthly',
      },
      display: { product_display_name: '云服务器' },
      financial: { amount: '93.00', discount: '10.00', paid_amount: '40.00', payable_amount: '53.00' },
      order: { id: 1, order_no: 'ORD202610310001', type_label: '新购' },
      payment_chain: { payments: [], payment_summary: null },
      payment_options: {
        can_pay: true,
        pay_methods: [
          { key: 'balance', option_key: 'balance', name: '余额支付', label: '余额支付', payment_type: 'balance' },
        ],
        payment_security: { can_pay: true, session_token: 'e2e-session-token' },
      },
      timestamps: { created_at: '2026-10-31 09:59:00', updated_at: '2026-10-31 10:00:00' },
    },
  });
  await page.route('**/api/v2/client/invoices/501', (route) => route.fulfill(jsonOk(payPagePayload)));

  await page.goto('/client/invoices/501/pay', { waitUntil: 'domcontentloaded' });

  // 摘要卡唯一金额位：实际支付金额，取 paid_amount，不是应付价也不是本次应付
  const paidRow = page.locator('.summary-paid-row');
  await expect(paidRow.locator('.summary-item__label')).toHaveText('实际支付金额');
  await expect(paidRow.locator('.summary-item__value')).toHaveText('¥40.00');

  // 账单信息卡不再重复金额：只留账单号 / 账单类型 / 计费周期 / 产品链路，无「账单金额」
  const infoCard = page.locator('.order-info-card');
  await expect(infoCard.getByText('账单号', { exact: true })).toBeVisible();
  await expect(infoCard.getByText('账单金额', { exact: true })).toHaveCount(0);

  // 支付页不得出现任何优惠拆分与减项金额
  const payText = (await page.locator('.invoice-pay-page').textContent()) ?? '';
  for (const banned of ['原价', '优惠券', '会员折扣', '优惠合计', '折扣率', '满减', '-¥']) {
    expect(payText, `账单支付页不应出现「${banned}」`).not.toContain(banned);
  }
  expect(payText).not.toContain('108.00');
});

test('续费账单支付页的续费信息只描述续费对象与账期，不重复金额', async ({ page }) => {
  await mockAuthenticatedSession(page);
  const renewRow = {
    ...INVOICE_ROW,
    type: 'renew',
    type_label: '续费',
    billing_cycle: 'monthly',
    service_id: 9,
    service: { id: 9, name: '云服务器 S1', expires_at: '2026-11-30 00:00:00' },
  };
  await page.route('**/api/v2/client/orders?*', (route) => route.fulfill(jsonOk(paged([ORDER_ROW]))));
  await page.route('**/api/v2/client/invoices?*', (route) => route.fulfill(jsonOk(paged([INVOICE_ROW]))));
  // 待支付的续费账单：type=renew 才会渲染「续费信息」卡，且不会被 loadDetail 直接踢回列表
  const renewDetailPayload = envelope({
    invoice: {
      id: 501,
      basic: {
        invoice_no: 'INV202610310001',
        type: 'renew',
        type_label: '续费',
        status: 0,
        status_label: '待支付',
        billing_cycle: 'monthly',
      },
      display: {
        product_display_name: '云服务器 S1',
        member_discount_amount: '5.00',
        coupon_name: '新人券',
      },
      financial: { amount: '93.00', discount: '10.00', paid_amount: '40.00', payable_amount: '53.00' },
      service: renewRow.service,
      order: { id: 1, order_no: 'ORD202610310001', type_label: '续费' },
      payment_chain: { payments: [], payment_summary: null },
      payment_options: {
        can_pay: true,
        pay_methods: [
          { key: 'balance', option_key: 'balance', name: '余额支付', label: '余额支付', payment_type: 'balance' },
        ],
        payment_security: { can_pay: true, session_token: 'e2e-session-token' },
        service_id: 9,
      },
      timestamps: { created_at: '2026-10-31 09:59:00', updated_at: '2026-10-31 10:00:00' },
    },
  });
  await page.route('**/api/v2/client/invoices/501', (route) => route.fulfill(jsonOk(renewDetailPayload)));

  await page.goto('/client/invoices/501/pay', { waitUntil: 'domcontentloaded' });

  const renewGrid = page.locator('.renew-kv-grid');
  await expect(renewGrid).toBeVisible();
  // 续费信息只回答「续的哪个服务、什么账期」；续费金额与本次应付已在摘要卡有唯一金额位
  await expect(renewGrid.getByText('续费金额', { exact: true })).toHaveCount(0);
  await expect(renewGrid.getByText('本次应付', { exact: true })).toHaveCount(0);
  const renewText = (await renewGrid.textContent()) ?? '';
  expect(renewText).not.toContain('¥');
  // 摘要卡里的实际支付金额仍是同一处金额位，与订单详情、账单列表同源
  await expect(page.locator('.summary-paid-row .summary-item__value')).toHaveText('¥40.00');
});
