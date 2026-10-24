import type { DatabaseStatus } from '../src/api/admin/database';
import { collectConsoleErrors, envelope, expect, mockAuthenticatedSession, test } from './fixtures';

/**
 * 管理端数据库页：备份队列化后的前端行为。
 *
 * 覆盖两处只有真实页面才能验证的接线：
 * - 轮询接口返回 completed 后自动触发下载并清掉本地 taskId；
 * - 刷新页面（本地仍留着未收敛 taskId）时自动续轮询，不需要用户再点一次导出。
 *
 * 覆盖不到的边界（在计划文档的风险里已注明）：服务端 409 在途去重、600 秒超时清理，
 * 属于后端队列语义，由 backend 的 Feature 测试与真实 mysqldump 验证，不在 e2e 里伪造。
 */

const STATUS_RESPONSE: DatabaseStatus = {
  database: 'caiwu_e2e',
  list: [
    { name: 'orders', rows: 120, size_mb: 1.5, update_time: '2026-10-23 10:00:00' },
    { name: 'users', rows: 30, size_mb: 0.25, update_time: '2026-10-23 09:00:00' },
  ],
  total_count: 2,
  total_rows: 150,
  total_size_mb: 1.75,
  optimization: {
    candidate_count: 0,
    estimated_reclaimable_mb: 0,
    candidates: [],
    cooldown_remaining_seconds: 0,
    last_optimized_at: null,
  },
};

const BACKUP_TASK_STORAGE_KEY = 'caiwu.admin.database.backupTaskId';

function jsonResponse(body: unknown, status = 200) {
  return { status, contentType: 'application/json', body: JSON.stringify(body) };
}

/** 轮询接口的异常形态：网络失败与任务过期（业务码 40400）要区别对待。 */
type PollFailure = 'none' | 'network-error' | 'expired';

/**
 * 拦截数据库页会打到的全部接口。
 *
 * 备份任务接口的状态由调用方给定：传 'completed' 即模拟导出完成，
 * 下载接口返回 SQL 正文与 content-disposition，前端据此触发浏览器下载。
 * pollFailure 用来模拟轮询失败：网络失败不该丢掉本地 taskId，任务过期才该丢。
 */
async function mockDatabaseApis(
  page: Parameters<typeof mockAuthenticatedSession>[0],
  taskStatus: string,
  pollFailure: PollFailure = 'none',
) {
  await page.route('**/v2/admin/database/**', (route) => {
    const request = route.request();
    const path = new URL(request.url()).pathname;

    if (path.endsWith('/database/status')) {
      return route.fulfill(jsonResponse(envelope(STATUS_RESPONSE)));
    }

    if (path.endsWith('/database/backups') && request.method() === 'POST') {
      return route.fulfill(
        jsonResponse(envelope({ id: 'backup-task-e2e', status: 'queued', task_id: 'backup-task-e2e' })),
      );
    }

    if (path.endsWith('/download')) {
      return route.fulfill({
        status: 200,
        contentType: 'application/sql',
        headers: { 'content-disposition': 'attachment; filename="caiwu_e2e.sql"' },
        body: '-- e2e dump\n',
      });
    }

    if (/\/database\/backups\/[^/]+$/.test(path)) {
      if (pollFailure === 'network-error') {
        // 模拟断网/请求被掐断：axios 没有 response，业务码也就无从谈起
        return route.abort('failed');
      }

      if (pollFailure === 'expired') {
        return route.fulfill(jsonResponse({ code: 40400, message: '备份任务不存在或已过期', data: null }, 404));
      }

      return route.fulfill(
        jsonResponse(
          envelope({
            status: taskStatus,
            message: taskStatus === 'completed' ? '备份完成' : '备份进行中',
            filename: taskStatus === 'completed' ? 'caiwu_e2e.sql' : null,
            size_bytes: taskStatus === 'completed' ? 11 : null,
          }),
        ),
      );
    }

    return route.fulfill(jsonResponse(envelope(null)));
  });
}

async function readStoredTaskId(page: Parameters<typeof mockAuthenticatedSession>[0]) {
  return page.evaluate((key) => window.localStorage.getItem(key), BACKUP_TASK_STORAGE_KEY);
}

test('点击导出备份后轮询到完成并清掉本地 taskId', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await mockDatabaseApis(page, 'completed');

  await page.goto('/admin/system/database', { waitUntil: 'domcontentloaded' });

  await expect(page.getByText('caiwu_e2e')).toBeVisible();
  await expect(page.locator('#main-content')).toBeVisible();

  await page.getByRole('button', { name: '导出备份' }).click();
  // 确认框正文说明「备份在后台执行」，确认后才真正投递任务
  await expect(page.getByText('备份在后台执行，完成后会自动下载，期间可以继续留在本页')).toBeVisible();
  await page.getByRole('button', { name: '确定' }).click();

  await expect(page.getByText('备份导出完成，已开始下载')).toBeVisible();
  expect(await readStoredTaskId(page)).toBeNull();
  await expect(page.getByRole('button', { name: '导出备份' })).toBeEnabled();

  expect(errors).toEqual([]);
});

test('轮询遇到网络错误时保留本地 taskId', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  // 投递成功，但第一次轮询就断网：备份还在服务端跑，入口不能丢
  await mockDatabaseApis(page, 'completed', 'network-error');

  await page.goto('/admin/system/database', { waitUntil: 'domcontentloaded' });

  await page.getByRole('button', { name: '导出备份' }).click();
  await page.getByRole('button', { name: '确定' }).click();

  await expect(page.getByText('导出数据库备份失败')).toBeVisible();
  expect(await readStoredTaskId(page)).toBe('backup-task-e2e');
  await expect(page.getByRole('button', { name: '导出备份' })).toBeEnabled();

  // 断网会让浏览器打一条资源加载失败的 console 错误，这是被测路径本身，不算 JS 异常
  expect(errors.filter((text) => !text.includes('Failed to load resource'))).toEqual([]);
});

test('轮询发现任务已过期时清掉本地 taskId', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await mockDatabaseApis(page, 'completed', 'expired');

  await page.goto('/admin/system/database', { waitUntil: 'domcontentloaded' });

  await page.getByRole('button', { name: '导出备份' }).click();
  await page.getByRole('button', { name: '确定' }).click();

  await expect(page.getByText('备份任务不存在或已过期')).toBeVisible();
  expect(await readStoredTaskId(page)).toBeNull();
  await expect(page.getByRole('button', { name: '导出备份' })).toBeEnabled();

  // 404 同样会留下一条资源加载失败的 console 错误，属于被测路径本身
  expect(errors.filter((text) => !text.includes('Failed to load resource'))).toEqual([]);
});

test('刷新页面后按本地 taskId 续轮询并自动下载', async ({ page }) => {
  const errors = collectConsoleErrors(page);
  await mockAuthenticatedSession(page);
  await mockDatabaseApis(page, 'completed');

  // 预置「上次未收敛」的任务记录，模拟用户在备份执行中刷新了页面
  await page.addInitScript(
    ([key, taskId]) => {
      window.localStorage.setItem(key, taskId);
    },
    [BACKUP_TASK_STORAGE_KEY, 'backup-task-e2e'] as const,
  );

  await page.goto('/admin/system/database', { waitUntil: 'domcontentloaded' });

  await expect(page.getByText('备份已就绪，已开始下载')).toBeVisible();
  expect(await readStoredTaskId(page)).toBeNull();
  await expect(page.getByRole('button', { name: '导出备份' })).toBeEnabled();

  expect(errors).toEqual([]);
});
