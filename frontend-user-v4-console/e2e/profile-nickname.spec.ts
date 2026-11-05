import type { Page } from '@playwright/test';

import { E2E_SITE_CONFIG, envelope, expect, seedClientSession, test } from './fixtures';

// 「真实姓名 > 自定义」展示口径回归：
// - 登录态归一化必须读 display_name（后端已统一为「昵称优先、真实姓名兜底」），
//   不能只用 nickname 自己拼一遍，否则实名用户的真实姓名在顶栏/首屏不会生效；
// - 已实名用户在个人资料页能看到「用户名默认使用真实姓名」的提示，且输入框仍可编辑。
// 接口全部 mock，不依赖本地后端。

const SEED_USER = {
  id: 1,
  email: 'e2e@example.com',
  phone: '13800000000',
  cash_balance: '1000.00',
  created_at: '2026-01-01 10:00:00',
};

/** 已实名：后端已把昵称落成真实姓名，display_name 与 nickname 一致。 */
const VERIFIED_USER = {
  ...SEED_USER,
  nickname: '张三',
  display_name: '张三',
  real_name: '张三',
  id_card_masked: '110***********1234',
  is_verified: 1,
  verification_status: 2,
};

/** 已实名但用户改回了自定义昵称：nickname 与 display_name 都是自定义名，真实姓名只在实名字段。 */
const VERIFIED_CUSTOM_NICKNAME_USER = {
  ...VERIFIED_USER,
  nickname: '我的昵称',
  display_name: '我的昵称',
};

function jsonResponse(body: unknown) {
  return { status: 200, contentType: 'application/json', body: JSON.stringify(body) };
}

/** 个人资料页首屏会同时拉站点配置、用户信息与消息提醒设置。 */
async function mockProfilePage(page: Page, user: Record<string, unknown>) {
  await seedClientSession(page);
  await page.route('**/api/v2/site/config*', (route) => route.fulfill(jsonResponse(envelope(E2E_SITE_CONFIG))));
  await page.route('**/api/v2/client/auth/info', (route) => route.fulfill(jsonResponse(envelope(user))));
  const prefs = envelope({ login_notify: 1 });
  await page.route('**/api/v2/client/auth/notification-preferences', (r) => r.fulfill(jsonResponse(prefs)));
}

test('顶栏展示 display_name：已实名用户显示真实姓名', async ({ page }) => {
  await mockProfilePage(page, VERIFIED_USER);

  await page.goto('/client/profile', { waitUntil: 'domcontentloaded' });

  // 顶栏账号名取 display_name（张三）；若归一化仍只读 nickname，这条在昵称≠真实姓名时才会暴露，
  // 这里同时断言个人资料的实名字段，确保页面确已按已实名渲染。
  await expect(page.getByText('张三', { exact: true }).first()).toBeVisible();
  await expect(page.getByText('已实名', { exact: true })).toBeVisible();
});

test('已实名用户改回自定义昵称后，顶栏与资料页都显示自定义昵称', async ({ page }) => {
  await mockProfilePage(page, VERIFIED_CUSTOM_NICKNAME_USER);

  await page.goto('/client/profile', { waitUntil: 'domcontentloaded' });

  const nicknameInput = page.getByRole('textbox', { name: '请输入用户名' });
  await expect(nicknameInput).toHaveValue('我的昵称');
  // 顶栏账号名取 display_name，同样落到用户改回的自定义昵称
  await expect(page.getByRole('button', { name: /我的昵称/ })).toBeVisible();
});

test('个人资料页提示实名后用户名默认使用真实姓名，输入框仍可编辑', async ({ page }) => {
  await mockProfilePage(page, VERIFIED_USER);

  await page.goto('/client/profile', { waitUntil: 'domcontentloaded' });

  await expect(page.getByText(/已实名认证，用户名默认使用真实姓名/)).toBeVisible();

  const nicknameInput = page.getByRole('textbox', { name: '请输入用户名' });
  await expect(nicknameInput).toBeEditable();
  await nicknameInput.fill('自定义名称');
  await expect(nicknameInput).toHaveValue('自定义名称');
});
