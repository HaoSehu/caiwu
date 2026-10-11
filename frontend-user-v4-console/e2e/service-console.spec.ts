import type { Page } from '@playwright/test';

import { envelope, expect, mockAuthenticatedSession, test } from './fixtures';

// 服务控制台用例：标签懒加载、监控范围切换竞态、安全组失败重试。
// 接口全部 mock，不依赖本地后端。CLOUD_TABS 无 nat，懒加载用「操作日志 / 财务日志」验证。

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

function jsonFail(message: string) {
  return { status: 500, contentType: 'application/json', body: JSON.stringify({ code: 500, message, data: null }) };
}

/** 控制台首屏的三个基础端点（详情 / 运行状态 / 连接信息）。 */
async function mockConsoleBase(page: Page) {
  await page.route('**/api/v2/client/services/1', (route) => route.fulfill(jsonOk({ service: SERVICE_DETAIL })));
  await page.route('**/api/v2/client/services/1/runtime', (route) =>
    route.fulfill(jsonOk({ runtime: { power_state: 'running', power_label: '运行中' } })));
  await page.route('**/api/v2/client/services/1/connection', (route) =>
    route.fulfill(jsonOk({ connection: { dedicated_ip: '203.0.113.10' } })));
}

/** 按范围构造监控 batch 响应：3h 为旧特征数据，其余范围为各自特征数据。 */
function buildMonitorPayload(range: string) {
  const label = range === '3h' ? 'CPU 负载（3 小时窗口）' : `CPU 负载（${range} 窗口）`;

  return {
    supported: true,
    range: { preset: range },
    charts: [
      {
        type: 'cpu',
        label,
        chart: {
          unit: '%',
          y_max: 100,
          list: [
            { time: '10:00', timestamp: 1767223200, value: 12 },
            { time: '10:05', timestamp: 1767223500, value: 18 },
          ],
        },
        summary: { latest: { text: `${range} 最新 12%`, time: '10:05' } },
      },
    ],
  };
}

/** 打开实例控制台入口，等待详情加载完成（侧栏出现）。 */
async function openConsole(page: Page) {
  await page.goto('/client/services/1', { waitUntil: 'domcontentloaded' });
  const sidebar = page.locator('button.console-sidebar-item');
  await expect(sidebar.filter({ hasText: '控制台总览' })).toBeVisible();
  return sidebar;
}

test('服务控制台：标签懒加载——首屏不发监控/日志/财务请求，切到对应标签才拉取', async ({ page }) => {
  await mockAuthenticatedSession(page);
  await mockConsoleBase(page);

  // CLOUD_TABS 注册表无 nat 标签，懒加载只验证 monitor / logs / ledger 三类请求
  const counters = { monitor: 0, logs: 0, ledger: 0 };
  await page.route('**/api/v2/client/services/1/monitor/batch*', (route) => {
    counters.monitor += 1;
    return route.fulfill(jsonOk({ supported: true, charts: [] }));
  });
  await page.route('**/api/v2/client/services/1/operation-logs*', (route) => {
    counters.logs += 1;
    return route.fulfill(jsonOk({
      list: [
        {
          id: 1,
          created_at: '2026-01-01 10:00:00',
          action_label: '开机',
          summary: 'E2E 开机操作',
          actor_name: '测试用户',
          ip_address: '1.2.3.4',
        },
      ],
      total: 1,
      summary: { total: 1, today_total: 1, latest_created_at: '2026-01-01 10:00:00', service_name: '云服务器 E2E-01' },
    }));
  });
  await page.route('**/api/v2/client/ledger*', (route) => {
    counters.ledger += 1;
    return route.fulfill(jsonOk({
      list: [],
      total: 0,
      summary: { total_in: '0.00', total_out: '0.00', refund_in: '0.00', total_count: 0 },
    }));
  });

  const sidebar = await openConsole(page);

  // 首屏稳定后，懒加载标签的接口都不应被触发
  await page.waitForTimeout(1500);
  expect(counters.monitor).toBe(0);
  expect(counters.logs).toBe(0);
  expect(counters.ledger).toBe(0);

  // 切到「操作日志」才发请求，且渲染出日志内容
  await sidebar.filter({ hasText: '操作日志' }).click();
  await expect.poll(() => counters.logs, { timeout: 10_000 }).toBe(1);
  await expect(page.getByText('E2E 开机操作')).toBeVisible();

  // 切到「财务日志」才发请求（列表 + 汇总两次）
  await sidebar.filter({ hasText: '财务日志' }).click();
  await expect.poll(() => counters.ledger, { timeout: 10_000 }).toBeGreaterThanOrEqual(1);
  await expect(page.getByText('共 0 条')).toBeVisible();
  // 日志已成功加载过（loadedTabs 置位），切走后不应重复拉取
  expect(counters.logs).toBe(1);
});

test('服务控制台：监控范围切换竞态——慢的旧响应不得覆盖新范围数据', async ({ page }) => {
  await mockAuthenticatedSession(page);
  await mockConsoleBase(page);

  await page.route('**/api/v2/client/services/1/monitor/batch*', async (route) => {
    const range = new URL(route.request().url()).searchParams.get('range') || '';
    if (range === '3h') {
      // 默认范围的慢响应：延迟返回旧特征数据，requestSeq 竞态防护应将其丢弃
      await new Promise((resolve) => setTimeout(resolve, 2500));
      return route.fulfill(jsonOk(buildMonitorPayload('3h')));
    }
    return route.fulfill(jsonOk(buildMonitorPayload(range || '3h')));
  });

  const sidebar = await openConsole(page);

  // 进入监控标签触发默认范围（3h，慢）请求，随后立刻切换到 24h（快）
  await sidebar.filter({ hasText: '监控信息' }).click();
  await page.getByText('24 小时', { exact: true }).click();

  // 新范围数据先到达并渲染
  await expect(page.getByText('CPU 负载（24h 窗口）')).toBeVisible({ timeout: 10_000 });
  // 慢的旧响应到达后：既不能覆盖新数据，也不能渲染旧数据
  await page.waitForTimeout(3000);
  await expect(page.getByText('CPU 负载（24h 窗口）')).toBeVisible();
  await expect(page.getByText('CPU 负载（3 小时窗口）')).toHaveCount(0);
});

test('服务控制台：安全组首次加载失败展示错误态，切回后自动重试成功', async ({ page }) => {
  await mockAuthenticatedSession(page);
  await mockConsoleBase(page);

  let groupsFailing = true;
  const counters = { groups: 0 };
  // 注意 glob 的 `*` 不跨 `/`，列表与 rules 子路径必须分开拦截
  await page.route('**/api/v2/client/services/1/security-groups/*/rules', (route) =>
    route.fulfill(jsonOk({
      list: [
        {
          id: 1,
          direction: 'in',
          direction_label: '入站',
          protocol: 'tcp',
          protocol_label: 'TCP',
          port: '80',
          ip: '0.0.0.0/0',
          description: 'HTTP 入站',
        },
      ],
    })));
  await page.route('**/api/v2/client/services/1/security-groups', (route) => {
    counters.groups += 1;
    if (groupsFailing) {
      return route.fulfill(jsonFail('加载安全组失败'));
    }
    return route.fulfill(jsonOk({
      supported: true,
      can_create: true,
      groups: [
        { id: 1, name: 'E2E 安全组', description: 'E2E 安全组描述', is_applied: true, can_view: true, can_add_rule: true },
      ],
    }));
  });

  const sidebar = await openConsole(page);

  // 首次进入安全组：持续 500 → 错误态提示，不出内容。
  // 注意 HTTP 层对 5xx 有自动重试（约 300ms 起、指数退避），先等重试耗尽再断言。
  await sidebar.filter({ hasText: '安全组' }).click();
  await page.waitForTimeout(2000);
  await expect(page.locator('.console-inline-alert')).toBeVisible();
  await expect(page.getByText('E2E 安全组描述')).toHaveCount(0);
  expect(counters.groups).toBeGreaterThanOrEqual(1);

  // 切回总览再切回安全组：失败未置位 loadedTabs → 自动重新请求并成功
  groupsFailing = false;
  const countBeforeRetry = counters.groups;
  await sidebar.filter({ hasText: '控制台总览' }).click();
  await sidebar.filter({ hasText: '安全组' }).click();
  await expect(page.getByText('E2E 安全组描述')).toBeVisible({ timeout: 10_000 });
  await expect(page.getByText('1 条规则')).toBeVisible();
  await expect(page.getByText('HTTP 入站').first()).toBeVisible();
  await expect.poll(() => counters.groups, { timeout: 10_000 }).toBeGreaterThan(countBeforeRetry);
});
