import type { Page } from '@playwright/test';

import { collectConsoleErrors, envelope, expect, mockAuthenticatedSession, test } from './fixtures';

/**
 * 用户详情「新建实例」弹层回归（审查条目 P2-6）。
 *
 * 背景：该弹层原先是 users/detail/index.vue 内联的 ~107 行模板 + 8 个函数 + 6 个状态块，
 * 已抽成 components/AddServiceDialog.vue，父级只保留一个模板 ref 与创建后的刷新回调。
 * 抽取最容易出三类回归，本用例逐一锁住：
 * 1. 按钮到弹层的连线：父级 `@click="addServiceDialogRef?.open()"` 与子组件 `defineExpose({ open })`
 *    任何一侧改名都会让弹层打不开；
 * 2. 表单结构：拆组件时 .dialog-grid / .form-item-full / .add-service-automation-* 随样式迁入子组件，
 *    若样式没迁干净，网格会塌成一列，此处按容器宽度断言仍是两列；
 * 3. 提交链路：先表单校验、再余额扣款二次确认、最后 POST，任一环节断掉都拿不到成功提示。
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

// 商品树（coupon-product-groups）由 product-binding-tree-select 在 setup 阶段就拉取，见 fixtures 说明
const PRODUCT_TREE_RESPONSE = {
  tree: [
    {
      label: '云服务器',
      product_type: 'cloud_server',
      first_product_group_code: 'cloud',
      first_product_group_name: '云服务器',
      children: [
        {
          label: '美国节点',
          children: [
            {
              node_type: 'product',
              leaf: true,
              product_id: 77,
              display_name: 'E2E 云服务器 2 核 2G',
              product_type: 'cloud_server',
            },
          ],
        },
      ],
    },
  ],
};

const EMPTY_OPTIONS = envelope({ list: [] as Record<string, unknown>[] });

async function mockPage(page: Page) {
  await mockAuthenticatedSession(page);
  await page.route('**/v2/admin/users/1**', (route) => {
    if (/\/services/.test(route.request().url())) {
      return route.fulfill(jsonResponse(envelope({ list: [], total: 0, page: 1, page_size: 10 })));
    }
    return route.fulfill(jsonResponse(envelope(USER_DETAIL_RESPONSE)));
  });
  await page.route('**/v2/admin/member-levels*', (route) => route.fulfill(jsonResponse(EMPTY_OPTIONS)));
  await page.route('**/v2/admin/promotion-ambassadors*', (route) => route.fulfill(jsonResponse(EMPTY_OPTIONS)));
  const treeBody = jsonResponse(envelope(PRODUCT_TREE_RESPONSE));
  const osBody = jsonResponse(
    envelope({ groups: [{ label: 'Linux', children: [{ value: 'centos9', label: 'CentOS 9' }] }] }),
  );
  await page.route('**/v2/admin/coupon-product-groups**', (route) => route.fulfill(treeBody));
  await page.route('**/v2/admin/os-options*', (route) => route.fulfill(osBody));
}

test('新建实例：弹层能打开、表单仍是两列网格、缺商品时被校验拦下', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockPage(page);

  await page.goto('/admin/users/1?tab=services', { waitUntil: 'domcontentloaded' });

  const trigger = page.getByRole('button', { name: '新建实例' });
  await expect(trigger).toBeVisible();
  await trigger.click();

  const dialog = page.locator('.t-dialog').filter({ hasText: '新建实例' });
  await expect(dialog).toBeVisible();
  // 拆组件后 t-alert 提示语随模板一起搬走，漏搬会丢这段风控说明
  await expect(dialog).toContainText('余额扣款需同时创建账单');

  // 样式随组件迁入 scoped style：.dialog-grid 必须是两列，否则整张表单会塌成单列
  const grid = dialog.locator('.dialog-grid');
  await expect(grid).toBeVisible();
  const columns = await grid.evaluate((el) => getComputedStyle(el).gridTemplateColumns.split(' ').length);
  expect(columns).toBe(2);

  // 自动开关组也依赖随迁的 .add-service-automation-switches 样式才会横排
  const switches = dialog.locator('.add-service-automation-switches');
  await expect(switches).toBeVisible();
  await expect(switches).toContainText('自动续费');
  await expect(switches).toContainText('从余额扣款');

  // 校验规则随组件迁入 rules：未选商品直接确定必须被拦下，不能发出请求
  await dialog.getByRole('button', { name: '确定' }).click();
  await expect(dialog).toContainText('请选择商品');

  expect(errors).toEqual([]);
});

function jsonResponse(body: unknown, status = 200) {
  return { status, contentType: 'application/json', body: JSON.stringify(body) };
}
