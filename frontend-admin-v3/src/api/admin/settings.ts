import { request } from '@/utils/request';
import type { SettingItem, ScheduleOverview } from './types';

export const settingsApi = {
  list: (params?: Record<string, unknown>) =>
    request.get<SettingItem[] | Record<string, unknown>>({ url: '/admin/settings', params }),
  save: (data: Record<string, unknown>) => request.post({ url: '/admin/settings', data }),
};

export const schedulesApi = {
  overview: () => request.get<ScheduleOverview>({ url: '/admin/schedules/overview' }),
  trigger: (data: { task: string }) =>
    request.post<Record<string, unknown>>({ url: '/admin/schedules/trigger', data }),
};
