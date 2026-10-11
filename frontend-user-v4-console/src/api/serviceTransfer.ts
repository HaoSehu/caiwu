import type { ApiEnvelope, PagedList } from '@/types/client';
import request from '@/utils/request';

/**
 * M6 服务转移（用户间过户）客户端 API。
 * 类型自含，不依赖共享 types/barrel（除通用信封与分页结构）。
 */

/** 服务转移记录（对应后端 ClientServiceTransferResource） */
export interface ServiceTransferRecord {
  id: number;
  service_id: number;
  service_name: string;
  service_domain: string;
  from_user_id: number;
  from_user_email: string;
  to_user_id: number;
  to_user_email: string;
  /** 0 待对方确认 1 双方已确认待生效 2 已完成 3 已取消 4 已拒绝 5 已过期 */
  status: number;
  status_label: string;
  /** 1 转出方 2 接收方 3 管理员 */
  initiated_by: number;
  initiated_by_label: string;
  from_confirmed_at: string | null;
  to_confirmed_at: string | null;
  expires_at: string | null;
  reject_reason: string | null;
  created_at: string | null;
}

/** 发起转移入参：to_user_id / to_user_email 二选一 */
export interface ServiceTransferInitiatePayload {
  to_user_id?: number;
  to_user_email?: string;
  [key: string]: unknown;
}

export interface ServiceTransferListParams {
  page?: number;
  page_size?: number;
  /** 0-5，见 ServiceTransferRecord.status */
  status?: number;
}

type QueryParams = Record<string, unknown> | undefined;

function getEnvelope<T>(url: string, config?: Record<string, unknown>) {
  return request.get<ApiEnvelope<T>, ApiEnvelope<T>>(url, config);
}

function postEnvelope<T>(url: string, data?: Record<string, unknown>) {
  return request.post<ApiEnvelope<T>, ApiEnvelope<T>>(url, data);
}

interface ServiceTransferMutationPayload {
  transfer?: ServiceTransferRecord | null;
}

const clientTransferApi = {
  /** 发起转移：POST /v2/client/services/{service}/transfer-requests */
  initiate: (serviceId: number | string, data: ServiceTransferInitiatePayload) =>
    postEnvelope<ServiceTransferMutationPayload>(`/v2/client/services/${serviceId}/transfer-requests`, data),

  /**
   * 转移列表：GET /v2/client/service-transfer-requests
   * 后端不区分视角，一次返回与我相关的全部记录（from/to），前端按视角分流。
   */
  requests: (params?: ServiceTransferListParams) =>
    getEnvelope<PagedList<ServiceTransferRecord>>('/v2/client/service-transfer-requests', { params }),

  /** 确认：POST /v2/client/service-transfer-requests/{transfer}/confirm */
  confirm: (transferId: number | string) =>
    postEnvelope<ServiceTransferMutationPayload>(`/v2/client/service-transfer-requests/${transferId}/confirm`),

  /** 拒绝：POST /v2/client/service-transfer-requests/{transfer}/reject */
  reject: (transferId: number | string, reason?: string) =>
    postEnvelope<ServiceTransferMutationPayload>(`/v2/client/service-transfer-requests/${transferId}/reject`, {
      reason: reason || undefined,
    }),

  /** 撤销（发起方）：POST /v2/client/service-transfer-requests/{transfer}/cancel */
  cancel: (transferId: number | string, reason?: string) =>
    postEnvelope<ServiceTransferMutationPayload>(`/v2/client/service-transfer-requests/${transferId}/cancel`, {
      reason: reason || undefined,
    }),
};

export default clientTransferApi;
