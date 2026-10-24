import { collectConsoleErrors, expect, mockDashboard, test } from './fixtures';

/**
 * 进度条配色：验证 A8 修复后 #nprogress .bar 真正取到品牌色。
 *
 * 修复前该规则写在 App.vue 的 scoped 块中，会编译成 `.bar[data-v-xxx]`，
 * 而 NProgress 的节点由运行库注入 body、不带作用域属性，导致覆写从未生效（实为默认 #29d）。
 * 这里直接在页面内注入同名结构，用真实层叠结果断言计算值，避免依赖跳转时机。
 */
test('NProgress 进度条取品牌色 #165dff 而非默认 #29d', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  // 看板页会并发请求 stats / monthly-revenue，必须一并拦截，否则真实请求穿透到线上域名
  await mockDashboard(page);
  await page.goto('/admin/dashboard', { waitUntil: 'domcontentloaded' });

  const background = await page.evaluate(() => {
    const wrapper = document.createElement('div');
    wrapper.id = 'nprogress';
    const bar = document.createElement('div');
    bar.className = 'bar';
    wrapper.appendChild(bar);
    document.body.appendChild(wrapper);
    const color = getComputedStyle(bar).backgroundColor;
    wrapper.remove();
    return color;
  });

  // #165DFF → rgb(22, 93, 255)
  expect(background).toBe('rgb(22, 93, 255)');
  expect(errors).toEqual([]);
});
