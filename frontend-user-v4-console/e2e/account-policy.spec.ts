import type { Page } from '@playwright/test';

import { E2E_SITE_CONFIG, envelope, expect, seedClientSession, test } from './fixtures';

/**
 * 账户策略强制引导回归：
 * - 开关开启且用户未完成时，控制台弹出不可关闭的引导弹窗（Esc / 遮罩都关不掉）；
 * - 不阻断只读页面：URL 不变、页面骨架在弹窗下方照常渲染；
 * - 「去完成」跳到个人资料页并自动打开对应绑定弹窗；
 * - 用户已满足或开关关闭时不弹。
 * 接口全部 mock，不依赖本地后端。
 */

const BASE_USER = {
  id: 1,
  nickname: '策略测试用户',
  display_name: '策略测试用户',
  email: 'policy@example.com',
  phone: '13800000000',
  cash_balance: '1000.00',
  created_at: '2026-01-01 10:00:00',
  is_verified: 1,
  verification_status: 2,
  real_name: '张三',
};

const POLICY_ON = {
  require_verification: true,
  require_phone: true,
  require_email: false,
  missing_items: ['phone'],
};

const POLICY_OFF = {
  require_verification: false,
  require_phone: false,
  require_email: false,
  missing_items: [] as string[],
};

function jsonResponse(body: unknown) {
  return { status: 200, contentType: 'application/json', body: JSON.stringify(body) };
}

async function mockConsole(page: Page, user: Record<string, unknown>, policy: Record<string, unknown>) {
  await seedClientSession(page);
  await page.route('**/api/v2/site/config*', (route) => route.fulfill(jsonResponse(envelope(E2E_SITE_CONFIG))));
  await page.route('**/api/v2/client/auth/info', (route) => {
    return route.fulfill(jsonResponse(envelope({ ...user, account_policy: policy })));
  });
  // 首页与账单页的只读请求，统一返回空列表，避免未 mock 的 404 被记成 console error
  await page.route('**/api/v2/client/invoices**', (route) => {
    return route.fulfill(jsonResponse(envelope({ list: [], total: 0, page: 1, page_size: 15 })));
  });
  await page.route('**/api/v2/client/invoices/summary**', (route) => route.fulfill(jsonResponse(envelope({}))));
}

test('开关开启且未绑手机：控制台弹出不可关闭弹窗', async ({ page }) => {
  await mockConsole(page, { ...BASE_USER, phone: '' }, POLICY_ON);

  await page.goto('/client/dashboard', { waitUntil: 'domcontentloaded' });

  const dialog = page.locator('.account-policy-dialog .t-dialog');
  await expect(dialog.getByText('绑定安全手机')).toBeVisible();
  await expect(dialog.getByRole('button', { name: '去完成' })).toBeVisible();

  // 无关闭按钮
  await expect(dialog.locator('.t-dialog__close')).toHaveCount(0);

  // Esc 与点击遮罩都关不掉
  await page.keyboard.press('Escape');
  await expect(dialog.getByText('绑定安全手机')).toBeVisible();
  await page.locator('.t-dialog__mask').click({ position: { x: 5, y: 5 }, force: true });
  await expect(dialog.getByText('绑定安全手机')).toBeVisible();

  // 只读页面不被重定向：URL 仍是首页
  expect(new URL(page.url()).pathname).toBe('/client/dashboard');
});

test('点「去完成」跳到个人资料页并自动打开手机绑定弹窗', async ({ page }) => {
  await mockConsole(page, { ...BASE_USER, phone: '' }, POLICY_ON);
  await page.route('**/api/v2/client/auth/notification-preferences', (route) => {
    return route.fulfill(jsonResponse(envelope({ login_notify: 1 })));
  });

  await page.goto('/client/dashboard', { waitUntil: 'domcontentloaded' });
  await page.locator('.account-policy-dialog .t-dialog').getByRole('button', { name: '去完成' }).click();

  await expect(page).toHaveURL(/\/client\/profile/);
  // 未绑定手机时弹窗标题是「绑定手机」，已绑定时才是「更换绑定手机」
  await expect(page.getByText('绑定手机', { exact: true })).toBeVisible();
});

test('未绑手机用户填写新号码后校验通过并提交', async ({ page }) => {
  await mockConsole(page, { ...BASE_USER, phone: '' }, POLICY_OFF);
  await page.route('**/api/v2/client/auth/notification-preferences', (route) => {
    return route.fulfill(jsonResponse(envelope({ login_notify: 1 })));
  });

  let submitted: Record<string, unknown> | null = null;
  await page.route('**/api/v2/client/auth/phone', async (route) => {
    submitted = route.request().postDataJSON() as Record<string, unknown>;
    return route.fulfill(jsonResponse(envelope({ phone: submitted.phone })));
  });

  // 回归点：t-form 必须绑定 :data，否则校验读不到输入值，填了号码仍提示「请输入新手机号」
  await page.goto('/client/profile?tab=security&bind=phone', { waitUntil: 'domcontentloaded' });
  const phoneDialog = page.locator('.t-dialog').filter({ hasText: '新手机号' });
  await expect(page.getByText('绑定手机', { exact: true })).toBeVisible();

  await phoneDialog.getByPlaceholder('请输入新手机号').fill('13171721367');
  await phoneDialog.getByPlaceholder('请输入 6 位验证码').fill('123456');

  await expect(phoneDialog.getByText('请输入新手机号')).toHaveCount(0);
  await phoneDialog.getByRole('button', { name: '确定' }).click();

  await expect.poll(() => submitted?.phone).toBe('13171721367');
  await expect.poll(() => submitted?.code).toBe('123456');
});

test('未绑邮箱用户填写新邮箱后校验通过并提交', async ({ page }) => {
  await mockConsole(page, { ...BASE_USER, email: '' }, POLICY_OFF);
  await page.route('**/api/v2/client/auth/notification-preferences', (route) => {
    return route.fulfill(jsonResponse(envelope({ login_notify: 0 })));
  });

  let submitted: Record<string, unknown> | null = null;
  await page.route('**/api/v2/client/auth/email', async (route) => {
    submitted = route.request().postDataJSON() as Record<string, unknown>;
    return route.fulfill(jsonResponse(envelope({ email: submitted.email })));
  });

  await page.goto('/client/profile?tab=security&bind=email', { waitUntil: 'domcontentloaded' });
  const emailDialog = page.locator('.t-dialog').filter({ hasText: '新邮箱' });
  await expect(page.getByText('绑定邮箱', { exact: true })).toBeVisible();

  await emailDialog.getByPlaceholder('请输入新邮箱').fill('user@example.com');
  await emailDialog.getByPlaceholder('请输入 6 位验证码').fill('654321');

  await expect(emailDialog.getByText('请输入新邮箱')).toHaveCount(0);
  await emailDialog.getByRole('button', { name: '确定' }).click();

  await expect.poll(() => submitted?.email).toBe('user@example.com');
  await expect.poll(() => submitted?.code).toBe('654321');
});

test('用户已满足全部强制项时不弹窗', async ({ page }) => {
  await mockConsole(page, BASE_USER, {
    require_verification: true,
    require_phone: true,
    require_email: true,
    missing_items: [],
  });

  await page.goto('/client/dashboard', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('.account-policy-dialog .t-dialog')).toBeHidden();
});

test('开关全关时不弹窗', async ({ page }) => {
  await mockConsole(
    page,
    { ...BASE_USER, phone: '', email: '' },
    {
      require_verification: false,
      require_phone: false,
      require_email: false,
      missing_items: [],
    },
  );

  await page.goto('/client/dashboard', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('.account-policy-dialog .t-dialog')).toBeHidden();
});

test('未实名用户被要求完成实名，且实名落地页不弹窗', async ({ page }) => {
  await mockConsole(
    page,
    { ...BASE_USER, is_verified: 0, verification_status: 0 },
    { require_verification: true, require_phone: false, require_email: false, missing_items: ['verification'] },
  );
  await page.route('**/api/v2/client/verification/**', (route) => route.fulfill(jsonResponse(envelope({}))));

  await page.goto('/client/dashboard', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('.account-policy-dialog .t-dialog').getByText('完成实名认证')).toBeVisible();

  // 去落地页后弹窗必须让位，否则遮罩会挡住认证表单
  await page.goto('/client/verification', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('.account-policy-dialog .t-dialog')).toBeHidden();
});
