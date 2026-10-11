import { request } from '@/utils/request';

// 状态与后端 ServiceTransfer 常量保持一致：0 待确认 / 1 双方已确认待生效 / 2 已完成 / 3 已取消 / 4 已拒绝 / 5 已过期
export const SERVICE_TRANSFER_STATUS_MAP: Record<number, { label: string; theme: 'warning' | 'primary' | 'success' | 'default' | 'danger' }> = {
  0: { label: '待对方确认', theme: 'warning' },
  1: { label: '待生效', theme: 'primary' },
  2: { label: '已完成', theme: 'success' },
  3: { label: '已取消', theme: 'default' },
  4: { label: '已拒绝', theme: 'danger' },
  5: { label: '已过期', theme: 'default' },
};

export interface ServiceTransferRecord {
  id: number;
  service_id: number;
  service_name: string;
  service_domain: string;
  from_user_id: number;
  from_user_email: string;
  to_user_id: number;
  to_user_email: string;
  status: number;
  status_label: string;
  initiated_by: number;
  initiated_by_label: string;
  from_confirmed_at: string | null;
  to_confirmed_at: string | null;
  expires_at: string | null;
  risk_flags: string[] | null;
  reject_reason: string | null;
  operator_id: number | null;
  trace_id: string;
  created_at: string | null;
}

export interface ServiceTransferListParams {
  status?: number | string;
  service_id?: number | string;
  keyword?: string;
  page?: number;
  page_size?: number;
}

export const serviceTransferApi = {
  list: (params: ServiceTransferListParams) =>
    request.get<{ list?: ServiceTransferRecord[]; total?: number; page?: number; page_size?: number }>({
      url: '/v2/admin/service-transfers',
      params,
    }),
  detail: (id: number | string) =>
    request.get<{ transfer: ServiceTransferRecord }>({ url: `/v2/admin/service-transfers/${id}` }),
  // 管理员代发起：service_id + 接收方（to_user_id 或 to_user_email），双确认豁免为单确认
  initiate: (data: { service_id: number; to_user_id?: number; to_user_email?: string }) =>
    request.post<{ transfer: ServiceTransferRecord }>({ url: '/v2/admin/service-transfers', data }),
  approve: (id: number | string) =>
    request.post<{ status: string; message: string; detail?: { transfer?: ServiceTransferRecord } }>({
      url: `/v2/admin/service-transfers/${id}/approve`,
    }),
  reject: (id: number | string, data: { reason?: string } = {}) =>
    request.post<{ status: string; message: string; detail?: { transfer?: ServiceTransferRecord } }>({
      url: `/v2/admin/service-transfers/${id}/reject`,
      data,
    }),
};
