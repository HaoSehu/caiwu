import type { Page } from '@playwright/test';
import { expect, test } from '@playwright/test';

/**
 * VNC 查看器（public/vnc/vnc.html）自身的移动端交互回归。
 *
 * 用桩替换 core/rfb.js，只验证页面接线：控制栏能否展开、Ctrl+Alt+Del /
 * 键盘 / 剪贴板三个远程控制入口是否真的调到 RFB；不连接真实 Relay 与上游。
 *
 * 背景缺陷：移动端关闭态曾用 translateY(calc(100% + 24px))，把 top:-24px 的
 * 拉手一起推到视口外，手机上无法展开控制栏；剪贴板面板因 visibility 带 0.4s
 * 过渡，点击后同步 focus() 落在仍 hidden 的元素上，iOS 软键盘不会弹出。
 */

const E2E_ORIGIN = 'http://127.0.0.1:4173';

const RFB_STUB = `
window.__vncCalls = [];
export default class RFB {
  constructor(container, url) {
    window.__vncRfb = this;
    this.listeners = {};
    setTimeout(() => this.emit('connect', { detail: { clean: true } }), 10);
  }
  addEventListener(name, handler) {
    (this.listeners[name] || (this.listeners[name] = [])).push(handler);
  }
  emit(name, event) {
    (this.listeners[name] || []).forEach((handler) => handler(event));
  }
  sendCtrlAltDel() { window.__vncCalls.push('cad'); }
  clipboardPasteFrom(text) { window.__vncCalls.push('clip:' + text); }
  sendKey(keysym, code) { window.__vncCalls.push('key:' + keysym + ':' + code); }
  sendCredentials() {}
  focus() { window.__vncCalls.push('focus'); }
  disconnect() { window.__vncCalls.push('disconnect'); this.emit('disconnect', { detail: { clean: true } }); }
}
`;

async function openViewer(page: Page, rfbStub: string = RFB_STUB) {
  await page.route('**/vnc/core/rfb.js', (route) => {
    return route.fulfill({ status: 200, contentType: 'application/javascript', body: rfbStub });
  });
  await page.route('**/api/v2/client/vnc-tokens/**', (route) => {
    return route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        code: 0,
        message: 'ok',
        data: {
          token: 'relay-e2e',
          relay_path: '/ws/vnc',
          username: 'root',
          target: 'host-1',
          expires_in: 60,
        },
      }),
    });
  });

  const apiBase = encodeURIComponent(`${E2E_ORIGIN}/api`);
  await page.goto(`/vnc/vnc.html?token=public-e2e&relay_path=%2Fws%2Fvnc&api_base=${apiBase}`);
  await expect(page.locator('#btn_cad')).toBeEnabled();
}

function rfbCalls(page: Page) {
  return page.evaluate(() => (window as unknown as { __vncCalls: string[] }).__vncCalls);
}

function activeElementId(page: Page) {
  return page.evaluate(() => document.activeElement?.id ?? '');
}

/**
 * 桩：带一个「假 WebSocket」，让页面能旁听到 Relay 下发的关闭原因。
 * 真实场景里 Relay 在上游长时间零数据时以 1011 + 原因关闭。
 */
const RFB_SILENT_UPSTREAM_STUB = `
window.__vncCalls = [];
export default class RFB {
  constructor(container, url) {
    window.__vncRfb = this;
    this.listeners = {};
    this._sock = { _websocket: new EventTarget() };
    setTimeout(() => this.emit('connect', { detail: { clean: true } }), 10);
  }
  addEventListener(name, handler) {
    (this.listeners[name] || (this.listeners[name] = [])).push(handler);
  }
  emit(name, event) {
    (this.listeners[name] || []).forEach((handler) => handler(event));
  }
  failSilently(reason) {
    const closeEvent = new Event('close');
    closeEvent.wasClean = true;
    closeEvent.code = 1011;
    closeEvent.reason = reason;
    this._sock._websocket.dispatchEvent(closeEvent);
    this.emit('disconnect', { detail: { clean: false } });
  }
  sendCtrlAltDel() {}
  clipboardPasteFrom() {}
  sendKey() {}
  sendCredentials() {}
  focus() {}
  disconnect() { this.emit('disconnect', { detail: { clean: true } }); }
}
`;

test.describe('VNC 查看器移动端控制栏', () => {
  test.use({ viewport: { width: 390, height: 844 }, hasTouch: true });

  test('拉手可见、控制栏可展开，三个远程控制入口生效', async ({ page }) => {
    await openViewer(page);

    // 关闭态拉手必须完整落在视口内，否则手机端无法展开控制栏。
    const handleBox = await page.locator('#noVNC_control_bar_handle').boundingBox();
    const viewport = page.viewportSize();
    expect(handleBox).not.toBeNull();
    expect(viewport).not.toBeNull();
    expect(handleBox!.y).toBeGreaterThanOrEqual(0);
    expect(handleBox!.y + handleBox!.height).toBeLessThanOrEqual(viewport!.height);

    await page.locator('#noVNC_control_bar_handle').tap();
    await expect(page.locator('#noVNC_control_bar')).toHaveClass(/open/);

    await page.locator('#btn_cad').tap();
    await expect.poll(() => rfbCalls(page)).toContain('cad');

    await page.locator('#btn_keyboard').tap();
    await expect.poll(() => activeElementId(page)).toBe('noVNC_kbd_proxy');

    await page.locator('#btn_clipboard').tap();
    // 必须同步聚焦：留到 setTimeout 里再 focus 会越过用户手势，iOS 拒绝弹键盘。
    await expect.poll(() => activeElementId(page)).toBe('clipboard_text');

    await page.locator('#clipboard_text').fill('vnc-e2e');
    await page.locator('#btn_clipboard_send').tap();
    // change（失焦自动同步）与显式发送会连着触发，按内容去重后只应发送一次；
    // 键盘代理失焦会额外触发 focusDesktop，这里只比对剪贴板写入。
    await expect
      .poll(async () => (await rfbCalls(page)).filter((call) => call.startsWith('clip:')))
      .toEqual(['clip:vnc-e2e']);
  });

  test('输入法中间态不发、回车发真实按键、空剪贴板不发送、干净断开收起遮罩', async ({ page }) => {
    await openViewer(page);
    await page.locator('#noVNC_control_bar_handle').tap();
    await page.locator('#btn_keyboard').tap();
    await expect.poll(() => activeElementId(page)).toBe('noVNC_kbd_proxy');

    // 输入法组合期间（拼音未上屏）发出的是半成品，不能当最终文本粘贴给远端。
    await page.evaluate(() => {
      const proxy = document.getElementById('noVNC_kbd_proxy') as HTMLTextAreaElement;
      proxy.value = 'zhong';
      proxy.dispatchEvent(new InputEvent('input', { bubbles: true, isComposing: true }));
    });
    await expect.poll(() => rfbCalls(page)).not.toContain('clip:zhong');

    // 输入法组合期间按回车是上屏候选词，不能发远程 Enter。
    await page.evaluate(() => {
      const proxy = document.getElementById('noVNC_kbd_proxy') as HTMLTextAreaElement;
      proxy.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', isComposing: true, bubbles: true }));
    });
    await expect.poll(() => rfbCalls(page)).not.toContain('key:65293:Enter');

    // 组合结束才提交最终文本。
    await page.evaluate(() => {
      const proxy = document.getElementById('noVNC_kbd_proxy') as HTMLTextAreaElement;
      proxy.value = '中文';
      proxy.dispatchEvent(new CompositionEvent('compositionend', { bubbles: true, data: '中文' }));
    });
    await expect.poll(() => rfbCalls(page)).toContain('clip:中文');

    // 回车必须是真正的 Enter 按键（0xFF0D），不能只粘贴一个换行字符。
    await page.keyboard.press('Enter');
    await expect.poll(() => rfbCalls(page)).toContain('key:65293:Enter');

    // 空剪贴板不发送，也不清空远端剪贴板。
    await page.locator('#btn_clipboard').tap();
    await page.locator('#btn_clipboard_send').tap();
    await expect(page.locator('#noVNC_status')).toContainText('剪贴板内容为空');
    await expect.poll(() => rfbCalls(page)).not.toContain('clip:');

    // 干净断开也必须收起“正在连接”遮罩，否则页面会永远停在连接中。
    await page.evaluate(() => {
      (window as unknown as { __vncRfb: { emit: (name: string, event: unknown) => void } }).__vncRfb.emit(
        'disconnect',
        { detail: { clean: true } },
      );
    });
    await expect(page.locator('#noVNC_transition')).not.toHaveClass(/show/);
  });

  test('上游长时间无数据时，展示 Relay 下发的具体原因而不是笼统的中断提示', async ({ page }) => {
    await openViewer(page, RFB_SILENT_UPSTREAM_STUB);
    await expect(page.locator('#btn_cad')).toBeEnabled();

    await page.evaluate(() => {
      (window as unknown as { __vncRfb: { failSilently: (reason: string) => void } }).__vncRfb.failSilently(
        '上游 VNC 无响应，请确认实例已开机且系统已完成引导',
      );
    });

    await expect(page.locator('#noVNC_error')).toHaveClass(/show/);
    // 关键：用户要能看到上游不可用，而不是只能看到「连接已中断」干等
    await expect(page.locator('#noVNC_error_message')).toContainText('上游 VNC 无响应');
    await expect(page.locator('#noVNC_error_message')).not.toContainText('目标地址');
  });
});
