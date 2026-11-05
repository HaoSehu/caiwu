import type { Page } from '@playwright/test';

import { collectConsoleErrors, envelope, expect, mockAuthenticatedSession, test } from './fixtures';

/**
 * 管理端「账户策略」设置页：三个强制开关的渲染、默认关闭与保存载荷。
 *
 * 只验证前端：路由可达、三个 switch 默认关闭、保存时按 1/0 提交到 account_policy 分组。
 * 后端拦截逻辑由 backend 的 AccountPolicyEnforcementTest 覆盖，不在 e2e 伪造。
 */

const JSON_HEADERS = { status: 200, contentType: 'application/json' };

async function mockEmptySettings(page: Page) {
  const body = JSON.stringify(envelope({ list: [] as Record<string, unknown>[] }));
  await page.route('**/v2/admin/settings*', (route) => route.fulfill({ ...JSON_HEADERS, body }));
}

/** 按开关文案取对应字段卡片里的 t-switch。 */
function policySwitch(page: Page, label: string) {
  return page.locator('article.field-card', { hasText: label }).locator('.t-switch').first();
}

test('账户策略：三个强制开关默认关闭', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await mockEmptySettings(page);

  await page.goto('/admin/account-policy', { waitUntil: 'domcontentloaded' });

  await expect(page.locator('#main-content')).toBeVisible();

  // 本页只有一个分组，不渲染分组切换器，直接断言字段卡片。

  for (const label of ['是否强制开启实名', '是否强制绑定手机号', '是否强制绑定邮箱']) {
    const toggle = policySwitch(page, label);
    await expect(toggle).toBeVisible();
    await expect(toggle).not.toHaveClass(/t-is-checked/);
  }

  expect(errors).toEqual([]);
});

test('账户策略：开启后保存以 1/0 提交到 account_policy 分组', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await mockEmptySettings(page);

  const savedBodies: Array<{ group: string; settings: Record<string, unknown> }> = [];
  await page.route('**/v2/admin/settings', async (route) => {
    if (route.request().method() === 'POST') {
      savedBodies.push(route.request().postDataJSON() as { group: string; settings: Record<string, unknown> });
      await route.fulfill({
        ...JSON_HEADERS,
        body: JSON.stringify(envelope({ id: 'settings', status: 'completed', message: '配置已更新' })),
      });
      return;
    }
    await route.fulfill({ ...JSON_HEADERS, body: JSON.stringify(envelope({ list: [] })) });
  });

  await page.goto('/admin/account-policy', { waitUntil: 'domcontentloaded' });
  await policySwitch(page, '是否强制开启实名').click();
  await page.getByRole('button', { name: '保存' }).click();

  await expect.poll(() => savedBodies.length).toBeGreaterThan(0);
  const accountPolicySave = savedBodies.find((item) => item.group === 'account_policy');
  expect(accountPolicySave).toBeTruthy();
  expect(accountPolicySave?.settings).toEqual({
    require_verification: 1,
    require_phone: 0,
    require_email: 0,
  });

  expect(errors).toEqual([]);
});
