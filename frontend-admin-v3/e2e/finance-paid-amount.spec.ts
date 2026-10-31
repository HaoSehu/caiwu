import { collectConsoleErrors, envelope, expect, mockAuthenticatedSession, test } from './fixtures';

/**
 * 「实付金额」口径（docs/设计文档/前端/优惠金额展示规范-2026-10-31.md §1.1 / §1.4 / §1.5 / §2.1）。
 *
 * 断言目的：
 * - 订单列表与账单列表都只有一个金额列，标题为「实付金额」，取值 paid_amount，
 *   「金额」「已付」「优惠」「优惠券」列头一概不存在；
 * - 订单详情的唯一金额位是「实付金额」，概览指标里只剩这一项金额指标；
 * - 优惠拆分只出现在订单详情页最下方的费用明细区块：原价（计算式）→ 优惠券 → 会员折扣，
 *   行序固定、缺项整行不渲染，且 原价 − 各项优惠 === 实付金额；
 * - 账单详情只回答「付了多少钱、怎么付的、关联哪张账单」，不出现费用明细、原价与任何减项金额。
 *
 * 接口全部由 page.route() 拦截，不依赖本地后端（见 e2e/fixtures.ts 的设计前提）。
 */

const ORDERS_LIST_RESPONSE = {
  list: [
    {
      id: 1,
      order_no: 'E2E202610230001',
      user_id: 1,
      user: { nickname: '测试用户', email: 'e2e@example.com' },
      product_name: '云服务器 E2E',
      type: 'normal',
      // 故意让 amount 与 paid_amount 不同：列表若又取回 amount，金额文本会露馅
      amount: '9999.99',
      paid_amount: '12345.60',
      discount: '0',
      billing_cycle: 'monthly',
      quantity: 1,
      status: 'paid',
      paid_at: '2026-10-23 10:05:00',
      finished_at: '2026-10-23 10:05:00',
      created_at: '2026-10-23 10:00:00',
    },
  ],
  total: 1,
  page: 1,
  page_size: 20,
};

const INVOICES_LIST_RESPONSE = {
  list: [
    {
      id: 1,
      invoice_no: 'E2E202610230001',
      user: { nickname: '测试用户', email: 'e2e@example.com' },
      product_full_path: '云服务器 / 标准型',
      type: 'new',
      type_label: '新购',
      // 同上：amount 与 paid_amount 必须能区分，确保断言真的落在 paid_amount 上
      amount: '99.00',
      paid_amount: '88.00',
      discount: '11.00',
      billing_cycle: 'annually',
      status: 1,
      created_at: '2026-10-23 10:00:00',
      paid_at: '2026-10-23 10:01:00',
      finished_at: '2026-10-23 10:01:00',
    },
  ],
  total: 1,
  page: 1,
  page_size: 20,
};

/**
 * 订单详情（优惠场景，数值自洽）：
 * 原价 = amount 93.00（应付价）+ discount 10.00 + member_discount_amount 5.00 = 108.00；
 * 计价快照 unit_total_amount 9.00 × quantity 12 = 108.00，与原价一致，
 * 因此原价行渲染成计算式「¥9.00/月 × 12 个月 = ¥108.00」。
 * 108.00 − 10.00 − 5.00 === paid_amount 93.00。
 */
const ORDER_DETAIL_RESPONSE = {
  order: {
    id: 1,
    basic: {
      order_no: 'E2E202610230002',
      type: 'normal',
      type_label: '新购',
      status: 1,
      status_label: '已支付',
      billing_cycle: 'monthly',
      quantity: 12,
    },
    financial: {
      amount: '93.00',
      discount: '10.00',
      member_discount_amount: '5.00',
      member_discount_snapshot: { member_level_name: 'E2E 黄金会员' },
      paid_amount: '93.00',
      paid_at: '2026-10-23 10:05:00',
    },
    coupon: { id: 1, code: 'E2ECOUPON', name: 'E2E 优惠券' },
    invoice: { id: 501, invoice_no: 'E2E-INV-202610230501' },
    configuration: { config_pricing_snapshot: { quantity: 12, unit_total_amount: '9.00' } },
    timestamps: { created_at: '2026-10-23 10:00:00', updated_at: '2026-10-23 10:00:00' },
  },
};

/** 订单详情（无优惠场景）：折扣全为 0，优惠行整行不渲染，费用明细只剩原价一行 */
const ORDER_DETAIL_PLAIN = {
  order: {
    ...ORDER_DETAIL_RESPONSE.order,
    financial: { amount: '93.00', discount: '0', member_discount_amount: '0', paid_amount: '93.00' },
    coupon: null as null | Record<string, unknown>,
    configuration: { config_pricing_snapshot: { quantity: 12, unit_total_amount: '7.75' } },
  },
};

/**
 * 账单详情（带折扣字段）：账单侧不再做任何金额拆分，
 * 即便后端下发了 discount 与 member_discount_amount，页面也不得把它们渲染出来。
 */
const INVOICE_DETAIL_DISCOUNTED = {
  invoice: {
    id: 501,
    basic: {
      invoice_no: 'E2E-INV-202610230501',
      type: 'normal',
      type_label: '新购',
      status: 1,
      status_label: '已支付',
      billing_cycle: 'monthly',
      quantity: 1,
    },
    financial: {
      amount: '93.00',
      discount: '10.00',
      member_discount_amount: '5.00',
      coupon_code: 'E2ECOUPON',
      coupon_name: 'E2E 优惠券',
      member_discount_snapshot: { member_level_name: 'E2E 黄金会员' },
      paid_amount: '93.00',
    },
    order: { id: 1, order_no: 'E2E202610230002' },
    actions: { can_refund_to_balance: false, can_cancel: false },
    timestamps: { created_at: '2026-10-23 10:00:00', updated_at: '2026-10-23 10:00:00' },
  },
  payments: [] as Record<string, unknown>[],
  items: [] as Record<string, unknown>[],
  logs: [] as Record<string, unknown>[],
};

test('订单列表：唯一金额列为「实付金额」且取 paid_amount', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await page.route('**/v2/admin/orders*', (route) => route.fulfill(jsonResponse(envelope(ORDERS_LIST_RESPONSE))));

  await page.goto('/admin/finance/orders', { waitUntil: 'domcontentloaded' });
  await expect(page.getByText('E2E202610230001')).toBeVisible();

  // 列头：实付金额存在；旧「金额」与优惠相关列头都必须消失
  await expect(page.getByRole('columnheader', { name: '实付金额' })).toBeVisible();
  await expect(page.getByRole('columnheader', { name: '金额', exact: true })).toHaveCount(0);
  await expect(page.getByRole('columnheader', { name: '优惠', exact: true })).toHaveCount(0);
  await expect(page.getByRole('columnheader', { name: '优惠券', exact: true })).toHaveCount(0);

  // 单元格：显示 paid_amount（¥12,345.60），且不显示 amount（¥9,999.99）
  await expect(page.getByRole('cell', { name: '¥12,345.60', exact: true })).toBeVisible();
  await expect(page.getByText('¥9,999.99')).toHaveCount(0);

  // 半角 ¥、千分位、固定两位小数、等宽数字（tabular-nums 由 .t-num-strong 提供）
  const moneyCell = page.getByRole('cell', { name: '¥12,345.60', exact: true });
  const moneyStyle = await moneyCell.locator('.t-num-strong').evaluate((el) => {
    const computed = getComputedStyle(el);
    return { fontVariant: computed.fontVariantNumeric, fontWeight: computed.fontWeight };
  });
  expect(moneyStyle.fontVariant).toContain('tabular-nums');
  expect(moneyStyle.fontWeight).toBe('600');

  expect(errors).toEqual([]);
});

test('账单列表：金额与已付合并为唯一「实付金额」列且不展开优惠', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await page.route('**/v2/admin/invoices*', (route) => route.fulfill(jsonResponse(envelope(INVOICES_LIST_RESPONSE))));

  await page.goto('/admin/finance/invoices', { waitUntil: 'domcontentloaded' });
  await expect(page.getByText('E2E202610230001')).toBeVisible();

  await expect(page.getByRole('columnheader', { name: '实付金额' })).toBeVisible();
  await expect(page.getByRole('columnheader', { name: '金额', exact: true })).toHaveCount(0);
  await expect(page.getByRole('columnheader', { name: '已付', exact: true })).toHaveCount(0);
  await expect(page.getByRole('columnheader', { name: '优惠', exact: true })).toHaveCount(0);
  await expect(page.getByRole('columnheader', { name: '优惠券', exact: true })).toHaveCount(0);

  // 列表只认 paid_amount（¥88.00），不得把 amount（¥99.00）当金额展示
  await expect(page.getByRole('cell', { name: '¥88.00', exact: true })).toBeVisible();
  await expect(page.getByText('¥99.00')).toHaveCount(0);

  // 唯一加粗等宽数值列（.t-num-strong = 600 + tabular-nums）
  const moneyCell = page.getByRole('cell', { name: '¥88.00', exact: true });
  const style = await moneyCell.locator('.t-num-strong').evaluate((el) => {
    const computed = getComputedStyle(el);
    return { fontWeight: computed.fontWeight, fontVariant: computed.fontVariantNumeric };
  });
  expect(style.fontWeight).toBe('600');
  expect(style.fontVariant).toContain('tabular-nums');

  expect(errors).toEqual([]);
});

test('订单详情：唯一金额位是实付金额，优惠拆分只在页面最下方费用明细区块', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await page.route('**/v2/admin/orders/1*', (route) => route.fulfill(jsonResponse(envelope(ORDER_DETAIL_RESPONSE))));

  await page.goto('/admin/finance/orders/1', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('.identity-title').first()).toHaveText('E2E202610230002');

  // 唯一金额位：概览指标卡里的「实付金额」，取值 paid_amount。
  // 规范 §1.1「管理端订单详情：概览指标卡中与金额有关的指标只保留「实付金额」一项；
  // 基本信息内不设金额区块」，所以定位指标卡，不在页签字段区找金额块。
  const paidCard = page.locator('.summary-metric-card', { hasText: '实付金额' });
  await expect(paidCard).toBeVisible();
  await expect(paidCard.locator('.metric-label')).toHaveText('实付金额');
  await expect(paidCard.locator('.metric-value')).toHaveText('¥93.00');

  // 概览指标卡只剩一项金额指标「实付金额」（另有创建时间等非金额项）
  const metricLabels = await page.locator('.record-detail-summary__metrics .metric-label').allInnerTexts();
  expect(metricLabels.filter((label) => label.includes('金额'))).toEqual(['实付金额']);

  // 费用明细必须排在详情主体之后、并落在详情主体的下方（页面最下方一个区块）。
  // 不用「父节点最后一个子元素」判定：页面根容器在区块之后还挂着账单抽屉与退款弹窗的挂载点，
  // 用文档顺序 + 实际渲染位置判定才是真实版式。
  const position = await page.evaluate(() => {
    const detail = document.querySelector('.record-detail-page');
    const fee = document.querySelector('.order-fee-detail');
    if (!detail || !fee) return { after: false, below: false };
    const after = Boolean(detail.compareDocumentPosition(fee) & Node.DOCUMENT_POSITION_FOLLOWING);
    const detailRect = detail.getBoundingClientRect();
    const feeRect = fee.getBoundingClientRect();
    return { after, below: feeRect.top >= detailRect.bottom - 1 };
  });
  expect(position.after).toBe(true);
  expect(position.below).toBe(true);

  // 行序固定：原价（计算式）→ 优惠券 → 会员折扣
  const feeDetail = page.locator('.order-fee-detail');
  const rows = feeDetail.locator('.order-fee-detail__row');
  await expect(rows).toHaveCount(3);
  await expect(rows.locator('dt')).toHaveText(['原价', 'E2E 优惠券', 'E2E 黄金会员']);
  await expect(rows.locator('dd')).toHaveText(['¥9.00/月 × 12 个月 = ¥108.00', '-¥10.00', '-¥5.00']);

  // 减号必须排在 ¥ 之前
  await expect(feeDetail).not.toContainText('¥-');

  // 优惠行左缩进 16px，体现「原价 → 递减项」的层级
  const indent = await rows.nth(1).evaluate((el) => getComputedStyle(el).paddingLeft);
  expect(indent).toBe('16px');

  // 等式可直接核对：108.00 − 10.00 − 5.00 === 93.00
  const catalogText = (await rows.nth(0).locator('dd').textContent()) ?? '';
  const toAmount = (text: string) => Number(text.replaceAll(/[¥,\s]/g, ''));
  const catalog = toAmount(catalogText.slice(catalogText.lastIndexOf('=') + 1));
  const coupon = Math.abs(toAmount((await rows.nth(1).locator('dd').textContent()) ?? ''));
  const member = Math.abs(toAmount((await rows.nth(2).locator('dd').textContent()) ?? ''));
  const paid = toAmount((await paidCard.locator('.metric-value').textContent()) ?? '');
  expect(catalog - coupon - member).toBeCloseTo(paid, 2);

  // 订单主体（费用明细之外）不得出现优惠拆分措辞。
  // 范围限定 #main-content：左侧营销菜单本身含「优惠券列表」，取 body 会误伤。
  const mainText = ((await page.locator('#main-content').textContent()) || '').replaceAll(/\s+/g, '');
  expect(mainText).toContain('费用明细');

  expect(errors).toEqual([]);
});

test('订单详情：无优惠时费用明细只剩原价一行，不渲染优惠行与占位', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await page.route('**/v2/admin/orders/1*', (route) => route.fulfill(jsonResponse(envelope(ORDER_DETAIL_PLAIN))));

  await page.goto('/admin/finance/orders/1', { waitUntil: 'domcontentloaded' });
  const paidValue = page.locator('.summary-metric-card', { hasText: '实付金额' }).locator('.metric-value');
  await expect(paidValue).toHaveText('¥93.00');

  const feeDetail = page.locator('.order-fee-detail');
  const rows = feeDetail.locator('.order-fee-detail__row');
  await expect(rows).toHaveCount(1);
  await expect(rows.locator('dt')).toHaveText(['原价']);
  await expect(rows.locator('dd')).toHaveText(['¥7.75/月 × 12 个月 = ¥93.00']);

  await expect(feeDetail.locator('.order-fee-detail__row--discount')).toHaveCount(0);
  const feeText = (await feeDetail.textContent()) ?? '';
  for (const word of ['优惠券', '会员折扣', '优惠合计']) {
    expect(feeText, `无优惠订单的费用明细不应出现「${word}」`).not.toContain(word);
  }

  expect(errors).toEqual([]);
});

test('账单详情：不出现费用明细与任何金额拆分', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await page.route('**/v2/admin/invoices/501**', (route) => {
    return route.fulfill(jsonResponse(envelope(INVOICE_DETAIL_DISCOUNTED)));
  });

  await page.goto('/admin/finance/invoices/501', { waitUntil: 'domcontentloaded' });

  // 金额只出现在概览与支付记录，且为 paid_amount
  await expect(page.getByText('¥93.00').first()).toBeVisible();

  // 账单侧不得再出现费用明细区块与任何减项金额行
  await expect(page.locator('.invoice-fee-detail')).toHaveCount(0);
  await expect(page.locator('.order-fee-detail')).toHaveCount(0);
  await expect(page.getByText('费用明细', { exact: true })).toHaveCount(0);

  const detailText = (await page.locator('#main-content').textContent()) || '';
  for (const word of ['原价', '会员折扣', '优惠合计', '折扣率', '满减', '-¥']) {
    expect(detailText, `账单详情不应出现「${word}」`).not.toContain(word);
  }

  expect(errors).toEqual([]);
});

function jsonResponse(body: unknown, status = 200) {
  return { status, contentType: 'application/json', body: JSON.stringify(body) };
}
