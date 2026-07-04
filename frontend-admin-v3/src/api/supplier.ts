import { request } from '@/utils/request';

import type { PagedListParams } from './types';

export type SupplierProviderConfig = Record<string, unknown>;

export interface SupplierListParams extends PagedListParams {
  status?: number | string;
  [key: string]: unknown;
}

export interface SupplierFormOption {
  label: string;
  value: string | number | boolean;
}

export type SupplierFormFieldType = 'text' | 'url' | 'password' | 'select' | 'switch' | 'boolean' | 'number' | 'textarea';

export interface SupplierFormField {
  key: string;
  label: string;
  type?: SupplierFormFieldType;
  required?: boolean;
  secret?: boolean;
  placeholder?: string;
  description?: string;
  default?: unknown;
  options?: SupplierFormOption[];
}

export interface SupplierFormSchema {
  fields?: SupplierFormField[];
  help?: string;
}

export interface SupplierUpstreamBindingRecord {
  id?: number | string | null;
  provider_key?: string;
  base_url?: string;
  account_name?: string;
  status?: number | string;
  last_checked_at?: string | null;
  last_check_status?: string | null;
  last_check_error?: string | null;
  config?: SupplierProviderConfig;
  has_secret_values?: Record<string, boolean>;
}

export interface SupplierRecord {
  id: number | string;
  name?: string;
  provider_key?: string;
  provider_label?: string;
  api_url?: string;
  api_username?: string;
  has_api_url?: boolean;
  has_api_key?: boolean;
  has_provider_secret_values?: Record<string, boolean>;
  provider_config?: SupplierProviderConfig;
  upstream_binding?: SupplierUpstreamBindingRecord | null;
  remote_balance?: number | string;
  remote_balance_status?: string;
  status?: number | string;
  updated_at?: string;
  [key: string]: unknown;
}

export interface SupplierUpsertPayload {
  name: string;
  status: number;
  api_url: unknown;
  api_username: unknown;
  api_key: unknown;
  provider_config: SupplierProviderConfig;
  upstream_binding?: {
    provider_key: string;
    base_url?: unknown;
    account_name?: unknown;
  };
}

export interface SupplierSummary {
  total?: number;
  active?: number;
  inactive?: number;
  [key: string]: unknown;
}

export interface ProviderTypeRecord {
  value: string;
  label: string;
  supplier_form?: SupplierFormSchema;
  [key: string]: unknown;
}

export const supplierApi = {
  list: (params: SupplierListParams) =>
    request.get<{ list?: SupplierRecord[]; total?: number; page?: number; page_size?: number }>({
      url: '/admin/suppliers',
      params,
    }),
  summary: () => request.get<SupplierSummary>({ url: '/admin/suppliers/summary' }),
  providerTypes: () => request.get<ProviderTypeRecord[] | Record<string, string>>({ url: '/admin/suppliers/provider-types' }),
  detail: (id: number | string) => request.get<SupplierRecord>({ url: `/admin/suppliers/${id}` }),
  revealSecret: (id: number | string, key: string) =>
    request.get<{ key: string; value: unknown }>({ url: `/admin/suppliers/${id}/secret/${encodeURIComponent(key)}` }),
  create: (data: SupplierUpsertPayload) => request.post({ url: '/admin/suppliers', data }),
  update: (id: number | string, data: SupplierUpsertPayload) => request.put({ url: `/admin/suppliers/${id}`, data }),
  delete: (id: number | string) => request.delete({ url: `/admin/suppliers/${id}` }),
  toggleStatus: (id: number | string) => request.post({ url: `/admin/suppliers/${id}/toggle-status` }),
  balance: (id: number | string, config: Record<string, unknown> = {}) =>
    request.get<Record<string, unknown>>({ url: `/admin/suppliers/${id}/balance`, ...config }),
  products: (id: number | string, config: Record<string, unknown> = {}) =>
    request.get<{ list?: Record<string, unknown>[]; groups?: Record<string, unknown>[] } | Record<string, unknown>[]>({
      url: `/admin/suppliers/${id}/products`,
      ...config,
    }),
  batchConnectProducts: (id: number | string, data: Record<string, unknown>) =>
    request.post({ url: `/admin/suppliers/${id}/products/batch-connect`, data }),
  productConfigTemplate: (supplierId: number | string, productId: number | string, config: Record<string, unknown> = {}) =>
    request.get<Record<string, unknown>>({
      url: `/admin/suppliers/${supplierId}/products/${productId}/config-template`,
      ...config,
    }),
};
