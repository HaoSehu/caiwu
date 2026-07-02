import { request } from '@/utils/request';

export type IntegrationPluginDomain = 'verification' | 'payment' | 'mail' | 'sms' | 'upstream';

export interface IntegrationPluginConfigSchema {
  key: string;
  label?: string;
  title?: string;
  type?: string;
  required?: boolean;
  secret?: boolean;
  options?: Record<string, string> | Array<{ label?: string; value?: string | number | boolean }>;
  default?: unknown;
  value?: unknown;
  placeholder?: string;
  description?: string;
  content?: string;
  theme?: 'success' | 'info' | 'warning' | 'error';
  width?: 'full' | 'half';
  disabled?: boolean;
  visible?: boolean;
  min?: number;
  max?: number;
  step?: number;
  rows?: number;
  visible_when?: {
    field?: string;
    operator?: 'eq' | 'neq' | 'in' | 'not_in';
    value?: unknown;
  };
}

export interface IntegrationPluginRecord {
  id?: number | string | null;
  domain: IntegrationPluginDomain;
  slug: string;
  key: string;
  name: string;
  version?: string;
  entry_class?: string;
  provider_class?: string | null;
  capabilities?: string[];
  config_schema?: IntegrationPluginConfigSchema[];
  base_path?: string;
  is_installed?: boolean;
  is_enabled?: boolean;
  status?: number;
  installed_at?: string | null;
  updated_at?: string | null;
  config?: Record<string, unknown>;
  has_secret_values?: Record<string, boolean>;
  secret_previews?: Record<string, IntegrationPluginSecretPreview>;
}

export interface IntegrationPluginSecretPreview {
  type?: string;
  configured?: boolean;
  count?: number;
  items?: Array<Record<string, unknown>>;
}

export interface IntegrationPluginListResponse {
  list?: IntegrationPluginRecord[];
  total?: number;
  page?: number;
  page_size?: number;
}

export const pluginsApi = {
  list: (params?: { domain?: IntegrationPluginDomain | '' }) =>
    request.get<IntegrationPluginListResponse>({ url: '/admin/integration-plugins', params }),
  scan: (params?: { domain?: IntegrationPluginDomain | '' }) =>
    request.post<IntegrationPluginListResponse>({ url: '/admin/integration-plugins/scan', data: params || {} }),
  install: (data: { domain: IntegrationPluginDomain; slug: string }) =>
    request.post<IntegrationPluginRecord>({ url: '/admin/integration-plugins/install', data }),
  detail: (id: number | string) => request.get<IntegrationPluginRecord>({ url: `/admin/integration-plugins/${id}` }),
  updateConfig: (id: number | string, config: Record<string, unknown>) =>
    request.put<IntegrationPluginRecord>({ url: `/admin/integration-plugins/${id}/config`, data: { config } }),
  enable: (id: number | string) => request.post<IntegrationPluginRecord>({ url: `/admin/integration-plugins/${id}/enable` }),
  disable: (id: number | string) =>
    request.post<IntegrationPluginRecord>({ url: `/admin/integration-plugins/${id}/disable` }),
  remove: (id: number | string) => request.delete({ url: `/admin/integration-plugins/${id}` }),
  healthCheck: (id: number | string) =>
    request.post<Record<string, unknown>>({ url: `/admin/integration-plugins/${id}/health-check` }),
  testEmail: (id: number | string, data: { account_index: number; to: string; subject: string; body?: string }) =>
    request.post<Record<string, unknown>>({ url: `/admin/integration-plugins/${id}/test-email`, data }),
  testSms: (id: number | string, data: { phone: string }) =>
    request.post<Record<string, unknown>>({ url: `/admin/integration-plugins/${id}/test-sms`, data }),
};
