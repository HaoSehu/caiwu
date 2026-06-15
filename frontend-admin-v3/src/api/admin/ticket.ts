import { request } from '@/utils/request';
import type {
  TicketListParams,
  TicketRecord,
  TicketDetail,
  TicketAdminUser,
  TicketAttachment,
} from './types';

export const ticketsApi = {
  list: (params: TicketListParams) =>
    request.get<{ list?: TicketRecord[]; total?: number; page?: number; page_size?: number }>({
      url: '/admin/tickets',
      params,
    }),
  summary: () => request.get<Record<string, unknown>>({ url: '/admin/tickets/summary' }),
  detail: (id: number | string) => request.get<TicketDetail>({ url: `/admin/tickets/${id}` }),
  adminUsers: () =>
    request.get<TicketAdminUser[] | { list?: TicketAdminUser[] }>({ url: '/admin/tickets/admin-users' }),
  close: (id: number | string) => request.post({ url: `/admin/tickets/${id}/close` }),
  assign: (id: number | string, data: { assignee_id?: number | string | null }) =>
    request.post({ url: `/admin/tickets/${id}/assign`, data }),
  reply: (
    id: number | string,
    data: { content?: string; attachments?: string[]; quote_reply_id?: number | string }
  ) => request.post({ url: `/admin/tickets/${id}/reply`, data }),
  recall: (id: number | string, replyId: number | string) =>
    request.post({ url: `/admin/tickets/${id}/replies/${replyId}/recall` }),
  uploadImage: (data: FormData) =>
    request.post<TicketAttachment>({
      url: '/admin/tickets/upload-image',
      data,
      headers: { 'Content-Type': 'multipart/form-data' },
    }),
};
