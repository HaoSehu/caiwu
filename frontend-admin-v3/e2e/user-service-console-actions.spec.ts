import type { Page } from '@playwright/test';

import { collectConsoleErrors, envelope, expect, mockAuthenticatedSession, test } from './fixtures';

/**
 * 服务控制台独立页回归。
 *
 * 背景：服务控制台原先是用户详情页里的抽屉，现已重构为独立路由页
 * `/admin/users/:id/services/:serviceId`，页内用 tab 承载概览、相关订单、相关账单、
 * 操作日志与历史跟踪五个板块。本用例锁定四件事：
 * 1. 用户详情页服务列表的「管理」跳转到独立页，URL 与页面标题正确；
 * 2. 首屏只等本地快照（sync_remote=0）就渲染，远程状态补丁放行后异步补齐运行状态；
 * 3. 概览页签的实例操作区恰好是需求约定的五项（电源能力为空时不出现开关机/重启）；
 * 4. 五个页签齐全，历史跟踪页签能新增带留言的记录并回显操作人；
 * 5. 全程无 console 报错。
 */

const USER_DETAIL_RESPONSE = {
  user: {
    id: 1,
    nickname: '测试用户',
    email: 'user@example.com',
    phone: '13800000000',
    status: 1,
    cash_balance: '100.00',
    member_level_id: null as number | null,
    promotion_ambassador_id: null as number | null,
  },
  stats: { ticket_open: 0, total_expense: '0' },
  referral: {},
};

const SERVICE_LIST_RESPONSE = {
  list: [
    {
      id: 9,
      name: 'E2E 云服务器',
      product_full_path: '云服务器/美国/三网精品/gscs-2vcpu-2gib',
      domain: 'e2e.example.com',
      status: 1,
      amount: '20.00',
      billing_cycle_label: '月付',
      created_at: '2026-08-25 09:28:30',
      expires_at: '2026-10-25 09:28:40',
      product: { type_label: '云服务器' },
      upstream: { dedicated_ip: '45.205.31.247' },
    },
  ],
  total: 1,
  page: 1,
  page_size: 20,
};

const SERVICE_DETAIL_RESPONSE = {
  id: 9,
  name: 'E2E 云服务器',
  product_full_path: '云服务器/美国/三网精品/gscs-2vcpu-2gib',
  domain: 'e2e.example.com',
  status: 1,
  billing_cycle_label: '月付',
  amount: '20.00',
  created_at: '2026-08-25 09:28:30',
  expires_at: '2026-10-25 09:28:40',
  product: { display_name: '云服务器/美国/三网精品/gscs-2vcpu-2gib' },
  upstream: { provider_key: 'e2e-upstream', host_id: 89841 },
  // 本地快照没有上游运行状态：首屏应显示「运行状态未知」，等远程补丁到达后才变成运行中
  runtime: {} as Record<string, unknown>,
  // 电源能力留空：上游未提供电源操作时，实例操作区只应出现业务入口本身
  actions: { refresh: true, password_reset: true, available: [] as string[] },
  specs: [] as Record<string, unknown>[],
};

/** 远程状态补丁只带上游才能提供的字段（运行状态、操作系统）。 */
const SERVICE_REMOTE_STATUS_RESPONSE = {
  id: 9,
  name: 'E2E 云服务器',
  upstream: { provider_key: 'e2e-upstream', host_id: 89841, os: 'Debian 12' },
  runtime: { power_state: 'running', power_label: '运行中' },
  actions: { refresh: true, password_reset: true, available: [] as string[] },
};

const SERVICE_CONNECTION_RESPONSE = {
  connection: {
    hostname: '45.205.31.247',
    username: 'root',
    port: 22,
    dedicated_ip: '45.205.31.247',
    internal_ip: '',
  },
};

/** 历史跟踪列表初始为空，新增后返回本次提交的内容。 */
let trackingRecords: Array<Record<string, unknown>> = [];

const EMPTY_OPTIONS = envelope({ list: [] as Record<string, unknown>[] });

/** 详情接口请求地址，用于断言首屏用的是本地快照（sync_remote=0）。 */
let detailRequestUrls: string[] = [];

/**
 * 用户维度与服务维度的接口共用 /v2/admin/users/1 前缀，统一按 pathname 分发。
 *
 * remoteStatusGate 用于把远程状态补丁拦在 pending 状态，让用例先断言首屏已经渲染、
 * 再放行补丁验证运行状态被补齐，从而锁定「先本地快照、后远程补丁」的两段式时序。
 */
async function mockServiceConsolePage(
  page: Page,
  options: { remoteStatusGate?: Promise<void>; remoteStatusFailure?: boolean } = {},
) {
  trackingRecords = [];
  detailRequestUrls = [];
  await mockAuthenticatedSession(page);
  await page.route('**/v2/admin/member-levels*', (route) => route.fulfill(jsonResponse(EMPTY_OPTIONS)));
  await page.route('**/v2/admin/promotion-ambassadors*', (route) => route.fulfill(jsonResponse(EMPTY_OPTIONS)));
  await page.route('**/v2/admin/coupon-product-groups**', (route) => route.fulfill(jsonResponse(EMPTY_OPTIONS)));
  await page.route('**/v2/admin/suppliers*', (route) => route.fulfill(jsonResponse(EMPTY_OPTIONS)));
  await page.route('**/v2/admin/users/1**', async (route) => {
    const { pathname } = new URL(route.request().url());
    const method = route.request().method();
    if (pathname.endsWith('/services/9/connection')) {
      return route.fulfill(jsonResponse(envelope(SERVICE_CONNECTION_RESPONSE)));
    }
    if (pathname.endsWith('/services/9/remote-status')) {
      if (options.remoteStatusGate) {
        await options.remoteStatusGate;
      }
      if (options.remoteStatusFailure) {
        return route.fulfill(jsonResponse({ code: 50000, message: '上游暂不可用', data: null }, 500));
      }
      return route.fulfill(jsonResponse(envelope({ service: SERVICE_REMOTE_STATUS_RESPONSE })));
    }
    if (pathname.endsWith('/services/9/tracking-records')) {
      if (method === 'POST') {
        const payload = route.request().postDataJSON() as Record<string, unknown>;
        const record = {
          id: trackingRecords.length + 1,
          service_id: 9,
          tracked_at: payload.tracked_at,
          content: payload.content,
          images: payload.images || [],
          operator_id: 1,
          operator_name: '测试管理员',
          created_at: payload.tracked_at,
        };
        trackingRecords = [record, ...trackingRecords];
        return route.fulfill(jsonResponse(envelope({ record })));
      }
      return route.fulfill(
        jsonResponse(
          envelope({
            list: trackingRecords,
            total: trackingRecords.length,
            page: 1,
            page_size: 10,
          }),
        ),
      );
    }
    if (pathname.endsWith('/services/9/operation-logs')) {
      return route.fulfill(
        jsonResponse(
          envelope({
            list: [
              {
                id: 1,
                created_at: '2026-08-25 09:30:00',
                action: 'service.console.name.update',
                action_label: '设置实例名称',
                category: 'service',
                category_label: '实例变更',
                summary: '管理员修改实例名称',
                actor_type: 'admin',
                actor_label: '管理员',
                actor_name: '测试管理员',
                ip_address: '127.0.0.1',
                detail_items: [{ label: '实例名称', value: 'E2E 云服务器' }],
              },
            ],
            total: 1,
            page: 1,
            page_size: 10,
            summary: {},
          }),
        ),
      );
    }
    if (pathname.endsWith('/services/9')) {
      detailRequestUrls.push(route.request().url());
      return route.fulfill(jsonResponse(envelope({ service: SERVICE_DETAIL_RESPONSE })));
    }
    if (pathname.endsWith('/services')) {
      return route.fulfill(jsonResponse(envelope(SERVICE_LIST_RESPONSE)));
    }
    if (pathname.endsWith('/invoices')) {
      return route.fulfill(
        jsonResponse(
          envelope({
            list: [
              {
                id: 31,
                invoice_no: 'INV-E2E-0001',
                amount: '20.00',
                status: 1,
                type: 'new',
                type_label: '新购',
                due_date: '2026-08-25 10:00:00',
                paid_at: '2026-08-25 09:30:00',
                created_at: '2026-08-25 09:28:30',
                payment_summary: { gateway: '支付宝' },
              },
            ],
            total: 1,
            page: 1,
            page_size: 10,
          }),
        ),
      );
    }
    if (pathname.endsWith('/orders')) {
      return route.fulfill(
        jsonResponse(
          envelope({
            list: [
              {
                id: 21,
                order_no: 'ORD-E2E-0001',
                product_full_path: '云服务器/美国/三网精品/gscs-2vcpu-2gib',
                type: 'new',
                status: 1,
                amount: '20.00',
                paid_at: '2026-08-25 09:30:00',
                created_at: '2026-08-25 09:28:30',
              },
            ],
            total: 1,
            page: 1,
            page_size: 10,
          }),
        ),
      );
    }
    return route.fulfill(jsonResponse(envelope(USER_DETAIL_RESPONSE)));
  });
}

test('服务控制台独立页：首屏先渲染本地快照，远程状态后补丁', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  // 远程状态补丁先挂起，确保断言首屏时上游数据尚未到达
  let releaseRemoteStatus = () => {};
  const remoteStatusGate = new Promise<void>((resolve) => {
    releaseRemoteStatus = resolve;
  });
  await mockServiceConsolePage(page, { remoteStatusGate });

  // 从用户详情页的服务列表进入，验证「管理」已改为路由跳转而非抽屉
  await page.goto('/admin/users/1?tab=services', { waitUntil: 'domcontentloaded' });
  const manageButton = page.locator('.row-actions').getByRole('button', { name: '管理', exact: true });
  await expect(manageButton).toHaveCount(1);
  await manageButton.click();

  await expect(page).toHaveURL(/\/admin\/users\/1\/services\/9$/);
  await expect(page.locator('.identity-title')).toHaveText('E2E 云服务器');
  await expect(page.locator('.t-tabs__nav-item')).toHaveText(['概览', '相关订单', '相关账单', '操作日志', '历史跟踪']);

  // 首屏用的是本地快照：详情请求带 sync_remote=0，且远程补丁还没回来时页面已完整渲染
  await expect.poll(() => detailRequestUrls.length).toBe(1);
  expect(detailRequestUrls[0]).toContain('sync_remote=0');
  await expect(page.locator('.console-actions')).toBeVisible();
  await expect(page.locator('.identity-headline')).toContainText('运行状态未知');

  // 放行远程补丁：运行状态与上游操作系统补齐，首屏不重挂
  releaseRemoteStatus();
  await expect(page.locator('.identity-headline')).toContainText('运行中');
  await expect(page.locator('.identity-headline')).not.toContainText('运行状态未知');

  // 显式「刷新远程」保留完整路径：带 loading 与成功提示
  await page.locator('.console-actions').getByRole('button', { name: '刷新远程' }).click();
  await expect(page.locator('.t-message')).toContainText('远程状态已刷新');

  // 实例操作区：没有电源能力时只应有需求约定的五个入口
  const actions = page.locator('.console-actions');
  await expect(actions.getByRole('button')).toHaveText(['刷新远程', '重置密码', '上游绑定', '调价', '改名称']);
  await expect(page.locator('.service-console-page')).not.toContainText('绑定服务');
  await expect(page.locator('.service-console-page')).not.toContainText('重新提交上游开通');

  // 相关订单 / 相关账单复用既有列表接口，按实例维度过滤
  await page.locator('.t-tabs__nav-item', { hasText: '相关订单' }).click();
  await expect(page.locator('.console-table-scroll .t-table')).toContainText('ORD-E2E-0001');
  await page.locator('.t-tabs__nav-item', { hasText: '相关账单' }).click();
  await expect(page.locator('.console-table-scroll .t-table')).toContainText('INV-E2E-0001');

  // 操作日志展示管理员操作
  await page.locator('.t-tabs__nav-item', { hasText: '操作日志' }).click();
  await expect(page.locator('.console-table-scroll .t-table')).toContainText('设置实例名称');
  await expect(page.locator('.console-table-scroll .t-table')).toContainText('管理员');

  // 历史跟踪：新增一条带留言的记录，操作人取当前登录管理员
  await page.locator('.t-tabs__nav-item', { hasText: '历史跟踪' }).click();
  await expect(page.locator('.tracking-list')).toHaveCount(0);
  await page.getByRole('button', { name: '新增记录' }).click();
  const createDialog = page.locator('.t-dialog').filter({ hasText: '新增跟踪记录' }).first();
  await expect(createDialog).toBeVisible();
  await createDialog.locator('textarea').fill('e2e 跟进：已联系上游确认续费');
  await createDialog.getByRole('button', { name: '确定' }).click();

  await expect(page.locator('.tracking-item__content')).toHaveText('e2e 跟进：已联系上游确认续费');
  await expect(page.locator('.tracking-item__operator')).toContainText('测试管理员');

  expect(errors).toEqual([]);
});

test('服务控制台独立页：远程补丁失败时保留本地快照且不报错', async ({ page }) => {
  // 补丁 500 会在浏览器控制台留下资源加载错误，这里不断言 errors 为空，只断言页面行为
  await mockServiceConsolePage(page, { remoteStatusFailure: true });

  await page.goto('/admin/users/1/services/9', { waitUntil: 'domcontentloaded' });

  await expect(page.locator('.identity-title')).toHaveText('E2E 云服务器');
  await expect(page.locator('.console-actions')).toBeVisible();
  // 补丁失败：首屏快照照常可用，运行状态停留在未知，不弹全局错误
  await expect(page.locator('.identity-headline')).toContainText('运行状态未知');
  await expect(page.locator('.t-message')).toHaveCount(0);
});

test('服务控制台独立页：工具栏刷新失败时给出提示', async ({ page }) => {
  await mockServiceConsolePage(page, { remoteStatusFailure: true });

  await page.goto('/admin/users/1/services/9', { waitUntil: 'domcontentloaded' });
  await expect(page.locator('.identity-title')).toHaveText('E2E 云服务器');

  await page.getByRole('button', { name: '刷新', exact: true }).click();
  // 后端返回了可读错误时优先展示后端文案
  await expect(page.locator('.t-message')).toContainText('上游暂不可用');
});

function jsonResponse(body: unknown, status = 200) {
  return { status, contentType: 'application/json', body: JSON.stringify(body) };
}
