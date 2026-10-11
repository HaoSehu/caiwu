import { request } from '@/utils/request';

// ─── 计提记录 ───────────────────────────────────────────────────────────────

export interface SalesCommissionRecord {
  id: number;
  admin_user_id: number;
  admin_user: { id: number; display_name: string } | null;
  user_id: number;
  user: { id: number; email: string; nickname: string; display_name: string } | null;
  order_id: number | null;
  invoice_id: number;
  invoice_no: string;
  plan_id: number | null;
  plan_snapshot: Record<string, unknown> | null;
  base_amount: string;
  rate: string;
  commission_amount: string;
  status: number;
  status_label: string;
  settlement_id: number | null;
  settled_at: string | null;
  trace_id: string;
  created_at: string | null;
}

export interface SalesCommissionListParams {
  status?: number | string;
  admin_user_id?: number | string;
  user_id?: number | string;
  date_from?: string;
  date_to?: string;
  page?: number;
  page_size?: number;
}

// ─── 提成方案 ───────────────────────────────────────────────────────────────

export interface SalesCommissionTierPayload {
  min_amount: number | string;
  max_amount: number | string | null;
  rate: number | string;
}

export interface SalesCommissionProductRatePayload {
  product_id: number;
  rate: number | string | null;
  fixed_amount: number | string | null;
}

export interface SalesCommissionPlanPayload {
  name: string;
  basis: number;
  is_enabled: number;
  valid_from: string | null;
  valid_to: string | null;
  remark: string | null;
  tiers: SalesCommissionTierPayload[];
  product_rates: SalesCommissionProductRatePayload[];
}

export interface SalesCommissionTierRecord {
  id: number;
  min_amount: string;
  max_amount: string | null;
  rate: string;
}

export interface SalesCommissionProductRateRecord {
  id: number;
  product_id: number;
  product_name: string;
  rate: string | null;
  fixed_amount: string | null;
}

export interface SalesCommissionPlanRecord {
  id: number;
  name: string;
  basis: number;
  basis_label: string;
  is_enabled: number;
  valid_from: string | null;
  valid_to: string | null;
  remark: string;
  tiers: SalesCommissionTierRecord[];
  product_rates: SalesCommissionProductRateRecord[];
  created_at: string | null;
}

// ─── 结算批次 ───────────────────────────────────────────────────────────────

export interface SalesCommissionSettlementRecord {
  id: number;
  admin_user_id: number;
  admin_user: { id: number; display_name: string } | null;
  period_start: string | null;
  period_end: string | null;
  total_amount: string;
  commission_count: number;
  status: number;
  status_label: string;
  operator_id: number | null;
  settled_at: string | null;
  remark: string;
  commissions?: Array<{
    id: number;
    invoice_id: number;
    base_amount: string;
    rate: string;
    commission_amount: string;
    status: number;
    status_label: string;
  }>;
  created_at: string | null;
}

export interface SalesCommissionSettlementListParams {
  status?: number | string;
  admin_user_id?: number | string;
  page?: number;
  page_size?: number;
}

export interface SalesOwnerPayload {
  admin_id: number | null;
  reason: string;
}

export interface SalesOwnerResult {
  user_id: number;
  sales_owner_admin_id: number | null;
  sales_owner: { id: number; display_name: string } | null;
  sales_owner_assigned_at: string | null;
  changed: boolean;
}

// M5 计提/结算控制器按 per_page 分页（非 page_size），此处统一转换，前端分页器无感知
function withPerPage<T extends { page_size?: number }>(params: T): Omit<T, 'page_size'> & { per_page?: number } {
  const { page_size, ...rest } = params;
  return page_size === undefined || page_size === null
    ? rest
    : { ...rest, per_page: page_size };
}

export const salesCommissionApi = {
  commissions: {
    list: (params: SalesCommissionListParams) =>
      request.get<{ list?: SalesCommissionRecord[]; total?: number; page?: number; page_size?: number }>({
        url: '/v2/admin/sales-commissions',
        params: withPerPage(params),
      }),
  },
  plans: {
    list: () => request.get<SalesCommissionPlanRecord[]>({ url: '/v2/admin/sales-commission-plans' }),
    create: (data: SalesCommissionPlanPayload) =>
      request.post<SalesCommissionPlanRecord>({ url: '/v2/admin/sales-commission-plans', data }),
    update: (id: number | string, data: SalesCommissionPlanPayload) =>
      request.put<SalesCommissionPlanRecord>({ url: `/v2/admin/sales-commission-plans/${id}`, data }),
    delete: (id: number | string) => request.delete({ url: `/v2/admin/sales-commission-plans/${id}` }),
  },
  settlements: {
    list: (params: SalesCommissionSettlementListParams) =>
      request.get<{ list?: SalesCommissionSettlementRecord[]; total?: number; page?: number; page_size?: number }>({
        url: '/v2/admin/sales-commission-settlements',
        params: withPerPage(params),
      }),
    detail: (id: number | string) =>
      request.get<SalesCommissionSettlementRecord>({ url: `/v2/admin/sales-commission-settlements/${id}` }),
    create: (data: { admin_user_id: number; period_start: string; period_end: string; remark?: string }) =>
      request.post<SalesCommissionSettlementRecord>({ url: '/v2/admin/sales-commission-settlements', data }),
    approve: (id: number | string, data: { remark?: string } = {}) =>
      request.post<SalesCommissionSettlementRecord>({
        url: `/v2/admin/sales-commission-settlements/${id}/approvals`,
        data,
      }),
    reject: (id: number | string, data: { remark?: string } = {}) =>
      request.post<SalesCommissionSettlementRecord>({
        url: `/v2/admin/sales-commission-settlements/${id}/rejections`,
        data,
      }),
  },
  salesOwner: {
    set: (userId: number | string, data: SalesOwnerPayload) =>
      request.post<SalesOwnerResult>({ url: `/v2/admin/users/${userId}/sales-owner`, data }),
  },
};
