import { collectConsoleErrors, expect, test } from './fixtures';

/**
 * 冒烟：未登录访问受保护路由应落到登录页，且不产生 JS 异常。
 *
 * 断言目的：P0-1（TDesign 全量注册改按需注册）之后，登录页的
 * t-form / t-input / t-button 仍能被正确解析并渲染。
 */
test('未登录访问后台首页会跳转登录页并正常渲染', async ({ page }) => {
  const errors = collectConsoleErrors(page);

  await page.goto('/admin/dashboard', { waitUntil: 'domcontentloaded' });

  await expect(page).toHaveURL(/\/admin\/login/);
  await expect(page.getByPlaceholder('请输入管理员账号')).toBeVisible();
  await expect(page.getByPlaceholder('请输入密码')).toBeVisible();
  await expect(page.getByRole('button', { name: '登录' })).toBeVisible();

  expect(errors).toEqual([]);
});
