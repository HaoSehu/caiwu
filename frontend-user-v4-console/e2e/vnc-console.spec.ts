import type { Page } from '@playwright/test';

import { envelope, expect, mockAuthenticatedSession, test } from './fixtures';

// VNC 控制台用例：能力标记隐藏标签、取链接后内嵌 iframe、状态回传、断开与重连。
// 接口全部 mock；内嵌页是 public/vnc/vnc.html，其内部对 api_base 的请求也一并拦截，
// 避免用例去连真实后端或等待 WebSocket 超时。

const SERVICE_DETAIL = {
  id: 1,
  name: '云服务器 E2E-01',
  status: 1,
  status_tone: 'success',
  console_template: '',
  machine_category: { key: 'cloud', label: '云服务器' },
  product: { id: 1, name: '云服务器', type: 'cloud', type_label: '云服务器', display_name: '云服务器 S1' },
  amount: '99.00',
  billing_cycle: 'monthly',
  billing_cycle_label: '月付',
};

function jsonOk(data: unknown) {
  return { status: 200, contentType: 'application/json', body: JSON.stringify(envelope(data)) };
}

/** 控制台首屏基础端点；actions 按需覆盖。 */
async function mockConsoleBase(page: Page, actions: Record<string, unknown> = {}) {
  const detail = {
    ...SERVICE_DETAIL,
    actions: {
      refresh: true,
      power: true,
      module_status: true,
      power_status: true,
      password_reset: true,
      reinstall: true,
      traffic_package: false,
      vnc: true,
      ...actions,
    },
  };
  await page.route('**/api/v2/client/services/1', (route) => route.fulfill(jsonOk({ service: detail })));
  await page.route('**/api/v2/client/services/1/runtime', (route) => {
    return route.fulfill(jsonOk({ runtime: { power_state: 'running', power_label: '运行中' } }));
  });
  await page.route('**/api/v2/client/services/1/connection', (route) => {
    return route.fulfill(jsonOk({ connection: { dedicated_ip: '203.0.113.10' } }));
  });
}

/** 内嵌查看器页会调 API 源站兑换 token / 重新申请，全部 mock 掉。 */
async function mockVncViewerEndpoints(page: Page) {
  // 内嵌查看器加载后会真的连 WebSocket。preview 没有 /ws/vnc，连接失败会触发查看器的
  // notifyParent('error')，把断言中的「连接中」覆盖成「连接失败」。这里用最小桩页替换查看器文档：
  // 父页面状态机与消息校验是本用例的被测对象，真实 noVNC 页由独立 e2e/手工验证覆盖。
  await page.route('**/vnc/vnc.html*', (route) => {
    return route.fulfill({
      status: 200,
      contentType: 'text/html; charset=utf-8',
      body: '<!DOCTYPE html><html><body></body></html>',
    });
  });
  await page.route('**/api/v2/client/vnc-tokens/*', (route) => {
    return route.fulfill(
      jsonOk({
        token: 'relay-token-e2e',
        service_id: 1,
        relay_path: '/ws/vnc',
        username: 'root',
        target: 'host-1',
        expires_in: 60,
      }),
    );
  });
  await page.route('**/api/v2/client/services/1/vnc', (route) => {
    return route.fulfill(
      jsonOk({
        message: '获取VNC链接成功',
        url: '/vnc/vnc.html?token=pub-token-e2e&service_id=1&relay_path=%2Fws%2Fvnc&api_base=http%3A%2F%2F127.0.0.1%3A4173%2Fapi',
        vnc_credentials: { username: 'root', target: 'host-1', password: 'secret' },
      }),
    );
  });
}

async function openConsole(page: Page) {
  await page.goto('/client/services/1', { waitUntil: 'domcontentloaded' });
  const sidebar = page.locator('button.console-sidebar-item');
  await expect(sidebar.filter({ hasText: '控制台总览' })).toBeVisible();
  return sidebar;
}

test('VNC 控制台：actions.vnc=false 时隐藏 VNC 标签', async ({ page }) => {
  await mockAuthenticatedSession(page);
  await mockConsoleBase(page, { vnc: false });

  const sidebar = await openConsole(page);

  await expect(sidebar.filter({ hasText: 'VNC 控制台' })).toHaveCount(0);
  // 其余标签不受影响
  await expect(sidebar.filter({ hasText: '控制台总览' })).toBeVisible();
});

test('VNC 控制台：取链接后内嵌查看器，状态回传与断开重连', async ({ page }) => {
  await mockAuthenticatedSession(page);
  await mockConsoleBase(page);
  await mockVncViewerEndpoints(page);

  const sidebar = await openConsole(page);
  await sidebar.filter({ hasText: 'VNC 控制台' }).click();

  // 初始为空态，未连接
  await expect(page.locator('.vnc-toolbar-status .t-tag').filter({ hasText: '未连接' })).toBeVisible();
  await expect(page.locator('iframe.vnc-frame')).toHaveCount(0);

  // 点击连接：拿到地址后才出现 iframe，带 service_id 与 fragment 凭据
  await page.getByRole('button', { name: '连接 VNC' }).click();
  const frame = page.locator('iframe.vnc-frame');
  await expect(frame).toHaveCount(1);
  const src = await frame.getAttribute('src');
  expect(src).toContain('/vnc/vnc.html');
  expect(src).toContain('service_id=1');
  expect(src).toContain('#vnc_auth=');

  // WebSocket 已被 routeWebSocket 挡住，查看器停在连接中；直接由父页面视角模拟回传，验证状态映射。
  await page.evaluate(() => {
    window.postMessage({ source: 'caiwu-vnc', status: 'connecting' }, window.location.origin);
  });
  await expect(page.locator('.vnc-toolbar-status .t-tag').filter({ hasText: '连接中' })).toBeVisible();
  await page.evaluate(() => {
    window.postMessage({ source: 'caiwu-vnc', status: 'connected' }, window.location.origin);
  });
  await expect(page.locator('.vnc-toolbar-status .t-tag').filter({ hasText: '已连接' })).toBeVisible();

  await page.evaluate(() => {
    window.postMessage(
      { source: 'caiwu-vnc', status: 'error', message: '上游拒绝 WebSocket 握手' },
      window.location.origin,
    );
  });
  await expect(page.locator('.vnc-toolbar-status .t-tag').filter({ hasText: '连接失败' })).toBeVisible();
  await expect(page.getByText('上游拒绝 WebSocket 握手')).toBeVisible();

  // 断开：移除 iframe、回到空态
  await page.getByRole('button', { name: '断开' }).click();
  await expect(page.locator('iframe.vnc-frame')).toHaveCount(0);
  await expect(page.locator('.vnc-toolbar-status .t-tag').filter({ hasText: '未连接' })).toBeVisible();

  // 重连：按钮文案变为「重新连接」，点击后重新申请地址
  await expect(page.getByRole('button', { name: '重新连接' })).toHaveCount(0);
  await page.getByRole('button', { name: '连接 VNC' }).click();
  await expect(page.locator('iframe.vnc-frame')).toHaveCount(1);
  await expect(page.getByRole('button', { name: '重新连接' })).toBeVisible();
});

test('VNC 控制台：伪造来源的状态消息不改变界面状态', async ({ page }) => {
  await mockAuthenticatedSession(page);
  await mockConsoleBase(page);
  await mockVncViewerEndpoints(page);

  const sidebar = await openConsole(page);
  await sidebar.filter({ hasText: 'VNC 控制台' }).click();
  await page.getByRole('button', { name: '连接 VNC' }).click();
  await expect(page.locator('iframe.vnc-frame')).toHaveCount(1);

  // 缺 source 标记、source 不符、status 非法、原型链键：一律忽略
  await page.evaluate(() => {
    window.postMessage({ status: 'connected' }, window.location.origin);
    window.postMessage({ source: 'other-app', status: 'connected' }, window.location.origin);
    window.postMessage({ source: 'caiwu-vnc', status: 'hacked' }, window.location.origin);
    for (const status of ['constructor', 'toString', '__proto__', 'hasOwnProperty']) {
      window.postMessage({ source: 'caiwu-vnc', status }, window.location.origin);
    }
  });
  await expect(page.locator('.vnc-toolbar-status .t-tag').filter({ hasText: '未连接' })).toHaveCount(0);
  await expect(page.locator('.vnc-toolbar-status .t-tag').filter({ hasText: '连接中' })).toBeVisible();
});

test('VNC 控制台：重连失败不影响已建立的会话', async ({ page }) => {
  await mockAuthenticatedSession(page);
  await mockConsoleBase(page);
  await mockVncViewerEndpoints(page);

  const sidebar = await openConsole(page);
  await sidebar.filter({ hasText: 'VNC 控制台' }).click();
  await page.getByRole('button', { name: '连接 VNC' }).click();
  const frame = page.locator('iframe.vnc-frame');
  await expect(frame).toHaveCount(1);
  const originalSrc = await frame.getAttribute('src');

  // 模拟查看器回传「已连接」，随后让取链接接口失败
  await page.evaluate(() => {
    window.postMessage({ source: 'caiwu-vnc', status: 'connected' }, window.location.origin);
  });
  await expect(page.locator('.vnc-toolbar-status .t-tag').filter({ hasText: '已连接' })).toBeVisible();

  await page.unroute('**/api/v2/client/services/1/vnc');
  await page.route('**/api/v2/client/services/1/vnc', (route) => {
    return route.fulfill({
      status: 422,
      contentType: 'application/json',
      body: JSON.stringify({ code: 42200, message: '当前实例状态不支持该操作', data: null }),
    });
  });

  await page.getByRole('button', { name: '重新连接' }).click();

  // 旧 iframe 与「已连接」状态保留，不会被一次失败的重连掐断
  await expect(frame).toHaveCount(1);
  await expect(frame).toHaveAttribute('src', originalSrc!);
  await expect(page.locator('.vnc-toolbar-status .t-tag').filter({ hasText: '已连接' })).toBeVisible();
});

test('VNC 控制台：actions 刷新为不可用后，标签消失且会话被断开', async ({ page }) => {
  await mockAuthenticatedSession(page);
  // 关掉 module_status / power_status：否则「状态同步」会先请求未 mock 的两个接口并中途失败，
  // 走不到本用例要验证的 /runtime 合并分支
  await mockConsoleBase(page, { module_status: false, power_status: false });
  await mockVncViewerEndpoints(page);

  const sidebar = await openConsole(page);
  await sidebar.filter({ hasText: 'VNC 控制台' }).click();
  await page.getByRole('button', { name: '连接 VNC' }).click();
  await expect(page.locator('iframe.vnc-frame')).toHaveCount(1);

  // 模拟运行态刷新把 actions.vnc 变为 false：侧栏标签消失，VNC 会话被主动断开
  await page.unroute('**/api/v2/client/services/1/runtime');
  await page.route('**/api/v2/client/services/1/runtime', (route) => {
    return route.fulfill(
      jsonOk({
        runtime: { power_state: 'rebooting', power_label: '重启中' },
        actions: {
          refresh: true,
          power: false,
          module_status: true,
          password_reset: false,
          reinstall: false,
          traffic_package: false,
          vnc: false,
        },
      }),
    );
  });
  await page.getByRole('button', { name: '状态同步' }).click();

  await expect(sidebar.filter({ hasText: 'VNC 控制台' })).toHaveCount(0);
  await expect(page.locator('iframe.vnc-frame')).toHaveCount(0);
  await expect(page.locator('.console-overview-grid')).toBeVisible();
});

// ---- 手机端 ----

/** 以手机视口打开控制台 VNC 标签并完成连接。 */
async function openVncOnMobile(page: Page) {
  await mockAuthenticatedSession(page);
  await mockConsoleBase(page);
  await mockVncViewerEndpoints(page);

  await page.setViewportSize({ width: 390, height: 844 });
  const sidebar = await openConsole(page);
  await sidebar.filter({ hasText: 'VNC 控制台' }).click();
  await page.getByRole('button', { name: '连接 VNC' }).click();
  await expect(page.locator('iframe.vnc-frame')).toHaveCount(1);
}

test.describe('手机端', () => {
  test('VNC 控制台：窄屏隐藏「新窗口打开」，主按钮独占一行且触控高度达标', async ({ page }) => {
    await openVncOnMobile(page);

    // 新窗口在手机上隐藏
    await expect(page.getByRole('button', { name: '新窗口打开' })).toBeHidden();

    // 主按钮占满整行：宽度与容器一致
    const actionsBox = await page.locator('.vnc-toolbar-actions').boundingBox();
    const primaryBox = await page.getByRole('button', { name: '重新连接' }).boundingBox();
    expect(actionsBox && primaryBox).toBeTruthy();
    expect(primaryBox!.width).toBeGreaterThan(actionsBox!.width - 2);

    // 触控目标不小于 44px（iOS HIG）
    const fullscreenBox = await page.getByRole('button', { name: '全屏', exact: true }).boundingBox();
    expect(fullscreenBox!.height).toBeGreaterThanOrEqual(44);
  });

  test('VNC 控制台：全屏按钮进入伪全屏后可退出', async ({ page }) => {
    await openVncOnMobile(page);

    const shell = page.locator('.vnc-frame-shell');
    await expect(shell).not.toHaveClass(/is-pseudo-fullscreen/);

    await page.getByRole('button', { name: '全屏', exact: true }).click();
    // 无头 Chromium 支持原生全屏或回退伪全屏，两种都应收起为「可退出」状态
    await expect(page.getByRole('button', { name: '退出全屏' }).first()).toBeVisible();

    // 原生全屏下工具栏不可交互，必须点画面区内的悬浮退出按钮
    await page.locator('.vnc-fullscreen-exit').click();
    await expect(page.getByRole('button', { name: '全屏', exact: true })).toBeVisible();
  });

  test('VNC 控制台：断开后在手机上收掉全屏与 iframe', async ({ page }) => {
    await openVncOnMobile(page);

    await page.getByRole('button', { name: '断开' }).click();
    await expect(page.locator('iframe.vnc-frame')).toHaveCount(0);
    await expect(page.locator('.vnc-toolbar-status .t-tag').filter({ hasText: '未连接' })).toBeVisible();
  });
});
