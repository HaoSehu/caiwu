import { envelope, expect, mockAuthenticatedSession, test } from './fixtures';

// 充值 → 支付轮询链路用例（mock 网关）：快捷金额选档 → 确认弹窗 → 生成二维码 →
// 自动轮询状态（前 1 次待支付、之后成功）→ 成功提示 + 轮询停止。接口全部 mock。

const PAYMENT_NO = 'E2E-PAY-0001';
const POLL_TOKEN = 'e2e-poll-token';

function jsonOk(data: unknown) {
  return { status: 200, contentType: 'application/json', body: JSON.stringify(envelope(data)) };
}

test('充值：生成二维码后自动轮询支付状态，成功后停止轮询并提示到账', async ({ page }) => {
  await mockAuthenticatedSession(page);

  let statusCalls = 0;
  await page.route('**/api/v2/client/recharge/gateways', (route) =>
    route.fulfill(jsonOk({
      list: [
        { key: 'alipay', option_key: 'alipay:alipay', name: '支付宝', label: '支付宝扫码', payment_type: 'alipay' },
      ],
    })));
  await page.route('**/api/v2/client/recharge', (route) =>
    route.fulfill(jsonOk({
      payment_no: PAYMENT_NO,
      qr_code: 'https://pay.e2e.example.com/qr/E2E-PAY-0001',
      amount: 50,
      gateway: 'alipay',
      poll_token: POLL_TOKEN,
    })));
  await page.route(`**/api/v2/client/recharge/${PAYMENT_NO}/status*`, (route) => {
    statusCalls += 1;
    // 第 1 次轮询待支付，之后返回已支付（成功后 clearPollingTimer 应停止轮询）
    const paid = statusCalls >= 2;
    return route.fulfill(jsonOk(
      paid
        ? { paid: true, cash_balance: '1050.00', message: '支付成功' }
        : { paid: false, message: '等待支付' },
    ));
  });

  await page.goto('/client/recharge', { waitUntil: 'domcontentloaded' });

  // 支付方式加载完成（金额输入框带 ¥ 前缀，快捷金额直接点选）
  const gatewayButton = page.getByRole('button', { name: '生成支付宝二维码' });
  await expect(gatewayButton).toBeVisible();

  await page.getByRole('button', { name: '¥50', exact: true }).click();
  await gatewayButton.click();
  // 确认弹窗：确认通过 支付宝 充值 ¥50
  await page.getByRole('button', { name: '确定' }).click();

  // 充值单创建成功：二维码区出现商家订单号
  await expect(page.getByText(PAYMENT_NO)).toBeVisible();

  // 轮询成功：二维码区覆盖为成功态（useRecharge：paid → rechargePaid）
  await expect(page.locator('.qrcode-success')).toBeVisible({ timeout: 15_000 });

  // 轮询已停止：记录成功时的请求数，等待超过一个轮询周期后数量不再增长
  const callsAtSuccess = statusCalls;
  expect(callsAtSuccess).toBeGreaterThanOrEqual(2);
  await page.waitForTimeout(3200);
  expect(statusCalls).toBe(callsAtSuccess);
});
