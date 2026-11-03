import type { Route } from '@playwright/test';

import { collectConsoleErrors, envelope, expect, mockAuthenticatedSession, test } from './fixtures';

/**
 * 列表加载竞态防护回归（2026-11-03 全项目审查）。
 *
 * 新客户日报页此前只写 `loading = true/false`，没有请求序号：
 * 用户连续点日期快捷键时，先发出的慢响应可能晚到，并覆盖后发的新数据。
 * 用例把第一次请求挂起、让第二次先返回，再放行第一次，断言旧数据被丢弃。
 * 修复前该断言会失败（界面会显示旧日期数据）。
 *
 * 营销组/推广大使两页的第二次加载只在写操作成功后触发（保存、圈选、删除），
 * e2e 不 mock 写链路，故只保留新客户日报这一条真回归用例；
 * 两页的序号防护由代码评审与 build 保证，不在此处强行构造。
 */

const JSON_HEADERS = { status: 200, contentType: 'application/json' };

test('新客户日报：慢的旧响应不覆盖新日期数据', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);

  let callIndex = 0;
  const releases: Array<() => void> = [];

  await page.route('**/v2/admin/finance/new-customer-daily-summary*', async (route: Route) => {
    const current = callIndex;
    callIndex += 1;
    // 第一次请求挂起，直到用例显式放行
    if (current === 0) {
      await new Promise<void>((resolve) => releases.push(resolve));
    }
    const body =
      current === 0
        ? { list: [{ date: '2026-10-01', new_customers: 111 }], summary: { new_customers: 111 } }
        : { list: [{ date: '2026-11-02', new_customers: 222 }], summary: { new_customers: 222 } };
    await route.fulfill({ ...JSON_HEADERS, body: JSON.stringify(envelope(body)) });
  });

  await page.goto('/admin/finance/new-customers', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('#main-content')).toBeVisible();

  // 同步点：必须确认首个请求已进入 route handler 并被挂起，否则下面的 forEach 会空转，
  // 用例在「第一次请求尚未发出」的时序下也会通过，等于没测到竞态。
  await expect.poll(() => releases.length, { message: '首个请求应已被挂起' }).toBeGreaterThan(0);

  // 第二次请求：点「上月」；其响应先返回并渲染
  await page.getByRole('button', { name: '上月' }).click();
  await expect(page.locator('.table-scroll')).toContainText('222');

  // 放行第一次（旧）请求：它必须被丢弃，不能把 111 写回界面
  releases.forEach((release) => release());
  await expect(page.locator('.table-scroll')).not.toContainText('111');
  await expect(page.locator('.table-scroll')).toContainText('222');

  expect(errors).toEqual([]);
});
