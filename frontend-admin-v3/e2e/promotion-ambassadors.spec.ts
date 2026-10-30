import type { Page } from '@playwright/test';

import { collectConsoleErrors, envelope, expect, mockAuthenticatedSession, test } from './fixtures';

/**
 * 推广大使档位回归（审查条目 L-27：管理端 57 页只有 10 个 e2e spec，营销与产品目录主流程无覆盖）。
 *
 * 只验证：路由可达、表头与档位数据渲染、返利百分比按 toFixed(2) 呈现、无 console error。
 * 不验证后端业务链路与数据库副作用（见 docs/参考资料/运维/测试指南.md 的 e2e 边界口径）。
 */
const AMBASSADORS_RESPONSE = {
  list: [
    {
      id: 1,
      name: '金牌大使',
      reward_rate: '10',
      renewal_reward_rate: '5',
      status: 1,
      remark: '年度合作',
      updated_at: '2026-04-01 10:00:00',
    },
  ],
};

const JSON_HEADERS = { status: 200, contentType: 'application/json' };

async function mockAmbassadorPage(page: Page) {
  const body = JSON.stringify(envelope(AMBASSADORS_RESPONSE));
  await page.route('**/v2/admin/promotion-ambassadors*', (route) => route.fulfill({ ...JSON_HEADERS, body }));
}

test('推广大使：档位表渲染名称与返利百分比', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await mockAmbassadorPage(page);

  await page.goto('/admin/promotion-ambassadors', { waitUntil: 'domcontentloaded' });

  await expect(page.locator('#main-content')).toBeVisible();
  await expect(page.getByText('推广大使档位')).toBeVisible();
  await expect(page.getByText('金牌大使')).toBeVisible();
  await expect(page.getByText('10.00%')).toBeVisible();
  await expect(page.getByText('5.00%')).toBeVisible();
  // 页面另有筛选/状态下拉也含「启用」文案，这里限定在表格单元格内断言
  await expect(page.locator('.table-scroll td').getByText('启用', { exact: true })).toBeVisible();

  expect(errors).toEqual([]);
});
