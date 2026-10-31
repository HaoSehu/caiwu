import type { Page } from '@playwright/test';

import { collectConsoleErrors, envelope, expect, mockAuthenticatedSession, test } from './fixtures';

/**
 * 管理端「日志归档」设置页：默认值回归。
 *
 * 背景（2026-10-31 数据库增长排查）：后端 backend/config/log_archive.php:10 的
 * retention_days 是 90，而本页字段默认值曾是 30。管理员打开页面看到的是 30，
 * 一旦点保存就会把保留期从 90 骤降到 30，下一次 02:00 归档会一次性删除
 * 大量历史日志。默认值必须与后端配置同口径。
 *
 * 只验证：路由可达、两组字段渲染、默认值等于后端配置值、无 console error。
 * 真实归档行为由 backend 的 Feature 测试与 db:logs:archive:health 覆盖，不在 e2e 伪造。
 */

/** 与 backend/config/log_archive.php:10 的 retention_days 保持一致。 */
const BACKEND_RETENTION_DAYS = 90;

/** 与 backend/config/log_archive.php:11 的 file_retention_days 保持一致。 */
const BACKEND_FILE_RETENTION_DAYS = 180;

const JSON_HEADERS = { status: 200, contentType: 'application/json' };

/**
 * 设置项为空：本用例验证的是「没有已保存值时字段回落到前端默认值」，
 * 因此必须返回空列表，否则拿到的是接口值而不是默认值。
 */
async function mockEmptySettings(page: Page) {
  const body = JSON.stringify(envelope({ list: [] as Record<string, unknown>[] }));
  await page.route('**/v2/admin/settings*', (route) => route.fulfill({ ...JSON_HEADERS, body }));
}

/** 分组切换按钮渲染成 label.t-radio-button，真实 input 是隐藏的。 */
function sectionSwitch(page: Page, title: string) {
  return page.locator('label.t-radio-button', { hasText: title });
}

/**
 * 按字段名取输入框。
 *
 * 不能用 getByLabel：字段卡片把 aria-label 挂在 input 上、标题挂在同一个 article 里，
 * getByLabel 会命中外层元素而不是 input。这里按「字段卡片包含标题」定位再取 input。
 * 数字字段（t-input-number）内部的 input 同样适用。
 */
function fieldInput(page: Page, label: string) {
  return page.locator('article.field-card', { hasText: label }).locator('input').first();
}

test('日志归档设置：默认值与后端配置一致', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await mockEmptySettings(page);

  await page.goto('/admin/log-archive', { waitUntil: 'domcontentloaded' });

  await expect(page.locator('#main-content')).toBeVisible();

  // 页面标题由布局渲染，这里只断言分组区渲染出来，避免依赖布局文案。
  await expect(sectionSwitch(page, '运行参数')).toBeVisible();
  await expect(sectionSwitch(page, '保留策略')).toBeVisible();

  // 默认停在第一个分组「运行参数」。
  await expect(fieldInput(page, 'pt-archiver 二进制路径')).toHaveValue('/usr/bin/pt-archiver');
  await expect(fieldInput(page, '默认配置文件')).toHaveValue('/etc/caiwu/pt-archiver.cnf');
  await expect(fieldInput(page, '每批处理行数')).toHaveValue('1000');

  // 保留策略分组的两个字段：默认值必须与后端 config 同口径，否则保存即降配。
  await sectionSwitch(page, '保留策略').click();
  await expect(fieldInput(page, '日志保留天数')).toHaveValue(String(BACKEND_RETENTION_DAYS));
  await expect(fieldInput(page, '归档文件保留天数')).toHaveValue(String(BACKEND_FILE_RETENTION_DAYS));

  expect(errors).toEqual([]);
});
