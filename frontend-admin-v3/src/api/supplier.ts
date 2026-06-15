import { request } from '@/utils/request';

export interface SupplierListParams {
  status?: number | string;
  page?: number;
  page_size?: number;
  [key: string]: unknown;
}

export interface SupplierRecord {
  id: number | string;
  name?: string;
  interface_type?: string;
  interface_type_label?: string;
  api_url?: string;
  api_username?: string;
  has_api_url?: boolean;
  has_api_key?: boolean;
  remote_balance?: number | string;
  remote_balance_status?: string;
  status?: number | string;
  updated_at?: string;
  [key: string]: unknown;
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
  create: (data: Record<string, unknown>) => request.post({ url: '/admin/suppliers', data }),
  update: (id: number | string, data: Record<string, unknown>) => request.put({ url: `/admin/suppliers/${id}`, data }),
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
