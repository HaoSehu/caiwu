import type { Page } from '@playwright/test';

import { collectConsoleErrors, envelope, expect, mockAuthenticatedSession, test } from './fixtures';

/**
 * 会员等级列表回归（审查条目 L-27）。
 *
 * 只验证：路由可达、档位名称与状态渲染、无 console error。
 */
const LEVELS_RESPONSE = {
  list: [
    {
      id: 1,
      name: '金牌会员',
      status: 1,
      remark: '年均消费满 10 万',
      updated_at: '2026-04-01 10:00:00',
    },
  ],
};

const JSON_HEADERS = { status: 200, contentType: 'application/json' };

async function mockMemberLevelPage(page: Page) {
  const body = JSON.stringify(envelope(LEVELS_RESPONSE));
  await page.route('**/v2/admin/member-levels*', (route) => route.fulfill({ ...JSON_HEADERS, body }));
}

test('会员等级：档位表渲染名称、状态与备注', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await mockMemberLevelPage(page);

  await page.goto('/admin/member-levels', { waitUntil: 'domcontentloaded' });

  await expect(page.locator('#main-content')).toBeVisible();
  await expect(page.getByText('等级列表')).toBeVisible();
  await expect(page.getByText('金牌会员')).toBeVisible();
  await expect(page.getByText('年均消费满 10 万')).toBeVisible();
  // 页面另有筛选/状态下拉也含「启用」文案，这里限定在表格单元格内断言
  await expect(page.locator('.table-scroll td').getByText('启用', { exact: true })).toBeVisible();

  expect(errors).toEqual([]);
});
