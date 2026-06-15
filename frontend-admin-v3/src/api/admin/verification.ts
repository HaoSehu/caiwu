import { request } from '@/utils/request';
import type {
  VerificationListParams,
  VerificationRecord,
  SettingItem,
  VerificationSettingsPayload,
} from './types';

export const verificationsApi = {
  list: (params: VerificationListParams) =>
    request.get<{ list?: VerificationRecord[]; total?: number; page?: number; page_size?: number }>({
      url: '/admin/verifications',
      params,
    }),
  summary: () =>
    request.get<{
      stats?: Record<string, number>;
      config?: Record<string, unknown>;
    }>({ url: '/admin/verifications/summary' }),
  detail: (id: number | string) =>
    request.get<VerificationRecord>({ url: `/admin/verifications/${id}` }),
  history: (id: number | string) =>
    request.get<{ user_name?: string; list?: VerificationRecord[] }>({
      url: `/admin/verifications/${id}/history`,
    }),
  unbind: (id: number | string, data: { reject_reason: string }) =>
    request.post({ url: `/admin/verifications/${id}/unbind`, data }),
  settings: () =>
    request.get<SettingItem[]>({ url: '/admin/settings', params: { group: 'verification' } }),
  saveSettings: (settings: VerificationSettingsPayload) =>
    request.post({ url: '/admin/settings', data: { group: 'verification', settings } }),
};
