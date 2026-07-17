import { request } from '@/utils/request';
import { getAdminToken } from '@/app/runtime/session';

export type DatabaseTableItem = {
  name: string;
  rows: number;
  size_mb: number;
  update_time?: string | null;
};

export type DatabaseStatus = {
  database: string;
  list: DatabaseTableItem[];
  total_count: number;
  total_rows: number;
  total_size_mb: number;
};

export type DatabaseOptimizeResult = {
  id?: string;
  status?: string;
  message?: string;
  detail?: {
    optimized_count?: number;
    failed_count?: number;
    optimized_tables?: string[];
    failed_tables?: Array<{ table?: string; message?: string } | string>;
  };
};

function resolveApiBase(): string {
  const env = import.meta.env.MODE || 'development';
  const rawApiUrl = String(import.meta.env.VITE_API_BASE_URL || '');
  const usesLocalProxy = import.meta.env.VITE_IS_REQUEST_PROXY === 'true' && rawApiUrl.startsWith('/');
  if (env === 'mock' || usesLocalProxy) {
    return '/api';
  }
  return rawApiUrl.replace(/\/$/, '');
}

export const databaseApi = {
  status: () => request.get<DatabaseStatus>({ url: '/v2/admin/database/status' }),
  optimize: (data?: { tables?: string[] }) =>
    request.post<DatabaseOptimizeResult>({ url: '/v2/admin/database/optimizations', data: data || {} }),
  exportBackup: async (): Promise<void> => {
    const token = getAdminToken();
    const response = await fetch(`${resolveApiBase()}/v2/admin/database/backups`, {
      method: 'POST',
      headers: {
        Accept: 'application/sql, application/json',
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
    });

    const contentType = String(response.headers.get('content-type') || '').toLowerCase();
    if (!response.ok || contentType.includes('application/json')) {
      let message = `导出备份失败（HTTP ${response.status}）`;
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
    const matched = disposition.match(/filename\*?=(?:UTF-8''|")?([^\";]+)/i);
    const filename = matched?.[1] ? decodeURIComponent(matched[1].replace(/"/g, '')) : `backup_${Date.now()}.sql`;

    const objectUrl = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = objectUrl;
    anchor.download = filename;
    document.body.appendChild(anchor);
    anchor.click();
    document.body.removeChild(anchor);
    URL.revokeObjectURL(objectUrl);
  },
};
