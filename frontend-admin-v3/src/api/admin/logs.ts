import { request } from '@/utils/request';
import type { LogListParams, LaravelPagination, LogCleanupPayload } from './types';

export const logsApi = {
  sms: (params: LogListParams) => request.get<LaravelPagination>({ url: '/admin/logs/sms', params }),
  smsSummary: (params: LogListParams) =>
    request.get<Record<string, unknown>>({ url: '/admin/logs/sms/summary', params }),
  email: (params: LogListParams) =>
    request.get<LaravelPagination>({ url: '/admin/logs/email', params }),
  emailSummary: (params: LogListParams) =>
    request.get<Record<string, unknown>>({ url: '/admin/logs/email/summary', params }),
  api: (params: LogListParams) => request.get<LaravelPagination>({ url: '/admin/logs/api', params }),
  tasks: (params: LogListParams) =>
    request.get<LaravelPagination>({ url: '/admin/logs/tasks', params }),
  tasksSummary: (params: LogListParams) =>
    request.get<Record<string, unknown>>({ url: '/admin/logs/tasks/summary', params }),
  system: (params: LogListParams) =>
    request.get<LaravelPagination>({ url: '/admin/logs/system', params }),
  systemSummary: (params: LogListParams) =>
    request.get<Record<string, unknown>>({ url: '/admin/logs/system/summary', params }),
  runtime: (params: LogListParams) =>
    request.get<LaravelPagination>({ url: '/admin/logs/runtime', params }),
  runtimeSummary: (params: LogListParams) =>
    request.get<Record<string, unknown>>({ url: '/admin/logs/runtime/summary', params }),
  adminLogins: (params: LogListParams) =>
    request.get<LaravelPagination>({ url: '/admin/logs/admin-logins', params }),
  gateway: (params: LogListParams) =>
    request.get<LaravelPagination>({ url: '/admin/logs/gateway', params }),
  activity: (params: LogListParams) =>
    request.get<LaravelPagination>({ url: '/admin/logs/activity', params }),
  cleanupOverview: () =>
    request.get<Record<string, unknown>>({ url: '/admin/logs/cleanup/overview' }),
  cleanup: (data: LogCleanupPayload) =>
    request.post<Record<string, unknown>>({ url: '/admin/logs/cleanup', data }),
};
