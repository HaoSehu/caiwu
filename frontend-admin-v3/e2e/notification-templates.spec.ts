import type { Page } from '@playwright/test';

import { collectConsoleErrors, envelope, expect, mockAuthenticatedSession, test } from './fixtures';

/**
 * 通知模板列表与邮件模板详情回归（审查条目 L-27）。
 *
 * 列表页与详情页共用 /v2/admin/notification-templates 与 /v2/admin/settings 两个接口，
 * 详情页还会额外按 group=basic 取站点名称与 logo；这里统一给出空的 settings 列表，
 * 让模板自身字段作为初值，避免断言依赖设置项。
 *
 * 只验证：路由可达、模板行与详情页关键区块渲染、无 console error。
 */
const TEMPLATE = {
  channel: 'email',
  code: 'order_paid',
  name: '订单支付成功',
  description: '用户支付成功后发送',
  audience: 'user',
  subject: '订单 {{order_no}} 支付成功',
  content: '<p>您好 {{display_name}}，订单 {{order_no}} 已完成支付。</p>',
  is_enabled: true,
  variables: ['display_name', 'order_no'],
};

const TEMPLATES_RESPONSE = { list: [TEMPLATE] };
const EMPTY_SETTINGS = { list: [] as Record<string, unknown>[] };
const JSON_HEADERS = { status: 200, contentType: 'application/json' };

/** 通知模板目录请求路径 */
const TEMPLATES_PATH = '**/v2/admin/notification-templates*';
/** 系统设置请求路径（列表页取 group=notification，详情页再取 group=basic） */
const SETTINGS_PATH = '**/v2/admin/settings*';

async function mockTemplateApis(page: Page) {
  const templatesBody = JSON.stringify(envelope(TEMPLATES_RESPONSE));
  const settingsBody = JSON.stringify(envelope(EMPTY_SETTINGS));
  await page.route(TEMPLATES_PATH, (route) => route.fulfill({ ...JSON_HEADERS, body: templatesBody }));
  await page.route(SETTINGS_PATH, (route) => route.fulfill({ ...JSON_HEADERS, body: settingsBody }));
}

test('通知模板列表：渲染模板名称、编号与正文类型', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await mockTemplateApis(page);

  await page.goto('/admin/notifications', { waitUntil: 'domcontentloaded' });

  await expect(page.locator('#main-content')).toBeVisible();
  await expect(page.getByRole('heading', { name: '邮件模板' })).toBeVisible();
  await expect(page.getByText('订单支付成功')).toBeVisible();
  await expect(page.getByText('order_paid')).toBeVisible();
  await expect(page.getByText('用户支付成功后发送')).toBeVisible();

  expect(errors).toEqual([]);
});

test('邮件模板详情：渲染模板名称、可用变量与预览区', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await mockTemplateApis(page);

  await page.goto('/admin/notifications/email-templates/order_paid', { waitUntil: 'domcontentloaded' });

  await expect(page.locator('#main-content')).toBeVisible();
  await expect(page.getByRole('heading', { name: '订单支付成功' })).toBeVisible();
  await expect(page.getByText('可用变量')).toBeVisible();
  await expect(page.getByText('主题预览')).toBeVisible();
  await expect(page.getByTitle('邮件模板预览')).toBeAttached();

  expect(errors).toEqual([]);
});
