import { getAdminToken } from '@/app/runtime/session';
import { request } from '@/utils/request';

export interface DatabaseTableItem {
  name: string;
  rows: number;
  size_mb: number;
  update_time?: string | null;
}

export interface DatabaseOptimizationCandidate {
  name: string;
  reclaimable_mb: number;
  fragmentation_ratio: number;
}

export interface DatabaseStatus {
  database: string;
  list: DatabaseTableItem[];
  total_count: number;
  total_rows: number;
  total_size_mb: number;
  optimization: {
    candidate_count: number;
    estimated_reclaimable_mb: number;
    candidates: DatabaseOptimizationCandidate[];
    cooldown_remaining_seconds: number;
    last_optimized_at?: string | null;
  };
}

export interface DatabaseOptimizeResult {
  id?: string;
  status?: string;
  message?: string;
  detail?: {
    optimized_count?: number;
    failed_count?: number;
    optimized_tables?: string[];
    failed_tables?: Array<{ table?: string; message?: string } | string>;
  };
}

/** 备份任务状态：queued/running 为进行中，completed 可下载，failed 已失败。 */
export interface DatabaseBackupTask {
  status: string;
  message: string;
  filename?: string | null;
  size_bytes?: number | null;
  created_at?: string | null;
  finished_at?: string | null;
}

/** 备份任务投递结果，task_id 用于轮询状态与下载。 */
export interface DatabaseBackupDispatchResult {
  id?: string;
  status?: string;
  task_id?: string;
  message?: string;
}

const DATABASE_OPTIMIZE_TIMEOUT = 5 * 60 * 1000;

/**
 * 备份轮询间隔与上限：整库导出分钟级，上限对齐后端 Job 的 900 秒超时。
 * 取 450 次 × 2 秒 = 15 分钟，后端先超时收敛为 failed，前端不必比后端更早放弃。
 */
const BACKUP_POLL_INTERVAL = 2000;
const BACKUP_POLL_MAX_ATTEMPTS = 450;

/**
 * 备份任务 ID 的本地持久化键：备份在服务端后台跑，刷新页面不该丢掉下载入口，
 * 因此把 taskId 落 localStorage，回到页面时接着轮询。
 */
const BACKUP_TASK_STORAGE_KEY = 'caiwu.admin.database.backupTaskId';

/** 后端「备份任务不存在或已过期」的业务码，见 DatabaseStatusController::showBackup。 */
const BACKUP_TASK_MISSING_CODE = 40400;

/**
 * 判断轮询错误是否表示「任务已过期/不存在」。
 *
 * 同一个业务码会从两条路径抛出，必须都认：
 * - 响应非 2xx 时走响应错误拦截器，业务码藏在 response.data.code 里；
 * - 响应 2xx 但 code !== 0 时由 transformRequestHook 抛出，code 直接挂在 Error 上。
 * 其余错误（断网、超时、5xx）不代表任务消失，不能用来清本地记录。
 */
function isBackupTaskMissing(error: unknown): boolean {
  const direct = Number((error as { code?: unknown } | null)?.code);
  if (direct === BACKUP_TASK_MISSING_CODE) {
    return true;
  }

  const fromResponse = Number((error as { response?: { data?: { code?: unknown } } } | null)?.response?.data?.code);
  return fromResponse === BACKUP_TASK_MISSING_CODE;
}

function readStoredBackupTaskId(): string {
  try {
    return String(window.localStorage.getItem(BACKUP_TASK_STORAGE_KEY) || '').trim();
  } catch {
    // 隐私模式等禁用 localStorage 的场景退化为仅当前页面轮询
    return '';
  }
}

function storeBackupTaskId(taskId: string): void {
  try {
    if (taskId === '') {
      window.localStorage.removeItem(BACKUP_TASK_STORAGE_KEY);
      return;
    }
    window.localStorage.setItem(BACKUP_TASK_STORAGE_KEY, taskId);
  } catch {
    // 存不下不影响本次轮询，只是刷新后会丢入口
  }
}

function resolveApiBase(): string {
  const apiBaseUrl = String(import.meta.env.VITE_API_BASE_URL || '')
    .trim()
    .replace(/\/+$/, '');
  if (!apiBaseUrl) {
    throw new Error('VITE_API_BASE_URL 必须配置');
  }
  return apiBaseUrl;
}

function delay(ms: number): Promise<void> {
  return new Promise((resolve) => {
    window.setTimeout(resolve, ms);
  });
}

/**
 * 用任务 ID 换回备份文件并触发浏览器下载。
 *
 * 下载必须带 Bearer token，无法用 <a href> 直链，因此仍走裸 fetch；
 * 文件名由服务端 content-disposition 给出，前端不拼路径。
 */
async function downloadBackupFile(taskId: string): Promise<void> {
  const token = getAdminToken();
  const response = await fetch(`${resolveApiBase()}/v2/admin/database/backups/${encodeURIComponent(taskId)}/download`, {
    method: 'GET',
    headers: {
      Accept: 'application/sql, application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
  });

  const contentType = String(response.headers.get('content-type') || '').toLowerCase();
  if (!response.ok || contentType.includes('application/json')) {
    let message = `下载备份失败（HTTP ${response.status}）`;
    try {
      const payload = await response.json();
      if (payload?.message) {
        message = String(payload.message);
      }
    } catch {
      // ignore parse errors
    }
    throw new Error(message);
  }

  const blob = await response.blob();
  const disposition = String(response.headers.get('content-disposition') || '');
  const matched = disposition.match(/filename\*?=(?:UTF-8''|")?([^";]+)/i);
  const filename = matched?.[1] ? decodeURIComponent(matched[1].replace(/"/g, '')) : `backup_${Date.now()}.sql`;

  const objectUrl = URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = objectUrl;
  anchor.download = filename;
  document.body.appendChild(anchor);
  anchor.click();
  document.body.removeChild(anchor);
  URL.revokeObjectURL(objectUrl);
}

export const databaseApi = {
  status: () => request.get<DatabaseStatus>({ url: '/v2/admin/database/status' }),
  optimize: (data?: { tables?: string[] }) =>
    request.post<DatabaseOptimizeResult>({
      url: '/v2/admin/database/optimizations',
      data: data || {},
      timeout: DATABASE_OPTIMIZE_TIMEOUT,
      retrySafeRequest: false,
      safeRetryCount: 0,
    }),
  /**
   * 提交备份任务（立即返回，不阻塞请求），返回任务 ID。
   */
  createBackupTask: () =>
    request.post<DatabaseBackupDispatchResult>({
      url: '/v2/admin/database/backups',
      retrySafeRequest: false,
      safeRetryCount: 0,
    }),
  /** 查询备份任务进度。 */
  backupTask: (taskId: string) =>
    request.get<DatabaseBackupTask>({
      url: `/v2/admin/database/backups/${encodeURIComponent(taskId)}`,
    }),
  /**
   * 提交备份任务并轮询到结束，完成后自动下载。
   *
   * 整库导出从秒级到分钟级不等，期间只轮询轻量状态接口，
   * 不再让单个 HTTP 请求挂住等 mysqldump 跑完。
   */
  exportBackup: async (): Promise<void> => {
    const dispatch = await databaseApi.createBackupTask();
    const taskId = String(dispatch?.task_id || '');
    if (!taskId) {
      throw new Error('备份任务提交失败，请稍后重试');
    }

    storeBackupTaskId(taskId);
    await databaseApi.waitBackupAndDownload(taskId);
  },
  /**
   * 轮询指定任务直到结束并自动下载。
   *
   * 抽出来供两处复用：刚提交任务后的本次等待，以及刷新页面后按 localStorage
   * 里的 taskId 续等；任务收敛（完成/失败/过期）后清掉本地记录。
   */
  waitBackupAndDownload: async (taskId: string): Promise<void> => {
    for (let attempt = 0; attempt < BACKUP_POLL_MAX_ATTEMPTS; attempt += 1) {
      let task: DatabaseBackupTask;
      try {
        task = await databaseApi.backupTask(taskId);
      } catch (error) {
        // 只有「任务已过期/不存在」才清记录：断网、超时、5xx 时备份仍在服务端跑，
        // 清掉 taskId 就丢了下载入口，用户只能重新导出一份。
        if (isBackupTaskMissing(error)) {
          storeBackupTaskId('');
        }
        throw error;
      }

      if (task.status === 'completed') {
        // 先下载再清记录：下载失败时保留 taskId，用户还能回到本页重试下载
        await downloadBackupFile(taskId);
        storeBackupTaskId('');
        return;
      }

      if (task.status === 'failed') {
        storeBackupTaskId('');
        throw new Error(task.message || '导出数据库备份失败');
      }

      await delay(BACKUP_POLL_INTERVAL);
    }

    throw new Error('备份仍在后台执行，请稍后回到本页重试下载');
  },
  /** 读取上次未完成的备份任务 ID（刷新页面后续等用）。 */
  pendingBackupTaskId: (): string => readStoredBackupTaskId(),
};
