import { request } from '@/utils/request';

import type {
  NotificationTemplateItem,
  NotificationTemplateTestSendPayload,
  NotificationTemplateTestSendResponse,
  ScheduleOverview,
  SettingItem,
} from './types';

type V2SettingItem = SettingItem & {
  sensitive?: boolean;
  configured?: boolean;
  display_value?: string | number | boolean | null;
};

interface V2SettingListResponse {
  list?: V2SettingItem[];
  total?: number;
  page?: number;
  page_size?: number;
}

interface NotificationTemplateListResponse {
  list?: NotificationTemplateItem[];
  total?: number;
}

function normalizeV2Setting(item: V2SettingItem): SettingItem {
  return {
    ...item,
    is_secret: Boolean(item.is_secret ?? item.sensitive),
    has_value: Boolean(item.has_value ?? item.configured),
    masked_value: item.masked_value ?? item.display_value ?? '',
  };
}

// M11 未实名自动限制（专用接口 GET/PUT /v2/admin/settings/verification-restrictions）
export type VerificationRestrictionScope = 'purchase' | 'recharge' | 'renewal' | 'withdrawal' | 'service_transfer_receive';

export interface VerificationRestrictionConfig {
  enabled: boolean;
  grace_days: number;
  exempt_member_level_ids: number[];
  scope: Record<VerificationRestrictionScope, boolean>;
}

// M10 登录成功提醒（group=users，login_alert.default / login_alert.channels 两键）
export type LoginAlertChannel = 'mail' | 'inbox';

export interface LoginAlertConfig {
  defaultEnabled: boolean;
  channels: LoginAlertChannel[];
}

const LOGIN_ALERT_GROUP = 'users';
const LOGIN_ALERT_KEY_DEFAULT = 'login_alert.default';
const LOGIN_ALERT_KEY_CHANNELS = 'login_alert.channels';
const LOGIN_ALERT_ALL_CHANNELS: LoginAlertChannel[] = ['mail', 'inbox'];

function parseLoginAlertChannels(raw: unknown): LoginAlertChannel[] {
  const parsed = String(raw ?? '')
    .split(',')
    .map((item) => item.trim().toLowerCase())
    .filter((item): item is LoginAlertChannel => item === 'mail' || item === 'inbox');
  // 后端约定：空值/全部非法时回退为邮件 + 站内信
  return parsed.length > 0 ? parsed : [...LOGIN_ALERT_ALL_CHANNELS];
}

function parseLoginAlertDefault(raw: unknown): boolean {
  if (raw === undefined || raw === null || raw === '') return true;
  return raw === true || raw === 1 || raw === '1' || raw === 'true' || raw === 'on';
}

export const settingsApi = {
  list: (params?: Record<string, unknown>) =>
    request.get<V2SettingListResponse>({ url: '/v2/admin/settings', params }).then((response) => ({
      ...response,
      list: Array.isArray(response.list) ? response.list.map((item) => normalizeV2Setting(item)) : [],
    })),
  notificationTemplates: (params?: { channel?: 'email' | 'sms' }) =>
    request
      .get<NotificationTemplateListResponse>({ url: '/v2/admin/notification-templates', params })
      .then((response) => ({
        ...response,
        list: Array.isArray(response.list) ? response.list : [],
      })),
  testNotificationTemplateSend: (data: NotificationTemplateTestSendPayload) =>
    request.post<NotificationTemplateTestSendResponse>({ url: '/v2/admin/notification-templates/test-send', data }),
  save: (data: Record<string, unknown>) => request.post({ url: '/v2/admin/settings', data }),
  revealSecret: (group: string, key: string) =>
    request.get<{ group: string; key: string; value: unknown }>({
      url: `/v2/admin/settings/${encodeURIComponent(group)}/secrets/${encodeURIComponent(key)}`,
    }),
};

export const verificationRestrictionApi = {
  get: () =>
    request.get<VerificationRestrictionConfig>({ url: '/v2/admin/settings/verification-restrictions' }),
  update: (data: VerificationRestrictionConfig) =>
    request.put<Record<string, unknown>>({ url: '/v2/admin/settings/verification-restrictions', data }),
};

export const loginAlertApi = {
  get: async (): Promise<LoginAlertConfig> => {
    const response = await settingsApi.list({ group: LOGIN_ALERT_GROUP });
    const map = Object.fromEntries(response.list.map((item) => [item.key, item.value]));
    return {
      defaultEnabled: parseLoginAlertDefault(map[LOGIN_ALERT_KEY_DEFAULT]),
      channels: parseLoginAlertChannels(map[LOGIN_ALERT_KEY_CHANNELS]),
    };
  },
  save: (data: LoginAlertConfig) =>
    request.post<Record<string, unknown>>({
      url: '/v2/admin/settings',
      data: {
        group: LOGIN_ALERT_GROUP,
        settings: {
          [LOGIN_ALERT_KEY_DEFAULT]: data.defaultEnabled ? 1 : 0,
          [LOGIN_ALERT_KEY_CHANNELS]: data.channels.join(','),
        },
      },
    }),
};

export const schedulesApi = {
  overview: () => request.get<ScheduleOverview>({ url: '/v2/admin/schedules/overview' }),
  queueJobs: (params: { queue: 'pending' | 'failed'; page?: number; page_size?: number }) =>
    request.get<Record<string, unknown>>({ url: '/v2/admin/schedule-queue-jobs', params }),
  deleteQueueJob: (queue: 'pending' | 'failed', jobId: number | string) =>
    request.delete<Record<string, unknown>>({ url: `/v2/admin/schedule-queue-jobs/${queue}/${jobId}` }),
  clearQueueJobs: (queue: 'pending' | 'failed', confirmText: string) =>
    request.delete<Record<string, unknown>>({
      url: `/v2/admin/schedule-queue-jobs/${queue}`,
      data: { confirm_text: confirmText },
    }),
  trigger: (data: { task: string }) =>
    request.post<Record<string, unknown>>({ url: '/v2/admin/schedule-triggers', data }).then((response) => {
      const detail = (response as { detail?: { task?: Record<string, unknown> } }).detail;
      return (detail?.task || response) as Record<string, unknown>;
    }),
};
