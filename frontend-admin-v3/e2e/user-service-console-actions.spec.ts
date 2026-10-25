import type { Page } from '@playwright/test';

import { collectConsoleErrors, envelope, expect, mockAuthenticatedSession, test } from './fixtures';

/**
 * 用户详情「服务控制台」抽屉的实例操作区回归。
 *
 * 背景：实例操作曾提供「绑定服务」（manual-provision 重新提交上游开通）。该功能已整体删除：
 * 后端路由、控制器方法、请求类、服务层方法与资源字段，以及前端按钮、弹窗、API 方法同步移除。
 * 本用例锁两件事：
 * 1. 未获得电源能力（actions.available 为空）时，实例操作区恰好是需求约定的五项：
 *    刷新远程、重置密码、上游绑定、调价、改名称；
 * 2. 抽屉里不再出现「绑定服务」入口及其「重新提交上游开通」的说明文案。
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
  runtime: { power_state: 'running', power_label: '运行中' },
  // 电源能力留空：上游未提供电源操作时，实例操作区只应出现业务入口本身
  actions: { refresh: true, password_reset: true, available: [] as string[] },
  specs: [] as Record<string, unknown>[],
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

// 末尾必须用 `**`：请求层会给 GET 追加 `?_t=` 时间戳防缓存；`*` 不跨 `/`，
// 服务列表与详情（/services、/services/9）也在该前缀下，必须一并拦截。
const USER_DETAIL_PATH = '**/v2/admin/users/1**';
// 选项列表用空数组：裸 [] 在 tsconfig noImplicitAny 下会被推断成 any[]（TS7018）
const EMPTY_OPTIONS = envelope({ list: [] as Record<string, unknown>[] });

/**
 * 用户详情、服务列表与服务详情共用 `/v2/admin/users/1` 前缀，
 * 若分别注册通配路由会互相覆盖，因此统一按 pathname 分发。
 */
async function mockServiceConsolePage(page: Page) {
  await mockAuthenticatedSession(page);
  await page.route('**/v2/admin/member-levels*', (route) => route.fulfill(jsonResponse(EMPTY_OPTIONS)));
  await page.route('**/v2/admin/promotion-ambassadors*', (route) => route.fulfill(jsonResponse(EMPTY_OPTIONS)));
  await page.route('**/v2/admin/coupon-product-groups**', (route) => route.fulfill(jsonResponse(EMPTY_OPTIONS)));
  await page.route(USER_DETAIL_PATH, (route) => {
    const { pathname } = new URL(route.request().url());
    if (pathname.endsWith('/services/9/connection')) {
      return route.fulfill(jsonResponse(envelope(SERVICE_CONNECTION_RESPONSE)));
    }
    if (pathname.endsWith('/services/9')) {
      return route.fulfill(jsonResponse(envelope({ service: SERVICE_DETAIL_RESPONSE })));
    }
    if (pathname.endsWith('/services')) {
      return route.fulfill(jsonResponse(envelope(SERVICE_LIST_RESPONSE)));
    }
    return route.fulfill(jsonResponse(envelope(USER_DETAIL_RESPONSE)));
  });
}

test('服务控制台实例操作仅保留五项，且不再提供绑定服务', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockServiceConsolePage(page);

  // 直接带 ?tab=services 进入，详情页 onMounted 会同步初始化该 Tab 并拉取服务列表
  await page.goto('/admin/users/1?tab=services', { waitUntil: 'domcontentloaded' });
  // 精确匹配行内「管理」：侧边栏「实名管理」等导航项也含「管理」二字
  const manageButton = page.locator('.row-actions').getByRole('button', { name: '管理', exact: true });
  await expect(manageButton).toHaveCount(1);
  await manageButton.click();

  const drawer = page.locator('.t-drawer').first();
  await expect(drawer.locator('.drawer-header__title')).toHaveText('服务控制台');
  await expect(drawer.locator('.console-section__title').first()).toHaveText('实例操作');

  // 实例操作区：没有电源能力时只应有需求约定的五个入口
  const actions = drawer.locator('.console-actions');
  await expect(actions.getByRole('button')).toHaveText(['刷新远程', '重置密码', '上游绑定', '调价', '改名称']);

  // 「绑定服务」已随 manual-provision 链路删除：入口与说明文案都不应再出现
  await expect(drawer).not.toContainText('绑定服务');
  await expect(drawer).not.toContainText('重新提交上游开通');

  // 保留下来的「改名称」仍能打开自己的弹窗
  await actions.getByRole('button', { name: '改名称' }).click();
  await expect(page.locator('.t-dialog').filter({ hasText: '编辑实例名称' }).first()).toBeVisible();

  expect(errors).toEqual([]);
});

function jsonResponse(body: unknown, status = 200) {
  return { status, contentType: 'application/json', body: JSON.stringify(body) };
}
