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

  test('微信支付：已是 weixin:// scheme 时原样拉起', async ({ page }) => {
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

    // 已是微信 scheme，原样拉起；同时保留复制入口。
    await expect(page.locator('.mobile-pay-helper')).toHaveAttribute('data-pay-target', /^weixin:\/\/wxpay\/bizpayurl/);
    await expect(page.getByRole('button', { name: '在微信中打开支付' })).toBeVisible();
    await expect(page.getByRole('button', { name: '复制支付链接' })).toBeVisible();
    // 可直接拉起的 scheme 不应套用「手动搬进微信」的引导，也不该把 scheme 明文展示给用户。
    await expect(page.locator('.mobile-pay-helper')).not.toContainText('微信支付需要在微信中完成');
    await expect(page.locator('.mobile-pay-url')).toHaveCount(0);
  });

  // 回归：易支付微信通道下发的是 https 公众号收银台（pay/jspay/…），该页面在任何浏览器
  // 都 302 到微信 OAuth，微信外打开会被微信拒绝（提示 invalid_source）；H5 支付未开通，
  // 微信外没有任何官方唤起方式。因此不拼 businessWebview scheme，改为展示链接 + 三步引导。
  test('微信支付：微信外展示链接并引导复制到微信打开', async ({ page }) => {
    const payUrl = 'https://bbs.example.com/pay/jspay/E2E-WXPAY-1/';
    await mockMobileRecharge(
      page,
      {
        key: 'yi_pay',
        option_key: 'yi_pay:wxpay',
        plugin_name: '易支付',
        channel_name: '微信支付',
        payment_type: 'wxpay',
      },
      payUrl,
    );

    await page.getByRole('button', { name: '立即充值' }).click();
    await page.getByRole('button', { name: '确定' }).click();

    const helper = page.locator('.mobile-pay-helper');
    await expect(helper).toBeVisible();
    // 没有发起任何跳转目标，页面必须给出可操作的原因，而不是静默失败。
    await expect(helper).toHaveAttribute('data-pay-target', '');
    await expect(helper).toContainText('微信支付需要在微信中完成');
    // 三步引导：复制 → 粘到文件传输助手发送 → 点开付款
    await expect(helper.locator('.mobile-pay-helper__steps li')).toHaveCount(3);
    await expect(helper).toContainText('文件传输助手');
    // 链接必须明文可见且可手动选中（自动复制失败时的兜底）
    await expect(helper.locator('.mobile-pay-url')).toHaveText(payUrl);
    await expect(page.getByRole('button', { name: '复制支付链接' })).toBeVisible();
    // 不做无法兑现的「唤起微信」承诺
    await expect(page.getByRole('button', { name: '在微信中打开支付' })).toHaveCount(0);
  });

  test('微信支付：点复制后提示需到微信粘贴打开', async ({ page, context }) => {
    await context.grantPermissions(['clipboard-read', 'clipboard-write']);
    const payUrl = 'https://bbs.example.com/pay/jspay/E2E-WXPAY-2/';
    await mockMobileRecharge(
      page,
      {
        key: 'yi_pay',
        option_key: 'yi_pay:wxpay',
        plugin_name: '易支付',
        channel_name: '微信支付',
        payment_type: 'wxpay',
      },
      payUrl,
    );

    await page.getByRole('button', { name: '立即充值' }).click();
    await page.getByRole('button', { name: '确定' }).click();
    await page.getByRole('button', { name: '复制支付链接' }).click();

    // 复制成功后的提示必须说清下一步，只回「已复制」等于没说。
    await expect(page.getByText('已复制，去微信「文件传输助手」粘贴发送后点开')).toBeVisible();
    const clipboard = await page.evaluate(() => navigator.clipboard.readText());
    expect(clipboard).toBe(payUrl);
  });

  // 微信内置浏览器是唯一能整页跳转完成的场景：跳走再回来必须能接上原来的支付单，
  // 否则用户看到「还没生成二维码」的空态，而钱可能已经到账（回归 2026-11 审查发现）。
  test('微信支付：微信内跳转收银台后返回可恢复支付单并继续轮询', async ({ page }) => {
    await page.addInitScript(() => {
      Object.defineProperty(navigator, 'userAgent', {
        configurable: true,
        get: () =>
          'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Mobile/15E148 MicroMessenger/8.0.49',
      });
    });

    await mockAuthenticatedSession(page);
    const gateway = {
      key: 'yi_pay',
      option_key: 'yi_pay:wxpay',
      plugin_name: '易支付',
      channel_name: '微信支付',
      payment_type: 'wxpay',
    };
    await page.route('**/api/v2/client/recharge/gateways', (route) => route.fulfill(jsonOk({ list: [gateway] })));
    const order = {
      payment_no: PAYMENT_NO,
      qr_code: 'https://bbs.example.com/pay/jspay/E2E-WX-INNER/',
      amount: 20,
      gateway: 'yi_pay',
      payment_type: 'wxpay',
      poll_token: POLL_TOKEN,
    };
    await page.route('**/api/v2/client/recharge', (route) => route.fulfill(jsonOk(order)));
    // 收银台域名给一个静态页：真机上这里是网关页面，测试里只需让导航能完成。
    const cashierPage = { status: 200, contentType: 'text/html', body: '<!doctype html><title>收银台</title>' };
    await page.route('https://bbs.example.com/**', (route) => route.fulfill(cashierPage));
    // 从收银台返回后轮询能查到「已支付」，证明恢复后直接进入成功态。
    const paidStatus = { paid: true, balance: '1020.00', message: '支付成功' };
    await page.route(`**/api/v2/client/recharge/${PAYMENT_NO}/status*`, (route) => route.fulfill(jsonOk(paidStatus)));

    await page.goto('/client/recharge', { waitUntil: 'domcontentloaded' });
    await page.getByRole('button', { name: '立即充值' }).click();
    await page.getByRole('button', { name: '确定' }).click();

    // 微信内不展示「复制到微信」引导，而是整页跳转到收银台——页面会真的离开控制台。
    await expect(page.locator('.mobile-pay-helper')).toHaveCount(0);
    await page.waitForURL(/bbs\.example\.com\/pay\/jspay\/E2E-WX-INNER/, { timeout: 10_000 });

    // 模拟从收银台返回：重新进入控制台（内存里的支付单已丢失）。
    // sessionStorage 按源隔离，凭证是在控制台源下写的，回到同源即可读到。
    await page.goto('/client/recharge', { waitUntil: 'domcontentloaded' });

    // 恢复后必须能认出这张单并进入成功态，而不是回到「待生成二维码」空态。
    // 这一条同时验证了「跳转前凭证已落盘」——没落盘就恢复不出来。
    await expect(page.getByText(PAYMENT_NO)).toBeVisible({ timeout: 10_000 });
    // 移动端不渲染 .qrcode-success（那是桌面二维码面板），成功态体现在状态行。
    await expect(page.locator('.mobile-payment-meta')).toContainText('已支付', { timeout: 10_000 });
    // 支付完成后凭证要被清掉，避免下次进入又"恢复"一张已支付的单。
    const afterPaid = await page.evaluate(() => window.sessionStorage.getItem('client-recharge-poll'));
    expect(afterPaid).toBeNull();
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
