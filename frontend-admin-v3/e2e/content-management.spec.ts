import type { Page } from '@playwright/test';

import { collectConsoleErrors, envelope, expect, mockAuthenticatedSession, test } from './fixtures';

/**
 * 内容管理（系统公告）列表回归（审查条目 L-27）。
 *
 * 页面挂载即并行拉分类（筛选选项源）与文章列表；两者都必须 mock，否则未匹配请求
 * 由 preview 返回 404 HTML，被前端记成 console error。
 * 只验证：路由可达、分类条与公告行渲染、状态标签映射、无 console error。
 */
const CATEGORIES_RESPONSE = {
  list: [{ id: 3, name: '产品公告', content_type: 'notice', articles_count: 1 }],
};

const ARTICLES_RESPONSE = {
  list: [
    {
      id: 12,
      title: '国庆期间服务安排',
      summary: '节假日照常受理工单',
      category_id: 3,
      category_name: '产品公告',
      status: 1,
      is_pinned: 1,
      is_recommended: 0,
      publish_at: '2026-09-30 09:00:00',
      view_count: 128,
      operator: '运营管理员',
      updated_at: '2026-09-30 09:00:00',
    },
  ],
  total: 1,
  page: 1,
  page_size: 20,
};

const JSON_HEADERS = { status: 200, contentType: 'application/json' };

/** 内容分类请求路径 */
const CATEGORIES_PATH = '**/v2/admin/content/categories*';
/** 内容文章列表请求路径 */
const ARTICLES_PATH = '**/v2/admin/content/articles*';

async function mockContentPage(page: Page) {
  const categoriesBody = JSON.stringify(envelope(CATEGORIES_RESPONSE));
  const articlesBody = JSON.stringify(envelope(ARTICLES_RESPONSE));
  await page.route(CATEGORIES_PATH, (route) => route.fulfill({ ...JSON_HEADERS, body: categoriesBody }));
  await page.route(ARTICLES_PATH, (route) => route.fulfill({ ...JSON_HEADERS, body: articlesBody }));
}

test('内容管理：公告列表渲染分类、标题与状态', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await mockContentPage(page);

  await page.goto('/admin/content/notices', { waitUntil: 'domcontentloaded' });

  await expect(page.locator('#main-content')).toBeVisible();
  await expect(page.getByText('系统公告列表')).toBeVisible();
  await expect(page.getByText('国庆期间服务安排')).toBeVisible();
  await expect(page.getByText('节假日照常受理工单')).toBeVisible();
  await expect(page.getByText('产品公告').first()).toBeVisible();
  await expect(page.getByText('已发布')).toBeVisible();
  await expect(page.getByText('置顶')).toBeVisible();

  expect(errors).toEqual([]);
});
