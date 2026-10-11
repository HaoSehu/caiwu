import { request } from '@/utils/request';

import type { TicketAdminUser, TicketAttachment, TicketDetail, TicketListParams, TicketRecord } from './types';

// ========== 工单部门（M3） ==========

/** 分派模式：0 手动 1 轮询 2 最少负载 */
export type TicketDepartmentAutoAssignMode = 0 | 1 | 2;

export interface TicketDepartmentStaffItem {
  admin_user_id: number;
  is_leader: number;
}

export interface TicketDepartmentRecord {
  id: number;
  name: string;
  description: string;
  auto_assign_mode: number;
  auto_assign_mode_label?: string;
  is_enabled: number;
  sort_order: number;
  staff_count: number | null;
  staff: TicketDepartmentStaffItem[] | null;
  created_at: string | null;
  updated_at: string | null;
}

export interface TicketDepartmentPayload {
  name: string;
  description?: string | null;
  auto_assign_mode?: TicketDepartmentAutoAssignMode;
  is_enabled?: number;
  sort_order?: number;
}

export interface TicketDepartmentStaffPayload {
  /** 全量覆盖的成员 admin_user_id 集合 */
  staff_ids: number[];
  /** staff_ids 的子集，标记负责人 */
  leader_ids: number[];
}

// ========== 工单预设回复（M2） ==========

export interface TicketPresetReplyCategoryRecord {
  id: number;
  name: string;
  is_enabled: number;
  sort_order: number;
  replies_count: number;
  created_at: string | null;
  updated_at: string | null;
}

export interface TicketPresetReplyCategoryPayload {
  name: string;
  sort_order?: number;
  is_enabled?: number;
}

export interface TicketPresetReplyRecord {
  id: number;
  category_id: number | null;
  category_name: string;
  department_id: number | null;
  department_name: string;
  title: string;
  content: string;
  is_enabled: number;
  sort_order: number;
  usage_count: number;
  created_by: number | null;
  creator_name: string;
  created_at: string | null;
  updated_at: string | null;
}

export interface TicketPresetReplyPayload {
  /** 传 null 表示无分类 */
  category_id?: number | null;
  /** 传 null 表示通用（不挂部门） */
  department_id?: number | null;
  title: string;
  content: string;
  is_enabled?: number;
  sort_order?: number;
}

export interface TicketPresetReplyListParams {
  category_id?: number | string;
  department_id?: number | string;
  keyword?: string;
  is_enabled?: number | string;
}

export interface TicketPresetReplyRenderResult {
  content: string;
  usage_count: number;
}

// ========== 工单转派（M3） ==========

export interface TicketTransferPayload {
  to_department_id: number;
  to_assignee_id?: number | string | null;
  reason?: string | null;
}

export interface TicketTransferResult {
  id: number;
  department: string;
  department_label: string;
  department_id: number | null;
  assignee_id: number | null;
  status: number;
  status_label: string;
}

// ========== 请求响应包装 ==========

interface TicketDepartmentListPayload {
  list?: TicketDepartmentRecord[];
}

interface TicketDepartmentItemPayload {
  department?: TicketDepartmentRecord;
}

interface TicketPresetReplyCategoryListPayload {
  list?: TicketPresetReplyCategoryRecord[];
}

interface TicketPresetReplyListPayload {
  list?: TicketPresetReplyRecord[];
}

interface TicketPresetReplyItemPayload {
  reply?: TicketPresetReplyRecord;
}

interface TicketTransferResultPayload {
  ticket?: TicketTransferResult;
}

interface TicketV2DetailPayload {
  ticket?: TicketDetail | null;
}

interface TicketV2RepliesPayload {
  list?: TicketDetail['replies'];
  total?: number;
  page?: number;
  page_size?: number;
}

interface TicketV2AdminUsersPayload {
  list?: TicketAdminUser[];
}

interface TicketV2UploadPayload {
  attachment?: TicketAttachment;
}

async function v2TicketDetail(id: number | string): Promise<TicketDetail> {
  const [detailPayload, repliesPayload] = await Promise.all([
    request.get<TicketV2DetailPayload>({ url: `/v2/admin/tickets/${id}` }),
    request.get<TicketV2RepliesPayload>({
      url: `/v2/admin/tickets/${id}/replies`,
      params: { page: 1, page_size: 100 },
    }),
  ]);
  const ticket = detailPayload.ticket || ({} as TicketDetail);

  return {
    ...ticket,
    replies: repliesPayload.list || [],
  };
}

export const ticketsApi = {
  list: (params: TicketListParams) =>
    request.get<{ list?: TicketRecord[]; total?: number; page?: number; page_size?: number }>({
      url: '/v2/admin/tickets',
      params,
    }),
  summary: () => request.get<Record<string, unknown>>({ url: '/v2/admin/tickets/summary' }),
  detail: (id: number | string) => v2TicketDetail(id),
  adminUsers: () => request.get<TicketV2AdminUsersPayload>({ url: '/v2/admin/tickets/admin-users' }),
  close: (id: number | string) => request.post({ url: `/v2/admin/tickets/${id}/closures` }),
  assign: (id: number | string, data: { assignee_id?: number | string | null }) =>
    request.put({ url: `/v2/admin/tickets/${id}/assignment`, data }),
  reply: (id: number | string, data: { content?: string; attachments?: string[]; quote_reply_id?: number | string }) =>
    request.post({ url: `/v2/admin/tickets/${id}/replies`, data }),
  recall: (id: number | string, replyId: number | string) =>
    request.post({ url: `/v2/admin/tickets/${id}/replies/${replyId}/recalls` }),
  uploadImage: (data: FormData) =>
    request
      .post<TicketV2UploadPayload>({
        url: '/v2/admin/tickets/upload-images',
        data,
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      .then((response) => response.attachment || {}),
  // —— 工单转派（M3） ——
  transfer: (id: number | string, data: TicketTransferPayload) =>
    request
      .post<TicketTransferResultPayload>({ url: `/v2/admin/tickets/${id}/transfer`, data })
      .then((response) => response.ticket || ({} as TicketTransferResult)),
  // —— 工单部门（M3） ——
  departments: {
    list: async () => {
      const response = await request.get<TicketDepartmentListPayload>({ url: '/v2/admin/ticket-departments' });
      return response.list || [];
    },
    create: (data: TicketDepartmentPayload) =>
      request
        .post<TicketDepartmentItemPayload>({ url: '/v2/admin/ticket-departments', data })
        .then((response) => response.department || ({} as TicketDepartmentRecord)),
    update: (id: number | string, data: TicketDepartmentPayload) =>
      request
        .put<TicketDepartmentItemPayload>({ url: `/v2/admin/ticket-departments/${id}`, data })
        .then((response) => response.department || ({} as TicketDepartmentRecord)),
    delete: (id: number | string) => request.delete({ url: `/v2/admin/ticket-departments/${id}` }),
    updateStaff: (id: number | string, data: TicketDepartmentStaffPayload) =>
      request
        .put<TicketDepartmentItemPayload>({ url: `/v2/admin/ticket-departments/${id}/staff`, data })
        .then((response) => response.department || ({} as TicketDepartmentRecord)),
  },
  // —— 工单预设回复（M2） ——
  presetReplies: {
    list: async (params?: TicketPresetReplyListParams) => {
      const response = await request.get<TicketPresetReplyListPayload>({
        url: '/v2/admin/ticket-preset-replies',
        params,
      });
      return response.list || [];
    },
    render: (id: number | string, ticketId: number | string) =>
      request
        .get<TicketPresetReplyRenderResult>({
          url: `/v2/admin/ticket-preset-replies/${id}/render`,
          params: { ticket_id: ticketId },
        })
        .then((response) => ({ content: response.content || '', usage_count: Number(response.usage_count || 0) })),
    create: (data: TicketPresetReplyPayload) =>
      request
        .post<TicketPresetReplyItemPayload>({ url: '/v2/admin/ticket-preset-replies', data })
        .then((response) => response.reply || ({} as TicketPresetReplyRecord)),
    update: (id: number | string, data: TicketPresetReplyPayload) =>
      request
        .put<TicketPresetReplyItemPayload>({ url: `/v2/admin/ticket-preset-replies/${id}`, data })
        .then((response) => response.reply || ({} as TicketPresetReplyRecord)),
    delete: (id: number | string) => request.delete({ url: `/v2/admin/ticket-preset-replies/${id}` }),
  },
  presetReplyCategories: {
    list: async () => {
      const response = await request.get<TicketPresetReplyCategoryListPayload>({
        url: '/v2/admin/ticket-preset-reply-categories',
      });
      return response.list || [];
    },
    create: (data: TicketPresetReplyCategoryPayload) =>
      request.post({ url: '/v2/admin/ticket-preset-reply-categories', data }),
    update: (id: number | string, data: TicketPresetReplyCategoryPayload) =>
      request.put({ url: `/v2/admin/ticket-preset-reply-categories/${id}`, data }),
    delete: (id: number | string) => request.delete({ url: `/v2/admin/ticket-preset-reply-categories/${id}` }),
  },
};
