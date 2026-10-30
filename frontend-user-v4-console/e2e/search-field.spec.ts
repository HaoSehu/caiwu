import type { Page } from '@playwright/test';

import { envelope, expect, mockAuthenticatedSession, test } from './fixtures';

// O-02：关键词输入框右侧的放大镜必须是可点按钮，点一下就能发一次带 keyword 的列表请求。
// 在此之前 #suffixIcon 里只是裸 <SearchIcon />：没有点击、没有语义，用户只能猜「按 Enter」。

const EMPTY_PAGE = { list: [] as Record<string, unknown>[], total: 0, page: 1, page_size: 20 };

/** 记录每个 /api/v2/client/orders 请求的 keyword，供断言。 */
async function recordOrderQueries(page: Page) {
  const keywords: string[] = [];
  await page.route('**/api/v2/client/orders*', async (route) => {
    const url = new URL(route.request().url());
    keywords.push(url.searchParams.get('keyword') ?? '');
    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(envelope(EMPTY_PAGE)),
    });
  });
  return keywords;
}

test('订单列表：搜索按钮可点，点击后发出带 keyword 的请求', async ({ page }) => {
  await mockAuthenticatedSession(page);
  const keywords = await recordOrderQueries(page);

  await page.goto('/client/orders', { waitUntil: 'domcontentloaded' });

  const searchButton = page.getByRole('button', { name: '搜索' });
  await expect(searchButton).toBeVisible();

  // 让按钮真正浮现在可点位置后输入关键词
  const input = page.getByPlaceholder('搜索订单号');
  await input.fill('E2E-KW');

  const before = keywords.length;
  await searchButton.click();

  await expect.poll(() => keywords.slice(before)).toContain('E2E-KW');
});

test('订单列表：输入可清除时搜索按钮仍在，点击不清空关键词且发出请求', async ({ page }) => {
  await mockAuthenticatedSession(page);
  const keywords = await recordOrderQueries(page);

  await page.goto('/client/orders', { waitUntil: 'domcontentloaded' });

  const input = page.getByPlaceholder('搜索订单号');
  await input.fill('KEYS');

  // 悬停会让 clearable 的输入框浮出清除图标；搜索按钮不能因此消失
  await page.hover('input[placeholder="搜索订单号"]');
  await expect(page.getByRole('button', { name: '搜索' })).toBeVisible();

  const before = keywords.length;
  await page.getByRole('button', { name: '搜索' }).click();

  // 搜的是关键词本身，不能被 t-input 根 div 的 onRootClick 抢走焦点或触发清空
  await expect(input).toHaveValue('KEYS');
  await expect.poll(() => keywords.slice(before)).toContain('KEYS');
});
