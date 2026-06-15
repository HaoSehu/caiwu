import { request } from '@/utils/request';

export interface UserListParams {
  keyword?: string;
  user_id?: number | string;
  status?: number | string;
  page?: number;
  page_size?: number;
}

export interface PageParams {
  page?: number;
  page_size?: number;
  [key: string]: unknown;
}

export interface AdminUser {
  id: number | string;
  phone?: string;
  email?: string;
  nickname?: string;
  display_name?: string;
  company?: string;
  qq?: string;
  admin_note?: string;
  real_name?: string;
  id_card_masked?: string;
  member_level?: Record<string, unknown> | null;
  referrer_user_id?: number | string | null;
  last_login_at?: string;
  last_login_ip?: string;
  balance?: number | string;
  opened_product_count?: number | string;
  status?: number | string;
  verification_status?: number | string;
  is_verified?: number | boolean;
  created_at?: string;
}

export interface UserCreatePayload {
  email?: string;
  nickname?: string;
  phone?: string;
  password?: string;
}

export interface UserRechargePayload {
  amount: number;
  remark: string;
}

export interface UserUpdatePayload {
  nickname?: string;
  phone?: string;
  password?: string;
  status?: number | string;
}

export interface UserDetailResponse {
  user?: AdminUser;
  stats?: Record<string, number | string>;
  referral?: Record<string, unknown> | null;
}

export interface UserServicePayload {
  product_id: number;
  billing_cycle: string;
  status: number | string;
  name?: string;
  amount: number;
  auto_renew?: number;
  upstream_host_id?: number | null;
  upstream_status?: string;
  os?: string;
  remark?: string;
}

export interface ServiceMetaPayload {
  supplier_id?: number | null;
  upstream_host_id?: number | null;
  amount?: number;
  locked_pricing?: Record<string, { enabled?: boolean; manual_amount?: number | null }>;
  clear_locked_pricing?: boolean;
  service_name?: string;
}

export interface RefundPayload {
  refund_method: 'balance' | 'original';
  amount?: number | string;
  remark: string;
}

export const userApi = {
  list: (params: UserListParams) =>
    request.get<{ list?: AdminUser[]; total?: number; page?: number; page_size?: number }>({
      url: '/admin/users',
      params,
    }),
  detail: (id: number | string) => request.get<UserDetailResponse>({ url: `/admin/users/${id}` }),
  create: (data: UserCreatePayload) => request.post({ url: '/admin/users', data }),
  update: (id: number | string, data: UserUpdatePayload) => request.put({ url: `/admin/users/${id}`, data }),
  delete: (id: number | string) => request.delete({ url: `/admin/users/${id}` }),
  toggleStatus: (id: number | string) => request.post({ url: `/admin/users/${id}/toggle-status` }),
  recharge: (id: number | string, data: UserRechargePayload) =>
    request.post({ url: `/admin/users/${id}/recharge`, data }),
  loginAs: (id: number | string) => request.post<{ login_code?: string; redirect_url?: string }>({ url: `/admin/users/${id}/login-as` }),
  services: (id: number | string, params: PageParams) =>
    request.get<{ list?: Record<string, unknown>[]; total?: number; page?: number; page_size?: number }>({
      url: `/admin/users/${id}/services`,
      params,
    }),
  storeService: (id: number | string, data: UserServicePayload) =>
    request.post({ url: `/admin/users/${id}/services`, data }),
  invoices: (id: number | string, params: PageParams) =>
    request.get<{ list?: Record<string, unknown>[]; total?: number; page?: number; page_size?: number }>({
      url: `/admin/users/${id}/invoices`,
      params,
    }),
  invoiceDetail: (id: number | string, invoiceId: number | string) =>
    request.get<Record<string, unknown>>({ url: `/admin/users/${id}/invoices/${invoiceId}` }),
  refundInvoice: (id: number | string, invoiceId: number | string, data: RefundPayload) =>
    request.post({ url: `/admin/users/${id}/invoices/${invoiceId}/refund`, data }),
  balanceLogs: (id: number | string, params: PageParams) =>
    request.get<{ list?: Record<string, unknown>[]; total?: number; page?: number; page_size?: number; summary?: unknown }>({
      url: `/admin/users/${id}/balance-logs`,
      params,
    }),
  tickets: (id: number | string, params: PageParams) =>
    request.get<{ list?: Record<string, unknown>[]; total?: number; page?: number; page_size?: number; summary?: unknown }>({
      url: `/admin/users/${id}/tickets`,
      params,
    }),
  operationLogs: (id: number | string, params: PageParams) =>
    request.get<{ list?: Record<string, unknown>[]; total?: number; page?: number; page_size?: number }>({
      url: `/admin/users/${id}/operation-logs`,
      params,
    }),
  smsLogs: (id: number | string, params: PageParams) =>
    request.get<{ list?: Record<string, unknown>[]; total?: number; page?: number; page_size?: number }>({
      url: `/admin/users/${id}/sms-logs`,
      params,
    }),
  emailLogs: (id: number | string, params: PageParams) =>
    request.get<{ list?: Record<string, unknown>[]; total?: number; page?: number; page_size?: number }>({
      url: `/admin/users/${id}/email-logs`,
      params,
    }),
  serviceDetail: (id: number | string, serviceId: number | string) =>
    request.get<Record<string, unknown>>({ url: `/admin/users/${id}/services/${serviceId}` }),
  serviceRemoteStatus: (id: number | string, serviceId: number | string) =>
    request.get<Record<string, unknown>>({ url: `/admin/users/${id}/services/${serviceId}/remote-status` }),
  refreshServiceStatuses: (id: number | string, data: { service_ids: Array<number | string> }) =>
    request.post({ url: `/admin/users/${id}/services/refresh-statuses`, data }),
  servicePower: (id: number | string, serviceId: number | string, data: { action: string }) =>
    request.post<{ detail?: Record<string, unknown>; message?: string }>({
      url: `/admin/users/${id}/services/${serviceId}/power`,
      data,
    }),
  serviceResetPassword: (id: number | string, serviceId: number | string, data: { password: string }) =>
    request.put({ url: `/admin/users/${id}/services/${serviceId}/password/reset`, data }),
  serviceDelete: (id: number | string, serviceId: number | string) =>
    request.delete({ url: `/admin/users/${id}/services/${serviceId}` }),
  updateServiceMeta: (id: number | string, serviceId: number | string, data: ServiceMetaPayload) =>
    request.put<Record<string, unknown>>({ url: `/admin/users/${id}/services/${serviceId}/meta`, data }),
  manualProvisionService: (id: number | string, serviceId: number | string, data: { upstream_host_id: number }) =>
    request.put({ url: `/admin/users/${id}/services/${serviceId}/manual-provision`, data }),
  refundService: (id: number | string, serviceId: number | string, data: RefundPayload) =>
    request.post<{ message?: string }>({ url: `/admin/users/${id}/services/${serviceId}/refund`, data }),
  osOptions: () => request.get<{ groups?: Record<string, unknown>[] }>({ url: '/admin/os-options' }),
};
