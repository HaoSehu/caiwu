import type { Page } from '@playwright/test';

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
  await page.route('**/api/v2/client/recharge/gateways', (route) => {
    return route.fulfill(
      jsonOk({
        // 后端已拆分展示名：plugin_name 是插件名（只允许管理端展示），channel_name 才是渠道名（用户端唯一可见）。
        // name/label 为兼容别名，分别等于 plugin_name / channel_name——用户端不得依赖它们作为展示名。
        list: [
          {
            key: 'alipay',
            option_key: 'alipay:alipay',
            plugin_name: '易支付',
            channel_name: '支付宝',
            name: '易支付',
            label: '支付宝',
            payment_type: 'alipay',
          },
        ],
      }),
    );
  });
  await page.route('**/api/v2/client/recharge', (route) => {
    return route.fulfill(
      jsonOk({
        payment_no: PAYMENT_NO,
        qr_code: 'https://pay.e2e.example.com/qr/E2E-PAY-0001',
        amount: 50,
        gateway: 'alipay',
        poll_token: POLL_TOKEN,
      }),
    );
  });
  await page.route(`**/api/v2/client/recharge/${PAYMENT_NO}/status*`, (route) => {
    statusCalls += 1;
    // 第 1 次轮询待支付，之后返回已支付（成功后 clearPollingTimer 应停止轮询）
    const paid = statusCalls >= 2;
    return route.fulfill(
      jsonOk(
        paid ? { paid: true, cash_balance: '1050.00', message: '支付成功' } : { paid: false, message: '等待支付' },
      ),
    );
  });

  await page.goto('/client/recharge', { waitUntil: 'domcontentloaded' });

  // 支付方式加载完成（金额输入框带 ¥ 前缀，快捷金额直接点选）
  // 按钮文案必须由渠道名（channel_name）拼出，插件名不得参与。
  const gatewayButton = page.getByRole('button', { name: '生成支付宝二维码' });
  await expect(gatewayButton).toBeVisible();

  // 移动端 / 桌面端布局由 isMobileScreen 二选一渲染，这里对当前渲染出的支付方式卡片断言：
  // 主标题是渠道名（channel_name），副标题不再重复它。
  const payMethodCard = page.locator('.pay-method-card--mobile');
  if ((await payMethodCard.count()) > 0) {
    await expect(payMethodCard.first().locator('strong')).toHaveText('支付宝');
    await expect(payMethodCard.first().locator('small')).toHaveText('支付宝扫码支付');
  }

  // 用户端任何位置都不得出现插件名，也不得出现「易支付 - 支付宝」这类插件名与渠道名的拼接串。
  await expect(page.locator('body')).not.toContainText('易支付');

  await page.getByRole('button', { name: '¥50', exact: true }).click();
  await gatewayButton.click();
  // 确认弹窗：确认通过 支付宝 充值 ¥50
  await page.getByRole('button', { name: '确定' }).click();

  // 充值单创建成功：二维码区出现商家订单号
  await expect(page.getByText(PAYMENT_NO)).toBeVisible();

  // 二维码区只保留一行标题：轮询在自动进行，但不再向用户渲染副标题说明，
  // 二维码下方直接是订单号与应付金额（回归保护：副标题如被加回，此断言失败）。
  await expect(page.locator('.qrcode-title')).toHaveCount(1);
  await expect(page.locator('.qrcode-subtitle')).toHaveCount(0);
  await expect(page.getByText('正在自动确认支付状态，请勿重复支付。')).toHaveCount(0);

  // 轮询成功：二维码区覆盖为成功态（useRecharge：paid → rechargePaid）
  await expect(page.locator('.qrcode-success')).toBeVisible({ timeout: 15_000 });

  // 轮询已停止：记录成功时的请求数，跨过一个轮询周期（useRecharge.ts 的 pollingInterval = 2000ms）后
  // 数量不再增长。这里必须真的等满一个周期——「没有新请求」是负向事实，没有可轮询的 DOM 状态可断言，
  // 所以保留固定等待并把周期写在注释里（3200 = 2000 + 余量），改周期时同步改这里。
  const callsAtSuccess = statusCalls;
  expect(callsAtSuccess).toBeGreaterThanOrEqual(2);
  await page.waitForTimeout(3200);
  expect(statusCalls).toBe(callsAtSuccess);
});

// 手机端：下单后必须直接唤起支付宝 / 微信 App，而不是只给一个二维码或链接。
// 唤起统一走隐藏 iframe（src/domains/finance/paymentLaunch.ts），该 iframe 1.2 秒后会被移除，
// 直接断言它与移除竞态；因此断言页面上稳定的 data-pay-target（= 本次实际发起的拉起目标）。
test.describe('手机端充值直接拉起支付 App', () => {
  test.use({ viewport: { width: 390, height: 844 } });

  /** 移动端下单所需的公共 mock：支付方式 + 建单 + 状态轮询（一直待支付）。 */
  async function mockMobileRecharge(page: Page, gateway: Record<string, unknown>, qrCode: string) {
    await mockAuthenticatedSession(page);
    await page.route('**/api/v2/client/recharge/gateways', (route) => route.fulfill(jsonOk({ list: [gateway] })));
    await page.route('**/api/v2/client/recharge', (route) => {
      const payload = {
        payment_no: PAYMENT_NO,
        qr_code: qrCode,
        amount: 20,
        gateway: String(gateway.key || ''),
        poll_token: POLL_TOKEN,
      };
      return route.fulfill(jsonOk(payload));
    });
    await page.route(`**/api/v2/client/recharge/${PAYMENT_NO}/status*`, (route) => {
      return route.fulfill(jsonOk({ paid: false, message: '等待支付' }));
    });
    await page.goto('/client/recharge', { waitUntil: 'domcontentloaded' });
  }

  test('微信支付：下单后拉起 weixin:// 支付 scheme', async ({ page }) => {
    await mockMobileRecharge(
      page,
      {
        key: 'yi_pay',
        option_key: 'yi_pay:wxpay',
        plugin_name: '易支付',
        channel_name: '微信支付',
        payment_type: 'wxpay',
      },
      'weixin://wxpay/bizpayurl?pr=e2e-wxpay',
    );

    await page.getByRole('button', { name: '立即充值' }).click();
    await page.getByRole('button', { name: '确定' }).click();

    // 已是微信 scheme，原样透传；同时保留重试入口。
    await expect(page.locator('.mobile-pay-helper')).toHaveAttribute('data-pay-target', /^weixin:\/\/wxpay\/bizpayurl/);
    await expect(page.getByRole('button', { name: '打开微信 App' })).toBeVisible();
    await expect(page.locator('.mobile-pay-helper')).toContainText('已尝试打开微信 App');
  });

  test('支付宝当面付：qr.alipay.com 走扫一扫 scheme', async ({ page }) => {
    await mockMobileRecharge(
      page,
      {
        key: 'ali_pay',
        option_key: 'ali_pay:alipay',
        plugin_name: '支付宝',
        channel_name: '支付宝',
        payment_type: 'alipay',
      },
      'https://qr.alipay.com/e2e-alipay',
    );

    await page.getByRole('button', { name: '立即充值' }).click();
    await page.getByRole('button', { name: '确定' }).click();

    await expect(page.locator('.mobile-pay-helper')).toHaveAttribute(
      'data-pay-target',
      /^alipays:\/\/platformapi\/startapp\?saId=10000007/,
    );
    await expect(page.getByRole('button', { name: '打开支付宝 App' })).toBeVisible();
  });
});
