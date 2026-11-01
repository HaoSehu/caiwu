import { Buffer } from 'node:buffer';

import type { Page, Route } from '@playwright/test';

import { collectConsoleErrors, envelope, expect, mockAuthenticatedSession, test } from './fixtures';

/**
 * 媒体库存储区能力回归（T6）。
 *
 * 覆盖点：
 * 1. 存储区标签展示（本地默认色、远程蓝色系、未知 slug 回退原值不报错）；
 * 2. 上传请求携带 driver 参数（FormData）；
 * 3. 单个转存：对话框目标选择 → POST media-migrations → 轮询 → 结果反馈；
 * 4. 批量转存：多选 + 批量条入口；
 * 5. 同目标转存：后端 skipped 语义在结果反馈中明确展示「已在目标存储区」；
 * 6. 部分失败：failures 明细展示每个文件的阶段与原因。
 *
 * 全部接口走 page.route mock，不依赖后端。
 */
const JSON_HEADERS = { status: 200, contentType: 'application/json' };

const DRIVERS = {
  drivers: [
    { slug: 'local', name: '本地存储' },
    { slug: 'ftp', name: 'FTP 存储' },
    { slug: 'qiniu', name: '七牛云存储' },
  ],
};

/** 一行本地、一行远程（ftp）、一行渠道禁用后的残留（kodo 不在 drivers 列表） */
function mediaList() {
  return {
    list: [
      {
        id: 101,
        filename: 'local-banner.jpg',
        url: '/media/2026/10/local-banner.jpg',
        path: 'media/2026/10/local-banner.jpg',
        mime_type: 'image/jpeg',
        type: 'image',
        size: 10240,
        group: 'content',
        created_at: '2026-10-28 09:00:00',
        width: 800,
        height: 600,
        disk: 'local',
      },
      {
        id: 102,
        filename: 'ftp-photo.png',
        url: 'https://ftp.example.com/media/2026/10/ftp-photo.png',
        path: 'media/2026/10/ftp-photo.png',
        mime_type: 'image/png',
        type: 'image',
        size: 20480,
        group: 'content',
        created_at: '2026-10-29 09:00:00',
        width: 640,
        height: 480,
        disk: 'ftp',
      },
      {
        id: 103,
        filename: 'legacy-kodo.webp',
        url: 'https://kodo.example.com/media/2026/10/legacy-kodo.webp',
        path: 'media/2026/10/legacy-kodo.webp',
        mime_type: 'image/webp',
        type: 'image',
        size: 4096,
        group: 'content',
        created_at: '2026-10-30 09:00:00',
        width: 320,
        height: 240,
        disk: 'kodo',
      },
    ],
    total: 3,
    page: 1,
    page_size: 24,
  };
}

/** 1x1 透明 PNG：列表里远程盘的 <img src> 指向假外域，必须拦截，否则 ERR_NAME_NOT_RESOLVED 污染 console 断言 */
const TRACKING_PNG = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
  'base64',
);

/** 1x1 透明 PNG 兜底响应：列表里远程盘的 <img src> 指向假外域，不拦截会污染 console 断言 */
function serveTrackingImage(route: Route) {
  return route.fulfill({ status: 200, contentType: 'image/png', body: TRACKING_PNG });
}

/** 转存接口 mock：创建与轮询返回同一批次快照 */
function migrationRoute(payload: Record<string, unknown>) {
  return async (route: Route) => {
    await route.fulfill({
      ...JSON_HEADERS,
      body: JSON.stringify(envelope(payload)),
    });
  };
}

/** 批量转存部分失败快照 */
const BATCH_FAILURES = {
  id: 502,
  direction: 'to_remote',
  driver: 'qiniu',
  delete_source: true,
  status: 'completed',
  total: 2,
  done: 1,
  failed: 1,
  skipped: 0,
  failures: [{ media_file_id: 103, stage: 'upload', message: '远端写入失败：磁盘配额不足' }],
};

/** 同目标转存跳过快照 */
const BATCH_SKIPPED = {
  id: 503,
  direction: 'to_remote',
  driver: 'ftp',
  delete_source: false,
  status: 'completed',
  total: 1,
  done: 0,
  failed: 0,
  skipped: 1,
  failures: [] as Array<{ media_file_id: number; stage: string; message: string }>,
};

async function mockMediaLibrary(page: Page) {
  const fulfill = (body: unknown) => ({ ...JSON_HEADERS, body: JSON.stringify(envelope(body)) });
  await page.route('**/v2/admin/media-storage-drivers*', (route) => route.fulfill(fulfill(DRIVERS)));
  await page.route('**/v2/admin/media-files?*', (route) => route.fulfill(fulfill(mediaList())));
  await page.route('**/v2/admin/media-files', (route) => route.fulfill(fulfill(mediaList())));
  // 远程存储假域名图片（ftp.example.com / kodo.example.com）与本地 /media 路径
  await page.route('**/media/**', serveTrackingImage);
  await page.route('**://**.example.com/**', serveTrackingImage);
}

test('存储区标签：本地默认色、远程蓝色、未知 slug 回退原值', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await mockMediaLibrary(page);

  await page.goto('/admin/content/media-library', { waitUntil: 'domcontentloaded' });

  await expect(page.getByRole('cell', { name: '本地存储', exact: true })).toBeVisible();
  await expect(page.getByRole('cell', { name: 'FTP 存储', exact: true })).toBeVisible();
  // kodo 不在 drivers 列表（渠道禁用后的残留数据）：回退显示 slug 原值，不出现崩溃
  await expect(page.getByRole('cell', { name: 'kodo', exact: true })).toBeVisible();

  // 远程行（FTP）标签带 primary 主题色 class，本地行不带
  const ftpTag = page.getByRole('cell', { name: 'FTP 存储', exact: true }).locator('.t-tag');
  await expect(ftpTag).toHaveClass(/t-tag--primary/);
  const localTag = page.getByRole('cell', { name: '本地存储', exact: true }).locator('.t-tag');
  await expect(localTag).not.toHaveClass(/t-tag--primary/);

  expect(errors).toEqual([]);
});

test('上传请求携带 driver 参数', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await mockMediaLibrary(page);

  const uploads: Array<{ driver: string | null; group: string | null }> = [];
  await page.route('**/v2/admin/media-files', async (route) => {
    if (route.request().method() === 'POST') {
      const body = route.request().postData();
      uploads.push({
        driver: body ? (/name="driver"\r\n\r\n([^\r\n]+)/.exec(body)?.[1] ?? null) : null,
        group: body ? (/name="group"\r\n\r\n([^\r\n]+)/.exec(body)?.[1] ?? null) : null,
      });
      await route.fulfill({ ...JSON_HEADERS, body: JSON.stringify(envelope({ id: 999 })) });
      return;
    }
    await route.fulfill({ ...JSON_HEADERS, body: JSON.stringify(envelope(mediaList())) });
  });

  await page.goto('/admin/content/media-library', { waitUntil: 'domcontentloaded' });

  // 两步制：点「上传」打开对话框 → 第一步必选存储区（未选时文件入口不可用）
  await page.getByRole('button', { name: '上传' }).first().click();
  const dialog = page.locator('.t-dialog').filter({ hasText: '上传媒体' }).first();
  await expect(dialog).toBeVisible();

  const pickButton = dialog.getByRole('button', { name: /选择文件/ });
  await expect(pickButton).toBeDisabled();
  await expect(dialog.getByText('请先选择存储区，再选择要上传的文件。')).toBeVisible();

  // 第一步：选择 FTP 存储区（下拉是 attach 到 body 的浮层）
  await dialog.locator('.media-upload-form__row').first().locator('.t-select').click();
  const uploadOption = page.locator('.t-select__dropdown .t-select-option').filter({ hasText: 'FTP 存储' }).first();
  await expect(uploadOption).toBeVisible();
  await uploadOption.click();
  await expect(pickButton).toBeEnabled();

  // 第二步：选文件（隐藏 input 直接 setInputFiles），再提交
  await page.locator('input.media-upload-input').setInputFiles({
    name: 'e2e-upload.png',
    mimeType: 'image/png',
    buffer: Buffer.from('fake-png-bytes'),
  });
  await dialog.getByRole('button', { name: '开始上传' }).click();

  await expect(page.getByText(/媒体上传成功/)).toBeVisible();
  expect(uploads).toHaveLength(1);
  expect(uploads[0].driver).toBe('ftp');
  expect(uploads[0].group).toBe('content');

  expect(errors).toEqual([]);
});

test('单个转存：目标选择 → 提交 → 轮询 → 结果反馈', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await mockMediaLibrary(page);

  // 迁移创建返回 running，首次轮询后 completed
  let pollCount = 0;
  const created: Array<Record<string, unknown>> = [];
  await page.route('**/v2/admin/media-migrations', async (route) => {
    if (route.request().method() === 'POST') {
      created.push(route.request().postDataJSON());
      await route.fulfill({
        ...JSON_HEADERS,
        body: JSON.stringify(
          envelope({
            id: 501,
            direction: 'to_remote',
            driver: 'ftp',
            delete_source: false,
            status: 'running',
            total: 1,
            done: 0,
            failed: 0,
            skipped: 0,
            failures: [],
          }),
        ),
      });
      return;
    }
    await route.fulfill({ ...JSON_HEADERS, body: JSON.stringify(envelope({})) });
  });
  await page.route('**/v2/admin/media-migrations/*', (route) => {
    pollCount += 1;
    return route.fulfill({
      ...JSON_HEADERS,
      body: JSON.stringify(
        envelope({
          id: 501,
          direction: 'to_remote',
          driver: 'ftp',
          delete_source: false,
          status: pollCount >= 2 ? 'completed' : 'running',
          total: 1,
          done: pollCount >= 2 ? 1 : 0,
          failed: 0,
          skipped: 0,
          failures: [],
          finished_at: '2026-11-01 12:00:00',
        }),
      ),
    });
  });

  await page.goto('/admin/content/media-library', { waitUntil: 'domcontentloaded' });

  // 第一行（本地）单个转存
  await page.locator('.t-table tbody tr').first().getByRole('button', { name: '转存' }).click();

  const dialog = page.locator('.t-dialog').filter({ hasText: '媒体转存' });
  await expect(dialog).toBeVisible();
  await expect(dialog.getByText('将 1 个媒体文件转存到指定存储区。')).toBeVisible();

  // 目标必选：未选择时提交按钮禁用
  await expect(dialog.getByRole('button', { name: '开始转存' })).toBeDisabled();

  // 选择 FTP 存储（下拉浮层 attach 到 body）
  await dialog.locator('.media-migrate-form__control').click();
  const singleOption = page.locator('.t-select__dropdown .t-select-option').filter({ hasText: 'FTP 存储' }).first();
  await expect(singleOption).toBeVisible();
  await singleOption.click();

  // 提交
  await dialog.getByRole('button', { name: '开始转存' }).click();

  // 进度对话框出现（轮询期间）
  await expect(page.locator('.t-dialog').filter({ hasText: '转存进行中' })).toBeVisible();

  // 结果反馈
  const result = page.locator('.t-dialog').filter({ hasText: '转存结果' });
  await expect(result).toBeVisible({ timeout: 15000 });
  await expect(result.getByText('转存完成。')).toBeVisible();
  await expect(result.getByText('共 1 个，成功 1 个，失败 0 个，跳过 0 个。')).toBeVisible();

  // 提交参数校验
  expect(created).toHaveLength(1);
  expect(created[0]).toMatchObject({
    direction: 'to_remote',
    driver: 'ftp',
    scope: 'ids',
    ids: [101],
    delete_source: false,
  });

  expect(errors).toEqual([]);
});

test('批量转存：多选 + 批量条入口 + 部分失败明细', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await mockMediaLibrary(page);

  await page.route('**/v2/admin/media-migrations', migrationRoute(BATCH_FAILURES));
  await page.route('**/v2/admin/media-migrations/*', migrationRoute(BATCH_FAILURES));

  await page.goto('/admin/content/media-library', { waitUntil: 'domcontentloaded' });

  // 多选前两行
  await page.locator('.t-table tbody tr').nth(0).locator('.t-checkbox').click();
  await page.locator('.t-table tbody tr').nth(1).locator('.t-checkbox').click();

  // 批量条出现
  await expect(page.getByText('已选 2 个媒体文件')).toBeVisible();
  await page.getByRole('button', { name: '批量转存' }).click();

  const dialog = page.locator('.t-dialog').filter({ hasText: '媒体转存' });
  await expect(dialog).toBeVisible();
  await expect(dialog.getByText('将 2 个媒体文件转存到指定存储区。')).toBeVisible();

  // 选择七牛云存储 + 打开删除源文件开关
  await dialog.locator('.media-migrate-form__control').click();
  const batchOption = page.locator('.t-select__dropdown .t-select-option').filter({ hasText: '七牛云存储' }).first();
  await expect(batchOption).toBeVisible();
  await batchOption.click();
  await dialog.locator('.t-switch').click();

  await dialog.getByRole('button', { name: '开始转存' }).click();

  // 部分失败结果：统计 + 失败明细（阶段与原因）
  const result = page.locator('.t-dialog').filter({ hasText: '转存结果' });
  await expect(result).toBeVisible();
  await expect(result.getByText('转存结束，存在失败项。')).toBeVisible();
  await expect(result.getByText('共 2 个，成功 1 个，失败 1 个，跳过 0 个。')).toBeVisible();
  await expect(result.getByText('#103（upload）：远端写入失败：磁盘配额不足')).toBeVisible();
  await expect(result.getByText('可稍后对失败文件重新发起转存。')).toBeVisible();

  expect(errors).toEqual([]);
});

test('同目标转存：skipped 明确展示「已在目标存储区」', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await mockMediaLibrary(page);

  // ftp 文件再转存到 ftp → 后端返回 skipped=1
  await page.route('**/v2/admin/media-migrations', migrationRoute(BATCH_SKIPPED));
  await page.route('**/v2/admin/media-migrations/*', migrationRoute(BATCH_SKIPPED));

  await page.goto('/admin/content/media-library', { waitUntil: 'domcontentloaded' });

  // 第二行（ftp）转存到 ftp（同目标）
  await page.locator('.t-table tbody tr').nth(1).getByRole('button', { name: '转存' }).click();

  const dialog = page.locator('.t-dialog').filter({ hasText: '媒体转存' });
  await expect(dialog).toBeVisible();

  await dialog.locator('.media-migrate-form__control').click();
  const sameOption = page.locator('.t-select__dropdown .t-select-option').filter({ hasText: 'FTP 存储' }).first();
  await expect(sameOption).toBeVisible();
  await sameOption.click();
  await dialog.getByRole('button', { name: '开始转存' }).click();

  const result = page.locator('.t-dialog').filter({ hasText: '转存结果' });
  await expect(result).toBeVisible();
  await expect(result.getByText('共 1 个，成功 0 个，失败 0 个，跳过 1 个。')).toBeVisible();
  // skipped 语义明确展示：已在目标存储区
  await expect(result.getByText('跳过的文件已在目标存储区，无需重复转存。')).toBeVisible();

  expect(errors).toEqual([]);
});

/** 上传 POST 的 FormData 字段抽取（multipart 原文解析） */
function uploadFormField(body: string | null, field: string): string | null {
  if (!body) return null;
  const match = new RegExp(`name="${field}"\\r\\n\\r\\n([^\\r\\n]+)`).exec(body);
  return match?.[1] ?? null;
}

test('上传两步制：未选存储区时文件入口不可用、提交禁用', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await mockMediaLibrary(page);

  await page.goto('/admin/content/media-library', { waitUntil: 'domcontentloaded' });
  await page.getByRole('button', { name: '上传' }).first().click();

  const dialog = page.locator('.t-dialog').filter({ hasText: '上传媒体' }).first();
  await expect(dialog).toBeVisible();
  // 第一步未完成前：文件入口禁用、提交禁用、提示可见
  await expect(dialog.getByRole('button', { name: /选择文件/ })).toBeDisabled();
  await expect(dialog.getByRole('button', { name: '开始上传' })).toBeDisabled();
  await expect(dialog.getByText('请先选择存储区，再选择要上传的文件。')).toBeVisible();
  // 未选存储区时即使误触文件选择也打不开系统文件框（事件被拦截，不会产生上传请求）
  await expect(dialog.getByRole('button', { name: /选择文件/ })).toBeDisabled();
  expect(errors).toEqual([]);
});

test('批量上传部分失败：整体计数 + 每文件终态 + 失败明细 + 重试只重投失败项', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await mockMediaLibrary(page);

  // 第 2 个文件失败、其余成功（按上传顺序计数）
  const uploads: Array<{ driver: string | null; name: string | null }> = [];
  let attempt = 0;
  await page.route('**/v2/admin/media-files', async (route) => {
    if (route.request().method() !== 'POST') {
      await route.fulfill({ ...JSON_HEADERS, body: JSON.stringify(envelope(mediaList())) });
      return;
    }
    attempt += 1;
    const body = route.request().postData();
    uploads.push({ driver: uploadFormField(body, 'driver'), name: uploadFormField(body, 'file') });
    // 首次的第 2 次请求失败；重试时全部成功
    if (attempt === 2) {
      await route.fulfill({
        ...JSON_HEADERS,
        status: 500,
        body: JSON.stringify({ code: 50000, message: '远端写入失败：连接超时', data: null }),
      });
      return;
    }
    await route.fulfill({ ...JSON_HEADERS, body: JSON.stringify(envelope({ id: 900 + attempt })) });
  });

  await page.goto('/admin/content/media-library', { waitUntil: 'domcontentloaded' });
  await page.getByRole('button', { name: '上传' }).first().click();
  const dialog = page.locator('.t-dialog').filter({ hasText: '上传媒体' }).first();

  await dialog.locator('.media-upload-form__row').first().locator('.t-select').click();
  await page.locator('.t-select__dropdown .t-select-option').filter({ hasText: '七牛云存储' }).first().click();

  await page.locator('input.media-upload-input').setInputFiles([
    { name: 'a.png', mimeType: 'image/png', buffer: Buffer.from('a') },
    { name: 'b.png', mimeType: 'image/png', buffer: Buffer.from('b') },
    { name: 'c.png', mimeType: 'image/png', buffer: Buffer.from('c') },
  ]);
  await expect(dialog.getByText('共 3 个，已结束 0 个')).toBeVisible();

  await dialog.getByRole('button', { name: '开始上传' }).click();

  // 结果反馈：成功 2 个、失败 1 个
  await expect(page.getByText('上传结束：成功 2 个，失败 1 个')).toBeVisible();
  // 每文件终态可分辨（3 个文件清单项 + 失败项标红展示错误信息）
  await expect(dialog.locator('.media-upload-files__item')).toHaveCount(3);
  await expect(dialog.locator('.media-upload-files__item.is-failed')).toHaveCount(1);
  await expect(dialog.getByText('远端写入失败：连接超时')).toBeVisible();
  await expect(dialog.getByText('共 3 个，已结束 3 个')).toBeVisible();
  // 上传中不得残留进行中文案
  await expect(dialog.locator('.media-upload-files__status.is-uploading')).toHaveCount(0);
  // 全部携带所选存储区
  expect(uploads.map((item) => item.driver)).toEqual(['qiniu', 'qiniu', 'qiniu']);

  // 重试只重投失败项
  await dialog.getByRole('button', { name: /重试失败文件（1）/ }).click();
  await expect(page.getByText(/媒体上传成功（1 个文件）/)).toBeVisible();
  await expect(dialog.locator('.media-upload-files__item.is-failed')).toHaveCount(0);
  expect(uploads).toHaveLength(4);
  expect(uploads[3].driver).toBe('qiniu');

  // 本轮故意 mock 了一次 500，浏览器必然记录该 resource 错误；其余错误必须为 0
  expect(errors.filter((text) => !text.includes('status of 500'))).toEqual([]);
});

test('上传中对话框不可关闭：关闭按钮与取消均被拦截', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await mockMediaLibrary(page);

  // 上传挂起，模拟长时间进行中
  let releaseUpload: (() => void) | null = null;
  const pending = new Promise<void>((resolve) => {
    releaseUpload = resolve;
  });
  await page.route('**/v2/admin/media-files', async (route) => {
    if (route.request().method() !== 'POST') {
      await route.fulfill({ ...JSON_HEADERS, body: JSON.stringify(envelope(mediaList())) });
      return;
    }
    await pending;
    await route.fulfill({ ...JSON_HEADERS, body: JSON.stringify(envelope({ id: 777 })) });
  });

  await page.goto('/admin/content/media-library', { waitUntil: 'domcontentloaded' });
  await page.getByRole('button', { name: '上传' }).first().click();
  const dialog = page.locator('.t-dialog').filter({ hasText: '上传媒体' }).first();
  await dialog.locator('.media-upload-form__row').first().locator('.t-select').click();
  await page.locator('.t-select__dropdown .t-select-option').filter({ hasText: 'FTP 存储' }).first().click();
  await page.locator('input.media-upload-input').setInputFiles({
    name: 'slow.png',
    mimeType: 'image/png',
    buffer: Buffer.from('slow'),
  });
  await dialog.getByRole('button', { name: '开始上传' }).click();

  // 上传中：状态为「上传中」、进度条可见、主按钮文案为「上传中…」、取消禁用、无关闭按钮
  await expect(dialog.locator('.media-upload-files__status.is-uploading')).toHaveText('上传中');
  await expect(dialog.locator('.media-upload-files__item .t-progress')).toHaveCount(1);
  await expect(dialog.getByRole('button', { name: '上传中…' })).toBeDisabled();
  await expect(dialog.getByRole('button', { name: '取消', exact: true })).toBeDisabled();
  await expect(dialog.locator('.t-dialog__close')).toHaveCount(0);
  // 整体进度只统计已结束文件：上传中为 0/1
  await expect(dialog.getByText('共 1 个，已结束 0 个')).toBeVisible();
  // 按 Esc 也不关闭
  await page.keyboard.press('Escape');
  await expect(dialog).toBeVisible();

  releaseUpload?.();
  await expect(page.getByText(/媒体上传成功（1 个文件）/)).toBeVisible();
  // 结束后不得残留「上传中」状态与进度条（禁止「已完成仍显示进行中」）
  await expect(dialog.locator('.media-upload-files__status.is-uploading')).toHaveCount(0);
  await expect(dialog.locator('.media-upload-files__item .t-progress')).toHaveCount(0);
  await expect(dialog.locator('.media-upload-files__status.is-success')).toHaveText('已上传');
  await expect(dialog.getByText('共 1 个，已结束 1 个')).toBeVisible();
  expect(errors).toEqual([]);
});
