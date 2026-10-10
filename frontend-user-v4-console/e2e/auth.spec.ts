import { expect, mockSiteConfig, test } from './fixtures';

// 控制台鉴权与登录页基线用例。
// 接口全部 mock（见 fixtures.ts），不依赖本地后端。

test('未登录访问受保护路由时重定向到登录页并携带 redirect', async ({ page }) => {
  await mockSiteConfig(page);

  await page.goto('/client/dashboard', { waitUntil: 'domcontentloaded' });

  await expect(page).toHaveURL(/\/client\/login/);
  expect(new URL(page.url()).searchParams.get('redirect')).toBe('/client/dashboard');
  await expect(page.locator('.client-auth-tabs')).toBeVisible();
});

test('登录页渲染完整且密码默认掩码、可见性开关可鼠标与键盘操作', async ({ page }) => {
  await mockSiteConfig(page);

  await page.goto('/client/login', { waitUntil: 'domcontentloaded' });

  // 表单骨架
  await expect(page.locator('#login-account')).toBeVisible();
  await expect(page.locator('#login-password')).toBeVisible();
  await expect(page.getByRole('button', { name: '登录', exact: true })).toBeVisible();

  // 密码默认掩码
  const passwordInner = page.locator('#login-password input');
  await expect(passwordInner).toHaveAttribute('type', 'password');

  // 可见性开关是自研可访问按钮（t-input 的内置眼睛图标会覆盖 suffix-icon 插槽，已被隐藏）
  const toggle = page.locator('#login-password .password-toggle');
  await expect(toggle).toHaveCount(1);
  await expect(page.locator('#login-password .t-input__suffix-icon .t-icon-browse-off')).toBeHidden();
  await expect(toggle).toHaveAttribute('aria-label', '显示密码');
  await expect(toggle).toHaveAttribute('aria-pressed', 'false');

  // 鼠标切换
  await toggle.click();
  await expect(toggle).toHaveAttribute('aria-label', '隐藏密码');
  await expect(toggle).toHaveAttribute('aria-pressed', 'true');
  await expect(passwordInner).toHaveAttribute('type', 'text');

  // 键盘切换（Enter）
  await toggle.focus();
  await page.keyboard.press('Enter');
  await expect(passwordInner).toHaveAttribute('type', 'password');

  // 键盘切换（Space）
  await page.keyboard.press('Space');
  await expect(passwordInner).toHaveAttribute('type', 'text');

  // clearable 的清除图标不受影响：只隐藏内置眼睛图标，不能整块隐藏 suffix-icon
  await passwordInner.fill('secret123');
  await expect(page.locator('#login-password .t-icon-close-circle-filled')).toBeVisible();
});

test('登录壳层为纯浅色：无暗色残留、无玻璃拟态与发光装饰', async ({ page }) => {
  await mockSiteConfig(page);

  await page.goto('/client/login', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('.auth-card')).toBeVisible();

  const measured = await page.evaluate(() => {
    const card = document.querySelector('.auth-card');
    const shell = document.querySelector('.auth-shell');
    const cardStyle = card ? getComputedStyle(card) : null;
    const shellStyle = shell ? getComputedStyle(shell) : null;
    return {
      themeMode: document.documentElement.getAttribute('theme-mode'),
      cardBackground: cardStyle?.backgroundColor ?? '',
      cardBackdropFilter: cardStyle?.backdropFilter ?? '',
      shellBackground: shellStyle?.backgroundColor ?? '',
      // 纯装饰几何体（网格/斜线/光带/方块簇/平面）必须已移除
      decorativeNodes: document.querySelectorAll(
        '.auth-shell__scene, .auth-shell__mesh, .auth-shell__beam, .auth-shell__cluster, .auth-shell__plane',
      ).length,
      // 任何模糊滤镜（发光球/毛玻璃）都必须已移除
      blurNodes: Array.from(document.querySelectorAll('.auth-shell *')).filter((el) => {
        const style = getComputedStyle(el);
        return `${style.filter}${style.backdropFilter}`.includes('blur');
      }).length,
    };
  });

  expect(measured.themeMode).not.toBe('dark');
  // 卡片与壳层必须是实色底（不允许 rgba 半透明玻璃）
  expect(measured.cardBackground).not.toMatch(/rgba\([^)]*,\s*(0?\.\d+|0)\)/);
  expect(measured.shellBackground).not.toMatch(/rgba\([^)]*,\s*(0?\.\d+|0)\)/);
  expect(measured.cardBackdropFilter === 'none' || measured.cardBackdropFilter === '').toBe(true);
  expect(measured.decorativeNodes).toBe(0);
  expect(measured.blurNodes).toBe(0);
});
