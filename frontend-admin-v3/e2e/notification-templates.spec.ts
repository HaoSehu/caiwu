import type { Page } from '@playwright/test';

import { collectConsoleErrors, envelope, expect, mockAuthenticatedSession, test } from './fixtures';

/**
 * 消息配置统一页回归（数据源 /v2/admin/message-scenes）。
 *
 * 页面按「受众页签 × 场景行 × 渠道列」渲染；场景详情抽屉额外读
 * /v2/admin/notification-templates（模板正文）与 /v2/admin/settings（分组设置）。
 *
 * 只验证：路由可达、场景行与渠道状态渲染、抽屉关键区块无 console error。
 */
const SCENE_ROW = {
  code: 'order_paid',
  name: '订单支付成功',
  description: '订单收款确认后通知客户',
  purpose: 'notification',
  revision: 1,
  metadata: {},
  // sms 被插件能力锁定：阿里云号码认证插件只有 verify_code 能力
  channels: { email: true, sms: false, message: false },
  sendable_channels: ['email', 'message'],
  locked_channels: { sms: '当前短信插件「阿里云号码认证短信」不支持业务通知类消息' },
  enabled: true,
};

const VERIFICATION_ROW = {
  code: 'register_verification',
  name: '注册验证码',
  description: '客户注册时验证手机号或邮箱',
  purpose: 'verification',
  revision: 0,
  metadata: {},
  channels: { email: false, sms: false },
  sendable_channels: ['email', 'sms'],
  locked_channels: {},
  enabled: false,
};

const SCENES_RESPONSE = { data: [SCENE_ROW, VERIFICATION_ROW], audience: 'customer' };

const SCENE_DETAIL = {
  code: 'order_paid',
  name: '订单支付成功',
  description: '订单收款确认后通知客户',
  purpose: 'notification',
  revisions: { customer: 1 },
  metadata: {
    customer: {
      code: 'order_paid',
      name: '订单支付成功',
      description: '订单收款确认后通知客户',
      channels: ['email', 'sms', 'message', 'wechat'],
      variables: [
        { key: 'display_name', label: '显示名称' },
        { key: 'invoice_no', label: '账单号' },
      ],
    },
  },
  groups: [] as Record<string, unknown>[],
  group_templates: {},
  channel_options: {},
  recipient_options: {},
  templates: [
    { channel: 'email', code: '100016', name: '付款成功通知', is_enabled: true },
    { channel: 'sms', code: '100014', name: '订单支付提醒(客户)', is_enabled: true },
  ],
};

const EMAIL_TEMPLATE = {
  channel: 'email',
  code: '100016',
  name: '付款成功通知',
  description: '客户付款成功或管理员手动入账后发送通知',
  audience: 'user',
  subject: '【{{site_name}}】付款成功通知 #{{invoice_no}}',
  content: '<p>您好 {{display_name}}，您的付款已确认。</p>',
  is_enabled: true,
  variables: ['display_name', 'invoice_no'],
};

const JSON_HEADERS = { status: 200, contentType: 'application/json' };

// Playwright glob 的 `*` 不跨越 `/`，列表与详情必须分开注册。
const SCENES_LIST_PATH = '**/v2/admin/message-scenes?*';
const SCENE_DETAIL_PATH = '**/v2/admin/message-scenes/*';
const TEMPLATES_PATH = '**/v2/admin/notification-templates*';
const SETTINGS_PATH = '**/v2/admin/settings*';

async function mockApis(page: Page) {
  const listBody = JSON.stringify(envelope(SCENES_RESPONSE));
  const detailBody = JSON.stringify(envelope(SCENE_DETAIL));
  const templatesBody = JSON.stringify(envelope({ list: [EMAIL_TEMPLATE] }));
  const settingsBody = JSON.stringify(envelope({ list: [] }));

  await page.route(SCENES_LIST_PATH, (route) => route.fulfill({ ...JSON_HEADERS, body: listBody }));
  await page.route(SCENE_DETAIL_PATH, (route) => route.fulfill({ ...JSON_HEADERS, body: detailBody }));
  await page.route(TEMPLATES_PATH, (route) => route.fulfill({ ...JSON_HEADERS, body: templatesBody }));
  await page.route(SETTINGS_PATH, (route) => route.fulfill({ ...JSON_HEADERS, body: settingsBody }));
}

test('从插件拉取模板：调用同步接口并展示结果', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await mockApis(page);

  const pullBody = JSON.stringify(
    envelope({
      plugin_key: 'aliyun',
      applied: 3,
      purposes: {
        generic: '您的验证码为{code}。以上验证码{min}分钟内有效。',
        reset: '您正在进行重置密码操作，验证码为{code}。',
      },
    }),
  );
  const pullHeaders = { ...JSON_HEADERS, body: pullBody };

  await page.route('**/v2/admin/notification-templates/sms/verify-code-sync', (route) => route.fulfill(pullHeaders));

  await page.goto('/admin/notifications', { waitUntil: 'domcontentloaded' });
  await page.getByRole('button', { name: '从插件拉取模板' }).click();

  await expect(page.getByText('已从当前短信插件的号码认证参数配置拉取验证码模板')).toBeVisible();
  await expect(page.getByText('aliyun')).toBeVisible();
  await expect(page.getByText('登录 / 注册验证码')).toBeVisible();

  // 「知道了」必须能关闭对话框；TDesign 关闭后保留隐藏 DOM，故断言不可见而非不存在
  await page.getByRole('button', { name: '知道了' }).click();
  await expect(page.getByText('已从当前短信插件的号码认证参数配置拉取验证码模板')).toBeHidden();

  expect(errors).toEqual([]);
});

test('消息配置列表：渲染场景行与渠道状态', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await mockApis(page);

  await page.goto('/admin/notifications', { waitUntil: 'domcontentloaded' });

  await expect(page.locator('#main-content')).toBeVisible();
  await expect(page.getByRole('heading', { name: '消息配置' })).toBeVisible();
  await expect(page.getByText('发送给客户')).toBeVisible();
  await expect(page.getByText('发送给管理员')).toBeVisible();
  await expect(page.getByRole('row', { name: /订单支付成功/ })).toBeVisible();
  await expect(page.getByRole('row', { name: /注册验证码/ })).toBeVisible();
  await expect(page.getByRole('button', { name: '批量配置' })).toBeVisible();

  // 渠道状态以开关呈现，且「公众号」列已移除
  await expect(page.locator('.channel-switch .t-switch').first()).toBeVisible();
  await expect(page.getByRole('columnheader', { name: '公众号' })).toHaveCount(0);

  // 被插件能力锁定的短信渠道：显示锁定态且开关禁用
  const lockedSms = page.locator('.channel-switch.is-locked');
  await expect(lockedSms).toBeVisible();
  await expect(lockedSms.locator('.t-switch')).toHaveClass(/t-is-disabled/);

  expect(errors).toEqual([]);
});

test('测试发送：按场景模板打开对话框并提交', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await mockApis(page);

  const testSendBody = JSON.stringify(
    envelope({
      channel: 'email',
      code: '100016',
      template_name: '付款成功通知',
      status: 'success',
      total: 1,
      success_count: 1,
      failed_count: 0,
      results: [{ recipient: 'tester@example.com', status: 'success', message: '' }],
    }),
  );
  const testSendHeaders = { ...JSON_HEADERS, body: testSendBody };

  await page.route('**/v2/admin/notification-templates/test-send', (route) => route.fulfill(testSendHeaders));

  await page.goto('/admin/notifications', { waitUntil: 'domcontentloaded' });
  await page.getByRole('button', { name: '测试发送' }).first().click();

  // TDesign 对话框不暴露 dialog role，按标题与目标模板断言
  await expect(page.getByText('测试发送邮件')).toBeVisible();
  await expect(page.getByText('付款成功通知').last()).toBeVisible();

  await page.getByPlaceholder(/接收邮箱/).fill('tester@example.com');
  await page.getByRole('button', { name: '确定' }).click();
  // 结果面板比全局提示条更稳定（提示条会消失，且文案与标题重复）
  await expect(page.getByText('成功 1 条，失败 0 条')).toBeVisible();

  expect(errors).toEqual([]);
});

test('测试发送：可在邮件与短信之间切换渠道', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await mockApis(page);

  await page.goto('/admin/notifications', { waitUntil: 'domcontentloaded' });
  await page.getByRole('button', { name: '测试发送' }).first().click();

  // 默认邮件
  await expect(page.getByText('测试发送邮件')).toBeVisible();
  await expect(page.getByPlaceholder(/接收邮箱/)).toBeVisible();

  // 切到短信：标题、收件人字段与模板码都随之变化
  // TDesign 的 radio-button 真正可点的是外层 label；「短信」也出现在表头，故限定在对话框内
  await page.locator('.t-dialog').getByText('短信', { exact: true }).click();
  await expect(page.getByText('测试发送短信')).toBeVisible();
  await expect(page.getByPlaceholder(/接收手机号/)).toBeVisible();
  await expect(page.getByText(/短信模板 · 100014/)).toBeVisible();

  expect(errors).toEqual([]);
});

test('消息配置抽屉：渲染模板名称、可用变量与预览区', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await mockApis(page);

  await page.goto('/admin/notifications', { waitUntil: 'domcontentloaded' });
  await page.getByRole('button', { name: '编辑' }).first().click();

  await expect(page.getByText('可用变量')).toBeVisible();
  await expect(page.getByText('主题预览')).toBeVisible();
  await expect(page.getByTitle('邮件模板预览')).toBeAttached();

  expect(errors).toEqual([]);
});
